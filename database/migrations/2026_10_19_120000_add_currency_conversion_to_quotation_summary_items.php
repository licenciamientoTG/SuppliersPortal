<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_summary_items', function (Blueprint $table) {
            $table->char('currency', 3)->default('MXN')->after('unit_price');
            $table->decimal('exchange_rate', 18, 6)->default(1.000000)->after('currency');
            $table->decimal('unit_price_original', 18, 2)->nullable()->after('exchange_rate');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_summary_items', function (Blueprint $table) {
            $table->dropColumn(['currency', 'exchange_rate', 'unit_price_original']);
        });
    }
};
