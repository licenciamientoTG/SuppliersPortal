<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products_services', function (Blueprint $table) {
            $table->foreignId('expense_category_id')->nullable()
                ->after('subcategory')
                ->constrained('expense_categories')
                ->onDelete('no action')->onUpdate('no action');

            $table->foreignId('budget_cedula_id')->nullable()
                ->after('expense_category_id')
                ->constrained('budget_cedulas')
                ->onDelete('no action')->onUpdate('no action');

            $table->boolean('is_inventoriable')->default(true)
                ->after('product_type')
                ->comment('TRUE si el producto se controla por inventario');

            $table->index('expense_category_id');
            $table->index('budget_cedula_id');
            $table->index('is_inventoriable');
        });

        DB::table('products_services')
            ->where('product_type', 'SERVICIO')
            ->update(['is_inventoriable' => false]);
    }

    public function down(): void
    {
        Schema::table('products_services', function (Blueprint $table) {
            $table->dropIndex('products_services_expense_category_id_index');
            $table->dropIndex('products_services_budget_cedula_id_index');
            $table->dropIndex('products_services_is_inventoriable_index');
            $table->dropForeign(['expense_category_id']);
            $table->dropForeign(['budget_cedula_id']);
            $table->dropColumn(['expense_category_id', 'budget_cedula_id', 'is_inventoriable']);
        });
    }
};
