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
        Schema::create('cfdi_validation_logs', function (Blueprint $table) {
            $table->id();

            $table->dateTime('attempted_at');
            $table->string('uuid', 80)->nullable();
            $table->string('issuer_rfc', 13)->nullable();
            $table->string('issuer_name')->nullable();
            $table->string('receiver_rfc', 13)->nullable();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('cfdi_type', 1)->nullable();
            $table->decimal('total', 18, 2)->nullable();
            $table->string('currency', 3)->nullable();

            $table->string('result', 10);
            // Sin FK a cfdi_rejection_codes: catálogo editable (altas/bajas) sin arrastrar
            // filas históricas; se valida en la capa de aplicación (CfdiValidationLogger).
            $table->string('rejection_code', 40)->nullable();

            $table->string('channel', 15);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supplier_invoice_id')->nullable()->constrained('supplier_invoices')->nullOnDelete();

            $table->string('xml_sha256', 64)->nullable();

            $table->timestamps();

            $table->index('attempted_at');
            $table->index(['uuid', 'attempted_at']);
            $table->index(['company_id', 'attempted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cfdi_validation_logs');
    }
};
