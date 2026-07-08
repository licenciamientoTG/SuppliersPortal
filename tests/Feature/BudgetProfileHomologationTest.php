<?php

namespace Tests\Feature;

use App\Models\BudgetProfile;
use App\Models\Employee;
use App\Models\EmployeePositionBudgetProfile;
use App\Models\User;
use App\Services\BudgetProfileHomologationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BudgetProfileHomologationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('excludedJobTitles')]
    public function test_service_excludes_configured_job_titles_from_budget_profiles(string $jobTitle): void
    {
        Employee::factory()->create([
            'job_title' => $jobTitle,
            'is_active' => 'SI',
        ]);

        app(BudgetProfileHomologationService::class)->syncEmployeePositions();

        $position = EmployeePositionBudgetProfile::where('raw_job_title', $jobTitle)->first();

        $this->assertNotNull($position);
        $this->assertTrue($position->is_excluded);
        $this->assertNull($position->budget_profile_id);
    }

    public static function excludedJobTitles(): array
    {
        return [
            ['Mensajero'],
            ['Escoltas'],
            ['Velador'],
            ['Vigilante'],
            ['Chofer Operador'],
            ['Auxiliar De Limpieza'],
            ['Chofer Ejecutivo'],
            ['Operador'],
            ['Oficial de Servicio al Cliente'],
        ];
    }

    public function test_service_excludes_misiones_aqua_department_from_budget_profiles(): void
    {
        $employee = Employee::factory()->create([
            'department' => 'Misiones Aqua',
            'job_title' => 'Desarrollador De Software',
            'is_active' => 'SI',
        ]);
        $user = User::factory()->create(['job_title' => null]);
        $employee->update(['user_id' => $user->id]);

        app(BudgetProfileHomologationService::class)->syncEmployeePositions();
        app(BudgetProfileHomologationService::class)->assignBudgetProfileFromEmployee($user, $employee);

        $this->assertDatabaseMissing('employee_position_budget_profile', [
            'raw_job_title' => 'Desarrollador De Software',
        ]);
        $this->assertNull($user->refresh()->budget_profile_id);
    }

    public function test_service_assigns_budget_profile_from_employee_job_title(): void
    {
        $employee = Employee::factory()->create([
            'job_title' => 'Desarrollador De Software',
            'is_active' => 'SI',
        ]);
        $user = User::factory()->create(['job_title' => null]);
        $employee->update(['user_id' => $user->id]);

        app(BudgetProfileHomologationService::class)->syncEmployeePositions();
        app(BudgetProfileHomologationService::class)->assignBudgetProfileFromEmployee($user, $employee);

        $user->refresh();

        $this->assertNotNull($user->budget_profile_id);
        $this->assertSame('technology', BudgetProfile::find($user->budget_profile_id)->key);
    }
}
