<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetMonthlyDistribution;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\Budget2026DgaImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CaptureBudgetBaselinesFromSourceCommandTest extends TestCase
{
    use RefreshDatabase;

    private AnnualBudget $approved;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $actor = User::factory()->create();
        $category = ExpenseCategory::factory()->create();
        $company = Company::factory()->create();
        $approvedCenter = CostCenter::factory()->create(['company_id' => $company->id]);
        $planningCenter = CostCenter::factory()->create(['company_id' => $company->id]);
        $this->approved = AnnualBudget::create(['cost_center_id' => $approvedCenter->id, 'fiscal_year' => 2026, 'total_annual_amount' => 900, 'status' => 'APROBADO', 'created_by' => $actor->id]);
        $planning = AnnualBudget::create(['cost_center_id' => $planningCenter->id, 'fiscal_year' => 2026, 'total_annual_amount' => 500, 'status' => 'PLANIFICACION', 'created_by' => $actor->id]);
        foreach ([[$this->approved, 900], [$planning, 500]] as [$budget, $amount]) {
            BudgetMonthlyDistribution::create(['annual_budget_id' => $budget->id, 'expense_category_id' => $category->id, 'month' => 3, 'assigned_amount' => $amount, 'created_by' => $actor->id]);
        }
        $sheet = fn ($center, $amount) => ['sheet' => $center->name, 'cost_center_id' => $center->id, 'cost_center_name' => $center->name, 'cost_center_missing' => false,
            'rows' => [['expense_category_id' => $category->id, 'budget_cedula_id' => null, 'months' => [3 => $amount]]]];

        $this->file = tempnam(sys_get_temp_dir(), 'budget').'.xlsx';
        file_put_contents($this->file, 'x');
        $analyzer = Mockery::mock(Budget2026DgaImportService::class);
        $analyzer->shouldReceive('analyze')->with($this->file, 2026)->andReturn([
            'processed_sheets' => [$sheet($approvedCenter, 1000), $sheet($planningCenter, 500)], 'missing_cost_centers' => [], 'unmatched_rows' => [],
        ]);
        $this->app->instance(Budget2026DgaImportService::class, $analyzer);
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    public function test_preview_reports_differences_and_writes_nothing(): void
    {
        $this->artisan('budget:capture-baselines-from-source', ['company' => 'dga', '--file' => $this->file])
            ->expectsOutputToContain('Vista previa')
            ->expectsOutputToContain('diferencias=1')
            ->assertSuccessful();

        $this->assertDatabaseCount('budget_distribution_baselines', 0);
    }

    public function test_apply_captures_only_approved_budgets_without_baseline(): void
    {
        $this->artisan('budget:capture-baselines-from-source', ['company' => 'dga', '--file' => $this->file, '--apply' => true])
            ->expectsOutputToContain('Conexión:')
            ->assertSuccessful();

        $this->assertDatabaseCount('budget_distribution_baselines', 1);
        $this->assertDatabaseHas('budget_distribution_baselines', ['annual_budget_id' => $this->approved->id, 'original_amount' => 1000]);

        $this->artisan('budget:capture-baselines-from-source', ['company' => 'dga', '--file' => $this->file, '--apply' => true])
            ->expectsOutputToContain('ya tiene base')->assertSuccessful();
        $this->assertDatabaseCount('budget_distribution_baselines', 1);
    }
}
