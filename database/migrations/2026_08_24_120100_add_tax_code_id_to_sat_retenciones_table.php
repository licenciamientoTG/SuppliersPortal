<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sat_retenciones', function (Blueprint $table) {
            $table->foreignId('tax_code_id')
                ->nullable()
                ->after('id')
                ->constrained('tax_codes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sat_retenciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_code_id');
        });
    }
};
