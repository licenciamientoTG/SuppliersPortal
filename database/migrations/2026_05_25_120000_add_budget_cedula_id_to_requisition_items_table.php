<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisition_items', function (Blueprint $table) {
            $table->unsignedBigInteger('budget_cedula_id')
                ->nullable()
                ->after('expense_category_id');
        });

        $this->backfillBudgetCedulaIds();

        Schema::table('requisition_items', function (Blueprint $table) {
            $table->unsignedBigInteger('budget_cedula_id')
                ->nullable(false)
                ->change();
            $table->index('budget_cedula_id');
            $table->foreign('budget_cedula_id')
                ->references('id')
                ->on('budget_cedulas')
                ->onDelete('no action');
        });
    }

    public function down(): void
    {
        Schema::table('requisition_items', function (Blueprint $table) {
            $table->dropForeign(['budget_cedula_id']);
            $table->dropIndex(['budget_cedula_id']);
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
};
