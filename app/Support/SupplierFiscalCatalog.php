<?php

namespace App\Support;

class SupplierFiscalCatalog
{
    /**
     * @return array<string, string>
     */
    public static function personTypes(): array
    {
        return [
            'fisica' => 'Persona física',
            'moral' => 'Persona moral',
            'extranjero' => 'Extranjero',
        ];
    }

    /**
     * @return array<int, array{person_type:string,code:string,label:string}>
     */
    public static function taxRegimes(): array
    {
        return [
            ['person_type' => 'moral', 'code' => '601', 'label' => 'General de Ley Personas Morales'],
            ['person_type' => 'moral', 'code' => '603', 'label' => 'Personas Morales con Fines no Lucrativos'],
            ['person_type' => 'moral', 'code' => '610', 'label' => 'Residentes en el Extranjero sin Establecimiento Permanente en México'],
            ['person_type' => 'moral', 'code' => '620', 'label' => 'Sociedades Cooperativas de Producción que optan por diferir sus ingresos'],
            ['person_type' => 'moral', 'code' => '622', 'label' => 'Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras'],
            ['person_type' => 'moral', 'code' => '623', 'label' => 'Opcional para Grupos de Sociedades'],
            ['person_type' => 'moral', 'code' => '624', 'label' => 'Coordinados'],
            ['person_type' => 'moral', 'code' => '626', 'label' => 'Régimen Simplificado de Confianza'],
            ['person_type' => 'fisica', 'code' => '605', 'label' => 'Sueldos y Salarios e Ingresos Asimilados a Salarios'],
            ['person_type' => 'fisica', 'code' => '606', 'label' => 'Arrendamiento'],
            ['person_type' => 'fisica', 'code' => '607', 'label' => 'Régimen de Enajenación o Adquisición de Bienes'],
            ['person_type' => 'fisica', 'code' => '608', 'label' => 'Demás ingresos'],
            ['person_type' => 'fisica', 'code' => '610', 'label' => 'Residentes en el Extranjero sin Establecimiento Permanente en México'],
            ['person_type' => 'fisica', 'code' => '611', 'label' => 'Ingresos por Dividendos (socios y accionistas)'],
            ['person_type' => 'fisica', 'code' => '612', 'label' => 'Personas Físicas con Actividades Empresariales y Profesionales'],
            ['person_type' => 'fisica', 'code' => '614', 'label' => 'Ingresos por intereses'],
            ['person_type' => 'fisica', 'code' => '615', 'label' => 'Régimen de los ingresos por obtención de premios'],
            ['person_type' => 'fisica', 'code' => '616', 'label' => 'Sin obligaciones fiscales'],
            ['person_type' => 'fisica', 'code' => '621', 'label' => 'Incorporación Fiscal'],
            ['person_type' => 'fisica', 'code' => '625', 'label' => 'Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas'],
            ['person_type' => 'fisica', 'code' => '626', 'label' => 'Régimen Simplificado de Confianza'],
        ];
    }

    /**
     * @return array<int, array{code:string,label:string}>
     */
    public static function regimesFor(?string $personType): array
    {
        return array_values(array_map(
            fn (array $regime) => ['code' => $regime['code'], 'label' => $regime['label']],
            array_filter(
                self::taxRegimes(),
                fn (array $regime) => $regime['person_type'] === $personType
            )
        ));
    }

    /**
     * @return array<string, array{code:string,label:string,person_type:string}>
     */
    public static function regimesIndexedByCode(): array
    {
        $indexed = [];

        foreach (self::taxRegimes() as $regime) {
            $indexed[$regime['person_type'] . ':' . $regime['code']] = $regime;
        }

        return $indexed;
    }

    /**
     * @param  array<int, mixed>|null  $regimes
     * @return array<int, array{code:string,label:string}>
     */
    public static function normalizeSelectedRegimes(?string $personType, ?array $regimes): array
    {
        if ($personType === 'extranjero' || empty($regimes)) {
            return [];
        }

        $indexed = self::regimesIndexedByCode();
        $normalized = [];

        foreach ($regimes as $regime) {
            $code = is_array($regime) ? ($regime['code'] ?? null) : $regime;

            if (! is_string($code)) {
                continue;
            }

            $key = $personType . ':' . trim($code);

            if (! isset($indexed[$key])) {
                continue;
            }

            $normalized[$key] = [
                'code' => $indexed[$key]['code'],
                'label' => $indexed[$key]['label'],
            ];
        }

        return array_values($normalized);
    }

    /**
     * @param  array<int, mixed>|null  $activities
     * @return array<int, string>
     */
    public static function normalizeActivities(?array $activities): array
    {
        if (empty($activities)) {
            return [];
        }

        $normalized = [];

        foreach ($activities as $activity) {
            if (! is_scalar($activity)) {
                continue;
            }

            $value = trim(preg_replace('/\s+/', ' ', (string) $activity) ?? '');

            if ($value === '') {
                continue;
            }

            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  array<int, array{code?:string,label?:string}>|null  $regimes
     */
    public static function formatRegimes(?array $regimes): string
    {
        if (empty($regimes)) {
            return '—';
        }

        return implode(', ', array_map(
            fn (array $regime) => trim(($regime['code'] ?? '') . ' - ' . ($regime['label'] ?? ''), ' -'),
            $regimes
        ));
    }

    /**
     * @param  array<int, string>|null  $activities
     */
    public static function formatActivities(?array $activities): string
    {
        if (empty($activities)) {
            return '—';
        }

        return implode(', ', $activities);
    }

    public static function personTypeLabel(?string $personType): string
    {
        return self::personTypes()[$personType] ?? '—';
    }
}
