<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('odc_direct_purchase_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('odc_direct_purchase_order_items', 'product_service_id')) {
                $table->foreignId('product_service_id')
                    ->nullable()
                    ->after('direct_purchase_order_id')
                    ->constrained('products_services')
                    ->noActionOnDelete();
            }

            if (! Schema::hasColumn('odc_direct_purchase_order_items', 'budget_cedula_id')) {
                $table->foreignId('budget_cedula_id')
                    ->nullable()
                    ->after('expense_category_id')
                    ->constrained('budget_cedulas')
                    ->noActionOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('odc_direct_purchase_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('odc_direct_purchase_order_items', 'budget_cedula_id')) {
                $table->dropForeign(['budget_cedula_id']);
                $table->dropColumn('budget_cedula_id');
            }

            if (Schema::hasColumn('odc_direct_purchase_order_items', 'product_service_id')) {
                $table->dropForeign(['product_service_id']);
                $table->dropColumn('product_service_id');
            }
        });
    }
};
