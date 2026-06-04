<?php

namespace Database\Seeders;

use App\Models\BudgetCedula;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BudgetCedulaSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $createdBy = User::where('email', 'licenciamiento@totalgas.com')->value('id');

        // Mapear código de categoría → ID
        $categoryIds = DB::table('expense_categories')
            ->pluck('id', 'code');

        $cedulas = [
            // A - INGRESOS
            ['code' => 'A', 'name' => 'T-MAXIMA REGULAR'],
            ['code' => 'A', 'name' => 'T-SUPER PREMIUM'],
            ['code' => 'A', 'name' => 'DIESEL AUTOMOTRIZ'],
            ['code' => 'A', 'name' => 'PREPAGO facturación (neto)'],
            ['code' => 'A', 'name' => 'INGRESOS ACEITES Y LUBRICANTES'],
            ['code' => 'A', 'name' => 'SOBRANTE REGULAR'],
            ['code' => 'A', 'name' => 'SOBRANTE PREMIUM'],
            ['code' => 'A', 'name' => 'SOBRANTE DIESEL'],

            // B - COSTO DE VENTA
            ['code' => 'B', 'name' => 'GASOLINA REGULAR'],
            ['code' => 'B', 'name' => 'GASOLINA PREMIUM'],
            ['code' => 'B', 'name' => 'DIESEL'],
            ['code' => 'B', 'name' => 'MERMA GASOLINA REGULAR'],
            ['code' => 'B', 'name' => 'MERMA GASOLINA PREMIUM'],
            ['code' => 'B', 'name' => 'MERMA DIESEL'],
            ['code' => 'B', 'name' => 'ADITIVO TOTAL POWER GASOLINA REG'],
            ['code' => 'B', 'name' => 'ADITIVO TOTAL POWER GASOLINA PREM'],
            ['code' => 'B', 'name' => 'FLETE GASOLINA REGULAR'],
            ['code' => 'B', 'name' => 'FLETE GASOLINA PREMIUM'],
            ['code' => 'B', 'name' => 'FLETE DIESEL'],
            ['code' => 'B', 'name' => 'ESTIMULO REGULAR'],
            ['code' => 'B', 'name' => 'ESTIMULO PREMIUM'],
            ['code' => 'B', 'name' => 'DESCUENTO SOBRE VENTA'],
            ['code' => 'B', 'name' => 'COSTO UNIFORMES'],
            ['code' => 'B', 'name' => 'COSTO ACEITES Y LUBRICANTES'],

            // C - NOMINA
            ['code' => 'C', 'name' => 'Aguinaldo'],
            ['code' => 'C', 'name' => 'Compensaciones'],
            ['code' => 'C', 'name' => 'Días festivos'],
            ['code' => 'C', 'name' => 'Fondo de ahorro'],
            ['code' => 'C', 'name' => 'Indemnizaciones'],
            ['code' => 'C', 'name' => 'Premios de asistencia'],
            ['code' => 'C', 'name' => 'Premios de puntualidad'],
            ['code' => 'C', 'name' => 'Previsión social'],
            ['code' => 'C', 'name' => 'Prima dominical'],
            ['code' => 'C', 'name' => 'Prima vacacional'],
            ['code' => 'C', 'name' => 'Primas de antigüedad'],
            ['code' => 'C', 'name' => 'PTU'],
            ['code' => 'C', 'name' => 'Sueldos y salarios'],
            ['code' => 'C', 'name' => 'Tiempos extras'],
            ['code' => 'C', 'name' => 'Vacaciones'],

            // D - COSTO SOCIAL
            ['code' => 'D', 'name' => 'Aportaciones al infonavit'],
            ['code' => 'D', 'name' => 'Aportaciones al SAR'],
            ['code' => 'D', 'name' => 'Cuotas al IMSS'],
            ['code' => 'D', 'name' => 'Impuesto Estatal'],

            // E - GASTOS DE OPERACIÓN
            ['code' => 'E', 'name' => 'Traslado de Valores'],
            ['code' => 'E', 'name' => 'Servicios Administrativos'],
            ['code' => 'E', 'name' => 'Eventos'],
            ['code' => 'E', 'name' => 'Otros Gastos'],
            ['code' => 'E', 'name' => 'Otros impuestos y derechos'],
            ['code' => 'E', 'name' => 'Gas Natural'],
            ['code' => 'E', 'name' => 'Limpieza'],
            ['code' => 'E', 'name' => 'Propaganda y publicidad'],
            ['code' => 'E', 'name' => 'Contingencia Covid'],
            ['code' => 'E', 'name' => 'Gastos no deducibles (sin requisitos fiscales)'],
            ['code' => 'E', 'name' => 'Asesoría en sistemas'],
            ['code' => 'E', 'name' => 'Papelería y artículos de oficina'],
            ['code' => 'E', 'name' => 'Faltantes'],
            ['code' => 'E', 'name' => 'Licencias y Permisos'],
            ['code' => 'E', 'name' => 'Capacitación al personal'],
            ['code' => 'E', 'name' => 'Licencias, Software, Antivirus'],
            ['code' => 'E', 'name' => 'Fletes Combustible'],
            ['code' => 'E', 'name' => 'Viáticos y gastos de viaje'],
            ['code' => 'E', 'name' => 'Recoleccion de Basura'],
            ['code' => 'E', 'name' => 'Agua para consumo'],
            ['code' => 'E', 'name' => 'Fletes Mensajeria'],
            ['code' => 'E', 'name' => 'Consumos Propios Combustibles'],
            ['code' => 'E', 'name' => 'Asistencia técnica'],
            ['code' => 'E', 'name' => 'Combustibles Reembolsos'],
            ['code' => 'E', 'name' => 'Renta Equipo de Sistemas'],
            ['code' => 'E', 'name' => 'Timbrado Facturas'],
            ['code' => 'E', 'name' => 'Rentas No Deducibles'],
            ['code' => 'E', 'name' => 'Uniformes'],
            ['code' => 'E', 'name' => 'Contratacion Empleados'],
            ['code' => 'E', 'name' => 'Multas y Recargos'],
            ['code' => 'E', 'name' => 'Honorarios Administrativos P.F.'],
            ['code' => 'E', 'name' => 'Honorarios Administrativos P.M.'],
            ['code' => 'E', 'name' => 'Honorarios Legales P.F.'],
            ['code' => 'E', 'name' => 'Honorarios Legales P.M.'],
            ['code' => 'E', 'name' => 'Honorarios de Consultoria'],
            ['code' => 'E', 'name' => 'Seguros y fianzas'],
            ['code' => 'E', 'name' => 'Vigilancia y seguridad'],
            ['code' => 'E', 'name' => 'Prediales'],

            // F - MANTENIMIENTO
            ['code' => 'F', 'name' => 'Mantenimiento de Equipo de Computo'],
            ['code' => 'F', 'name' => 'Mantenimiento de Vehiculos'],
            ['code' => 'F', 'name' => 'Seguridad e Higiene Ambiental'],
            ['code' => 'F', 'name' => 'Certificacion SASISOPA'],
            ['code' => 'F', 'name' => 'Protección Civil, Bomberos y Municipales'],
            ['code' => 'F', 'name' => 'Verificaciones de estaciones'],
            ['code' => 'F', 'name' => 'Gastos CRE'],
            ['code' => 'F', 'name' => 'Certificacion Asea'],
            ['code' => 'F', 'name' => 'Gastos ASEA'],
            ['code' => 'F', 'name' => 'Gastos de administración de construcción'],
            ['code' => 'F', 'name' => 'Mantenimiento General'],
            ['code' => 'F', 'name' => 'Extintores'],
            ['code' => 'F', 'name' => 'Cedula COA'],
            ['code' => 'F', 'name' => 'Analisis calidad de combustible'],
            ['code' => 'F', 'name' => 'Limpieza faldon, totem, baños'],
            ['code' => 'F', 'name' => 'Refacciones dispensarios'],
            ['code' => 'F', 'name' => 'Plomeria y ferreteria'],
            ['code' => 'F', 'name' => 'Mantenimiento eléctrico'],
            ['code' => 'F', 'name' => 'Mantenimiento equipo de estación'],
            ['code' => 'F', 'name' => 'Mantenimiento de oficina estacion'],
            ['code' => 'F', 'name' => 'Calibraciones'],
            ['code' => 'F', 'name' => 'Accesorios'],
            ['code' => 'F', 'name' => 'Pinturas y Acabados'],
            ['code' => 'F', 'name' => 'Herramientas de trabajo'],
            ['code' => 'F', 'name' => 'Mantenimiento a los tanques'],
            ['code' => 'F', 'name' => 'Pruebas hermeticidad'],
            ['code' => 'F', 'name' => 'Verificación eléctrica'],
            ['code' => 'F', 'name' => 'Jardineria'],
            ['code' => 'F', 'name' => 'Fumigación'],
            ['code' => 'F', 'name' => 'Limpieza ecológica'],
            ['code' => 'F', 'name' => 'Recolección de residuos'],
            ['code' => 'F', 'name' => 'Basura y Recolección'],
            ['code' => 'F', 'name' => 'Calcamonias y senalamientos'],
            ['code' => 'F', 'name' => 'Verificaciones profeco'],

            // H - GASTOS FIJOS
            ['code' => 'H', 'name' => 'Arrendamiento Extranjeros'],
            ['code' => 'H', 'name' => 'Arrendamiento'],
            ['code' => 'H', 'name' => 'Arrendamiento a personas morales resid nal'],
            ['code' => 'H', 'name' => 'Energía eléctrica'],
            ['code' => 'H', 'name' => 'Arrendamiento a personas físicas resid nal'],
            ['code' => 'H', 'name' => 'Agua Servicio'],
            ['code' => 'H', 'name' => 'Teléfono, internet'],

            // I - INGRESOS NO OPERATIVOS
            ['code' => 'I', 'name' => 'Utilidad cambiaria'],
            ['code' => 'I', 'name' => 'Otros Ingresos'],
            ['code' => 'I', 'name' => 'Sobrantes'],
            ['code' => 'I', 'name' => 'Ingresos por Rentas'],

            // J - DEPRECIACIONES Y AMORTIZACIONES
            ['code' => 'J', 'name' => 'Depr de autom, autob, camiones, tractoc'],
            ['code' => 'J', 'name' => 'Depreciación de adaptaciones y mejoras'],
            ['code' => 'J', 'name' => 'Depreciación de edificios'],
            ['code' => 'J', 'name' => 'Depreciación de equipo de cómputo'],
            ['code' => 'J', 'name' => 'Depreciación de maquinaria y equipo'],
            ['code' => 'J', 'name' => 'Depreciación de mobiliario y eq de ofic'],

            // K - CIF
            ['code' => 'K', 'name' => 'Pérdida cambiaria'],
            ['code' => 'K', 'name' => 'Intereses a cargo bancario nacional'],
            ['code' => 'K', 'name' => 'Pérdida en vta y/o baja de autom autob camion'],
            ['code' => 'K', 'name' => 'Comisiones bancarias'],
            ['code' => 'K', 'name' => 'Intereses a cargo de personas morales nacional'],

            // L - PARTIDAS EXTRAORDINARIAS TOTAL 2.0
            ['code' => 'L', 'name' => 'Aeronautica'],
            ['code' => 'L', 'name' => 'Anapra'],
            ['code' => 'L', 'name' => 'Aztecas'],
            ['code' => 'L', 'name' => 'Custodia'],
            ['code' => 'L', 'name' => 'Electrolux'],
            ['code' => 'L', 'name' => 'Hermanos Escobar'],
            ['code' => 'L', 'name' => 'Estacion Custodia'],
            ['code' => 'L', 'name' => 'Estacion Delicias'],
            ['code' => 'L', 'name' => 'Gemela Chica'],
            ['code' => 'L', 'name' => 'Gemela Grande'],
            ['code' => 'L', 'name' => 'Lerdo'],
            ['code' => 'L', 'name' => 'Lopez Mateos'],
            ['code' => 'L', 'name' => 'Malecon'],
            ['code' => 'L', 'name' => 'Miguel de la Madrid'],
            ['code' => 'L', 'name' => 'Misiones'],
            ['code' => 'L', 'name' => 'Municipio Libre'],
            ['code' => 'L', 'name' => 'Parral'],
            ['code' => 'L', 'name' => 'Permuta'],
            ['code' => 'L', 'name' => 'Plutarco'],
            ['code' => 'L', 'name' => 'Puerto de Palos'],
            ['code' => 'L', 'name' => 'Tecnologico'],
            ['code' => 'L', 'name' => 'Operativo'],
            ['code' => 'L', 'name' => 'Ventas'],
            ['code' => 'L', 'name' => 'Expansión'],
            ['code' => 'L', 'name' => 'Administración'],

            // M - OTROS PROYECTOS
            ['code' => 'M', 'name' => 'Estación Hermanos Escobar'],
            ['code' => 'M', 'name' => 'TOP TIER'],
            ['code' => 'M', 'name' => 'Bodega de Aditivo'],
            ['code' => 'M', 'name' => 'Petrotal'],
            ['code' => 'M', 'name' => 'Expansión 22-26'],
            ['code' => 'M', 'name' => 'Zona Bajio'],
            ['code' => 'M', 'name' => 'Gasomex'],
            ['code' => 'M', 'name' => 'Estación Km 30'],
            ['code' => 'M', 'name' => 'Total Gas de Juárez'],
            ['code' => 'M', 'name' => 'Estación Praxedis'],
            ['code' => 'M', 'name' => 'Proyecto 6947'],
            ['code' => 'M', 'name' => 'Estación Aduana'],
            ['code' => 'M', 'name' => 'Sistema de documentación y procesos web'],
            ['code' => 'M', 'name' => 'Proyecto 2 Estaciones en San Miguel Allende'],
            ['code' => 'M', 'name' => 'Paneles solares'],
            ['code' => 'M', 'name' => 'Facturacion 4.0'],
            ['code' => 'M', 'name' => 'Proyecto Oficinas'],
            ['code' => 'M', 'name' => 'Casa de cambio'],
            ['code' => 'M', 'name' => 'NFC'],
            ['code' => 'M', 'name' => 'Estandarización de Procesos'],
            ['code' => 'M', 'name' => 'OMA Aeropuerto'],
            ['code' => 'M', 'name' => 'Fletes México'],
            ['code' => 'M', 'name' => 'Santiago Troncoso'],
            ['code' => 'M', 'name' => 'El castaño'],
            ['code' => 'M', 'name' => 'Assesment'],
            ['code' => 'M', 'name' => 'Proyecto Vallas Publicitarias'],
            ['code' => 'M', 'name' => 'One Goal'],
            ['code' => 'M', 'name' => 'Autolavado Tecnológico'],
            ['code' => 'M', 'name' => 'Estacion Tijuana'],
            ['code' => 'M', 'name' => 'Villa Ahumada'],
        ];

        $rows = array_map(function ($item) use ($categoryIds, $now, $createdBy) {
            return [
                'expense_category_id' => $categoryIds[$item['code']],
                'name'                => $item['name'],
                'status'              => 'ACTIVO',
                'created_by'          => $createdBy,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];
        }, $cedulas);

        foreach ($rows as $row) {
            BudgetCedula::updateOrCreate(
                [
                    'expense_category_id' => $row['expense_category_id'],
                    'name' => $row['name'],
                ],
                $row
            );
        }

        $this->command->info('✅ Se han insertado ' . count($rows) . ' cédulas presupuestarias correctamente.');
    }
}
