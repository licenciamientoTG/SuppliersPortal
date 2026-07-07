<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('budget_cedula_product_service', function (Blueprint $table) {
            $table->foreignId('budget_cedula_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_service_id')->constrained('products_services')->cascadeOnDelete();
            $table->primary(['budget_cedula_id', 'product_service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_cedula_product_service');
    }
};
