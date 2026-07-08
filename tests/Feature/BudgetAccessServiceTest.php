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

    public function test_it_combines_department_profile_and_direct_subaccounts_without_duplicates(): void
    {
        $departmentSubaccount = Subaccount::factory()->create();
        $profileSubaccount = Subaccount::factory()->create();
        $directSubaccount = Subaccount::factory()->create();

        $department = Department::create([
            'name' => 'Sistemas Test',
            'abbreviated' => 'SIST',
            'is_active' => true,
        ]);
        $department->subaccounts()->sync([$departmentSubaccount->id, $profileSubaccount->id]);

        $profile = BudgetProfile::factory()->create();
        $profile->subaccounts()->sync([$profileSubaccount->id]);

        $user = User::factory()->create([
            'department_id' => $department->id,
            'budget_profile_id' => $profile->id,
        ]);
        $user->subaccounts()->sync([$directSubaccount->id]);

        $ids = app(BudgetAccessService::class)->subaccountIdsFor($user)->all();

        $this->assertEqualsCanonicalizing([
            $departmentSubaccount->id,
            $profileSubaccount->id,
            $directSubaccount->id,
        ], $ids);
    }
}
