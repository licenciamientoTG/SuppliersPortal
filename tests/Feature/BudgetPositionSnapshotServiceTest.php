<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetCedula;
use App\Models\BudgetMonthlyDistribution;
use App\Models\BudgetPositionSnapshot;
use App\Models\Category;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\BudgetPositionSnapshotService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** RP-01 (punto #6): foto diaria de la posición presupuestal y lectura por fecha de corte. */
class BudgetPositionSnapshotServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private BudgetPositionSnapshotService $snapshots;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->snapshots = app(BudgetPositionSnapshotService::class);
    }

    public function test_capture_persists_one_row_per_distribution_line(): void
    {
        $this->line(10000, 3);
        $this->line(5000, 4);

        $count = $this->snapshots->capture(Carbon::parse('2026-10-19'));

        $this->assertSame(2, $count);
        $this->assertSame(2, BudgetPositionSnapshot::whereDate('snapshot_date', '2026-10-19')->count());
    }

    public function test_capture_stores_the_computed_position_in_the_payload(): void
    {
        $line = $this->line(10000, 3);

        $this->snapshots->capture(Carbon::parse('2026-10-19'));

        $snapshot = BudgetPositionSnapshot::sole();
        $this->assertSame($line->annualBudget->cost_center_id, $snapshot->cost_center_id);
        // JSON no distingue 10000 de 10000.0 al decodificar: comparación por valor, no por tipo.
        $this->assertEquals(10000.0, $snapshot->payload['current_budget']);
        $this->assertEquals(10000.0, $snapshot->payload['available']);
    }

    public function test_capture_twice_the_same_day_updates_instead_of_duplicating(): void
    {
        $line = $this->line(10000, 3);
        $this->snapshots->capture(Carbon::parse('2026-10-19'));

        DB::table('budget_monthly_distributions')->where('id', $line->id)->update(['consumed_amount' => 2000]);
        $this->snapshots->capture(Carbon::parse('2026-10-19'));

        $this->assertSame(1, BudgetPositionSnapshot::count());
        $this->assertEquals(2000.0, BudgetPositionSnapshot::sole()->payload['accrued']);
    }

    public function test_positions_as_of_falls_back_to_the_nearest_earlier_snapshot(): void
    {
        $this->line(10000, 3);
        $this->snapshots->capture(Carbon::parse('2026-10-17'));

        [$dateUsed, $rows] = $this->snapshots->positionsAsOf(Carbon::parse('2026-10-19'), ['fiscal_year' => now()->year]);

        $this->assertSame('2026-10-17', $dateUsed);
        $this->assertCount(1, $rows);
    }

    public function test_positions_as_of_returns_null_date_when_there_is_no_snapshot_yet(): void
    {
        [$dateUsed, $rows] = $this->snapshots->positionsAsOf(Carbon::parse('2026-10-19'), ['fiscal_year' => now()->year]);

        $this->assertNull($dateUsed);
        $this->assertCount(0, $rows);
    }

    private function line(float $assigned = 10000, int $month = 3): BudgetMonthlyDistribution
    {
        $center = CostCenter::factory()->create([
            'company_id' => Company::factory(),
            'category_id' => Category::factory(),
            'responsible_user_id' => $this->user->id,
            'budget_type' => 'ANNUAL',
            'global_amount' => 0,
            'status' => 'ACTIVO',
            'purchase_type' => 'Gasto Operativo',
        ]);
        $category = ExpenseCategory::factory()->create();
        $budget = AnnualBudget::create([
            'cost_center_id' => $center->id,
            'fiscal_year' => (int) now()->year,
            'total_annual_amount' => 0,
            'status' => 'APROBADO',
            'created_by' => $this->user->id,
        ]);

        return BudgetMonthlyDistribution::create([
            'annual_budget_id' => $budget->id,
            'budget_cedula_id' => BudgetCedula::factory()->create(['expense_category_id' => $category->id])->id,
            'expense_category_id' => $category->id,
            'month' => $month,
            'assigned_amount' => $assigned,
            'consumed_amount' => 0,
            'committed_amount' => 0,
            'created_by' => $this->user->id,
        ])->load('annualBudget.costCenter', 'expenseCategory');
    }
}
