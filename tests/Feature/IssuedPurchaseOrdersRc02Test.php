<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLockScreen;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\DirectPurchaseOrder;
use App\Models\DirectPurchaseOrderItem;
use App\Models\ExpenseCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\RequisitionItem;
use App\Models\Supplier;
use App\Models\User;
use App\Reports\Purchasing\IssuedPurchaseOrdersReport;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-02 · Órdenes de compra emitidas: universo OC/OCD, origen, tipo de cambio (total_mxn),
 * acuse del proveedor (sent_at/supplier_ack_at/hours_to_ack) y % de compra directa.
 */
class IssuedPurchaseOrdersRc02Test extends TestCase
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
        foreach (['reportes.purchase_orders_issued.ver', 'reportes.purchase_orders_issued.exportar'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        Role::findOrCreate('buyer', 'web')->givePermissionTo(['reportes.purchase_orders_issued.ver', 'reportes.purchase_orders_issued.exportar']);
        Role::findOrCreate('department_head', 'web')->givePermissionTo('reportes.purchase_orders_issued.ver');

        $this->company = Company::factory()->create(['name' => 'Gasolinera Norte']);
        $this->center = CostCenter::factory()->create(['company_id' => $this->company->id, 'code' => 'CC-N1']);
        $this->category = ExpenseCategory::factory()->create(['code' => 'MAT']);
        $this->supplier = Supplier::factory()->create(['company_name' => 'Proveedor Uno', 'rfc' => 'ABC010101AAA']);
        $this->buyer = User::factory()->create()->assignRole('buyer');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function oc(array $orderAttrs = [], array $itemAttrs = [], array $reqItemAttrs = []): PurchaseOrder
    {
        $order = PurchaseOrder::factory()->create(array_merge([
            'supplier_id' => $this->supplier->id,
            'status' => 'ISSUED',
            'issued_at' => now(),
            'created_by' => $this->buyer->id,
        ], $orderAttrs));

        $reqItem = RequisitionItem::factory()->create(array_merge([
            'cost_center_id' => $this->center->id,
            'expense_category_id' => $this->category->id,
        ], $reqItemAttrs));

        PurchaseOrderItem::factory()->create(array_merge([
            'purchase_order_id' => $order->id,
            'requisition_item_id' => $reqItem->id,
            'quantity' => 1,
            'unit_price' => 100,
            'subtotal' => 100,
            'iva_amount' => 16,
            'total' => 116,
        ], $itemAttrs));

        return $order->fresh();
    }

    private function ocd(array $orderAttrs = [], array $itemAttrs = []): DirectPurchaseOrder
    {
        $order = DirectPurchaseOrder::factory()->create(array_merge([
            'supplier_id' => $this->supplier->id,
            'status' => 'ISSUED',
            'issued_at' => now(),
            'created_by' => $this->buyer->id,
            'subtotal' => 100,
            'iva_amount' => 16,
            'total' => 116,
        ], $orderAttrs));

        DirectPurchaseOrderItem::factory()->create(array_merge([
            'direct_purchase_order_id' => $order->id,
            'cost_center_id' => $this->center->id,
            'expense_category_id' => $this->category->id,
            'quantity' => 1,
            'unit_price' => 100,
        ], $itemAttrs));

        return $order->fresh();
    }

    private function build(array $params = [], ?User $user = null): array
    {
        $defaults = [
            'company_ids' => [], 'supplier_id' => null, 'buyer_id' => null,
            'origin' => [], 'status' => [], 'date_from' => null, 'date_to' => null,
        ];

        return app(IssuedPurchaseOrdersReport::class)->build($user ?? $this->buyer, array_merge($defaults, $params));
    }

    public function test_oc_from_a_regular_requisition_is_classified_as_requisicion_origin(): void
    {
        $order = $this->oc();

        $row = $this->build()['rows']->firstWhere('order_id', $order->id);

        $this->assertSame('OC', $row['type']);
        $this->assertSame('REQUISICION', $row['origin']);
    }

    public function test_oc_with_contract_source_type_is_classified_as_contrato_origin(): void
    {
        $order = $this->oc(['source_type' => 'contract']);

        $row = $this->build()['rows']->firstWhere('order_id', $order->id);

        $this->assertSame('CONTRATO', $row['origin']);
    }

    public function test_ocd_is_always_classified_as_directa_origin(): void
    {
        $order = $this->ocd();

        $row = $this->build()['rows']->firstWhere('order_id', $order->id);

        $this->assertSame('OCD', $row['type']);
        $this->assertSame('DIRECTA', $row['origin']);
    }

    public function test_orders_without_issued_at_are_excluded(): void
    {
        $this->oc(['issued_at' => null, 'status' => 'PENDING_APPROVAL']);
        $this->ocd(['issued_at' => null, 'status' => 'DRAFT']);

        $this->assertCount(0, $this->build()['rows']);
    }

    public function test_total_mxn_for_oc_is_the_total_already_converted_upstream(): void
    {
        $order = $this->oc(['currency' => 'USD', 'total' => 500]);

        $row = $this->build()['rows']->firstWhere('order_id', $order->id);

        $this->assertSame(500.0, $row['total_mxn']);
    }

    public function test_total_mxn_for_ocd_applies_its_own_exchange_rate(): void
    {
        $order = $this->ocd(['currency' => 'USD', 'exchange_rate' => 18.5], ['unit_price' => 100, 'iva_rate' => 0]);

        $row = $this->build()['rows']->firstWhere('order_id', $order->id);

        $this->assertSame(1850.0, $row['total_mxn']);
    }

    public function test_cost_center_label_shows_varios_when_items_span_multiple_centers(): void
    {
        $otherCenter = CostCenter::factory()->create(['company_id' => $this->company->id, 'code' => 'CC-N2']);
        $order = $this->ocd();
        DirectPurchaseOrderItem::factory()->create([
            'direct_purchase_order_id' => $order->id,
            'cost_center_id' => $otherCenter->id,
            'expense_category_id' => $this->category->id,
            'quantity' => 1,
            'unit_price' => 50,
        ]);

        $row = $this->build()['rows']->firstWhere('order_id', $order->id);

        $this->assertSame('Varios', $row['cost_center_label']);
    }

    public function test_supplier_ack_at_and_hours_to_ack_are_derived_from_the_acknowledgement_timestamp(): void
    {
        $order = $this->oc(['issued_at' => '2026-10-20 08:00:00', 'supplier_acknowledged_at' => '2026-10-20 14:00:00']);

        $row = $this->build()['rows']->firstWhere('order_id', $order->id);

        $this->assertTrue(Carbon::parse($row['sent_at'])->eq(Carbon::parse('2026-10-20 08:00:00')));
        $this->assertTrue(Carbon::parse($row['supplier_ack_at'])->eq(Carbon::parse('2026-10-20 14:00:00')));
        $this->assertSame(6.0, $row['hours_to_ack']);
    }

    public function test_hours_to_ack_is_null_when_the_supplier_has_not_acknowledged_yet(): void
    {
        $order = $this->oc(['supplier_acknowledged_at' => null]);

        $row = $this->build()['rows']->firstWhere('order_id', $order->id);

        $this->assertNull($row['supplier_ack_at']);
        $this->assertNull($row['hours_to_ack']);
    }

    public function test_exceptions_include_every_ocd_regardless_of_cost_center(): void
    {
        $order = $this->ocd();

        $exceptionIds = $this->build()['exceptions']->pluck('order_id')->all();

        $this->assertContains($order->id, $exceptionIds);
    }

    public function test_exceptions_include_oc_without_any_cost_center_assigned(): void
    {
        // OC sin ninguna partida (dato huérfano/legacy, no bloqueado por constraints de BD a
        // diferencia de una partida sin requisition_item_id, que sí es NOT NULL): no hay de
        // dónde derivar centro de costo, y la validación debe exponerlo, no ocultarlo.
        $order = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'ISSUED',
            'issued_at' => now(),
            'created_by' => $this->buyer->id,
        ]);

        $exceptionIds = $this->build()['exceptions']->pluck('order_id')->all();

        $this->assertContains($order->id, $exceptionIds);
    }

    public function test_normal_oc_with_a_cost_center_is_not_an_exception(): void
    {
        $order = $this->oc();

        $exceptionIds = $this->build()['exceptions']->pluck('order_id')->all();

        $this->assertNotContains($order->id, $exceptionIds);
    }

    public function test_kpis_report_direct_share_in_count_and_amount(): void
    {
        $this->oc(['total' => 100]);
        $this->oc(['total' => 100]);
        $this->ocd([], ['unit_price' => 200, 'iva_rate' => 0]);

        $kpis = $this->build()['kpis'];

        $this->assertSame(3, $kpis['total_orders']);
        $this->assertSame(1, $kpis['direct_count']);
        $this->assertSame(400.0, $kpis['total_amount_mxn']);
        $this->assertSame(200.0, $kpis['direct_amount_mxn']);
        $this->assertEqualsWithDelta(33.33, $kpis['direct_pct_count'], 0.01);
        $this->assertEqualsWithDelta(50.0, $kpis['direct_pct_amount'], 0.01);
    }

    public function test_kpi_no_ack_over_48h_ignores_orders_issued_before_the_tracking_cutoff(): void
    {
        Carbon::setTestNow('2026-10-25 12:00:00');
        // Antes del despliegue del acuse automático: nunca va a tener supplier_ack_at, no cuenta.
        $this->oc(['issued_at' => '2026-10-10 00:00:00', 'supplier_acknowledged_at' => null]);
        // Después del despliegue, sin acuse y ya pasaron más de 48h: sí cuenta.
        $late = $this->oc(['issued_at' => '2026-10-21 00:00:00', 'supplier_acknowledged_at' => null]);
        // Después del despliegue, con acuse: no cuenta como "sin acuse".
        $this->oc(['issued_at' => '2026-10-21 00:00:00', 'supplier_acknowledged_at' => '2026-10-21 01:00:00']);

        $kpis = $this->build()['kpis'];

        // El denominador son las órdenes ya "lo bastante viejas para juzgar" (>=48h) emitidas
        // después del despliegue del acuse ($late y la acusada); la de antes del despliegue
        // nunca cuenta, aunque también tenga más de 48h.
        $this->assertSame(2, $kpis['no_ack_over_48h_eligible']);
        $this->assertSame(1, $kpis['no_ack_over_48h_count']);
        $this->assertSame(50.0, $kpis['no_ack_over_48h_pct']);
    }

    public function test_filters_by_company_supplier_buyer_origin_and_status(): void
    {
        $otherCompany = Company::factory()->create();
        $otherCenter = CostCenter::factory()->create(['company_id' => $otherCompany->id]);
        $otherSupplier = Supplier::factory()->create();
        $otherBuyer = User::factory()->create()->assignRole('buyer');

        $here = $this->oc();
        $elsewhereCompany = $this->oc([], [], ['cost_center_id' => $otherCenter->id]);
        $elsewhereSupplier = $this->ocd(['supplier_id' => $otherSupplier->id]);
        $byOtherBuyer = $this->oc(['created_by' => $otherBuyer->id]);
        $direct = $this->ocd();
        $cancelled = $this->oc(['status' => 'CANCELLED']);
        $folios = fn ($rows) => $rows->pluck('folio')->all();

        $byCompany = $folios($this->build(['company_ids' => [$this->company->id]])['rows']);
        $this->assertNotContains($elsewhereCompany->folio, $byCompany);

        $bySupplier = $folios($this->build(['supplier_id' => $this->supplier->id])['rows']);
        $this->assertNotContains($elsewhereSupplier->folio, $bySupplier);

        $byBuyer = $folios($this->build(['buyer_id' => $this->buyer->id])['rows']);
        $this->assertNotContains($byOtherBuyer->folio, $byBuyer);

        $byOrigin = $folios($this->build(['origin' => ['DIRECTA']])['rows']);
        $this->assertContains($direct->folio, $byOrigin);
        $this->assertNotContains($here->folio, $byOrigin);

        $byStatus = $folios($this->build(['status' => ['CANCELLED']])['rows']);
        $this->assertContains($cancelled->folio, $byStatus);
        $this->assertNotContains($here->folio, $byStatus);
    }

    public function test_department_head_only_sees_orders_touching_their_cost_centers(): void
    {
        $head = User::factory()->create()->assignRole('department_head');
        $this->center->update(['responsible_user_id' => $head->id]);
        $otherCenter = CostCenter::factory()->create(['company_id' => $this->company->id]);

        $mine = $this->oc();
        $notMine = $this->oc([], [], ['cost_center_id' => $otherCenter->id]);

        $ids = $this->build([], $head)['rows']->pluck('order_id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($notMine->id, $ids);
    }

    public function test_the_ver_and_exportar_permissions_gate_the_routes(): void
    {
        $noPermission = User::factory()->create();

        $this->actingAs($noPermission)->get(route('issued-purchase-orders-reports.index'))->assertForbidden();
        $this->actingAs($this->buyer)->get(route('issued-purchase-orders-reports.index'))->assertOk();
        $this->actingAs($this->buyer)->get(route('issued-purchase-orders-reports.export', 'csv'))->assertOk();
    }

    public function test_csv_export_matches_the_filtered_rows(): void
    {
        $order = $this->oc();

        $response = $this->actingAs($this->buyer)->get(route('issued-purchase-orders-reports.export', 'csv'));

        $response->assertOk();
        $this->assertStringContainsString($order->folio, $response->streamedContent());
    }
}
