<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Department;
use App\Models\Requisition;
use App\Services\ReportingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_authorized_user_can_open_the_reports_catalog(): void
    {
        $this->actingAs($this->reportViewer())
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSeeText('Reportería integral')
            ->assertSeeText('Órdenes en riesgo');
    }

    public function test_report_data_rejects_an_invalid_date_range(): void
    {
        $this->actingAs($this->reportViewer())
            ->getJson(route('reports.data', 'requisition-funnel').'?date_from=2026-02-01&date_to=2026-01-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_to');
    }

    public function test_contract_report_exposes_the_contract_filter(): void
    {
        $this->actingAs($this->reportViewer())
            ->get(route('reports.show', 'contracts-usage'))
            ->assertOk()
            ->assertSee('name="contract_id"', false)
            ->assertSeeText('Consumo y vigencia de contratos');
    }

    public function test_superadmin_can_persist_the_validation_sla_goal(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        $this->actingAs($user)
            ->put(route('reports.settings.validation-sla'), ['sla_days' => 4])
            ->assertRedirect(route('reports.show', 'purchasing-sla'));

        $this->assertDatabaseHas('report_settings', [
            'key' => 'purchasing_validation_sla_days',
            'value' => 4,
        ]);
    }

    public function test_department_report_uses_the_requisitioners_department(): void
    {
        $department = Department::create(['name' => 'Operaciones', 'abbreviated' => 'OPS']);
        $requester = User::factory()->create(['department_id' => $department->id]);
        Requisition::factory()->create([
            'requested_by' => $requester->id,
            'created_by' => $requester->id,
            'department_id' => null,
            'created_at' => now(),
        ]);

        $result = app(ReportingService::class)->result('requisitions-by-department', [
            'date_from' => now()->startOfDay()->toDateString(),
            'date_to' => now()->endOfDay()->toDateString(),
        ]);

        $this->assertSame('Operaciones', $result['rows']->first()->departamento);
    }

    private function reportViewer(): User
    {
        $user = User::factory()->create();
        $user->assignRole('report_viewer');

        return $user;
    }
}
