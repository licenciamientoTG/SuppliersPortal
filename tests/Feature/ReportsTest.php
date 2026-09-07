<?php

namespace Tests\Feature;

use App\Models\User;
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

    private function reportViewer(): User
    {
        $user = User::factory()->create();
        $user->assignRole('report_viewer');

        return $user;
    }
}
