<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BudgetCedula;
use App\Models\Department;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use App\Models\Subaccount;
use App\Models\User;
use App\Services\ProductBudgetClassificationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDepartmentSubaccountResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_papelera_rubbermaid_rule_resolves_the_subaccount_by_department(): void
    {
        $user = User::factory()->create();
        [$maintenance, $cleaning] = $this->makeClassifications($user);
        $product = ProductService::factory()->create(['short_name' => 'PAPELERA RUBBERMAID 28 QTS']);
        $product->subaccounts()->sync([$maintenance['subaccount']->id, $cleaning['subaccount']->id]);

        $operations = Department::create(['name' => 'Operaciones', 'abbreviated' => 'OPE', 'is_active' => true, 'created_by' => $user->id]);
        $departmentFour = Department::create(['name' => 'Departamento 4', 'abbreviated' => 'D4', 'is_active' => true, 'created_by' => $user->id]);

        $product->departmentSubaccountMappings()->createMany([
            ['department_id' => $operations->id, 'subaccount_id' => $maintenance['subaccount']->id],
            ['department_id' => $departmentFour->id, 'subaccount_id' => $cleaning['subaccount']->id],
        ]);

        $service = app(ProductBudgetClassificationService::class);

        $this->assertSame($maintenance['cedula']->id, $service->resolveForProduct($product, $operations->id)['budget_cedula_id']);
        $this->assertSame($cleaning['cedula']->id, $service->resolveForProduct($product, $departmentFour->id)['budget_cedula_id']);
    }

    public function test_a_multi_subaccount_product_is_unavailable_without_a_department_rule(): void
    {
        $user = User::factory()->create();
        [$maintenance, $cleaning] = $this->makeClassifications($user);
        $product = ProductService::factory()->create();
        $product->subaccounts()->sync([$maintenance['subaccount']->id, $cleaning['subaccount']->id]);

        $department = Department::create(['name' => 'Sin regla', 'abbreviated' => 'SR', 'is_active' => true, 'created_by' => $user->id]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no tiene una subcuenta configurada');

        app(ProductBudgetClassificationService::class)->resolveForProduct($product, $department->id);
    }

    public function test_a_department_can_only_have_one_subaccount_rule_per_product(): void
    {
        $user = User::factory()->create();
        [$maintenance, $cleaning] = $this->makeClassifications($user);
        $product = ProductService::factory()->create();
        $product->subaccounts()->sync([$maintenance['subaccount']->id, $cleaning['subaccount']->id]);
        $department = Department::create(['name' => 'Operaciones', 'abbreviated' => 'OPE', 'is_active' => true, 'created_by' => $user->id]);

        $product->departmentSubaccountMappings()->create([
            'department_id' => $department->id,
            'subaccount_id' => $maintenance['subaccount']->id,
        ]);

        $this->expectException(QueryException::class);

        $product->departmentSubaccountMappings()->create([
            'department_id' => $department->id,
            'subaccount_id' => $cleaning['subaccount']->id,
        ]);
    }

    public function test_a_single_subaccount_product_keeps_its_existing_resolution_behavior(): void
    {
        $user = User::factory()->create();
        [$maintenance] = $this->makeClassifications($user);
        $product = ProductService::factory()->create();
        $product->subaccounts()->sync([$maintenance['subaccount']->id]);
        $department = Department::create(['name' => 'Operaciones', 'abbreviated' => 'OPE', 'is_active' => true, 'created_by' => $user->id]);

        $this->assertSame(
            $maintenance['cedula']->id,
            app(ProductBudgetClassificationService::class)->resolveForProduct($product, $department->id)['budget_cedula_id']
        );
    }

    private function makeClassifications(User $user): array
    {
        $maintenanceCategory = ExpenseCategory::factory()->create(['code' => 'MNT', 'created_by' => $user->id]);
        $cleaningCategory = ExpenseCategory::factory()->create(['code' => 'LMP', 'created_by' => $user->id]);
        $maintenanceCedula = BudgetCedula::factory()->create(['expense_category_id' => $maintenanceCategory->id, 'name' => 'Mantenimiento General', 'created_by' => $user->id]);
        $cleaningCedula = BudgetCedula::factory()->create(['expense_category_id' => $cleaningCategory->id, 'name' => 'Limpieza', 'created_by' => $user->id]);
        $maintenanceAccount = Account::factory()->create(['legacy_expense_category_id' => $maintenanceCategory->id, 'created_by' => $user->id]);
        $cleaningAccount = Account::factory()->create(['legacy_expense_category_id' => $cleaningCategory->id, 'created_by' => $user->id]);

        return [
            [
                'cedula' => $maintenanceCedula,
                'subaccount' => Subaccount::factory()->create([
                    'account_id' => $maintenanceAccount->id,
                    'legacy_budget_cedula_id' => $maintenanceCedula->id,
                    'name' => 'Mantenimiento General',
                    'created_by' => $user->id,
                ]),
            ],
            [
                'cedula' => $cleaningCedula,
                'subaccount' => Subaccount::factory()->create([
                    'account_id' => $cleaningAccount->id,
                    'legacy_budget_cedula_id' => $cleaningCedula->id,
                    'name' => 'Limpieza',
                    'created_by' => $user->id,
                ]),
            ],
        ];
    }
}
