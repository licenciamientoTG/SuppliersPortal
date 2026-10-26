<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cfdi_rejection_codes', function (Blueprint $table) {
            $table->string('code', 40)->primary();
            $table->string('rule_description', 300);
            $table->string('supplier_message', 500);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Catálogo aprobado por el usuario (docs/reportes/RF-01_mapeo_y_huecos.md §8):
        // uno por cada regla que InvoiceService/CfdiXmlParser ya aplican hoy, más el de
        // la tolerancia de importe de FinancialProvisionService (ya vigente, feature cb1bd54).
        $now = now();
        DB::table('cfdi_rejection_codes')->insert([
            [
                'code' => 'RFC_EMISOR_AUSENTE',
                'rule_description' => 'El XML no contiene el RFC del emisor',
                'supplier_message' => 'El XML no contiene el RFC del emisor.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'RFC_RECEPTOR_AUSENTE',
                'rule_description' => 'El XML no contiene el RFC del receptor',
                'supplier_message' => 'El XML no contiene el RFC del receptor.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'RFC_NO_COINCIDE',
                'rule_description' => 'El RFC emisor del XML no coincide con el RFC del proveedor autenticado',
                'supplier_message' => 'El RFC emisor del XML no coincide con el RFC del proveedor.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'EFOS_69B',
                'rule_description' => 'El proveedor aparece en el listado 69-B del SAT (Definitivo/Presunto)',
                'supplier_message' => 'Tu RFC aparece en el listado de contribuyentes del SAT (69-B). No se puede registrar la factura.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'RFC_RECEPTOR_INVALIDO',
                'rule_description' => 'El RFC receptor no corresponde a ninguna empresa activa de TotalGas',
                'supplier_message' => 'El RFC receptor del XML no corresponde a una empresa activa de TotalGas.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'TOTAL_INVALIDO',
                'rule_description' => 'El total del CFDI debe ser mayor a cero',
                'supplier_message' => 'El total de la factura debe ser mayor a cero.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'UUID_DUPLICADO',
                'rule_description' => 'El UUID de este CFDI ya fue registrado previamente',
                'supplier_message' => 'El UUID de esta factura ya fue registrado.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'XML_MALFORMADO',
                'rule_description' => 'El archivo XML no se pudo leer o no contiene la estructura mínima de un CFDI (UUID/RFC/Total)',
                'supplier_message' => 'El archivo XML no es válido o está incompleto.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'TOLERANCIA_IMPORTE_EXCEDIDA',
                'rule_description' => 'El importe facturado excede la tolerancia de $0.05 contra la OC/recepción',
                'supplier_message' => 'El importe facturado difiere del esperado fuera de tolerancia. Favor de emitir una factura corregida.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cfdi_rejection_codes');
    }
};
