<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products_services', function (Blueprint $table) {
            $table->dropForeign(['expense_category_id']);
            $table->dropForeign(['budget_cedula_id']);
            $table->dropColumn(['expense_category_id', 'budget_cedula_id']);
        });
    }

    public function down(): void
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
        });
    }
};
