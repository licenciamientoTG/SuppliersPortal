<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CostCenter;
use App\Models\User;
use App\Http\Middleware\CheckLockScreen;
use App\Http\Middleware\ModuleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCompanyAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_revoking_a_company_removes_only_its_cost_center_assignments(): void
    {
        $this->withoutMiddleware([ModuleAccess::class, CheckLockScreen::class]);

        $administrator = User::factory()->create();
        $user = User::factory()->create();
        $revokedCompany = Company::factory()->create();
        $retainedCompany = Company::factory()->create();
        $revokedCostCenter = CostCenter::factory()->create(['company_id' => $revokedCompany->id]);
        $retainedCostCenter = CostCenter::factory()->create(['company_id' => $retainedCompany->id]);

        $user->companies()->sync([$revokedCompany->id, $retainedCompany->id]);
        $user->costCenters()->attach($revokedCostCenter->id, [
            'is_active' => true,
            'created_by' => $administrator->id,
            'updated_by' => $administrator->id,
        ]);
        $user->costCenters()->attach($retainedCostCenter->id, [
            'is_active' => true,
            'created_by' => $administrator->id,
            'updated_by' => $administrator->id,
        ]);

        $this->actingAs($administrator)
            ->patchJson(route('users.companies.update', $user), [
                'company_ids' => [$retainedCompany->id],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame([$retainedCompany->id], $user->fresh()->companies()->pluck('companies.id')->all());
        $this->assertSame([$retainedCostCenter->id], $user->fresh()->costCenters()->pluck('cost_centers.id')->all());
    }
}
