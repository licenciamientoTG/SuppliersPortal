<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registra qué parte de cada compromiso ya se consumió, para poder
     * reconocer el gasto en proporción a lo efectivamente recibido en
     * recepciones parciales (antes se consumía siempre el 100%).
     */
    public function up(): void
    {
        Schema::table('budget_commitments', function (Blueprint $table): void {
            $table->decimal('consumed_amount', 12, 2)
                ->default(0)
                ->after('committed_amount')
                ->comment('Parte del compromiso ya reconocida como consumo por recepciones');
        });

        // Los compromisos ya cerrados como RECEIVED se consumieron por completo.
        DB::table('budget_commitments')
            ->where('status', 'RECEIVED')
            ->update(['consumed_amount' => DB::raw('committed_amount')]);
    }

    public function down(): void
    {
        Schema::table('budget_commitments', function (Blueprint $table): void {
            $table->dropColumn('consumed_amount');
        });
    }
};
