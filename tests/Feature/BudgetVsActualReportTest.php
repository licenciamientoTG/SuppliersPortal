<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetCedula;
use App\Models\BudgetCommitment;
use App\Models\BudgetMonthlyDistribution;
use App\Models\Category;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\PurchaseOrder;
use App\Models\QuotationSummary;
use App\Models\User;
use App\Services\BudgetAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** RP-01: pantalla, datos, detalle por monto, permisos y alcance por usuario. */
class BudgetVsActualReportTest extends TestCase
{
    use RefreshDatabase;

    private int $year;

    protected function setUp(): void
    {
        parent::setUp();
        $this->year = (int) now()->year;
        Permission::findOrCreate('reportes.budget_vs_actual.ver', 'web');
    }

    public function test_users_without_permission_get_403(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get(route('budget-vs-actual-reports.index'))->assertForbidden();
        $this->actingAs($user)->getJson(route('budget-vs-actual-reports.data'))->assertForbidden();
        $this->actingAs($user)->getJson(route('budget-vs-actual-reports.detail', ['cost_center_id' => 1, 'expense_category_id' => 1, 'bucket' => 'committed']))->assertForbidden();
    }

    public function test_screen_renders_for_users_with_permission(): void
    {
        $this->actingAs($this->viewer('accounting'))->get(route('budget-vs-actual-reports.index'))
            ->assertOk()->assertSee('Presupuesto vs. ejercido por departamento y renglón');
    }

    public function test_data_returns_rows_subtotals_and_kpis_that_add_up(): void
    {
        $user = $this->viewer('accounting');
        $company = Company::factory()->create();
        $first = $this->line(10000, $this->center($company));
        $second = $this->line(5000, $first->annualBudget->costCenter);
        $other = $this->line(2000, $this->center($company));
        $this->commit($first, 'purchase_order', 3000, 1000);
        $this->commit($second, 'quotation', 500);

        $response = $this->data($user)->assertOk();

        $response->assertJsonPath('pagination.total', 3)
            ->assertJsonPath('kpis.current_budget', 17000)
            ->assertJsonPath('kpis.consumed_total', 3500)
            ->assertJsonPath('kpis.available', 13500)
            ->assertJsonPath('total.reserved', 500)
            ->assertJsonPath('total.committed', 2000)
            ->assertJsonPath('total.accrued', 1000)
            ->assertJsonPath('total.paid', null);
        $this->assertSame(15000, $response->json('cost_centers.'.$first->annualBudget->cost_center_id.'.current_budget'));
        $this->assertSame(2000, $response->json('cost_centers.'.$other->annualBudget->cost_center_id.'.current_budget'));
        $this->assertSame(17000, $response->json('companies.'.$company->id.'.current_budget'));

        foreach ($response->json('rows') as $row) {
            $this->assertEqualsWithDelta($row['current_budget'], $row['reserved'] + $row['committed'] + $row['accrued'] + $row['available'] + $row['unreconciled'], 0.001);
        }
        $rows = collect($response->json('rows'));
        $this->assertSame(1, $rows->where('last_of_company', true)->count());
        $this->assertSame(2, $rows->where('last_of_cost_center', true)->count());
    }

    public function test_report_available_is_the_amount_the_budget_guard_uses(): void
    {
        $user = $this->viewer('accounting');
        $line = $this->line(10000, $this->center());
        $this->commit($line, 'quotation', 1200);
        $this->commit($line, 'purchase_order', 2500, 700);

        $row = $this->data($user)->json('rows.0');
        $check = app(BudgetAllocationService::class)->checkAvailability($line->annualBudget->cost_center_id, $this->year, $line->month, $line->expense_category_id, 1, $line->budget_cedula_id);

        $this->assertEquals($check['available_amount'], $row['available']);
    }

    public function test_year_to_date_scope_accumulates_months_up_to_the_period(): void
    {
        $user = $this->viewer('accounting');
        $march = $this->line(1000, $this->center(), month: 3);
        $this->line(2000, $march->annualBudget->costCenter, month: 4, category: $march->expenseCategory, cedula: BudgetCedula::find($march->budget_cedula_id));

        $this->data($user, ['scope' => 'ACU', 'period_month' => 4])
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('rows.0.period_scope', 'ACU')
            ->assertJsonPath('rows.0.current_budget', 3000);
        $this->data($user, ['scope' => 'MES', 'period_month' => 4])
            ->assertJsonPath('rows.0.current_budget', 2000);
    }

    public function test_department_head_only_sees_cost_centers_they_are_responsible_for(): void
    {
        $head = $this->viewer('department_head');
        $own = $this->line(1000, $this->center(responsible: $head));
        $foreign = $this->line(9000, $this->center());

        $this->data($head)->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('rows.0.cost_center_id', $own->annualBudget->cost_center_id);
        // Pedir un centro ajeno por filtro no lo vuelve visible.
        $this->data($head, ['cost_center_ids' => [$foreign->annualBudget->cost_center_id]])->assertJsonPath('pagination.total', 0);
    }

    public function test_accounting_with_assigned_companies_only_sees_those_companies(): void
    {
        $user = $this->viewer('accounting');
        $mine = Company::factory()->create();
        $user->companies()->attach($mine->id);
        $this->line(1000, $this->center($mine));
        $this->line(9000, $this->center());

        $this->data($user)->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('rows.0.company_id', $mine->id);
    }

    public function test_detail_lists_the_documents_that_make_up_each_amount(): void
    {
        $user = $this->viewer('accounting');
        $line = $this->line(10000, $this->center());
        $this->commit($line, 'purchase_order', 3000, 1000);
        $this->commit($line, 'purchase_order', 400);
        $this->commit($line, 'quotation', 600);

        $base = ['fiscal_year' => $this->year, 'period_month' => $line->month, 'cost_center_id' => $line->annualBudget->cost_center_id, 'expense_category_id' => $line->expense_category_id, 'budget_cedula_id' => $line->budget_cedula_id];
        $row = $this->data($user)->json('rows.0');

        foreach (['committed', 'accrued', 'reserved'] as $bucket) {
            $detail = $this->actingAs($user)->getJson(route('budget-vs-actual-reports.detail', $base + ['bucket' => $bucket]))->assertOk();
            $this->assertEquals($row[$bucket], $detail->json('total'), "Monto {$bucket}");
        }
        $this->actingAs($user)->getJson(route('budget-vs-actual-reports.detail', $base + ['bucket' => 'committed']))
            ->assertJsonCount(2, 'documents')
            ->assertJsonPath('documents.0.type', 'OC');
    }

    public function test_detail_of_a_cost_center_outside_the_user_scope_is_forbidden(): void
    {
        $head = $this->viewer('department_head');
        $foreign = $this->line(1000, $this->center());

        $this->actingAs($head)->getJson(route('budget-vs-actual-reports.detail', [
            'fiscal_year' => $this->year, 'period_month' => $foreign->month, 'bucket' => 'committed',
            'cost_center_id' => $foreign->annualBudget->cost_center_id, 'expense_category_id' => $foreign->expense_category_id,
        ]))->assertForbidden();
    }

    public function test_invalid_parameters_are_rejected(): void
    {
        $this->data($this->viewer('accounting'), ['scope' => 'XYZ', 'period_month' => 13])
            ->assertUnprocessable()->assertJsonValidationErrors(['scope', 'period_month']);
    }

    public function test_export_requires_the_export_permission(): void
    {
        $user = $this->viewer('department_head');

        $this->actingAs($user)->get(route('budget-vs-actual-reports.export', ['format' => 'xlsx']))->assertForbidden();
        $this->actingAs($user)->get(route('budget-vs-actual-reports.export', ['format' => 'csv']))->assertForbidden();
    }

    public function test_excel_has_typed_summary_with_subtotals_and_document_detail_that_adds_up(): void
    {
        $user = $this->exporter();
        $company = Company::factory()->create();
        $line = $this->line(10000, $this->center($company));
        $this->commit($line, 'purchase_order', 3000, 1000);
        $this->commit($line, 'quotation', 600);
        $this->line(2000, $this->center($company));

        $response = $this->actingAs($user)->get(route('budget-vs-actual-reports.export', ['format' => 'xlsx', 'fiscal_year' => $this->year, 'period_month' => 3]))->assertOk();
        $book = $this->spreadsheet($response->streamedContent());

        $this->assertSame(['Resumen', 'Detalle por documento'], $book->getSheetNames());
        $summary = $book->getSheet(0)->toArray(null, false, false);
        $this->assertStringContainsString('RP-01', $summary[0][0]);
        $headings = $summary[7];
        $total = collect($summary)->first(fn ($row) => $row[0] === 'Total general');
        $this->assertNotNull($total);
        $this->assertSame(12000.0, (float) $total[array_search('Vigente', $headings)]);
        $this->assertSame(2000.0, (float) $total[array_search('Comprometido', $headings)]);
        $this->assertSame('N/D', $total[array_search('Pagado', $headings)]);
        $this->assertIsNumeric($total[array_search('Disponible', $headings)]);
        $this->assertSame(2, collect($summary)->where(0, 'Subtotal centro')->count());
        $this->assertSame(1, collect($summary)->where(0, 'Subtotal empresa')->count());

        $detail = collect($book->getSheet(1)->toArray(null, false, false))->slice(8);
        $sum = fn (string $bucket) => round($detail->where(6, $bucket)->sum(14), 2);
        $this->assertSame(2000.0, $sum('Comprometido'));
        $this->assertSame(1000.0, $sum('Devengado'));
        $this->assertSame(600.0, $sum('Reservado'));

        $this->assertDatabaseHas('activity_log', ['log_name' => 'reportes', 'description' => 'Exportación RP-01 xlsx', 'causer_id' => $user->id]);
    }

    public function test_csv_reproduces_the_filtered_view_with_bom(): void
    {
        $user = $this->exporter();
        $kept = $this->line(1000, $this->center());
        $this->line(9000, $this->center());

        $content = $this->actingAs($user)->get(route('budget-vs-actual-reports.export', [
            'format' => 'csv', 'fiscal_year' => $this->year, 'period_month' => 3, 'cost_center_ids' => [$kept->annualBudget->cost_center_id],
        ]))->assertOk()->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $lines = array_map('str_getcsv', array_filter(explode("\n", substr($content, 3))));
        $this->assertSame('Tipo de fila', $lines[0][0]);
        $this->assertCount(2, $lines);
        $this->assertSame((string) $kept->annualBudget->costCenter->code, $lines[1][3]);
    }

    private function exporter(): User
    {
        Permission::findOrCreate('reportes.budget_vs_actual.exportar', 'web');
        $user = $this->viewer('accounting');
        $user->givePermissionTo('reportes.budget_vs_actual.exportar');

        return $user;
    }

    private function spreadsheet(string $content): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'rp01').'.xlsx';
        file_put_contents($path, $content);
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        @unlink($path);

        return $book;
    }

    private function viewer(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::findOrCreate($role, 'web'));
        $user->givePermissionTo('reportes.budget_vs_actual.ver');

        return $user;
    }

    private function data(User $user, array $params = []): TestResponse
    {
        return $this->actingAs($user)->getJson(route('budget-vs-actual-reports.data', $params + ['fiscal_year' => $this->year, 'period_month' => 3]));
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

    private function line(float $assigned, CostCenter $center, int $month = 3, ?ExpenseCategory $category = null, ?BudgetCedula $cedula = null): BudgetMonthlyDistribution
    {
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

    /** Igual que BudgetAllocationService: mueve contadores del renglón y registra el compromiso. */
    private function commit(BudgetMonthlyDistribution $line, string $document, float $amount, float $consumed = 0): void
    {
        $line->refresh();
        $this->assertTrue($line->commitAmount($amount));
        if ($consumed > 0) {
            $this->assertTrue($line->commitToConsume($consumed));
        }

        BudgetCommitment::create([
            'quotation_summary_id' => $document === 'quotation' ? QuotationSummary::factory()->create()->id : null,
            'purchase_order_id' => $document === 'purchase_order' ? PurchaseOrder::factory()->create()->id : null,
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
}
