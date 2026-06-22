<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfq_responses', function (Blueprint $table) {
            // Diferencia explícita entre "no cotizada" y "cotizada en $0 real".
            // Default false: las filas existentes quedan como cotizadas.
            $table->boolean('not_available')->default(false)->after('meets_specs');
        });
    }

    public function down(): void
    {
        Schema::table('rfq_responses', function (Blueprint $table) {
            $table->dropColumn('not_available');
        });
    }
};
