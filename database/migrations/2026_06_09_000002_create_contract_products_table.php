<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contract_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_service_id')->constrained('products_services')->noActionOnDelete();
            $table->decimal('unit_price', 14, 4);
            $table->char('currency_code', 3)->default('MXN');
            $table->string('unit_of_measure', 50);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['contract_id', 'product_service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_products');
    }
};
