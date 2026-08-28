<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products_services', function (Blueprint $table): void {
            $table->string('sat_product_code', 8)
                ->nullable()
                ->after('short_name')
                ->index()
                ->comment('ClaveProdServ del catálogo SAT');
        });
    }

    public function down(): void
    {
        Schema::table('products_services', function (Blueprint $table): void {
            $table->dropIndex(['sat_product_code']);
            $table->dropColumn('sat_product_code');
        });
    }
};
