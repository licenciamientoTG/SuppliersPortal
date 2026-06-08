<?php

namespace Database\Factories;

use App\Models\BudgetCedula;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use App\Models\Requisition;
use Illuminate\Database\Eloquent\Factories\Factory;

class RequisitionItemFactory extends Factory
{
    protected $model = \App\Models\RequisitionItem::class;

    public function definition(): array
    {
        $expenseCategory = ExpenseCategory::factory()->create();

        return [
            'requisition_id'      => Requisition::factory(),
            'product_service_id'  => ProductService::factory(),
            'line_number'         => $this->faker->unique()->numberBetween(1, 999),
            'item_category'       => 'producto',
            'product_code'        => strtoupper($this->faker->unique()->lexify('P-???')),
            'description'         => $this->faker->sentence(),
            'expense_category_id' => $expenseCategory->id,
            'budget_cedula_id'    => BudgetCedula::factory()->create(['expense_category_id' => $expenseCategory->id])->id,
            'cost_center_id'      => CostCenter::factory(),
            'quantity'            => 1,
            'unit'                => 'PZA',
        ];
    }
}
