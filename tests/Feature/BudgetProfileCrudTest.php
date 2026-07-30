<?php

namespace Tests\Feature;

use App\Http\Controllers\BudgetProfileController;
use App\Models\BudgetProfile;
use App\Models\Department;
use App\Models\Subaccount;
use App\Models\User;
use App\Services\BudgetAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BudgetProfileCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_syncs_only_users_from_its_department(): void
    {
        $department = $this->makeDepartment('Operaciones');
        $otherDepartment = $this->makeDepartment('Compras');
        $subaccount = Subaccount::factory()->create();
        $allowedUser = User::factory()->create(['department_id' => $department->id]);
        $otherUser = User::factory()->create(['department_id' => $otherDepartment->id]);

        app(BudgetProfileController::class)->storeProfile(Request::create('/', 'POST', [
            'department_id' => $department->id,
            'name' => 'Operación de prueba',
            'is_active' => true,
            'subaccount_ids' => [$subaccount->id],
            'user_ids' => [$allowedUser->id],
        ]));

        $profile = BudgetProfile::query()->firstOrFail();
        $this->assertEquals([$allowedUser->id], $profile->users()->pluck('users.id')->all());
        $this->assertEquals([$subaccount->id], $profile->subaccounts()->pluck('subaccounts.id')->all());

        $this->expectException(ValidationException::class);
        app(BudgetProfileController::class)->updateProfile(Request::create('/', 'PUT', [
            'department_id' => $department->id,
            'name' => $profile->name,
            'is_active' => true,
            'subaccount_ids' => [$subaccount->id],
            'user_ids' => [$otherUser->id],
        ]), $profile);
    }

    public function test_profile_cannot_be_deleted_while_it_has_users_and_inactive_profile_revokes_access(): void
    {
        $department = $this->makeDepartment('Sistemas');
        $subaccount = Subaccount::factory()->create(['is_active' => true]);
        $user = User::factory()->create(['department_id' => $department->id]);
        $profile = BudgetProfile::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $profile->subaccounts()->sync([$subaccount->id]);
        $profile->users()->sync([$user->id]);

        $this->assertEquals([$subaccount->id], app(BudgetAccessService::class)->subaccountIdsFor($user)->all());
        app(BudgetProfileController::class)->toggleProfile($profile);
        $this->assertEmpty(app(BudgetAccessService::class)->subaccountIdsFor($user)->all());

        app(BudgetProfileController::class)->destroyProfile($profile);
        $this->assertDatabaseHas('budget_profiles', ['id' => $profile->id]);

        $profile->users()->detach();
        app(BudgetProfileController::class)->destroyProfile($profile);
        $this->assertDatabaseMissing('budget_profiles', ['id' => $profile->id]);
    }

    private function makeDepartment(string $name): Department
    {
        return Department::query()->create([
            'name' => $name,
            'abbreviated' => strtoupper(substr($name, 0, 4)),
            'is_active' => true,
        ]);
    }
}
