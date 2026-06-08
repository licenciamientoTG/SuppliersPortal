<?php

namespace Tests\Feature;

use App\Models\CostCenter;
use App\Models\Company;
use App\Models\Category;
use App\Models\User;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\ProductService;
use App\Models\ExpenseCategory;
use App\Models\BudgetCedula;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequisitionItemCostCenterTest extends TestCase
{
    use RefreshDatabase;

    private function makeCostCenter(): CostCenter
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create();
        $user = User::factory()->create();

        return CostCenter::factory()->create([
            'company_id'          => $company->id,
            'category_id'         => $category->id,
            'responsible_user_id' => $user->id,
            'budget_type'         => 'FREE_CONSUMPTION',
            'global_amount'       => 100000,
            'status'              => 'ACTIVO',
        ]);
    }

    public function test_cost_center_id_is_fillable(): void
    {
        $this->assertContains('cost_center_id', (new RequisitionItem())->getFillable());
    }

    public function test_cost_center_relation_returns_cost_center_instance(): void
    {
        $costCenter = $this->makeCostCenter();
        $item = new RequisitionItem(['cost_center_id' => $costCenter->id]);
        $item->cost_center_id = $costCenter->id;

        $this->assertInstanceOf(CostCenter::class, $item->costCenter()->getRelated());
    }

    public function test_is_valid_requires_cost_center_id(): void
    {
        $item = new RequisitionItem([
            'product_service_id'  => 1,
            'expense_category_id' => 1,
            'budget_cedula_id'    => 1,
            'quantity'            => 1,
            'cost_center_id'      => null,
        ]);

        $this->assertFalse($item->isValid());
    }

    public function test_is_valid_passes_with_all_required_fields(): void
    {
        $item = new RequisitionItem([
            'product_service_id'  => 1,
            'expense_category_id' => 1,
            'budget_cedula_id'    => 1,
            'quantity'            => 1,
            'cost_center_id'      => 1,
        ]);

        $this->assertTrue($item->isValid());
    }
}
