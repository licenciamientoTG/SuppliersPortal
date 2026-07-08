<?php

namespace Tests\Feature;

use App\Models\BudgetProfile;
use App\Models\Department;
use App\Models\Subaccount;
use App\Models\User;
use App\Services\BudgetAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_inherits_subaccounts_from_department_profiles_and_direct_assignments(): void
    {
        $allowedSubaccount = Subaccount::factory()->create();
        $departmentDirectSubaccount = Subaccount::factory()->create();
        $userDirectSubaccount = Subaccount::factory()->create();
        $userProfileSubaccount = Subaccount::factory()->create();

        $department = Department::create([
            'name' => 'Sistemas Test',
            'abbreviated' => 'SIST',
            'is_active' => true,
        ]);
        $department->subaccounts()->sync([$departmentDirectSubaccount->id]);

        $departmentProfile = BudgetProfile::factory()->create(['department_id' => $department->id]);
        $departmentProfile->subaccounts()->sync([$allowedSubaccount->id]);

        $legacyUserProfile = BudgetProfile::factory()->create(['department_id' => $department->id]);
        $legacyUserProfile->subaccounts()->sync([$userProfileSubaccount->id]);

        $user = User::factory()->create([
            'department_id' => $department->id,
            'budget_profile_id' => $legacyUserProfile->id,
            'job_title' => 'Desarrollador De Software',
        ]);
        $user->subaccounts()->sync([$userDirectSubaccount->id]);
        $user->budgetProfiles()->sync([$departmentProfile->id]);

        $ids = app(BudgetAccessService::class)->subaccountIdsFor($user)->all();

        $this->assertEqualsCanonicalizing([$allowedSubaccount->id, $userDirectSubaccount->id], $ids);
    }

    public function test_budget_access_uses_assigned_profiles_not_job_titles(): void
    {
        $headSubaccount = Subaccount::factory()->create();
        $assistantSubaccount = Subaccount::factory()->create();
        $department = Department::create([
            'name' => 'Compras Test',
            'abbreviated' => 'COMP',
            'is_active' => true,
        ]);
        $headProfile = BudgetProfile::factory()->create(['department_id' => $department->id]);
        $headProfile->subaccounts()->sync([$headSubaccount->id]);
        $assistantProfile = BudgetProfile::factory()->create(['department_id' => $department->id]);
        $assistantProfile->subaccounts()->sync([$assistantSubaccount->id]);

        $head = User::factory()->create([
            'department_id' => $department->id,
            'job_title' => 'Jefe de Compras',
        ]);
        $assistant = User::factory()->create([
            'department_id' => $department->id,
            'job_title' => 'Auxiliar de Compras',
        ]);
        $head->budgetProfiles()->sync([$headProfile->id]);
        $assistant->budgetProfiles()->sync([$assistantProfile->id]);

        $service = app(BudgetAccessService::class);

        $this->assertEquals([$headSubaccount->id], $service->subaccountIdsFor($head)->all());
        $this->assertEquals([$assistantSubaccount->id], $service->subaccountIdsFor($assistant)->all());
    }
}
