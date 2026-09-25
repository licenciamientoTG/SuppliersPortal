<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLockScreen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RequestedReportsIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(CheckLockScreen::class);
    }

    private function superadmin(): User
    {
        return User::factory()->create()->assignRole(Role::findOrCreate('superadmin', 'web'));
    }

    public function test_catalog_has_the_31_requested_reports_with_unique_codes_and_feasibility(): void
    {
        $reports = collect(config('requested_reports.reports'));

        $this->assertCount(31, $reports);
        $this->assertCount(31, $reports->pluck('code')->unique());
        $this->assertSame(range(1, 31), $reports->pluck('feasibility')->sort()->values()->all());
        $this->assertEmpty($reports->pluck('domain')->diff(array_keys(config('requested_reports.domains'))));
    }

    public function test_index_shows_one_card_per_report(): void
    {
        $response = $this->actingAs($this->superadmin())->get(route('requested-reports.index'));

        $response->assertOk()->assertSeeText('Reportes solicitados');

        foreach (config('requested_reports.reports') as $report) {
            $response->assertSeeText($report['code'])->assertSeeText($report['name']);
        }

        $this->assertSame(31, substr_count($response->getContent(), 'data-requested-report='));
    }

    public function test_sidebar_links_to_the_section(): void
    {
        $this->actingAs($this->superadmin())
            ->get(route('dashboard'))
            ->assertSee(route('requested-reports.index'), false);
    }

    public function test_users_without_reports_access_are_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('requested-reports.index'))
            ->assertForbidden();
    }
}
