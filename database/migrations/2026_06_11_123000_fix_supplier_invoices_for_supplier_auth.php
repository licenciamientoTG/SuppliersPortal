<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_invoices')) {
            return;
        }

        if (! Schema::hasColumn('supplier_invoices', 'uploaded_by_supplier_id')) {
            Schema::table('supplier_invoices', function (Blueprint $table) {
                $table->unsignedBigInteger('uploaded_by_supplier_id')->nullable()->after('uploaded_by');
                $table->index('uploaded_by_supplier_id', 'supplier_invoices_uploaded_by_supplier_id_index');
            });
        }

        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement('ALTER TABLE supplier_invoices ALTER COLUMN uploaded_by BIGINT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('supplier_invoices')) {
            return;
        }

        if (Schema::hasColumn('supplier_invoices', 'uploaded_by_supplier_id')) {
            Schema::table('supplier_invoices', function (Blueprint $table) {
                $table->dropIndex('supplier_invoices_uploaded_by_supplier_id_index');
                $table->dropColumn('uploaded_by_supplier_id');
            });
        }
    }
};
