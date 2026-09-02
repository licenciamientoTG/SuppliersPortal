<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::statement("ALTER TABLE quotation_summaries MODIFY approval_status ENUM('pending', 'approved', 'partially_approved', 'rejected') NOT NULL DEFAULT 'pending'"),
            'pgsql' => DB::statement('ALTER TABLE quotation_summaries ALTER COLUMN approval_status TYPE VARCHAR(30)'),
            'sqlsrv' => $this->rebuildSqlServerApprovalStatusConstraint([
                'pending',
                'approved',
                'partially_approved',
                'rejected',
            ], 30),
            // SQLite conserva el CHECK del enum original, que rechaza
            // 'partially_approved'. Se reemplaza por una cadena simple.
            'sqlite' => $this->relaxSqliteApprovalStatus(30),
            default => null,
        };
    }

    public function down(): void
    {
        DB::table('quotation_summaries')
            ->where('approval_status', 'partially_approved')
            ->update(['approval_status' => 'approved']);

        match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::statement("ALTER TABLE quotation_summaries MODIFY approval_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending'"),
            'pgsql' => DB::statement('ALTER TABLE quotation_summaries ALTER COLUMN approval_status TYPE VARCHAR(20)'),
            'sqlsrv' => $this->rebuildSqlServerApprovalStatusConstraint([
                'pending',
                'approved',
                'rejected',
            ], 20),
            'sqlite' => $this->relaxSqliteApprovalStatus(20),
            default => null,
        };
    }

    private function relaxSqliteApprovalStatus(int $length): void
    {
        Schema::table('quotation_summaries', function (Blueprint $table) use ($length): void {
            $table->string('approval_status', $length)->default('pending')->change();
        });
    }

    private function rebuildSqlServerApprovalStatusConstraint(array $allowedStatuses, int $length): void
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
