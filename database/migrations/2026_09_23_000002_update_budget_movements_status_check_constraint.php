<?php

use App\Models\BudgetMovement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La columna status nació como enum (PENDIENTE, APROBADO, RECHAZADO). En SQL Server
 * eso crea un CHECK constraint que el ->change() a string de 2026_08_12 no eliminó,
 * así que los estados del flujo nuevo (PENDIENTE_ORIGEN, PENDIENTE_DIRECCION,
 * DEVUELTO) eran rechazados. Se reemplaza por un CHECK con nombre fijo y todos los
 * estados vigentes. Otros motores no crean ese constraint.
 */
return new class extends Migration
{
    private const CONSTRAINT = 'ck_budget_movements_status';

    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlsrv') {
            return;
        }

        $this->dropStatusCheckConstraints();

        $statuses = collect([
            BudgetMovement::STATUS_PENDING,
            BudgetMovement::STATUS_PENDING_ORIGIN,
            BudgetMovement::STATUS_PENDING_EXECUTIVE,
            BudgetMovement::STATUS_RETURNED,
            BudgetMovement::STATUS_APPROVED,
            BudgetMovement::STATUS_REJECTED,
        ])->map(fn (string $status) => "N'{$status}'")->implode(', ');

        DB::statement('ALTER TABLE [budget_movements] ADD CONSTRAINT ['.self::CONSTRAINT."] CHECK ([status] IN ({$statuses}))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlsrv') {
            return;
        }

        // No se restaura el CHECK original: rechazaría los movimientos ya creados con los estados nuevos.
        $this->dropStatusCheckConstraints();
    }

    /** Borra todos los CHECK de budget_movements.status, sin depender de su nombre autogenerado. */
    private function dropStatusCheckConstraints(): void
    {
        DB::statement(<<<'SQL'
            DECLARE @sql nvarchar(max) = N'';
            SELECT @sql += N'ALTER TABLE [budget_movements] DROP CONSTRAINT ' + QUOTENAME(cc.name) + N';'
            FROM sys.check_constraints cc
            JOIN sys.columns c ON c.object_id = cc.parent_object_id AND c.column_id = cc.parent_column_id
            WHERE cc.parent_object_id = OBJECT_ID(N'budget_movements') AND c.name = N'status';
            EXEC sp_executesql @sql;
            SQL);
    }
};
