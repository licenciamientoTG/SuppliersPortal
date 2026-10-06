<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetCedula;
use App\Models\BudgetCommitment;
use App\Models\BudgetDistributionBaseline;
use App\Models\BudgetMonthlyDistribution;
use App\Models\BudgetMovement;
use App\Models\BudgetMovementDetail;
use App\Models\Category;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\DirectPurchaseOrder;
use App\Models\ExpenseCategory;
use App\Models\PurchaseOrder;
use App\Models\QuotationSummary;
use App\Models\User;
use App\Services\BudgetAllocationService;
use App\Services\BudgetPositionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RP-01 (puntos 3 a 5): posición presupuestal por renglón. Reporte y bloqueo
 * deben salir del mismo cálculo y los montos deben sumar el vigente.
 */
class BudgetPositionServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private BudgetPositionService $positions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->positions = app(BudgetPositionService::class);
    }

    public function test_splits_a_line_into_reserved_committed_accrued_and_available(): void
    {
        $line = $this->line();
        $this->addCommitment($line, 'quotation', 500);
        $this->addCommitment($line, 'purchase_order', 1000, 400);

        $position = $this->positions->fromDistributions(collect([$line->fresh()]))->sole();

        $this->assertSame(10000.0, $position['current_budget']);
        $this->assertSame(500.0, $position['reserved']);
        $this->assertSame(600.0, $position['committed']);
        $this->assertSame(400.0, $position['accrued']);
        $this->assertNull($position['paid']);
        $this->assertSame(8500.0, $position['available']);
        $this->assertSame(0.0, $position['unreconciled']);
    }

    public function test_direct_purchase_orders_count_as_committed(): void
    {
        $line = $this->line();
        $this->addCommitment($line, 'direct_purchase_order', 750);

        $position = $this->positions->fromDistributions(collect([$line->fresh()]))->sole();

        $this->assertSame(0.0, $position['reserved']);
        $this->assertSame(750.0, $position['committed']);
    }

    public function test_released_commitments_do_not_count_in_any_amount(): void
    {
        $line = $this->line();
        $this->addCommitment($line, 'purchase_order', 1000, 0, 'RELEASED');

        $position = $this->positions->fromDistributions(collect([$line->fresh()]))->sole();

        $this->assertSame(0.0, $position['committed']);
        $this->assertSame(10000.0, $position['available']);
        $this->assertSame(1000.0, $position['released']);
    }

    public function test_counter_without_a_backing_document_is_shown_as_unreconciled(): void
    {
        $line = $this->line();
        $line->update(['committed_amount' => 300]);

        $position = $this->positions->fromDistributions(collect([$line->fresh()]))->sole();

        $this->assertSame(0.0, $position['committed']);
        $this->assertSame(300.0, $position['unreconciled']);
        $this->assertSame(9700.0, $position['available']);
    }

    public function test_available_is_reported_negative_when_the_line_is_overdrawn(): void
    {
        $line = $this->line(1000);
        $this->overdraw($line, 1200);

        $position = $this->positions->fromDistributions(collect([$line->fresh()]))->sole();

        $this->assertSame(-200.0, $position['available']);
    }

    public function test_amounts_add_up_to_current_budget_for_random_filter_combinations(): void
    {
        mt_srand(20261005);
        $lines = $this->randomScenario();

        for ($i = 0; $i < 20; $i++) {
            $filters = $this->randomFilters($lines);
            $positions = $this->positions->positions($filters);

            $this->assertNotEmpty($positions, 'Filtros: '.json_encode($filters));
            foreach ($positions as $p) {
                $this->assertIdentity($p);
            }
            $this->assertEqualsWithDelta(
                round($positions->sum('current_budget'), 2),
                round($positions->sum(fn ($p) => $p['reserved'] + $p['committed'] + $p['accrued'] + $p['available'] + $p['unreconciled']), 2),
                0.001,
                'Filtros: '.json_encode($filters)
            );
        }
    }

    public function test_every_open_commitment_lands_in_exactly_one_amount(): void
    {
        mt_srand(7);
        $this->randomScenario();

        $positions = $this->positions->positions([]);
        $open = BudgetCommitment::query()->where('status', 'COMMITTED')->get()
            ->sum(fn ($c) => round((float) $c->committed_amount - (float) $c->consumed_amount, 2));

        $this->assertEqualsWithDelta($open, $positions->sum('reserved') + $positions->sum('committed'), 0.001);
        $this->assertSame(0.0, round($positions->sum('unreconciled'), 2));
    }

    public function test_filters_by_company_fiscal_year_month_cost_center_and_line(): void
    {
        $a = $this->line(1000, month: 3);
        $b = $this->line(2000, month: 4);

        $this->assertSame([$a->id], $this->positions->positions(['months' => [3]])->pluck('distribution_id')->all());
        $this->assertSame([$b->id], $this->positions->positions(['cost_center_ids' => [$b->annualBudget->cost_center_id]])->pluck('distribution_id')->all());
        $this->assertSame([$b->id], $this->positions->positions(['company_ids' => [$b->annualBudget->costCenter->company_id]])->pluck('distribution_id')->all());
        $this->assertSame([$a->id], $this->positions->positions(['budget_cedula_ids' => [$a->budget_cedula_id]])->pluck('distribution_id')->all());
        $this->assertSame([$a->id], $this->positions->positions(['expense_category_ids' => [$a->expense_category_id]])->pluck('distribution_id')->all());
        $this->assertCount(0, $this->positions->positions(['fiscal_year' => (int) now()->year + 1]));
    }

    public function test_lines_of_unapproved_budgets_are_excluded(): void
    {
        $line = $this->line();
        $line->annualBudget->update(['status' => 'PLANIFICACION']);

        $this->assertCount(0, $this->positions->positions([]));
    }

    public function test_guard_uses_the_same_available_amount_as_the_report_for_a_subaccount(): void
    {
        $line = $this->line();
        $this->addCommitment($line, 'quotation', 500);
        $this->addCommitment($line, 'purchase_order', 1000, 400);
        $center = $line->annualBudget->cost_center_id;

        $check = app(BudgetAllocationService::class)->checkAvailability($center, (int) now()->year, $line->month, $line->expense_category_id, 1, $line->budget_cedula_id);
        $position = $this->positions->positions(['budget_cedula_ids' => [$line->budget_cedula_id]])->sole();

        $this->assertSame($position['available'], $check['available_amount']);
        $this->assertSame(8500.0, $check['available_amount']);
    }

    public function test_guard_uses_the_same_available_amount_as_the_report_for_a_category(): void
    {
        $first = $this->line(1000);
        $second = $this->line(2000, center: $first->annualBudget->costCenter, category: $first->expenseCategory);
        $this->overdraw($second, 2500);
        $this->addCommitment($first, 'purchase_order', 300);

        $check = app(BudgetAllocationService::class)->checkAvailability($first->annualBudget->cost_center_id, (int) now()->year, $first->month, $first->expense_category_id, 1);
        $positions = $this->positions->positions(['expense_category_ids' => [$first->expense_category_id]]);

        // El bloqueo no deja que el sobregiro de una subcuenta reste disponible a las demás.
        $this->assertSame(round($positions->sum(fn ($p) => max(0, $p['available'])), 2), $check['available_amount']);
        $this->assertSame(700.0, $check['available_amount']);
    }

    private function assertIdentity(array $p): void
    {
        $this->assertEqualsWithDelta(
            $p['current_budget'],
            round($p['reserved'] + $p['committed'] + $p['accrued'] + $p['available'] + $p['unreconciled'], 2),
            0.001,
            'Renglón '.$p['distribution_id']
        );
    }

    public function test_authorized_plus_increases_minus_decreases_reconciles_with_current_budget(): void
    {
        $line = $this->line(10000);
        $this->baseline($line, 9000);
        $this->movement($line, 1500);
        $this->movement($line, -500);

        $position = $this->positions->positions([])->sole();

        $this->assertSame(9000.0, $position['authorized_amount']);
        $this->assertSame(1500.0, $position['increases']);
        $this->assertSame(500.0, $position['decreases']);
        $this->assertSame(0.0, $position['budget_difference']);
        $this->assertSame('CONCILIA', $position['baseline_status']);
    }

    public function test_a_line_whose_reconstruction_does_not_match_is_flagged(): void
    {
        $line = $this->line(10000);
        $this->baseline($line, 9000);

        $position = $this->positions->positions([])->sole();

        $this->assertSame(-1000.0, $position['budget_difference']);
        $this->assertSame('DIFERENCIA', $position['baseline_status']);
    }

    public function test_a_line_without_baseline_reports_null_authorized_amount_not_zero(): void
    {
        $this->line(10000);
        $other = $this->line(5000);
        $this->baseline($other, 5000);

        $positions = $this->positions->positions([]);
        $total = $this->positions->summarize($positions);

        $this->assertNull($positions->firstWhere('baseline_status', 'SIN_BASE')['authorized_amount']);
        $this->assertNull($total['authorized_amount']);
        $this->assertSame(1, $total['lines_without_baseline']);
    }

    public function test_movements_of_unapproved_requests_are_ignored(): void
    {
        $line = $this->line(10000);
        $this->baseline($line, 10000);
        $this->movement($line, 700, BudgetMovement::STATUS_PENDING);

        $position = $this->positions->positions([])->sole();

        $this->assertSame(0.0, $position['increases']);
        $this->assertSame('CONCILIA', $position['baseline_status']);
    }

    public function test_progress_and_traffic_light_follow_the_configured_thresholds(): void
    {
        $green = $this->line(1000);
        $this->addCommitment($green, 'purchase_order', 790);
        $yellow = $this->line(1000);
        $this->addCommitment($yellow, 'quotation', 850);
        $red = $this->line(1000);
        $this->addCommitment($red, 'purchase_order', 1000, 1000);
        // Renglón reducido a cero con consumo previo.
        $empty = $this->line(100);
        DB::table('budget_monthly_distributions')->where('id', $empty->id)->update(['assigned_amount' => 0]);
        $this->overdraw($empty, 50);

        $positions = $this->positions->positions([])->keyBy('distribution_id');

        $this->assertSame(0.79, $positions[$green->id]['progress_pct']);
        $this->assertSame('VERDE', $positions[$green->id]['traffic_light']);
        $this->assertSame(0.85, $positions[$yellow->id]['progress_pct']);
        $this->assertSame('AMARILLO', $positions[$yellow->id]['traffic_light']);
        $this->assertSame('ROJO', $positions[$red->id]['traffic_light']);
        $this->assertNull($positions[$empty->id]['progress_pct']);
        $this->assertSame('ROJO', $positions[$empty->id]['traffic_light']);

        config(['budget_position.traffic_light.yellow' => 0.9]);
        $this->assertSame('VERDE', $this->positions->positions([])->firstWhere('distribution_id', $yellow->id)['traffic_light']);
    }

    public function test_exercised_excludes_reserved_and_consumed_includes_everything_not_available(): void
    {
        $line = $this->line(10000);
        $this->addCommitment($line, 'quotation', 500);
        $this->addCommitment($line, 'purchase_order', 1000, 400);

        $position = $this->positions->positions([])->sole();

        $this->assertSame(1000.0, $position['exercised_total']);
        $this->assertSame(1500.0, $position['consumed_total']);
        $this->assertSame(0.15, $position['progress_pct']);
    }

    public function test_subtotals_add_amounts_and_recompute_the_percentage(): void
    {
        $small = $this->line(100);
        $this->addCommitment($small, 'purchase_order', 100);
        $large = $this->line(900);

        $total = $this->positions->summarize($this->positions->positions([]));

        // Promediar porcentajes daría 50 %; sumando montos es 10 %.
        $this->assertSame(1000.0, $total['current_budget']);
        $this->assertSame(100.0, $total['consumed_total']);
        $this->assertSame(0.1, $total['progress_pct']);
        $this->assertSame('VERDE', $total['traffic_light']);
    }

    public function test_accumulated_projects_close_with_the_average_of_the_last_three_closed_months(): void
    {
        $year = (int) now()->year;
        $first = $this->line(10000, month: 3);
        $center = $first->annualBudget->costCenter;
        $category = $first->expenseCategory;
        $cedula = BudgetCedula::find($first->budget_cedula_id);
        $april = $this->line(10000, 4, $center, $category, cedula: $cedula);
        $may = $this->line(10000, 5, $center, $category, cedula: $cedula);
        $july = $this->line(10000, 7, $center, $category, cedula: $cedula);
        $this->addCommitment($first, 'purchase_order', 300);
        $this->addCommitment($april, 'purchase_order', 600, 600);
        $this->addCommitment($may, 'purchase_order', 900);
        $this->addCommitment($july, 'quotation', 1000);

        $row = $this->positions->accumulated(['fiscal_year' => $year], 5, Carbon::create($year, 6, 15))->sole();

        $this->assertSame('ACU', $row['period_scope']);
        $this->assertSame(30000.0, $row['current_budget']);
        $this->assertSame(1800.0, $row['consumed_total']);
        // 1,800 + promedio (300 + 600 + 900) / 3 × 7 meses restantes.
        $this->assertSame(6000.0, $row['projected_close']);
        $this->assertSame(3, $row['projection_basis_months']);
    }

    public function test_projection_uses_the_closed_months_available_and_is_null_without_history(): void
    {
        $year = (int) now()->year;
        $line = $this->line(10000, month: 1);
        $this->addCommitment($line, 'purchase_order', 400);

        $short = $this->positions->accumulated(['fiscal_year' => $year], 2, Carbon::create($year, 3, 10))->sole();
        $none = $this->positions->accumulated(['fiscal_year' => $year], 1, Carbon::create($year, 1, 20))->sole();

        $this->assertSame(2, $short['projection_basis_months']);
        $this->assertSame(round(400 + (400 / 2) * 10, 2), $short['projected_close']);
        $this->assertSame(0, $none['projection_basis_months']);
        $this->assertNull($none['projected_close']);
    }

    private function baseline(BudgetMonthlyDistribution $line, float $amount): void
    {
        BudgetDistributionBaseline::create([
            'annual_budget_id' => $line->annual_budget_id,
            'budget_monthly_distribution_id' => $line->id,
            'month' => $line->month,
            'expense_category_id' => $line->expense_category_id,
            'budget_cedula_id' => $line->budget_cedula_id,
            'original_amount' => $amount,
            'source' => 'TEST',
            'captured_by' => $this->user->id,
            'captured_at' => now(),
        ]);
    }

    private function movement(BudgetMonthlyDistribution $line, float $amount, string $status = BudgetMovement::STATUS_APPROVED): void
    {
        $movement = BudgetMovement::create([
            'movement_type' => $amount > 0 ? BudgetMovement::TYPE_INCREASE : BudgetMovement::TYPE_DECREASE,
            'fiscal_year' => $line->annualBudget->fiscal_year,
            'movement_date' => now()->toDateString(), 'total_amount' => abs($amount),
            'justification' => 'Movimiento de prueba para la posición presupuestal.',
            'status' => $status, 'created_by' => $this->user->id,
            'approved_by' => $status === BudgetMovement::STATUS_APPROVED ? $this->user->id : null,
            'approved_at' => $status === BudgetMovement::STATUS_APPROVED ? now() : null,
        ]);
        BudgetMovementDetail::create([
            'budget_movement_id' => $movement->id, 'detail_type' => BudgetMovementDetail::TYPE_ADJUSTMENT,
            'cost_center_id' => $line->annualBudget->cost_center_id, 'month' => $line->month,
            'expense_category_id' => $line->expense_category_id, 'budget_cedula_id' => $line->budget_cedula_id,
            'amount' => $amount,
        ]);
    }

    private function line(float $assigned = 10000, int $month = 3, ?CostCenter $center = null, ?ExpenseCategory $category = null, ?Company $company = null, ?BudgetCedula $cedula = null): BudgetMonthlyDistribution
    {
        $center ??= CostCenter::factory()->create([
            'company_id' => $company?->id ?? Company::factory(),
            'category_id' => Category::factory(),
            'responsible_user_id' => $this->user->id,
            'budget_type' => 'ANNUAL',
            'global_amount' => 0,
            'status' => 'ACTIVO',
            'purchase_type' => 'Gasto Operativo',
        ]);
        $category ??= ExpenseCategory::factory()->create();
        $budget = AnnualBudget::firstOrCreate(
            ['cost_center_id' => $center->id, 'fiscal_year' => (int) now()->year],
            ['total_annual_amount' => 0, 'status' => 'APROBADO', 'created_by' => $this->user->id]
        );

        return BudgetMonthlyDistribution::create([
            'annual_budget_id' => $budget->id,
            'budget_cedula_id' => ($cedula ?? BudgetCedula::factory()->create(['expense_category_id' => $category->id]))->id,
            'expense_category_id' => $category->id,
            'month' => $month,
            'assigned_amount' => $assigned,
            'consumed_amount' => 0,
            'committed_amount' => 0,
            'created_by' => $this->user->id,
        ])->load('annualBudget.costCenter', 'expenseCategory');
    }

    /** Simula un sobregiro ya autorizado (el modelo lo impide sin excepción aprobada). */
    private function overdraw(BudgetMonthlyDistribution $line, float $consumed): void
    {
        DB::table('budget_monthly_distributions')->where('id', $line->id)->update(['consumed_amount' => $consumed]);
    }

    /** Reproduce lo que hace BudgetAllocationService al comprometer, consumir y liberar. */
    private function addCommitment(BudgetMonthlyDistribution $line, string $document, float $amount, float $consumed = 0, string $status = 'COMMITTED'): void
    {
        $line->refresh();
        $this->assertTrue($line->commitAmount($amount));
        if ($consumed > 0) {
            $this->assertTrue($line->commitToConsume($consumed));
        }
        if ($status === 'RELEASED') {
            $this->assertTrue($line->releaseCommitment($amount - $consumed));
        }

        BudgetCommitment::create([
            'quotation_summary_id' => $document === 'quotation' ? QuotationSummary::factory()->create()->id : null,
            'purchase_order_id' => $document === 'purchase_order' ? PurchaseOrder::factory()->create()->id : null,
            'direct_purchase_order_id' => $document === 'direct_purchase_order' ? DirectPurchaseOrder::factory()->create()->id : null,
            'cost_center_id' => $line->annualBudget->cost_center_id,
            'application_month' => sprintf('%04d-%02d', $line->annualBudget->fiscal_year, $line->month),
            'expense_category_id' => $line->expense_category_id,
            'budget_cedula_id' => $line->budget_cedula_id,
            'committed_amount' => $amount,
            'consumed_amount' => $consumed,
            'status' => $status,
            'committed_at' => now(),
            'released_at' => $status === 'RELEASED' ? now() : null,
        ]);
    }

    /** Dos empresas, tres centros, dos cuentas y tres meses con compromisos al azar. */
    private function randomScenario(): Collection
    {
        $companies = Company::factory()->count(2)->create();
        $categories = ExpenseCategory::factory()->count(2)->create();
        $lines = collect();
        foreach ([$companies[0], $companies[0], $companies[1]] as $company) {
            $center = null;
            foreach ($categories as $category) {
                foreach ([1, 2, 3] as $month) {
                    $line = $this->line(mt_rand(50, 200) * 100, $month, $center, $category, $company);
                    $center = $line->annualBudget->costCenter;
                    $lines->push($line);
                }
            }
        }
        foreach ($lines->random(8) as $line) {
            $document = ['quotation', 'purchase_order', 'purchase_order'][mt_rand(0, 2)];
            $amount = mt_rand(100, 150000) / 100;
            $consumed = $document === 'purchase_order' ? round($amount * mt_rand(0, 100) / 100, 2) : 0;
            $this->addCommitment($line, $document, $amount, $consumed, mt_rand(0, 4) === 0 && $consumed == 0 ? 'RELEASED' : 'COMMITTED');
        }

        return $lines;
    }

    private function randomFilters(Collection $lines): array
    {
        $pick = fn (Collection $values) => $values->unique()->values()->random(mt_rand(1, $values->unique()->count()))->values()->all();
        $filters = [];
        if (mt_rand(0, 1)) {
            $filters['company_ids'] = $pick($lines->map(fn ($l) => $l->annualBudget->costCenter->company_id));
        }
        if (mt_rand(0, 1)) {
            $filters['cost_center_ids'] = $pick($lines->map(fn ($l) => $l->annualBudget->cost_center_id));
        }
        if (mt_rand(0, 1)) {
            $filters['months'] = $pick($lines->pluck('month'));
        }
        if (mt_rand(0, 1)) {
            $filters['expense_category_ids'] = $pick($lines->pluck('expense_category_id'));
        }
        $filters['fiscal_year'] = (int) now()->year;

        // Garantiza que la combinación tenga al menos un renglón.
        $match = $lines->first(fn ($l) => (! isset($filters['company_ids']) || in_array($l->annualBudget->costCenter->company_id, $filters['company_ids']))
            && (! isset($filters['cost_center_ids']) || in_array($l->annualBudget->cost_center_id, $filters['cost_center_ids']))
            && (! isset($filters['months']) || in_array($l->month, $filters['months']))
            && (! isset($filters['expense_category_ids']) || in_array($l->expense_category_id, $filters['expense_category_ids'])));

        return $match ? $filters : ['fiscal_year' => (int) now()->year, 'budget_cedula_ids' => [$lines->first()->budget_cedula_id]];
    }
}
