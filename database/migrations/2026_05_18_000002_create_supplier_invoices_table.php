<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_invoices')) {
            return;
        }

        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->noActionOnDelete();
            $table->foreignId('financial_provision_id')->nullable()->constrained('financial_provisions')->nullOnDelete();
            $table->nullableMorphs('receivable');
            $table->string('uuid', 80)->unique();
            $table->string('xml_path', 500);
            $table->string('pdf_path', 500);
            $table->string('issuer_rfc', 13);
            $table->string('receiver_rfc', 13)->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('iva_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('currency', 3)->default('MXN');
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('uploaded_origin', 20);
            $table->string('status', 30)->default('UPLOADED');
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};
