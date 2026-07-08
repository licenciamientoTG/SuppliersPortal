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

    public function test_user_inherits_subaccounts_from_department_profiles_only(): void
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

        $departmentProfile = BudgetProfile::factory()->create();
        $departmentProfile->subaccounts()->sync([$allowedSubaccount->id]);
        $department->budgetProfiles()->sync([$departmentProfile->id]);

        $legacyUserProfile = BudgetProfile::factory()->create();
        $legacyUserProfile->subaccounts()->sync([$userProfileSubaccount->id]);

        $user = User::factory()->create([
            'department_id' => $department->id,
            'budget_profile_id' => $legacyUserProfile->id,
            'job_title' => 'Desarrollador De Software',
        ]);
        $user->subaccounts()->sync([$userDirectSubaccount->id]);

        $ids = app(BudgetAccessService::class)->subaccountIdsFor($user)->all();

        $this->assertEquals([$allowedSubaccount->id], $ids);
    }

    public function test_users_in_same_department_have_same_budget_access_even_with_different_job_titles(): void
    {
        $subaccount = Subaccount::factory()->create();
        $department = Department::create([
            'name' => 'Compras Test',
            'abbreviated' => 'COMP',
            'is_active' => true,
        ]);
        $profile = BudgetProfile::factory()->create();
        $profile->subaccounts()->sync([$subaccount->id]);
        $department->budgetProfiles()->sync([$profile->id]);

        $head = User::factory()->create([
            'department_id' => $department->id,
            'job_title' => 'Jefe de Compras',
        ]);
        $assistant = User::factory()->create([
            'department_id' => $department->id,
            'job_title' => 'Auxiliar de Compras',
        ]);

        $service = app(BudgetAccessService::class);

        $this->assertSame($service->subaccountIdsFor($head)->all(), $service->subaccountIdsFor($assistant)->all());
    }
}
