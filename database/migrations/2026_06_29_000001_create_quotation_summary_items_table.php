<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quotation_summary_items')) {
            Schema::create('quotation_summary_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quotation_summary_id')->constrained('quotation_summaries')->cascadeOnDelete();
                $table->foreignId('rfq_response_id')->constrained('rfq_responses')->noActionOnDelete();
                $table->foreignId('requisition_item_id')->constrained('requisition_items')->noActionOnDelete();
                $table->decimal('quoted_quantity', 10, 3);
                $table->decimal('approved_quantity', 10, 3)->default(0);
                $table->decimal('rejected_quantity', 10, 3)->default(0);
                $table->decimal('unit_price', 12, 2);
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('iva_rate', 5, 2)->default(16.00);
                $table->decimal('iva_amount', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->string('approval_status', 30)->default('pending');
                $table->text('approver_notes')->nullable();
                $table->timestamps();

                $table->unique(['quotation_summary_id', 'rfq_response_id'], 'qsi_summary_response_unique');
                $table->index(['quotation_summary_id', 'approval_status'], 'qsi_summary_status_index');
            });
        }

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("UPDATE quotation_summaries SET approval_status = 'pending' WHERE approval_status IS NULL");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_summary_items');
    }
};
