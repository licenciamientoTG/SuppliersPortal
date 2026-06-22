<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlsrv') {
            return;
        }

        if (Schema::hasColumn('suppliers', 'user_id')) {
            DB::statement('ALTER TABLE suppliers ALTER COLUMN user_id BIGINT NULL');
        }

        if (Schema::hasColumn('suppliers', 'status')) {
            DB::statement('ALTER TABLE suppliers ALTER COLUMN status NVARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        // Legacy columns are intentionally left nullable for the direct supplier-auth flow.
    }
};
