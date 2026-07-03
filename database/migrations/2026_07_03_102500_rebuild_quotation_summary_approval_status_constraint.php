<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlsrv') {
            return;
        }

        $this->rebuildApprovalStatusConstraint([
            'pending',
            'approved',
            'partially_approved',
            'rejected',
        ], 30);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlsrv') {
            return;
        }

        DB::table('quotation_summaries')
            ->where('approval_status', 'partially_approved')
            ->update(['approval_status' => 'approved']);

        $this->rebuildApprovalStatusConstraint([
            'pending',
            'approved',
            'rejected',
        ], 20);
    }

    private function rebuildApprovalStatusConstraint(array $allowedStatuses, int $length): void
    {
        $constraints = DB::select(<<<'SQL'
            SELECT cc.name
            FROM sys.check_constraints cc
            INNER JOIN sys.tables t ON cc.parent_object_id = t.object_id
            INNER JOIN sys.schemas s ON t.schema_id = s.schema_id
            INNER JOIN sys.sql_expression_dependencies sed ON sed.referencing_id = cc.object_id
            INNER JOIN sys.columns c ON c.object_id = t.object_id AND c.column_id = sed.referenced_minor_id
            WHERE s.name = SCHEMA_NAME()
              AND t.name = 'quotation_summaries'
              AND c.name = 'approval_status'
        SQL);

        foreach ($constraints as $constraint) {
            DB::statement("ALTER TABLE quotation_summaries DROP CONSTRAINT [{$constraint->name}]");
        }

        DB::statement("ALTER TABLE quotation_summaries ALTER COLUMN approval_status NVARCHAR({$length}) NOT NULL");

        $quotedStatuses = collect($allowedStatuses)
            ->map(fn (string $status) => "N'".str_replace("'", "''", $status)."'")
            ->implode(', ');

        DB::statement("ALTER TABLE quotation_summaries ADD CONSTRAINT CK_quotation_summaries_approval_status_allowed CHECK ([approval_status] IN ({$quotedStatuses}))");
    }
};
