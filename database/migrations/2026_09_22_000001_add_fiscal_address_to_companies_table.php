<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domicilio fiscal y régimen fiscal de la empresa compradora (membrete de OC/OCD).
 * Solo agrega columnas opcionales: los registros existentes quedan intactos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('tax_regime', 3)->nullable()->comment('Clave SAT del régimen fiscal (c_RegimenFiscal)');
            $table->string('fiscal_street', 150)->nullable()->comment('Domicilio fiscal: calle');
            $table->string('fiscal_exterior_number', 20)->nullable()->comment('Domicilio fiscal: número exterior');
            $table->string('fiscal_interior_number', 20)->nullable()->comment('Domicilio fiscal: número interior');
            $table->string('fiscal_neighborhood', 100)->nullable()->comment('Domicilio fiscal: colonia');
            $table->string('fiscal_municipality', 100)->nullable()->comment('Domicilio fiscal: municipio o alcaldía');
            $table->string('fiscal_state', 50)->nullable()->comment('Domicilio fiscal: estado');
            $table->string('fiscal_postal_code', 5)->nullable()->comment('Domicilio fiscal: código postal');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'tax_regime',
                'fiscal_street',
                'fiscal_exterior_number',
                'fiscal_interior_number',
                'fiscal_neighborhood',
                'fiscal_municipality',
                'fiscal_state',
                'fiscal_postal_code',
            ]);
        });
    }
};
