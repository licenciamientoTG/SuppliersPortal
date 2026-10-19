<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foto diaria de BudgetPositionService::positions() (RP-01, punto #6): un renglón
     * por fila, congelado el día del snapshot_date, para consultar fechas de corte pasadas.
     */
    public function up(): void
    {
        Schema::create('budget_position_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date');

            $table->foreignId('cost_center_id')
                ->constrained('cost_centers')
                ->onDelete('no action')
                ->onUpdate('no action');

            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedTinyInteger('month');

            $table->foreignId('expense_category_id')
                ->constrained('expense_categories')
                ->onDelete('no action')
                ->onUpdate('no action');

            $table->foreignId('budget_cedula_id')
                ->nullable()
                ->constrained('budget_cedulas')
                ->onDelete('no action')
                ->onUpdate('no action');

            // Renglón completo tal cual lo devuelve BudgetPositionService::positions() ese día.
            $table->json('payload');

            $table->timestamps();

            $table->index('snapshot_date');
        });

        // Índice único filtrado: por cédula cuando existe (SQL Server no permite NULL en unique normal).
        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement('CREATE UNIQUE INDEX ux_bps_date_line ON budget_position_snapshots (snapshot_date, cost_center_id, fiscal_year, month, expense_category_id, budget_cedula_id) WHERE budget_cedula_id IS NOT NULL');
        } else {
            Schema::table('budget_position_snapshots', function (Blueprint $table) {
                $table->unique(['snapshot_date', 'cost_center_id', 'fiscal_year', 'month', 'expense_category_id', 'budget_cedula_id'], 'ux_bps_date_line');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_position_snapshots');
    }
};
