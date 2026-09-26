<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BudgetCedula;
use App\Models\BudgetProfile;
use App\Models\Department;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use App\Models\Subaccount;
use App\Models\User;
use App\Services\ProductBudgetClassificationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductServiceDepartmentCedulaFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_returns_only_departments_with_active_budget_profiles_for_each_cedula(): void
    {
        $user = User::factory()->create();

        // Departamentos
        $deptAdmin = Department::create(['name' => 'Administración', 'abbreviated' => 'ADM', 'is_active' => true, 'created_by' => $user->id]);
        $deptMaint = Department::create(['name' => 'Mantenimiento', 'abbreviated' => 'MNT', 'is_active' => true, 'created_by' => $user->id]);
        $deptInactive = Department::create(['name' => 'Inactivo', 'abbreviated' => 'INA', 'is_active' => false, 'created_by' => $user->id]);

        // Categorías y Cédulas
        $cat = ExpenseCategory::factory()->create(['code' => 'GEN', 'name' => 'General', 'status' => 'ACTIVO']);
        $cedulaA = BudgetCedula::factory()->create(['expense_category_id' => $cat->id, 'name' => 'Papelería']);
        $cedulaB = BudgetCedula::factory()->create(['expense_category_id' => $cat->id, 'name' => 'Refacciones']);
        $cedulaC = BudgetCedula::factory()->create(['expense_category_id' => $cat->id, 'name' => 'Sin perfiles']);

        $account = Account::factory()->create(['legacy_expense_category_id' => $cat->id]);
        $subaccountA = Subaccount::factory()->create(['account_id' => $account->id, 'legacy_budget_cedula_id' => $cedulaA->id, 'is_active' => true]);
        $subaccountB = Subaccount::factory()->create(['account_id' => $account->id, 'legacy_budget_cedula_id' => $cedulaB->id, 'is_active' => true]);
        $subaccountC = Subaccount::factory()->create(['account_id' => $account->id, 'legacy_budget_cedula_id' => $cedulaC->id, 'is_active' => true]);

        // Perfil 1: ADM tiene subaccountA (Cédula A)
        $profile1 = BudgetProfile::create([
            'department_id' => $deptAdmin->id,
            'key' => 'adm_profile',
            'name' => 'Perfil Admin',
            'is_active' => true,
        ]);
        $profile1->subaccounts()->attach($subaccountA->id);

        // Perfil 2: MNT tiene subaccountB (Cédula B)
        $profile2 = BudgetProfile::create([
            'department_id' => $deptMaint->id,
            'key' => 'mnt_profile',
            'name' => 'Perfil MNT',
            'is_active' => true,
        ]);
        $profile2->subaccounts()->attach($subaccountB->id);

        // Perfil 3: Inactivo con subaccountA en MNT
        $profileInactive = BudgetProfile::create([
            'department_id' => $deptMaint->id,
            'key' => 'inactive_profile',
            'name' => 'Perfil Inactivo',
            'is_active' => false,
        ]);
        $profileInactive->subaccounts()->attach($subaccountA->id);

        // Perfil 4: Departamento inactivo con subaccountA
        $profileInactiveDept = BudgetProfile::create([
            'department_id' => $deptInactive->id,
            'key' => 'ina_dept_profile',
            'name' => 'Perfil Dept Inactivo',
            'is_active' => true,
        ]);
        $profileInactiveDept->subaccounts()->attach($subaccountA->id);

        $service = app(ProductBudgetClassificationService::class);
        $result = $service->eligibleDepartmentsByCedula([$cedulaA->id, $cedulaB->id, $cedulaC->id]);

        // Cédula A solo debe tener Administración (deptAdmin)
        $this->assertTrue($result->has($cedulaA->id));
        $this->assertCount(1, $result->get($cedulaA->id));
        $this->assertSame($deptAdmin->id, $result->get($cedulaA->id)->first()->id);

        // Cédula B solo debe tener Mantenimiento (deptMaint)
        $this->assertTrue($result->has($cedulaB->id));
        $this->assertCount(1, $result->get($cedulaB->id));
        $this->assertSame($deptMaint->id, $result->get($cedulaB->id)->first()->id);

        // Cédula C no tiene ningún departamento con perfil presupuestal
        $this->assertFalse($result->has($cedulaC->id));
    }

    public function test_service_supports_budget_profile_department_pivot(): void
    {
        $user = User::factory()->create();

        $dept = Department::create(['name' => 'Operaciones', 'abbreviated' => 'OPE', 'is_active' => true, 'created_by' => $user->id]);
        $cat = ExpenseCategory::factory()->create(['code' => 'OPE', 'status' => 'ACTIVO']);
        $cedula = BudgetCedula::factory()->create(['expense_category_id' => $cat->id, 'name' => 'Combustibles']);
        $account = Account::factory()->create(['legacy_expense_category_id' => $cat->id]);
        $subaccount = Subaccount::factory()->create(['account_id' => $account->id, 'legacy_budget_cedula_id' => $cedula->id, 'is_active' => true]);

        // Perfil asociado al departamento vía pivot table budget_profile_department
        $profile = BudgetProfile::create([
            'department_id' => null,
            'key' => 'pivot_profile',
            'name' => 'Perfil Pivot',
            'is_active' => true,
        ]);
        $profile->departments()->attach($dept->id);
        $profile->subaccounts()->attach($subaccount->id);

        $service = app(ProductBudgetClassificationService::class);
        $result = $service->eligibleDepartmentsByCedula([$cedula->id]);

        $this->assertTrue($result->has($cedula->id));
        $this->assertSame($dept->id, $result->get($cedula->id)->first()->id);
    }

    public function test_create_and_edit_views_only_show_eligible_departments_per_cedula(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('catalog_admin');

        $deptAdmin = Department::create(['name' => 'Administración', 'abbreviated' => 'ADM', 'is_active' => true, 'created_by' => $user->id]);
        $deptMaint = Department::create(['name' => 'Mantenimiento', 'abbreviated' => 'MNT', 'is_active' => true, 'created_by' => $user->id]);
        $deptOther = Department::create(['name' => 'Ventas', 'abbreviated' => 'VTS', 'is_active' => true, 'created_by' => $user->id]);

        $cat = ExpenseCategory::factory()->create(['code' => 'CAT1', 'name' => 'Cat Uno', 'status' => 'ACTIVO']);
        $cedulaA = BudgetCedula::factory()->create(['expense_category_id' => $cat->id, 'name' => 'Cédula Papelería']);
        $cedulaB = BudgetCedula::factory()->create(['expense_category_id' => $cat->id, 'name' => 'Cédula Refacciones']);
        $cedulaC = BudgetCedula::factory()->create(['expense_category_id' => $cat->id, 'name' => 'Cédula Huérfana']);

        $account = Account::factory()->create(['legacy_expense_category_id' => $cat->id]);
        $subaccountA = Subaccount::factory()->create(['account_id' => $account->id, 'legacy_budget_cedula_id' => $cedulaA->id, 'is_active' => true]);
        $subaccountB = Subaccount::factory()->create(['account_id' => $account->id, 'legacy_budget_cedula_id' => $cedulaB->id, 'is_active' => true]);

        $profileA = BudgetProfile::create(['department_id' => $deptAdmin->id, 'key' => 'pa', 'name' => 'PA', 'is_active' => true]);
        $profileA->subaccounts()->attach($subaccountA->id);

        $profileB = BudgetProfile::create(['department_id' => $deptMaint->id, 'key' => 'pb', 'name' => 'PB', 'is_active' => true]);
        $profileB->subaccounts()->attach($subaccountB->id);

        // CREATE VIEW
        $responseCreate = $this->actingAs($user)->get(route('products-services.create'));
        $responseCreate->assertOk();

        $content = $responseCreate->getContent();

        // En la tarjeta de cedulaA debe estar deptAdmin, pero NO deptMaint ni deptOther
        $this->assertStringContainsString('data-cedula-id="'.$cedulaA->id.'"', $content);
        preg_match('/<section[^>]*data-cedula-id="'.$cedulaA->id.'"[^>]*>(.*?)<\/section>/s', $content, $matchesA);
        $sectionA = $matchesA[1] ?? '';
        $this->assertStringContainsString('value="'.$deptAdmin->id.'"', $sectionA);
        $this->assertStringNotContainsString('value="'.$deptMaint->id.'"', $sectionA);
        $this->assertStringNotContainsString('value="'.$deptOther->id.'"', $sectionA);

        // En la tarjeta de cedulaB debe estar deptMaint, pero NO deptAdmin
        preg_match('/<section[^>]*data-cedula-id="'.$cedulaB->id.'"[^>]*>(.*?)<\/section>/s', $content, $matchesB);
        $sectionB = $matchesB[1] ?? '';
        $this->assertStringContainsString('value="'.$deptMaint->id.'"', $sectionB);
        $this->assertStringNotContainsString('value="'.$deptAdmin->id.'"', $sectionB);

        // En la tarjeta de cedulaC no hay ningún departamento y muestra el mensaje
        preg_match('/<section[^>]*data-cedula-id="'.$cedulaC->id.'"[^>]*>(.*?)<\/section>/s', $content, $matchesC);
        $sectionC = $matchesC[1] ?? '';
        $this->assertStringContainsString('No hay departamentos con perfiles presupuestales activos', $sectionC);

        // EDIT VIEW
        $product = ProductService::factory()->create();
        $product->expenseCategories()->sync([$cat->id]);
        $product->budgetCedulas()->sync([$cedulaA->id, $cedulaB->id]);
        $product->subaccounts()->sync([$subaccountA->id, $subaccountB->id]);

        $responseEdit = $this->actingAs($user)->get(route('products-services.edit', $product));
        $responseEdit->assertOk();

        $contentEdit = $responseEdit->getContent();
        preg_match('/<section[^>]*data-cedula-id="'.$cedulaA->id.'"[^>]*>(.*?)<\/section>/s', $contentEdit, $matchesEditA);
        $sectionEditA = $matchesEditA[1] ?? '';
        $this->assertStringContainsString('value="'.$deptAdmin->id.'"', $sectionEditA);
        $this->assertStringNotContainsString('value="'.$deptMaint->id.'"', $sectionEditA);
    }
}
