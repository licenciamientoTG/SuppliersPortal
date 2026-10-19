<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Importe estimado de la partida (producto.estimated_price × cantidad), congelado al
     * momento de capturar la requisición (RC-01, punto #4). Nulo en partidas ya existentes:
     * no se simula un estimado que nunca se capturó.
     */
    public function up(): void
    {
        Schema::table('requisition_items', function (Blueprint $table) {
            $table->decimal('estimated_amount', 15, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('requisition_items', function (Blueprint $table) {
            $table->dropColumn('estimated_amount');
        });
    }
};
