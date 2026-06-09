<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de evidencias de entrega subidas por el proveedor.
 *
 * Guarda la remisión digital (PDF/JPG/PNG) que el proveedor sube como
 * comprobante de que realizó la entrega física en la estación u oficina.
 * Relación polimórfica para soportar tanto OC estándar como OC directas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_delivery_evidences', function (Blueprint $table) {
            $table->id();

            // Relación polimórfica: PurchaseOrder o DirectPurchaseOrder
            $table->morphs('evidenceable');

            $table->string('file_path')
                  ->comment('Ruta del archivo en storage');
            $table->string('file_format', 10)
                  ->comment('Extensión del archivo: pdf, jpg, png');
            $table->foreignId('uploaded_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('Usuario interno que subió la evidencia, si aplica');
            $table->foreignId('uploaded_by_supplier_id')
                  ->nullable()
                  ->constrained('suppliers')
                  ->noActionOnDelete()
                  ->comment('Proveedor autenticado que subió la evidencia');
            $table->dateTime('uploaded_at')
                  ->comment('Fecha y hora de carga');

            $table->timestamps();

            // Índices para consultas frecuentes
            $table->index('uploaded_by');
            $table->index('uploaded_by_supplier_id');
            $table->index('uploaded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_delivery_evidences');
    }
};
