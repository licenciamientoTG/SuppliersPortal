<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLockScreen;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\QuotationSummary;
use App\Models\ReceivingLocation;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\User;
use App\Reports\Purchasing\RequisitionPipelineReport;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-01 · Pipeline de requisiciones: cuadre de horas por etapa, aprobador pendiente,
 * filtros, exportación, permisos y alcance.
 */
class RequisitionPipelineRc01Test extends TestCase
{
    use RefreshDatabase;

    private const NOW = '2026-10-09 12:00:00';

    private Company $company;

    private CostCenter $center;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(CheckLockScreen::class);
        Notification::fake();
        foreach (['reportes.requisition_pipeline.ver', 'reportes.requisition_pipeline.exportar'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        Role::findOrCreate('buyer', 'web')->givePermissionTo(['reportes.requisition_pipeline.ver', 'reportes.requisition_pipeline.exportar']);
        Role::findOrCreate('accounting', 'web')->givePermissionTo(['reportes.requisition_pipeline.ver', 'reportes.requisition_pipeline.exportar']);
        Role::findOrCreate('department_head', 'web')->givePermissionTo('reportes.requisition_pipeline.ver');

        $this->company = Company::factory()->create(['name' => 'Gasolinera Norte']);
        $this->center = CostCenter::factory()->create(['company_id' => $this->company->id, 'code' => 'CC-N1']);
        $this->buyer = User::factory()->create()->assignRole('buyer');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_cycle_hours_are_exactly_the_sum_of_step_hours(): void
    {
        $open = $this->requisition('2026-10-01 08:00', [['PENDING', 2], ['IN_QUOTATION', 5], ['QUOTED', 1], ['IN_APPROVAL', 3]]);
        $resubmitted = $this->requisition('2026-09-30 08:00', [['PENDING', 1], ['REJECTED', 2], ['DRAFT', 3], ['PENDING', 1.5], ['CANCELLED', 0.5]], ['cancellation_reason' => 'Ya no se requiere.']);
        Carbon::setTestNow('2026-10-01 23:00');

        $result = $this->build(['statuses' => ['IN_APPROVAL', 'CANCELLED']]);

        foreach ($result['timelines'] as $timeline) {
            $this->assertSame(round(array_sum(array_column($timeline['steps'], 'hours')), 1), $timeline['total_cycle_hours']);
        }

        $openRow = $result['rows']->firstWhere('id', $open->id);
        $this->assertSame(15.0, $openRow['total_cycle_hours']);
        $this->assertSame(4.0, $openRow['hours_in_current_step']);
        $this->assertSame('Autorización de cotización', $openRow['current_step']);
        $this->assertSame([2.0, 5.0, 1.0, 3.0, 4.0], array_column($result['timelines'][$open->id]['steps'], 'hours'));

        $closedRow = $result['rows']->firstWhere('id', $resubmitted->id);
        $this->assertSame(8.0, $closedRow['total_cycle_hours']);
        $this->assertSame(0.0, $closedRow['hours_in_current_step']);
        $this->assertSame('Ya no se requiere.', $closedRow['outcome_reason']);
        $this->assertSame(
            ['Captura', 'Validación de Compras', 'Devuelta al requisitor', 'Corrección del requisitor', 'Validación de Compras', 'Cancelada'],
            array_column($result['timelines'][$resubmitted->id]['steps'], 'step_name')
        );
    }

    public function test_drafts_never_sent_are_excluded_and_the_slowest_comes_first(): void
    {
        $draft = $this->requisition('2026-10-05 08:00', []);
        $fast = $this->requisition('2026-10-05 08:00', [['PENDING', 1]]);
        $slow = $this->requisition('2026-10-01 08:00', [['PENDING', 1]]);

        $ids = $this->build()['rows']->pluck('id')->all();

        $this->assertNotContains($draft->id, $ids);
        $this->assertSame([$slow->id, $fast->id], $ids);
    }

    public function test_pending_approver_is_the_one_who_has_it_in_the_approval_inbox(): void
    {
        $approver = User::factory()->create(['name' => 'Gerente Norte']);
        $requisition = $this->requisition('2026-10-05 08:00', [['PENDING', 1], ['IN_QUOTATION', 1], ['QUOTED', 1], ['IN_APPROVAL', 1]]);
        QuotationSummary::factory()->create(['requisition_id' => $requisition->id, 'approval_status' => 'pending', 'current_approver_user_id' => $approver->id, 'total' => 1500]);
        $other = $this->requisition('2026-10-05 08:00', [['PENDING', 1]]);

        $row = $this->build()['rows']->firstWhere('id', $requisition->id);

        $this->assertSame('Gerente Norte', $row['pending_approver']);
        $this->assertSame([$approver->id], $row['pending_approver_ids']);
        $this->assertSame(1500.0, $row['estimated_amount']);
        $this->assertSame(RequisitionPipelineReport::PURCHASING_QUEUE, $this->build()['rows']->firstWhere('id', $other->id)['pending_approver']);
        $this->assertSame([$requisition->id], $this->build(['pending_approver_id' => $approver->id])['rows']->pluck('id')->all());
    }

    public function test_requisitions_created_before_the_history_show_no_hours(): void
    {
        $old = $this->requisition('2026-08-20 08:00', [['PENDING', 1]]);

        $result = $this->build();
        $row = $result['rows']->firstWhere('id', $old->id);

        $this->assertFalse($row['has_history']);
        $this->assertNull($row['hours_in_current_step']);
        $this->assertNull($row['total_cycle_hours']);
        $this->assertSame(1, $result['kpis']['without_history']);
        $this->assertNull($result['kpis']['avg_hours_in_current_step']);
    }

    public function test_repse_mark_comes_from_service_items(): void
    {
        $services = ExpenseCategory::factory()->create(['code' => 'SER']);
        $requisition = $this->requisition('2026-10-05 08:00', [['PENDING', 1]], [], $services->id);

        $this->assertTrue($this->build()['rows']->firstWhere('id', $requisition->id)['is_repse']);
    }

    public function test_combined_filters_and_csv_export_match_the_screen(): void
    {
        $otherCenter = CostCenter::factory()->create(['company_id' => $this->company->id]);
        $match = $this->requisition('2026-09-20 08:00', [['PENDING', 1], ['IN_QUOTATION', 1], ['QUOTED', 1], ['IN_APPROVAL', 1]]);
        QuotationSummary::factory()->create(['requisition_id' => $match->id, 'total' => 900]);
        $tooCheap = $this->requisition('2026-09-20 08:00', [['PENDING', 1], ['IN_QUOTATION', 1], ['QUOTED', 1], ['IN_APPROVAL', 1]]);
        QuotationSummary::factory()->create(['requisition_id' => $tooCheap->id, 'total' => 100]);
        $tooNew = $this->requisition('2026-10-08 08:00', [['PENDING', 1]]);
        $wrongCenter = $this->requisition('2026-09-20 08:00', [['PENDING', 1]], [], null, $otherCenter);

        $filters = ['company_ids' => [$this->company->id], 'cost_center_ids' => [$this->center->id], 'older_than_days' => 10, 'amount_from' => 500, 'amount_to' => 1000];
        Carbon::setTestNow(self::NOW);

        $data = $this->actingAs($this->buyer)->getJson(route('requisition-pipeline-reports.data', $filters))->assertOk()->json();
        $this->assertSame([$match->id], array_column($data['rows'], 'id'));

        $csv = $this->actingAs($this->buyer)->get(route('requisition-pipeline-reports.export', ['format' => 'csv'] + $filters))->assertOk()->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $this->assertCount(2, $lines);
        $this->assertStringContainsString($match->folio, $lines[1]);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'reportes', 'description' => 'Exportación RC-01 csv']);

        $this->actingAs($this->buyer)->get(route('requisition-pipeline-reports.export', ['format' => 'xlsx'] + $filters))->assertOk();
    }

    public function test_users_without_permission_get_403(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('requisition-pipeline-reports.index'))->assertForbidden();
        $this->actingAs($user)->getJson(route('requisition-pipeline-reports.data'))->assertForbidden();

        $viewer = User::factory()->create()->assignRole('department_head');
        $this->actingAs($viewer)->get(route('requisition-pipeline-reports.export', ['format' => 'csv']))->assertForbidden();
    }

    public function test_scope_by_company_and_by_department_head_centers(): void
    {
        $mine = $this->requisition('2026-10-05 08:00', [['PENDING', 1]]);
        $otherCompany = Company::factory()->create();
        $foreign = $this->requisition('2026-10-05 08:00', [['PENDING', 1]], [], null, CostCenter::factory()->create(['company_id' => $otherCompany->id]), $otherCompany);

        $accountant = User::factory()->create()->assignRole('accounting');
        $accountant->companies()->attach($this->company->id);
        Carbon::setTestNow(self::NOW);
        $ids = array_column($this->actingAs($accountant)->getJson(route('requisition-pipeline-reports.data'))->assertOk()->json('rows'), 'id');
        $this->assertSame([$mine->id], $ids);
        $this->actingAs($accountant)->getJson(route('requisition-pipeline-reports.steps', $foreign->id))->assertForbidden();
        $this->actingAs($accountant)->getJson(route('requisition-pipeline-reports.steps', $mine->id))->assertOk()->assertJsonPath('has_history', true);

        $head = User::factory()->create()->assignRole('department_head');
        $this->center->update(['responsible_user_id' => $head->id]);
        $ids = array_column($this->actingAs($head)->getJson(route('requisition-pipeline-reports.data'))->assertOk()->json('rows'), 'id');
        $this->assertSame([$mine->id], $ids);
    }

    public function test_screen_renders(): void
    {
        $this->actingAs($this->buyer)->get(route('requisition-pipeline-reports.index'))
            ->assertOk()->assertSee('Pipeline de requisiciones y tiempos de ciclo');
    }

    /**
     * Crea la requisición en $createdAt y aplica cada cambio de estatus [estatus, horas después del anterior]
     * por Eloquent, para que el observer registre el historial como en producción.
     */
    private function requisition(string $createdAt, array $changes, array $attributes = [], ?int $expenseCategoryId = null, ?CostCenter $center = null, ?Company $company = null): Requisition
    {
        $moment = Carbon::parse($createdAt);
        Carbon::setTestNow($moment);
        $requester = User::factory()->create();
        $requisition = Requisition::factory()->create([
            'company_id' => ($company ?? $this->company)->id,
            'receiving_location_id' => ReceivingLocation::factory()->create()->id,
            'requested_by' => $requester->id,
            'created_by' => $requester->id,
            'status' => 'DRAFT',
        ]);
        RequisitionItem::factory()->create(array_filter([
            'requisition_id' => $requisition->id,
            'cost_center_id' => ($center ?? $this->center)->id,
            'expense_category_id' => $expenseCategoryId,
        ]));

        foreach ($changes as [$status, $hours]) {
            $moment = $moment->copy()->addMinutes((int) round($hours * 60));
            Carbon::setTestNow($moment);
            $requisition->update(['status' => $status] + ($status === 'REJECTED' ? ['rejection_reason' => 'Faltan especificaciones.'] : []) + ($status === 'CANCELLED' ? $attributes : []));
        }

        Carbon::setTestNow(self::NOW);

        return $requisition;
    }

    private function build(array $params = []): array
    {
        Carbon::setTestNow(Carbon::getTestNow() ?? self::NOW);

        return app(RequisitionPipelineReport::class)->build($this->buyer, $params + [
            'statuses' => ['DRAFT', 'PENDING', 'PAUSED', 'APPROVED', 'IN_QUOTATION', 'QUOTED', 'IN_APPROVAL', 'PENDING_BUDGET_ADJUSTMENT', 'REJECTED'],
            'pending_approver_id' => null,
            'older_than_days' => 0,
            'cost_center_ids' => [],
            'amount_from' => null,
            'amount_to' => null,
            'company_ids' => [],
            'date_from' => '2026-07-11',
            'date_to' => '2026-10-09',
        ]);
    }
}
