<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('expense_category_product_service', function (Blueprint $table) {
            $table->foreignId('expense_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_service_id')->constrained('products_services')->cascadeOnDelete();
            $table->primary(['expense_category_id', 'product_service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_category_product_service');
    }
};
