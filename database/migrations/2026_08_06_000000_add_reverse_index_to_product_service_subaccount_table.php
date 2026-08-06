<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_service_subaccount', function (Blueprint $table) {
            $table->index(
                ['subaccount_id', 'product_service_id'],
                'ps_subaccount_product_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('product_service_subaccount', function (Blueprint $table) {
            $table->dropIndex('ps_subaccount_product_idx');
        });
    }
};
