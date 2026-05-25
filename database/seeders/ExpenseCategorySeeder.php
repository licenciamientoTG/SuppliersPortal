<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $createdBy = 6;

        $categories = [
            [
                'code' => 'A',
                'name' => 'Ingresos',
                'description' => 'Ingresos generados por la operacion',
                'status' => 'ACTIVO',
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'B',
                'name' => 'Costo de Venta',
                'description' => 'Costos directamente asociados a la venta de productos o servicios',
                'status' => 'ACTIVO',
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'C',
                'name' => 'Nomina',
                'description' => 'Sueldos, salarios y pagos al personal',
                'status' => 'ACTIVO',
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'D',
                'name' => 'Costo Social',
                'description' => 'Prestaciones sociales, IMSS, Infonavit y cargas patronales',
                'status' => 'ACTIVO',
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'E',
                'name' => 'Gastos de Operacion',
                'description' => 'Gastos necesarios para el funcionamiento diario de la operacion',
                'status' => 'ACTIVO',
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'F',
                'name' => 'Mantenimiento',
                'description' => 'Mantenimiento preventivo y correctivo de equipos e instalaciones',
                'status' => 'ACTIVO',
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'H',
                'name' => 'Gastos Fijos',
                'description' => 'Gastos recurrentes fijos independientes del volumen de operacion',
                'status' => 'ACTIVO',
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'I',
                'name' => 'Ingresos No Operativos',
                'description' => 'Ingresos no derivados de la operacion principal (utilidad cambiaria, rentas, otros)',
                'status' => 'ACTIVO',
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'J',
                'name' => 'Depreciaciones y Amortizaciones',
                'description' => 'Depreciacion de activos fijos y amortizacion de intangibles',
                'status' => 'ACTIVO',
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'K',
                'name' => 'CIF',
                'description' => 'Costos indirectos de fabricacion',
                'status' => 'ACTIVO',
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'L',
                'name' => 'Partidas Extraordinarias Total 2.0',
                'description' => 'Partidas extraordinarias y conceptos especiales fuera de la operacion ordinaria',
                'status' => 'ACTIVO',
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'M',
                'name' => 'Otros Proyectos',
                'description' => 'Gastos e inversiones relacionados con otros proyectos',
                'status' => 'ACTIVO',
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($categories as $category) {
            ExpenseCategory::updateOrCreate(
                ['code' => $category['code']],
                $category
            );
        }

        $this->command->info('Se actualizaron o insertaron ' . count($categories) . ' categorias de gasto correctamente.');
    }
}
