<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetCedula;
use App\Models\BudgetCommitment;
use App\Models\BudgetException;
use App\Models\BudgetMonthlyDistribution;
use App\Models\Category;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\DirectPurchaseOrder;
use App\Models\ExpenseCategory;
use App\Models\PurchaseOrder;
use App\Models\QuotationSummary;
use App\Models\User;
use App\Notifications\BudgetDailySummaryNotification;
use App\Notifications\BudgetThresholdAlertNotification;
use App\Reports\Budget\BudgetAlertsReport;
use App\Services\BudgetPositionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** RP-03: renglones en riesgo, excepciones, avisos por evento, resumen diario, permisos y exportación. */
class BudgetAlertsReportTest extends TestCase
{
    use RefreshDatabase;

    private int $year;

    protected function setUp(): void
    {
        parent::setUp();
        $this->year = (int) now()->year;
        Permission::findOrCreate('reportes.budget_alerts.ver', 'web');
        Permission::findOrCreate('reportes.budget_alerts.exportar', 'web');
    }

    public function test_users_without_permission_get_403(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get(route('budget-alert-reports.index'))->assertForbidden();
        $this->actingAs($user)->getJson(route('budget-alert-reports.data'))->assertForbidden();
        $this->actingAs($this->viewer('department_head'))->get(route('budget-alert-reports.export', ['format' => 'xlsx']))->assertForbidden();
    }

    public function test_screen_renders(): void
    {
        $this->actingAs($this->viewer('accounting'))->get(route('budget-alert-reports.index'))
            ->assertOk()->assertSee('Alertas de agotamiento, sobregiro y excepciones');
    }

    public function test_threshold_filter_and_alert_levels(): void
    {
        $user = $this->viewer('accounting');
        $this->consumed($this->line(1000), 850);
        $this->consumed($this->line(1000), 920);
        $over = $this->line(1000);
        DB::table('budget_monthly_distributions')->where('id', $over->id)->update(['consumed_amount' => 1200]);
        $this->consumed($this->line(1000), 500);

        $this->data($user)->assertJsonPath('pagination.total', 3)
            ->assertJsonPath('kpis.level_80', 1)->assertJsonPath('kpis.level_90', 1)->assertJsonPath('kpis.level_100', 1)
            // El más comprometido (sobregiro) va primero y conserva el disponible negativo.
            ->assertJsonPath('lines.0.available', -200)
            ->assertJsonPath('lines.0.alert_level', 100);
        $this->data($user, ['threshold' => 90])->assertJsonPath('pagination.total', 2);
        $this->data($user, ['threshold' => 100])->assertJsonPath('pagination.total', 1);
    }

    public function test_available_is_the_same_as_rp01_and_the_guard(): void
    {
        $user = $this->viewer('accounting');
        $line = $this->line(1000);
        $this->commit($line, 'quotation', 300);
        $this->commit($line, 'purchase_order', 600, 200);

        $row = $this->data($user)->json('lines.0');
        $position = app(BudgetPositionService::class)->fromDistributions(collect([$line->fresh()]))->sole();

        $this->assertEquals($position['available'], $row['available']);
        $this->assertEquals($position['progress_pct'], $row['progress_pct']);
        $this->assertEquals(100, $row['available']);
    }

    public function test_burn_rate_and_projected_exhaustion_follow_the_last_three_closed_months(): void
    {
        $march = $this->line(10000, month: 3);
        $center = $march->annualBudget->costCenter;
        $cedula = BudgetCedula::find($march->budget_cedula_id);
        $april = $this->line(10000, 4, $center, $march->expenseCategory, $cedula);
        $may = $this->line(10000, 5, $center, $march->expenseCategory, $cedula);
        $june = $this->line(10000, 6, $center, $march->expenseCategory, $cedula);
        $this->line(10000, 7, $center, $march->expenseCategory, $cedula);
        $this->commit($march, 'purchase_order', 300);
        $this->commit($april, 'purchase_order', 600, 600);
        $this->commit($may, 'purchase_order', 900);
        $this->commit($june, 'purchase_order', 9500);

        $row = app(BudgetAlertsReport::class)->riskLines(collect([$center->id]), $this->params(), Carbon::create($this->year, 6, 15))->sole();

        $this->assertSame(6, $row['month']);
        $this->assertSame(600.0, $row['burn_rate_3m']);
        // Quedan 500 de junio + 10,000 de julio = 10,500 ÷ 600 = 17.5 meses.
        $this->assertSame(10500.0, $row['remaining_year_available']);
        $this->assertSame(17.5, $row['months_to_exhaustion']);
        $this->assertSame(Carbon::create($this->year, 6, 1)->addMonths(17)->format('Y-m'), $row['projected_exhaustion_month']);
    }

    public function test_pending_documents_are_listed_and_show_what_is_left_if_rejected(): void
    {
        $user = $this->viewer('accounting');
        $line = $this->line(1000);
        $this->commit($line, 'quotation', 500, summaryStatus: 'pending');
        $this->commit($line, 'direct_purchase_order', 200, orderStatus: 'PENDING_APPROVAL');
        $this->commit($line, 'quotation', 150, summaryStatus: 'approved');

        $row = $this->data($user)->json('lines.0');

        $this->assertSame(2, $row['pending_docs_count']);
        $this->assertEquals(700, $row['pending_docs_amount']);
        $this->assertEquals(150, $row['available']);
        $this->assertEquals(850, $row['available_if_rejected']);
    }

    public function test_exceptions_are_listed_and_lines_with_approved_exception_appear(): void
    {
        $user = $this->viewer('accounting');
        $director = User::factory()->create();
        $line = $this->line(1000);
        $this->exception($line, 'APPROVED', $director);
        $this->exception($line, 'PENDING');
        // Aprobada sin autorizador: datos dañados que el reporte debe señalar.
        $this->exception($line, 'APPROVED', null);

        $response = $this->data($user)->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('lines.0.alert_level', null)
            ->assertJsonPath('lines.0.approved_exceptions', 2)
            ->assertJsonPath('kpis.approved_exceptions', 2)
            ->assertJsonPath('kpis.pending_exceptions', 1)
            ->assertJsonPath('kpis.incomplete_exceptions', 1);
        $this->assertCount(3, $response->json('exceptions'));
        $this->data($user, ['exception_status' => 'PENDING'])->assertJsonCount(1, 'exceptions');
        $this->data($user, ['exceptions_from' => ($this->year + 1).'-01-01', 'exceptions_to' => ($this->year + 1).'-12-31'])->assertJsonCount(0, 'exceptions');
    }

    public function test_department_head_only_sees_own_cost_centers_and_their_exceptions(): void
    {
        $head = $this->viewer('department_head');
        $own = $this->line(1000, center: $this->center(responsible: $head));
        $foreign = $this->line(1000);
        $this->consumed($own, 900);
        $this->consumed($foreign, 950);
        $this->exception($foreign, 'PENDING');

        $this->data($head)->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('lines.0.cost_center_id', $own->annualBudget->cost_center_id)
            ->assertJsonCount(0, 'exceptions');
    }

    public function test_crossing_90_percent_sends_exactly_one_alert_and_no_repeat_in_the_same_month(): void
    {
        Notification::fake();
        $responsible = User::factory()->create(['is_active' => true]);
        $line = $this->line(1000, center: $this->center(responsible: $responsible));

        $this->commit($line, 'purchase_order', 920);
        Notification::assertSentToTimes($responsible, BudgetThresholdAlertNotification::class, 1);

        $this->commit($line, 'purchase_order', 30);
        Notification::assertSentToTimes($responsible, BudgetThresholdAlertNotification::class, 1);

        // Llega al 100 %: nuevo umbral, nuevo aviso.
        $this->commit($line, 'purchase_order', 50);
        Notification::assertSentToTimes($responsible, BudgetThresholdAlertNotification::class, 2);
        $this->assertSame([80, 90, 100], DB::table('budget_threshold_alerts')->where('budget_monthly_distribution_id', $line->id)->orderBy('threshold_percent')->pluck('threshold_percent')->map(fn ($t) => (int) $t)->all());
    }

    public function test_daily_summary_sends_one_mail_per_responsible_and_one_to_controller(): void
    {
        Notification::fake();
        config(['budget_alerts.controller_email' => 'contraloria@example.test']);
        $first = User::factory()->create(['is_active' => true]);
        $second = User::factory()->create(['is_active' => true]);
        $centerA = $this->center(responsible: $first);
        $this->consumed($this->line(1000, center: $centerA), 850);
        $this->consumed($this->line(1000, center: $centerA), 950);
        $this->consumed($this->line(1000, center: $this->center(responsible: $second)), 990);
        $this->consumed($this->line(1000, center: $this->center(responsible: $second)), 100);
        Notification::fake();

        $this->artisan('budget:send-daily-alerts', ['--dry-run' => true])->assertSuccessful();
        Notification::assertNothingSent();

        $this->artisan('budget:send-daily-alerts')->assertSuccessful();
        Notification::assertSentToTimes($first, BudgetDailySummaryNotification::class, 1);
        Notification::assertSentTo($first, BudgetDailySummaryNotification::class, fn ($n) => $n->lines->count() === 2);
        Notification::assertSentTo($second, BudgetDailySummaryNotification::class, fn ($n) => $n->lines->count() === 1);
        Notification::assertSentTo(new AnonymousNotifiable, BudgetDailySummaryNotification::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'contraloria@example.test' && $n->lines->count() === 3);
    }

    public function test_excel_has_risk_and_exception_sheets_and_is_logged(): void
    {
        $user = $this->viewer('accounting');
        $user->givePermissionTo('reportes.budget_alerts.exportar');
        $line = $this->line(1000);
        $this->consumed($line, 950);
        $this->exception($line, 'APPROVED', User::factory()->create());

        $content = $this->actingAs($user)->get(route('budget-alert-reports.export', ['format' => 'xlsx', 'fiscal_year' => $this->year]))->assertOk()->streamedContent();
        $path = tempnam(sys_get_temp_dir(), 'rp03').'.xlsx';
        file_put_contents($path, $content);
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        @unlink($path);

        $this->assertSame(['Renglones en riesgo', 'Excepciones'], $book->getSheetNames());
        $risk = $book->getSheet(0)->toArray(null, false, false);
        $this->assertStringContainsString('RP-03', $risk[0][0]);
        $this->assertSame('90 %', $risk[8][array_search('Nivel de alerta', $risk[7])]);
        $this->assertEqualsWithDelta(0.95, $risk[8][array_search('% consumido', $risk[7])], 0.0001);
        $this->assertSame('Aprobada', $book->getSheet(1)->toArray(null, false, false)[8][15]);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'reportes', 'description' => 'Exportación RP-03 xlsx', 'causer_id' => $user->id]);

        $csv = $this->actingAs($user)->get(route('budget-alert-reports.export', ['format' => 'csv', 'fiscal_year' => $this->year]))->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertCount(2, array_filter(explode("\n", $csv)));
    }

    public function test_exception_decision_endpoint_is_not_available_to_non_directors(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        Permission::findOrCreate('reportes.budget_alerts.excepcion.aprobar', 'web');
        $user->givePermissionTo('reportes.budget_alerts.excepcion.aprobar');
        Role::findOrCreate('general_director', 'web');
        $exception = $this->exception($this->line(1000), 'PENDING');

        $this->actingAs($user)->postJson(route('budget-alert-reports.exceptions.decide', ['exception' => $exception->id]), ['decision' => 'APPROVED'])
            ->assertForbidden();
    }

    private function viewer(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::findOrCreate($role, 'web'));
        $user->givePermissionTo('reportes.budget_alerts.ver');

        return $user;
    }

    private function data(User $user, array $params = []): TestResponse
    {
        return $this->actingAs($user)->getJson(route('budget-alert-reports.data', $params + ['fiscal_year' => $this->year]))->assertOk();
    }

    private function params(): array
    {
        return ['fiscal_year' => $this->year, 'threshold' => 80, 'months' => [], 'exceptions_from' => "{$this->year}-01-01", 'exceptions_to' => "{$this->year}-12-31"];
    }

    private function center(?Company $company = null, ?User $responsible = null): CostCenter
    {
        return CostCenter::factory()->create([
            'company_id' => $company?->id ?? Company::factory(),
            'category_id' => Category::factory(),
            'responsible_user_id' => $responsible?->id ?? User::factory(),
            'budget_type' => 'ANNUAL', 'global_amount' => 0, 'status' => 'ACTIVO', 'purchase_type' => 'Gasto Operativo',
        ]);
    }

    private function line(float $assigned, int $month = 3, ?CostCenter $center = null, ?ExpenseCategory $category = null, ?BudgetCedula $cedula = null): BudgetMonthlyDistribution
    {
        $center ??= $this->center();
        $category ??= ExpenseCategory::factory()->create();
        $budget = AnnualBudget::firstOrCreate(
            ['cost_center_id' => $center->id, 'fiscal_year' => $this->year],
            ['total_annual_amount' => 0, 'status' => 'APROBADO', 'created_by' => $center->responsible_user_id]
        );

        return BudgetMonthlyDistribution::create([
            'annual_budget_id' => $budget->id,
            'budget_cedula_id' => ($cedula ?? BudgetCedula::factory()->create(['expense_category_id' => $category->id]))->id,
            'expense_category_id' => $category->id,
            'month' => $month,
            'assigned_amount' => $assigned,
            'consumed_amount' => 0,
            'committed_amount' => 0,
            'created_by' => $center->responsible_user_id,
        ])->load('annualBudget.costCenter', 'expenseCategory');
    }

    /** Consumo recibido sin documento abierto (como una OC ya recibida por completo). */
    private function consumed(BudgetMonthlyDistribution $line, float $amount): void
    {
        $this->commit($line, 'purchase_order', $amount, $amount);
    }

    /** Igual que BudgetAllocationService: mueve contadores del renglón y registra el compromiso. */
    private function commit(BudgetMonthlyDistribution $line, string $document, float $amount, float $consumed = 0, string $summaryStatus = 'approved', string $orderStatus = 'APPROVED'): void
    {
        $line->refresh();
        $this->assertTrue($line->commitAmount($amount));
        if ($consumed > 0) {
            $this->assertTrue($line->commitToConsume($consumed));
        }

        BudgetCommitment::create([
            'quotation_summary_id' => $document === 'quotation' ? QuotationSummary::factory()->create(['approval_status' => $summaryStatus])->id : null,
            'purchase_order_id' => $document === 'purchase_order' ? PurchaseOrder::factory()->create()->id : null,
            'direct_purchase_order_id' => $document === 'direct_purchase_order' ? DirectPurchaseOrder::factory()->create(['status' => $orderStatus])->id : null,
            'cost_center_id' => $line->annualBudget->cost_center_id,
            'application_month' => sprintf('%04d-%02d', $this->year, $line->month),
            'expense_category_id' => $line->expense_category_id,
            'budget_cedula_id' => $line->budget_cedula_id,
            'committed_amount' => $amount,
            'consumed_amount' => $consumed,
            'status' => 'COMMITTED',
            'committed_at' => now(),
        ]);
    }

    private function exception(BudgetMonthlyDistribution $line, string $status, ?User $decider = null): BudgetException
    {
        return BudgetException::create([
            'document_type' => 'direct_purchase_order', 'document_id' => 1, 'document_line_id' => 1,
            'budget_monthly_distribution_id' => $line->id,
            'cost_center_id' => $line->annualBudget->cost_center_id, 'expense_category_id' => $line->expense_category_id,
            'budget_cedula_id' => $line->budget_cedula_id, 'application_month' => sprintf('%04d-%02d', $this->year, $line->month),
            'line_amount' => 100, 'available_at_request' => 0, 'requested_excess' => 100,
            'approved_excess' => $status === 'APPROVED' ? 100 : null,
            'reason' => 'Compra urgente autorizada por Dirección.', 'status' => $status,
            'requested_by' => User::factory()->create()->id, 'requested_at' => now(),
            'decided_by' => $decider?->id, 'decided_at' => $status === 'PENDING' ? null : now(),
        ]);
    }
}
