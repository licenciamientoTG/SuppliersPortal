<?php

namespace Database\Seeders;

use App\Models\SatRetencion;
use App\Models\TaxCode;
use Illuminate\Database\Seeder;

class SatRetencionSeeder extends Seeder
{
    public function run(): void
    {
        $oneGoalTaxCodeIds = [
            'ISR-HON' => 5,
            'IVA-HON' => 15,
            'ISR-ARR' => 5,
            'IVA-ARR' => 15,
            'ISR-RES' => 31,
            'ISR-DIV' => 5,
            'IVA-TRA' => 7,
            'IVA-ESP' => 25,
            'IVA-DES' => 29,
            'IVA-COM' => 15,
            'ISR-EXT' => 30,
        ];

        $retenciones = [
            [
                'clave' => 'ISR-HON',
                'nombre' => 'ISR Honorarios',
                'impuesto' => 'ISR',
                'descripcion' => 'Servicios profesionales (persona física)',
                'porcentaje' => 10.0000,
                'porcentaje_display' => '10%',
                'base_calculo' => 'Monto del pago',
                'aplica_cuando' => 'Persona moral paga a persona física',
                'base_legal' => 'ISR Art. 106',
                'requiere_cfdi_retencion' => true,
                'notas' => 'Se entera en la declaración mensual del retenedor.',
            ],
            [
                'clave' => 'IVA-HON',
                'nombre' => 'IVA Honorarios',
                'impuesto' => 'IVA',
                'descripcion' => 'Retención de IVA en servicios profesionales',
                'porcentaje' => 10.6667,
                'porcentaje_display' => '10.6667% (2/3 del IVA)',
                'base_calculo' => 'IVA trasladado en el CFDI',
                'aplica_cuando' => 'Persona moral paga a persona física',
                'base_legal' => 'IVA Art. 1-A, Fr. II, inc. a)',
                'requiere_cfdi_retencion' => true,
                'notas' => 'El IVA retenido es acreditable para el retenedor.',
            ],
            [
                'clave' => 'ISR-ARR',
                'nombre' => 'ISR Arrendamiento',
                'impuesto' => 'ISR',
                'descripcion' => 'Renta de inmuebles (persona física)',
                'porcentaje' => 10.0000,
                'porcentaje_display' => '10%',
                'base_calculo' => 'Monto de la renta',
                'aplica_cuando' => 'Persona moral paga renta a persona física',
                'base_legal' => 'ISR Art. 116',
                'requiere_cfdi_retencion' => true,
                'notas' => 'Art. 116 — distinto al Art. 106 de honorarios.',
            ],
            [
                'clave' => 'IVA-ARR',
                'nombre' => 'IVA Arrendamiento',
                'impuesto' => 'IVA',
                'descripcion' => 'Retención de IVA por arrendamiento',
                'porcentaje' => 10.6667,
                'porcentaje_display' => '10.6667%',
                'base_calculo' => 'IVA trasladado en el CFDI',
                'aplica_cuando' => 'Persona moral paga a persona física',
                'base_legal' => 'IVA Art. 1-A, Fr. II, inc. b)',
                'requiere_cfdi_retencion' => true,
                'notas' => null,
            ],
            [
                'clave' => 'ISR-RES',
                'nombre' => 'ISR RESICO PF',
                'impuesto' => 'ISR',
                'descripcion' => 'Régimen Simplificado de Confianza (persona física)',
                'porcentaje' => 1.2500,
                'porcentaje_display' => '1.25%',
                'base_calculo' => 'Monto del pago',
                'aplica_cuando' => 'Persona moral paga a persona física en RESICO',
                'base_legal' => 'ISR Art. 113-J',
                'requiere_cfdi_retencion' => true,
                'notas' => 'Retención definitiva. Solicitar constancia de régimen al proveedor.',
            ],
            [
                'clave' => 'ISR-SUE',
                'nombre' => 'ISR Sueldos',
                'impuesto' => 'ISR',
                'descripcion' => 'Retención por salarios y asimilados',
                'porcentaje' => null,
                'porcentaje_display' => 'Variable — tabla Art. 96',
                'base_calculo' => 'Ingreso gravable del período',
                'aplica_cuando' => 'Patrón a empleado',
                'base_legal' => 'ISR Art. 96',
                'requiere_cfdi_retencion' => false,
                'notas' => 'No genera CFDI de retenciones; se refleja en el complemento de nómina.',
            ],
            [
                'clave' => 'ISR-DIV',
                'nombre' => 'ISR Dividendos',
                'impuesto' => 'ISR',
                'descripcion' => 'Distribución de utilidades',
                'porcentaje' => 10.0000,
                'porcentaje_display' => '10%',
                'base_calculo' => 'Dividendo distribuido',
                'aplica_cuando' => 'Persona moral distribuye a socios',
                'base_legal' => 'ISR Art. 140',
                'requiere_cfdi_retencion' => true,
                'notas' => 'Solo aplica sobre dividendos no provenientes de CUFIN.',
            ],
            [
                'clave' => 'ISR-INT',
                'nombre' => 'ISR Intereses',
                'impuesto' => 'ISR',
                'descripcion' => 'Intereses pagados por instituciones financieras',
                'porcentaje' => 0.1500,
                'porcentaje_display' => '0.15% anual sobre saldo promedio',
                'base_calculo' => 'Saldo promedio diario del período',
                'aplica_cuando' => 'Institución de crédito a persona física o moral',
                'base_legal' => 'ISR Art. 54',
                'requiere_cfdi_retencion' => true,
                'notas' => 'Tasa anual sobre el saldo, no sobre el interés. El SAT publica la tasa vigente cada año.',
            ],
            [
                'clave' => 'IVA-TRA',
                'nombre' => 'IVA Transporte',
                'impuesto' => 'IVA',
                'descripcion' => 'Autotransporte terrestre de bienes',
                'porcentaje' => 4.0000,
                'porcentaje_display' => '4%',
                'base_calculo' => 'Monto del servicio (sin IVA)',
                'aplica_cuando' => 'Persona moral contrata a persona física transportista',
                'base_legal' => 'IVA Art. 1-A, Fr. IV',
                'requiere_cfdi_retencion' => true,
                'notas' => 'Solo transporte terrestre de bienes. No aplica a pasajeros ni transporte aéreo.',
            ],
            [
                'clave' => 'IVA-ESP',
                'nombre' => 'IVA Servicios especializados',
                'impuesto' => 'IVA',
                'descripcion' => 'Subcontratación y servicios especializados',
                'porcentaje' => 6.0000,
                'porcentaje_display' => '6%',
                'base_calculo' => 'Monto del servicio (sin IVA)',
                'aplica_cuando' => 'Proveedor con registro REPSE vigente',
                'base_legal' => 'IVA Art. 1-A Bis',
                'requiere_cfdi_retencion' => true,
                'notas' => 'Verificar registro REPSE vigente antes de retener. El contratante entera al SAT.',
            ],
            [
                'clave' => 'IVA-DES',
                'nombre' => 'IVA Desperdicios',
                'impuesto' => 'IVA',
                'descripcion' => 'Adquisición de desperdicios industriales y chatarra',
                'porcentaje' => 16.0000,
                'porcentaje_display' => '16% (IVA completo)',
                'base_calculo' => 'Precio de compra',
                'aplica_cuando' => 'Adquirente de desperdicios industriales',
                'base_legal' => 'IVA Art. 1-A, Fr. III',
                'requiere_cfdi_retencion' => true,
                'notas' => 'El adquirente retiene y entera el 100% del IVA (no solo 2/3 como en honorarios).',
            ],
            [
                'clave' => 'IVA-COM',
                'nombre' => 'IVA Comisionistas',
                'impuesto' => 'IVA',
                'descripcion' => 'Comisiones pagadas a persona física',
                'porcentaje' => 10.6667,
                'porcentaje_display' => '10.6667% (2/3 del IVA)',
                'base_calculo' => 'IVA trasladado en el CFDI',
                'aplica_cuando' => 'Persona moral paga comisión a persona física',
                'base_legal' => 'IVA Art. 1-A, Fr. II, inc. c)',
                'requiere_cfdi_retencion' => true,
                'notas' => 'Aplica a agentes y representantes persona física.',
            ],
            [
                'clave' => 'ISR-EXT',
                'nombre' => 'ISR Extranjeros',
                'impuesto' => 'ISR',
                'descripcion' => 'Pagos a residentes en el extranjero',
                'porcentaje' => 25.0000,
                'porcentaje_display' => '25% (puede variar por tratado o tipo de ingreso)',
                'base_calculo' => 'Monto bruto del pago',
                'aplica_cuando' => 'PM o PF paga a residente extranjero sin establecimiento permanente',
                'base_legal' => 'ISR Arts. 153–175',
                'requiere_cfdi_retencion' => true,
                'notas' => 'La tasa varía por tipo de ingreso (regalías, intereses, servicios técnicos). Revisar convenios de doble tributación.',
            ],
            [
                'clave' => 'IVA-DIG',
                'nombre' => 'IVA Plataformas digitales',
                'impuesto' => 'IVA',
                'descripcion' => 'Servicios digitales de residentes en el extranjero',
                'porcentaje' => null,
                'porcentaje_display' => '1.4% – 16% según tipo de intermediación',
                'base_calculo' => 'Precio del servicio',
                'aplica_cuando' => 'Plataformas digitales extranjeras con usuarios en México',
                'base_legal' => 'IVA Arts. 18-J y 18-K',
                'requiere_cfdi_retencion' => true,
                'notas' => 'Aplica a apps de transporte, hospedaje, contenidos, etc.',
            ],
        ];

        foreach ($retenciones as $retencion) {
            $taxCodeId = isset($oneGoalTaxCodeIds[$retencion['clave']])
                ? TaxCode::query()
                    ->where('one_goal_id', $oneGoalTaxCodeIds[$retencion['clave']])
                    ->value('id')
                : null;

            SatRetencion::updateOrCreate(
                ['clave' => $retencion['clave']],
                [...$retencion, 'tax_code_id' => $taxCodeId]
            );
        }
    }
}
