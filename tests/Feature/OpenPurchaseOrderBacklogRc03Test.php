<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLockScreen;
use App\Models\BudgetCommitment;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\DirectPurchaseOrder;
use App\Models\DirectPurchaseOrderItem;
use App\Models\ExpenseCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivingLocation;
use App\Models\RequisitionItem;
use App\Models\Supplier;
use App\Models\User;
use App\Reports\Purchasing\OpenPurchaseOrderBacklogReport;
use App\Services\ReceptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-03 · Backlog de órdenes de compra abiertas: saldo pendiente de recibir,
 * atraso contra la fecha prometida, OC "zombi" y cuadre con el comprometido de RP-01.
 */
class OpenPurchaseOrderBacklogRc03Test extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private CostCenter $center;

    private ExpenseCategory $category;

    private Supplier $supplier;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(CheckLockScreen::class);
        Notification::fake();
        foreach (['reportes.open_po_backlog.ver', 'reportes.open_po_backlog.exportar'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        Role::findOrCreate('buyer', 'web')->givePermissionTo(['reportes.open_po_backlog.ver', 'reportes.open_po_backlog.exportar']);
        Role::findOrCreate('department_head', 'web')->givePermissionTo('reportes.open_po_backlog.ver');

        $this->company = Company::factory()->create(['name' => 'Gasolinera Norte']);
        $this->center = CostCenter::factory()->create(['company_id' => $this->company->id, 'code' => 'CC-N1']);
        $this->category = ExpenseCategory::factory()->create(['code' => 'MAT']);
        $this->supplier = Supplier::factory()->create(['company_name' => 'Proveedor Uno']);
        $this->buyer = User::factory()->create()->assignRole('buyer');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function ocItem(array $orderAttrs = [], array $itemAttrs = [], array $reqItemAttrs = []): PurchaseOrderItem
    {
        $order = PurchaseOrder::factory()->create(array_merge([
            'supplier_id' => $this->supplier->id,
            'status' => 'ISSUED',
        ], $orderAttrs));

        $reqItem = RequisitionItem::factory()->create(array_merge([
            'cost_center_id' => $this->center->id,
            'expense_category_id' => $this->category->id,
        ], $reqItemAttrs));

        return PurchaseOrderItem::factory()->create(array_merge([
            'purchase_order_id' => $order->id,
            'requisition_item_id' => $reqItem->id,
            'quantity' => 10,
            'quantity_received' => 0,
            'unit_price' => 100,
        ], $itemAttrs));
    }

    private function ocdItem(array $orderAttrs = [], array $itemAttrs = []): DirectPurchaseOrderItem
    {
        $order = DirectPurchaseOrder::factory()->create(array_merge([
            'supplier_id' => $this->supplier->id,
            'status' => 'ISSUED',
        ], $orderAttrs));

        return DirectPurchaseOrderItem::factory()->create(array_merge([
            'direct_purchase_order_id' => $order->id,
            'cost_center_id' => $this->center->id,
            'expense_category_id' => $this->category->id,
            'quantity' => 10,
            'quantity_received' => 0,
            'unit_price' => 100,
        ], $itemAttrs));
    }

    private function build(array $params = [], ?User $user = null): array
    {
        $defaults = [
            'company_ids' => [], 'supplier_id' => null, 'cost_center_ids' => [],
            'only_late' => false, 'only_zombie' => false,
            'zombie_days' => OpenPurchaseOrderBacklogReport::DEFAULT_ZOMBIE_DAYS,
            'as_of_date' => now()->toDateString(),
        ];

        return app(OpenPurchaseOrderBacklogReport::class)->build($user ?? $this->buyer, array_merge($defaults, $params));
    }

    public function test_pending_amount_is_the_pre_tax_unit_price_times_the_quantity_still_pending(): void
    {
        $item = $this->ocItem([], ['quantity' => 10, 'quantity_received' => 4, 'unit_price' => 150]);

        $row = $this->build()['rows']->firstWhere('item_id', $item->id);

        $this->assertSame(6.0, $row['pending_qty']);
        $this->assertSame(900.0, $row['pending_amount']);
    }

    public function test_fully_received_items_are_excluded(): void
    {
        $this->ocItem([], ['quantity' => 5, 'quantity_received' => 5]);

        $this->assertCount(0, $this->build()['rows']);
    }

    public function test_orders_outside_the_receivable_statuses_are_excluded(): void
    {
        foreach (['CANCELLED', 'RECEIVED', 'REJECTED', 'CLOSED_BY_INACTIVITY', 'PENDING_APPROVAL'] as $status) {
            $this->ocItem(['status' => $status], ['quantity' => 5, 'quantity_received' => 1]);
        }
        $open = $this->ocItem(['status' => 'PARTIALLY_RECEIVED'], ['quantity' => 5, 'quantity_received' => 1]);

        $ids = $this->build()['rows']->pluck('item_id')->all();

        $this->assertSame([$open->id], $ids);
    }

    public function test_ocd_items_use_their_own_cost_center_and_category_directly(): void
    {
        $otherCenter = CostCenter::factory()->create(['company_id' => $this->company->id, 'code' => 'CC-N2']);
        $item = $this->ocdItem([], ['cost_center_id' => $otherCenter->id, 'quantity' => 8, 'quantity_received' => 3, 'unit_price' => 50]);

        $row = $this->build()['rows']->firstWhere('item_id', $item->id);

        $this->assertSame('OCD', $row['type']);
        $this->assertSame($otherCenter->id, $row['cost_center_id']);
        $this->assertSame(250.0, $row['pending_amount']);
    }

    public function test_promised_date_and_days_late_require_both_issued_at_and_estimated_delivery_days(): void
    {
        Carbon::setTestNow('2026-10-19 12:00:00');
        $withoutDays = $this->ocItem(['issued_at' => '2026-10-01', 'estimated_delivery_days' => null]);
        $withBoth = $this->ocItem(['issued_at' => '2026-10-01', 'estimated_delivery_days' => 5]);

        $rows = $this->build()['rows'];
        $rowWithout = $rows->firstWhere('item_id', $withoutDays->id);
        $rowWith = $rows->firstWhere('item_id', $withBoth->id);

        $this->assertNull($rowWithout['promised_date']);
        $this->assertNull($rowWithout['days_late']);
        $this->assertSame('2026-10-06', $rowWith['promised_date']);
        $this->assertSame(13, $rowWith['days_late']);
    }

    public function test_days_late_is_zero_with_no_grace_period_before_the_promised_date(): void
    {
        Carbon::setTestNow('2026-10-19 12:00:00');
        $notDueYet = $this->ocItem(['issued_at' => '2026-10-18', 'estimated_delivery_days' => 5]);
        $dueToday = $this->ocItem(['issued_at' => '2026-10-19', 'estimated_delivery_days' => 0]);

        $rows = $this->build()['rows'];

        $this->assertSame(0, $rows->firstWhere('item_id', $notDueYet->id)['days_late']);
        $this->assertSame(0, $rows->firstWhere('item_id', $dueToday->id)['days_late']);
    }

    public function test_is_expired_uses_each_order_types_own_vigencia_window(): void
    {
        Carbon::setTestNow('2026-10-19 12:00:00');
        // OC: PurchaseOrder::INACTIVITY_DAYS = 10 días desde issued_at.
        $ocExpired = $this->ocItem(['issued_at' => '2026-10-01']);
        $ocFresh = $this->ocItem(['issued_at' => '2026-10-15']);
        // OCD: DirectPurchaseOrder::INACTIVITY_DAYS = 7 días desde submitted_at.
        $ocdExpired = $this->ocdItem(['submitted_at' => '2026-10-05']);
        $ocdFresh = $this->ocdItem(['submitted_at' => '2026-10-15']);
        $ocdNeverSubmitted = $this->ocdItem(['submitted_at' => null]);

        $rows = $this->build()['rows'];

        $this->assertTrue($rows->firstWhere('item_id', $ocExpired->id)['is_expired']);
        $this->assertFalse($rows->firstWhere('item_id', $ocFresh->id)['is_expired']);
        $this->assertTrue($rows->firstWhere('item_id', $ocdExpired->id)['is_expired']);
        $this->assertFalse($rows->firstWhere('item_id', $ocdFresh->id)['is_expired']);
        $this->assertFalse($rows->firstWhere('item_id', $ocdNeverSubmitted->id)['is_expired']);
    }

    public function test_zombie_flag_uses_last_reception_or_issued_at_against_the_threshold(): void
    {
        Carbon::setTestNow('2026-10-19 12:00:00');
        $location = ReceivingLocation::factory()->create();
        $receiver = User::factory()->create();

        $neverReceived = $this->ocItem(['issued_at' => '2026-07-01'], ['quantity' => 10, 'quantity_received' => 0]);
        $recentlyReceived = $this->ocItem(['issued_at' => '2026-07-01', 'status' => 'PARTIALLY_RECEIVED', 'receiving_location_id' => $location->id], ['quantity' => 10, 'quantity_received' => 3]);
        $recentlyReceivedOrder = $recentlyReceived->purchaseOrder;
        app(ReceptionService::class)->receive($recentlyReceivedOrder, [[
            'receivable_item_id' => $recentlyReceived->id,
            'quantity_received' => 3,
            'conformity' => 'CONFORME',
        ]], $receiver, ['receiving_location_id' => $location->id, 'received_at' => '2026-10-10']);

        $tooRecentToJudge = $this->ocItem(['issued_at' => '2026-10-10'], ['quantity' => 10, 'quantity_received' => 0]);

        $rows = $this->build()['rows'];

        $this->assertTrue($rows->firstWhere('item_id', $neverReceived->id)['is_zombie']);
        $this->assertFalse($rows->firstWhere('item_id', $recentlyReceived->id)['is_zombie']);
        $this->assertFalse($rows->firstWhere('item_id', $tooRecentToJudge->id)['is_zombie']);
    }

    public function test_filters_by_company_supplier_only_late_and_only_zombie(): void
    {
        Carbon::setTestNow('2026-10-19 12:00:00');
        $otherCompany = Company::factory()->create();
        $otherCenter = CostCenter::factory()->create(['company_id' => $otherCompany->id]);
        $otherSupplier = Supplier::factory()->create();

        $here = $this->ocItem(['issued_at' => '2026-10-15']);
        $elsewhereCompany = $this->ocItem([], [], ['cost_center_id' => $otherCenter->id]);
        $elsewhereSupplier = $this->ocdItem(['supplier_id' => $otherSupplier->id]);
        $late = $this->ocItem(['issued_at' => '2026-09-01', 'estimated_delivery_days' => 1]);
        $zombie = $this->ocItem(['issued_at' => '2026-01-01']);
        // Folio en vez de item_id: OC y OCD tienen autoincrementales independientes (pueden coincidir en número).
        $folios = fn ($rows) => $rows->pluck('folio')->all();

        $byCompany = $folios($this->build(['company_ids' => [$this->company->id]])['rows']);
        $this->assertNotContains($elsewhereCompany->purchaseOrder->folio, $byCompany);

        $bySupplier = $folios($this->build(['supplier_id' => $this->supplier->id])['rows']);
        $this->assertNotContains($elsewhereSupplier->directPurchaseOrder->folio, $bySupplier);

        $onlyLate = $folios($this->build(['only_late' => true])['rows']);
        $this->assertContains($late->purchaseOrder->folio, $onlyLate);
        $this->assertNotContains($here->purchaseOrder->folio, $onlyLate);

        $onlyZombie = $folios($this->build(['only_zombie' => true])['rows']);
        $this->assertContains($zombie->purchaseOrder->folio, $onlyZombie);
        $this->assertNotContains($here->purchaseOrder->folio, $onlyZombie);
    }

    public function test_kpis_summarize_pending_amount_late_and_zombie_lines(): void
    {
        Carbon::setTestNow('2026-10-19 12:00:00');
        $this->ocItem(['issued_at' => '2026-10-01', 'estimated_delivery_days' => 1], ['quantity' => 10, 'quantity_received' => 0, 'unit_price' => 100]);
        $this->ocItem(['issued_at' => '2026-01-01'], ['quantity' => 5, 'quantity_received' => 0, 'unit_price' => 200]);

        $kpis = $this->build()['kpis'];

        $this->assertSame(2, $kpis['lines']);
        $this->assertSame(2000.0, $kpis['pending_amount_total']);
        $this->assertSame(1, $kpis['late_lines']);
        $this->assertSame(1, $kpis['zombie_lines']);
        $this->assertSame(1000.0, $kpis['zombie_amount']);
    }

    public function test_reconciliation_reports_the_open_committed_balance_from_rp01(): void
    {
        $this->ocItem();
        BudgetCommitment::create([
            'purchase_order_id' => null,
            'cost_center_id' => $this->center->id,
            'application_month' => '2026-10',
            'expense_category_id' => $this->category->id,
            'committed_amount' => 1500,
            'consumed_amount' => 200,
            'status' => 'COMMITTED',
            'committed_at' => now(),
        ]);
        BudgetCommitment::create([
            'purchase_order_id' => null,
            'cost_center_id' => $this->center->id,
            'application_month' => '2026-10',
            'expense_category_id' => $this->category->id,
            'committed_amount' => 999,
            'consumed_amount' => 999,
            'status' => 'RELEASED',
            'committed_at' => now(),
        ]);

        $reconciliation = $this->build()['reconciliation'];

        $this->assertSame(1300.0, $reconciliation['budget_commitments_pending']);
    }

    public function test_department_head_only_sees_the_cost_centers_they_are_responsible_for(): void
    {
        $head = User::factory()->create()->assignRole('department_head');
        $this->center->update(['responsible_user_id' => $head->id]);
        $otherCenter = CostCenter::factory()->create(['company_id' => $this->company->id]);

        $mine = $this->ocItem();
        $notMine = $this->ocItem([], [], ['cost_center_id' => $otherCenter->id]);

        $ids = $this->build([], $head)['rows']->pluck('item_id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($notMine->id, $ids);
    }

    public function test_the_ver_and_exportar_permissions_gate_the_routes(): void
    {
        $noPermission = User::factory()->create();

        $this->actingAs($noPermission)->get(route('open-po-backlog-reports.index'))->assertForbidden();
        $this->actingAs($this->buyer)->get(route('open-po-backlog-reports.index'))->assertOk();
        $this->actingAs($this->buyer)->get(route('open-po-backlog-reports.export', 'csv'))->assertOk();
    }

    public function test_csv_export_matches_the_filtered_rows(): void
    {
        $item = $this->ocItem([], ['quantity' => 10, 'quantity_received' => 2, 'unit_price' => 100]);

        $response = $this->actingAs($this->buyer)->get(route('open-po-backlog-reports.export', 'csv'));

        $response->assertOk();
        $this->assertStringContainsString($item->purchaseOrder->folio, $response->streamedContent());
    }
}
