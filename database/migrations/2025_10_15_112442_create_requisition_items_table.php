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
        Schema::create('requisition_items', function (Blueprint $table) {
            $table->id();

            // Relación con requisición (obligatoria)
            $table->foreignId('requisition_id')
                ->constrained('requisitions')
                ->onDelete('cascade');

            // Relación con producto/servicio del catálogo (obligatoria)
            $table->foreignId('product_service_id')
                ->constrained('products_services')
                ->onDelete('no action'); // ✅ Cambiado de 'restrict'

            // Información de la partida
            $table->unsignedSmallInteger('line_number'); // Número de partida
            $table->string('item_category', 20); // 'producto' o 'servicio'
            $table->string('product_code', 50); // Código del producto/servicio
            $table->text('description'); // Descripción completa

            // Categoría de gasto (obligatoria para validación presupuestal)
            $table->foreignId('expense_category_id')
                ->constrained('expense_categories')
                ->onDelete('no action'); // ✅ Cambiado de 'restrict'

            $table->foreignId('cost_center_id')
                ->constrained('cost_centers')
                ->onUpdate('NO ACTION')
                ->onDelete('NO ACTION');

            // Cantidad y unidad
            $table->decimal('quantity', 10, 3); // Cantidad solicitada
            $table->string('unit', 20); // Unidad de medida

            // Proveedor sugerido (opcional)
            $table->foreignId('suggested_vendor_id')
                ->nullable()
                ->constrained('suppliers')
                ->onDelete('set null');

            // Notas adicionales (opcional)
            $table->text('notes')->nullable();

            $table->timestamps();

            // Índices para optimizar consultas
            $table->index('requisition_id');
            $table->index('product_service_id');
            $table->index('expense_category_id');
            $table->index('cost_center_id');
            $table->index(['requisition_id', 'cost_center_id']);

            // Asegurar que line_number sea único dentro de cada requisición
            $table->unique(['requisition_id', 'line_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requisition_items');
    }
};
