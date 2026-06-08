<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('odc_direct_purchase_order_items', function (Blueprint $table) {
            $table->id();

            // Relación con la OCD
            $table->foreignId('direct_purchase_order_id')
                ->constrained('odc_direct_purchase_orders')
                ->onDelete('cascade'); // Si se elimina la OCD, se eliminan sus items

            // Relación con Categoría de Gasto por partida
            $table->foreignId('expense_category_id')
                ->constrained('expense_categories')
                ->noActionOnDelete();

            $table->foreignId('cost_center_id')
                ->constrained('cost_centers')
                ->noActionOnDelete();

            // Datos de la partida
            $table->text('description'); // Descripción del bien o servicio
            $table->decimal('quantity', 12, 2); // Cantidad solicitada
            $table->decimal('quantity_received', 10, 3)->default(0); // Cantidad recibida
            $table->decimal('unit_price', 12, 2); // Precio unitario
            $table->decimal('iva_rate', 5, 2)->default(16.00); // Tasa de IVA (0, 8, 16)

            // Cálculos por partida
            $table->decimal('subtotal', 12, 2);   // quantity * unit_price
            $table->decimal('iva_amount', 12, 2); // subtotal * (iva_rate / 100)
            $table->decimal('total', 12, 2);      // subtotal + iva_amount

            // Campos adicionales útiles
            $table->string('unit_of_measure', 50)->nullable(); // Unidad de medida (pzas, kg, litros, etc.)
            $table->string('sku', 100)->nullable(); // Código del producto (opcional)
            $table->text('notes')->nullable(); // Notas adicionales sobre el item

            $table->timestamps();

            // Índices para consultas rápidas
            $table->index('direct_purchase_order_id');
            $table->index('expense_category_id');
            $table->index('cost_center_id');
            $table->index(['direct_purchase_order_id', 'cost_center_id']);
            $table->index('iva_rate');
            $table->index('sku');

            // Índice compuesto para búsquedas específicas
            $table->index(['direct_purchase_order_id', 'iva_rate']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('odc_direct_purchase_order_items');
    }
};
