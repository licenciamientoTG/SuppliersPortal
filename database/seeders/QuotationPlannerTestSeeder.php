<?php

namespace Database\Seeders;

use App\Enum\RequisitionStatus;
use App\Models\BudgetCedula;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use App\Models\ReceivingLocation;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class QuotationPlannerTestSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Iniciando carga de datos de prueba para el planificador de cotizacion...');

        $admin = User::firstOrCreate(
            ['email' => 'admin@totalgas.com'],
            [
                'name' => 'Admin Sistema',
                'password' => bcrypt('password123'),
            ]
        );

        $this->command->info('Usuario admin verificado [ID: '.$admin->id.']');

        $expenseCategory = ExpenseCategory::firstOrCreate(
            ['code' => 'EXP-OP-001'],
            [
                'name' => 'Gasto Operativo',
                'description' => 'Operacion general de estaciones de servicio',
                'status' => 'ACTIVO',
                'created_by' => $admin->id,
            ]
        );

        $budgetCedula = BudgetCedula::firstOrCreate(
            [
                'expense_category_id' => $expenseCategory->id,
                'name' => 'Seeder Planificador de Cotizacion',
            ],
            [
                'status' => 'ACTIVO',
                'created_by' => $admin->id,
            ]
        );

        $this->command->info('Categoria y cedula presupuestal verificadas.');

        $testData = [
            [
                'category' => 'Equipo de Computo',
                'products' => [
                    ['name' => 'Mouse inalambrico Logitech', 'code' => 'MOUSE-001'],
                    ['name' => 'Teclado mecanico Keychron', 'code' => 'TECLADO-001'],
                    ['name' => 'Monitor LG 27 pulgadas', 'code' => 'MONITOR-001'],
                ],
            ],
            [
                'category' => 'Papeleria',
                'products' => [
                    ['name' => 'Resma papel bond carta', 'code' => 'PAPEL-001'],
                    ['name' => 'Plumas BIC azul caja 50', 'code' => 'PLUMA-001'],
                ],
            ],
        ];

        foreach ($testData as $categoryData) {
            foreach ($categoryData['products'] as $productData) {
                ProductService::firstOrCreate(
                    ['code' => $productData['code']],
                    [
                        'short_name' => $productData['name'],
                        'technical_description' => $productData['name'].' - Especificacion tecnica corporativa requerida por TotalGas.',
                        'product_type' => 'PRODUCTO',
                        'status' => 'ACTIVE',
                        'is_active' => true,
                        'created_by' => $admin->id,
                    ]
                );
            }
        }

        $this->command->info('Catalogo de productos verificado.');

        $defaultLocation = ReceivingLocation::where('is_active', true)->firstOrFail();

        $requisition = Requisition::create([
            'company_id' => 1,
            'cost_center_id' => 1,
            'receiving_location_id' => $defaultLocation->id,
            'department_id' => 1,
            'folio' => Requisition::nextFolio(),
            'requested_by' => $admin->id,
            'required_date' => now()->addDays(15),
            'description' => 'Requisicion para pruebas del planificador de cotizacion',
            'status' => RequisitionStatus::IN_QUOTATION,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->command->info('Requisicion creada: '.$requisition->folio);

        $products = ProductService::all();

        foreach ($products as $index => $product) {
            RequisitionItem::create([
                'requisition_id' => $requisition->id,
                'product_service_id' => $product->id,
                'line_number' => $index + 1,
                'item_category' => 'PRODUCTO',
                'product_code' => $product->code,
                'description' => $product->short_name,
                'expense_category_id' => $expenseCategory->id,
                'budget_cedula_id' => $budgetCedula->id,
                'quantity' => 5,
                'unit' => 'PZA',
                'notes' => 'Partida generada para validacion del Portal de Proveedores.',
            ]);
        }

        $requisition->load(['items.productService']);

        $this->command->newLine();
        $this->command->info('Datos de prueba del planificador listos.');
        $this->command->info('ID: '.$requisition->id);
        $this->command->info('Folio: '.$requisition->folio);
        $this->command->info('Estado: '.$requisition->statusLabel());
        $this->command->info('Partidas: '.$requisition->items->count());
        $this->command->line('URL: http://localhost/requisitions/'.$requisition->id.'/quotation-planner');
    }
}
