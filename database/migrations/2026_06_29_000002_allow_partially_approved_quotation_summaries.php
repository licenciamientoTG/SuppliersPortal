<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::statement("ALTER TABLE quotation_summaries MODIFY approval_status ENUM('pending', 'approved', 'partially_approved', 'rejected') NOT NULL DEFAULT 'pending'"),
            'pgsql' => DB::statement("ALTER TABLE quotation_summaries ALTER COLUMN approval_status TYPE VARCHAR(30)"),
            'sqlsrv' => DB::statement("ALTER TABLE quotation_summaries ALTER COLUMN approval_status NVARCHAR(30) NOT NULL"),
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
            'pgsql' => DB::statement("ALTER TABLE quotation_summaries ALTER COLUMN approval_status TYPE VARCHAR(20)"),
            'sqlsrv' => DB::statement("ALTER TABLE quotation_summaries ALTER COLUMN approval_status NVARCHAR(20) NOT NULL"),
            default => null,
        };
    }
};
