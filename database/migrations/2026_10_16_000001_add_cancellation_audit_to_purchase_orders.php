<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('cancelled_by')->nullable()->after('rejected_by')->constrained('users')->noActionOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('closed_at');
            $table->text('cancellation_reason')->nullable()->after('resolution_notes');
        });

        Schema::table('odc_direct_purchase_orders', function (Blueprint $table) {
            $table->foreignId('cancelled_by')->nullable()->after('returned_by')->constrained('users')->noActionOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('closed_at');
            $table->text('cancellation_reason')->nullable()->after('resolution_notes');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['cancelled_by']);
            $table->dropColumn(['cancelled_by', 'cancelled_at', 'cancellation_reason']);
        });

        Schema::table('odc_direct_purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['cancelled_by']);
            $table->dropColumn(['cancelled_by', 'cancelled_at', 'cancellation_reason']);
        });
    }
};
