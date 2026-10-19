<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('odc_direct_purchase_orders', function (Blueprint $table) {
            $table->decimal('exchange_rate', 18, 6)->default(1.000000)->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('odc_direct_purchase_orders', function (Blueprint $table) {
            $table->dropColumn('exchange_rate');
        });
    }
};
