<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('requisition_items', function (Blueprint $table) {
            $table->foreignId('contract_id')->nullable()->constrained()->noActionOnDelete();
            $table->foreignId('contract_product_id')->nullable()->constrained()->noActionOnDelete();
            $table->decimal('unit_price', 14, 4)->nullable();
            $table->char('currency_code', 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('requisition_items', function (Blueprint $table) {
            $table->dropForeign(['contract_id']);
            $table->dropForeign(['contract_product_id']);
            $table->dropColumn(['contract_id', 'contract_product_id', 'unit_price', 'currency_code']);
        });
    }
};
