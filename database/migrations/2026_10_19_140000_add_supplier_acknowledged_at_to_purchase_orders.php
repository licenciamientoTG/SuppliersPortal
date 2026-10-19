<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->timestamp('supplier_acknowledged_at')->nullable()->after('issued_at')
                ->comment('Primera vez que el proveedor vio la OC en el portal (acuse automatico)');
        });

        Schema::table('odc_direct_purchase_orders', function (Blueprint $table) {
            $table->timestamp('supplier_acknowledged_at')->nullable()->after('issued_at')
                ->comment('Primera vez que el proveedor vio la OCD en el portal (acuse automatico)');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('supplier_acknowledged_at');
        });

        Schema::table('odc_direct_purchase_orders', function (Blueprint $table) {
            $table->dropColumn('supplier_acknowledged_at');
        });
    }
};
