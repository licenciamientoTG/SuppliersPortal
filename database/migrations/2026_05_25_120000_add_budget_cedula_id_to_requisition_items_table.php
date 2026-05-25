<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('requisition_items', 'budget_cedula_id')) {
            Schema::table('requisition_items', function (Blueprint $table) {
                $table->unsignedBigInteger('budget_cedula_id')
                    ->nullable()
                    ->after('expense_category_id');
            });
        }

        $this->backfillBudgetCedulaIds();

        Schema::table('requisition_items', function (Blueprint $table) {
            $table->unsignedBigInteger('budget_cedula_id')
                ->nullable(false)
                ->change();
        });

        if (! $this->hasIndex('requisition_items', 'requisition_items_budget_cedula_id_index')) {
            Schema::table('requisition_items', function (Blueprint $table) {
                $table->index('budget_cedula_id');
            });
        }

        if (! $this->hasForeignKey('requisition_items', 'requisition_items_budget_cedula_id_foreign')) {
            Schema::table('requisition_items', function (Blueprint $table) {
                $table->foreign('budget_cedula_id')
                    ->references('id')
                    ->on('budget_cedulas')
                    ->onDelete('no action');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('requisition_items', 'budget_cedula_id')) {
            return;
        }

        if ($this->hasForeignKey('requisition_items', 'requisition_items_budget_cedula_id_foreign')) {
            Schema::table('requisition_items', function (Blueprint $table) {
                $table->dropForeign(['budget_cedula_id']);
            });
        }

        if ($this->hasIndex('requisition_items', 'requisition_items_budget_cedula_id_index')) {
            Schema::table('requisition_items', function (Blueprint $table) {
                $table->dropIndex(['budget_cedula_id']);
            });
        }

        Schema::table('requisition_items', function (Blueprint $table) {
            $table->dropColumn('budget_cedula_id');
        });
    }

    private function backfillBudgetCedulaIds(): void
    {
        $unresolvedItems = [];

        DB::table('requisition_items')
            ->select('id', 'expense_category_id')
            ->whereNull('budget_cedula_id')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $item) use (&$unresolvedItems) {
                $cedulaId = DB::table('budget_cedulas')
                    ->where('expense_category_id', $item->expense_category_id)
                    ->where('status', 'ACTIVO')
                    ->whereNull('deleted_at')
                    ->orderBy('name')
                    ->orderBy('id')
                    ->value('id');

                if (! $cedulaId) {
                    $unresolvedItems[] = $item->id;

                    return;
                }

                DB::table('requisition_items')
                    ->where('id', $item->id)
                    ->update([
                        'budget_cedula_id' => $cedulaId,
                        'updated_at' => now(),
                    ]);
            });

        if (! empty($unresolvedItems)) {
            throw new \RuntimeException(
                'No fue posible resolver una cédula presupuestal para las partidas: '.implode(', ', $unresolvedItems)
            );
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $database = DB::getDatabaseName();

            return DB::table('information_schema.statistics')
                ->where('table_schema', $database)
                ->where('table_name', $table)
                ->where('index_name', $indexName)
                ->exists();
        }

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");

            return collect($indexes)->contains(fn ($index) => ($index->name ?? null) === $indexName);
        }

        return false;
    }

    private function hasForeignKey(string $table, string $foreignKeyName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $database = DB::getDatabaseName();

            return DB::table('information_schema.table_constraints')
                ->where('constraint_schema', $database)
                ->where('table_name', $table)
                ->where('constraint_name', $foreignKeyName)
                ->where('constraint_type', 'FOREIGN KEY')
                ->exists();
        }

        return false;
    }
};
