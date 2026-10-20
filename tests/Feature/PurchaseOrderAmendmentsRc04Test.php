<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLockScreen;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\DirectPurchaseOrder;
use App\Models\DirectPurchaseOrderApproval;
use App\Models\DirectPurchaseOrderItem;
use App\Models\ExpenseCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderApproval;
use App\Models\PurchaseOrderItem;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Supplier;
use App\Models\User;
use App\Reports\Purchasing\PurchaseOrderAmendmentsReport;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * RC-04 · Modificaciones, adendas y cancelaciones de OC: una fila por evento
 * (cancelación OC/OCD, rechazo OC, devolución a revisión OCD, reemisión tras
 * revisión OCD). Ver docs/reportes/RC-04_mapeo_y_huecos.md §8: sin snapshot de
 * campos, el monto reportado siempre es el actual de la orden, no un histórico.
 */
class PurchaseOrderAmendmentsRc04Test extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private CostCenter $center;

    private ExpenseCategory $category;

    private Supplier $supplier;

    private User $buyer;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(CheckLockScreen::class);
        Notification::fake();

        foreach (['reportes.po_amendments.ver', 'reportes.po_amendments.exportar'] as $permission) {
            \Spatie\Permission\Models\Permission::findOrCreate($permission, 'web');
        }
        \Spatie\Permission\Models\Role::findOrCreate('buyer', 'web')->givePermissionTo(['reportes.po_amendments.ver', 'reportes.po_amendments.exportar']);

        $this->company = Company::factory()->create(['name' => 'Gasolinera Norte']);
        $this->center = CostCenter::factory()->create(['company_id' => $this->company->id, 'code' => 'CC-N1']);
        $this->category = ExpenseCategory::factory()->create(['code' => 'MAT']);
        $this->supplier = Supplier::factory()->create(['company_name' => 'Proveedor Uno']);
        $this->buyer = User::factory()->create()->assignRole($this->role());
        $this->actor = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function role(): \Spatie\Permission\Models\Role
    {
        return \Spatie\Permission\Models\Role::findOrCreate('buyer', 'web');
    }

    /** @see IssuedPurchaseOrdersRc02Test::oc() — misma razón: PurchaseOrderFactory crea su propia Requisition. */
    private function oc(array $orderAttrs = [], ?Company $requisitionCompany = null): PurchaseOrder
    {
        $requisition = Requisition::factory()->create(['company_id' => ($requisitionCompany ?? $this->company)->id]);

        $order = PurchaseOrder::factory()->create(array_merge([
            'supplier_id' => $this->supplier->id,
            'requisition_id' => $requisition->id,
            'status' => 'ISSUED',
            'issued_at' => now(),
            'created_by' => $this->buyer->id,
            'total' => 500,
        ], $orderAttrs));

        $reqItem = RequisitionItem::factory()->create([
            'requisition_id' => $requisition->id,
            'cost_center_id' => $this->center->id,
            'expense_category_id' => $this->category->id,
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'requisition_item_id' => $reqItem->id,
            'quantity' => 1,
            'unit_price' => 500,
            'subtotal' => 500,
            'iva_amount' => 0,
            'total' => 500,
        ]);

        return $order->fresh();
    }

    private function ocd(array $orderAttrs = []): DirectPurchaseOrder
    {
        $order = DirectPurchaseOrder::factory()->create(array_merge([
            'supplier_id' => $this->supplier->id,
            'status' => 'ISSUED',
            'issued_at' => now(),
            'created_by' => $this->buyer->id,
            'subtotal' => 300,
            'iva_amount' => 0,
            'total' => 300,
            'exchange_rate' => 1,
        ], $orderAttrs));

        DirectPurchaseOrderItem::factory()->create([
            'direct_purchase_order_id' => $order->id,
            'cost_center_id' => $this->center->id,
            'expense_category_id' => $this->category->id,
            'quantity' => 1,
            'unit_price' => 300,
        ]);

        return $order->fresh();
    }

    private function build(array $params = [], ?User $user = null): array
    {
        $defaults = [
            'company_ids' => [], 'supplier_id' => null, 'buyer_id' => null,
            'order_type' => [], 'event_type' => [], 'date_from' => null, 'date_to' => null,
        ];

        return app(PurchaseOrderAmendmentsReport::class)->build($user ?? $this->buyer, array_merge($defaults, $params));
    }

    public function test_a_cancelled_oc_produces_a_cancelled_event_row(): void
    {
        $order = $this->oc([
            'status' => 'CANCELLED',
            'cancelled_at' => '2026-10-15 10:00:00',
            'cancelled_by' => $this->actor->id,
            'cancellation_reason' => 'Proveedor dejó de surtir el insumo solicitado.',
        ]);

        $row = $this->build()['rows']->firstWhere('order_id', $order->id);

        $this->assertNotNull($row);
        $this->assertSame('OC', $row['order_type']);
        $this->assertSame('CANCELLED', $row['event_type']);
        $this->assertSame($this->actor->name, $row['actor_name']);
        $this->assertSame('Proveedor dejó de surtir el insumo solicitado.', $row['reason']);
        $this->assertTrue(Carbon::parse($row['occurred_at'])->eq(Carbon::parse('2026-10-15 10:00:00')));
        $this->assertSame(500.0, $row['total_mxn_current']);
    }

    public function test_a_cancelled_ocd_produces_a_cancelled_event_row(): void
    {
        $order = $this->ocd([
            'status' => 'CANCELLED',
            'cancelled_at' => '2026-10-16 09:00:00',
            'cancelled_by' => $this->actor->id,
            'cancellation_reason' => 'Se encontró un proveedor con mejor precio.',
        ]);

        $row = $this->build()['rows']->firstWhere('order_id', $order->id);

        $this->assertNotNull($row);
        $this->assertSame('OCD', $row['order_type']);
        $this->assertSame('CANCELLED', $row['event_type']);
        $this->assertSame($this->actor->name, $row['actor_name']);
        $this->assertSame('Se encontró un proveedor con mejor precio.', $row['reason']);
    }

    public function test_a_rejected_oc_produces_a_rejected_event_row_with_the_approval_comment_as_reason(): void
    {
        $order = $this->oc([
            'status' => 'REJECTED',
            'rejected_at' => '2026-10-17 11:00:00',
            'rejected_by' => $this->actor->id,
        ]);
        PurchaseOrderApproval::create([
            'purchase_order_id' => $order->id,
            'approver_user_id' => $this->actor->id,
            'action' => 'REJECTED',
            'comments' => 'El monto excede el presupuesto autorizado para este centro de costo.',
            'approved_at' => '2026-10-17 11:00:00',
        ]);

        $row = $this->build()['rows']->firstWhere('order_id', $order->id);

        $this->assertNotNull($row);
        $this->assertSame('OC', $row['order_type']);
        $this->assertSame('REJECTED', $row['event_type']);
        $this->assertSame($this->actor->name, $row['actor_name']);
        $this->assertSame('El monto excede el presupuesto autorizado para este centro de costo.', $row['reason']);
    }

    public function test_an_ocd_returned_to_revision_produces_a_returned_to_revision_event_row(): void
    {
        $order = $this->ocd(['status' => 'RETURNED', 'returned_by' => $this->actor->id, 'returned_at' => '2026-10-18 08:00:00']);
        DirectPurchaseOrderApproval::create([
            'direct_purchase_order_id' => $order->id,
            'approval_level' => 1,
            'approver_user_id' => $this->actor->id,
            'action' => 'RETURNED',
            'comments' => 'Falta corregir la cantidad solicitada en la partida 1.',
            'approved_at' => '2026-10-18 08:00:00',
        ]);

        $row = $this->build()['rows']->firstWhere('order_id', $order->id);

        $this->assertNotNull($row);
        $this->assertSame('OCD', $row['order_type']);
        $this->assertSame('RETURNED_TO_REVISION', $row['event_type']);
        $this->assertSame($this->actor->name, $row['actor_name']);
        $this->assertSame('Falta corregir la cantidad solicitada en la partida 1.', $row['reason']);
    }

    public function test_an_ocd_returned_twice_produces_two_returned_to_revision_rows(): void
    {
        $order = $this->ocd(['status' => 'RETURNED', 'returned_at' => '2026-10-19 08:00:00']);
        DirectPurchaseOrderApproval::create([
            'direct_purchase_order_id' => $order->id, 'approval_level' => 1, 'approver_user_id' => $this->actor->id,
            'action' => 'RETURNED', 'comments' => 'Primera devolución.', 'approved_at' => '2026-10-10 08:00:00',
        ]);
        DirectPurchaseOrderApproval::create([
            'direct_purchase_order_id' => $order->id, 'approval_level' => 1, 'approver_user_id' => $this->actor->id,
            'action' => 'RETURNED', 'comments' => 'Segunda devolución.', 'approved_at' => '2026-10-19 08:00:00',
        ]);

        $rows = $this->build()['rows']->where('order_id', $order->id)->where('event_type', 'RETURNED_TO_REVISION');

        $this->assertCount(2, $rows);
        $this->assertSame(['Primera devolución.', 'Segunda devolución.'], $rows->pluck('reason')->sort()->values()->all());
    }

    public function test_an_ocd_reissued_after_a_return_produces_a_reissued_after_revision_event_row(): void
    {
        $order = $this->ocd([
            'status' => 'ISSUED',
            'returned_at' => '2026-10-10 08:00:00',
            'issued_at' => '2026-10-20 09:00:00',
            'approved_by' => $this->actor->id,
        ]);
        DirectPurchaseOrderApproval::create([
            'direct_purchase_order_id' => $order->id, 'approval_level' => 1, 'approver_user_id' => $this->actor->id,
            'action' => 'RETURNED', 'comments' => 'Corregir precio.', 'approved_at' => '2026-10-10 08:00:00',
        ]);

        $row = $this->build()['rows']->firstWhere('event_type', 'REISSUED_AFTER_REVISION');

        $this->assertNotNull($row);
        $this->assertSame($order->id, $row['order_id']);
        $this->assertSame('OCD', $row['order_type']);
        $this->assertSame($this->actor->name, $row['actor_name']);
        $this->assertTrue(Carbon::parse($row['occurred_at'])->eq(Carbon::parse('2026-10-20 09:00:00')));
    }

    public function test_an_ocd_still_in_returned_status_does_not_produce_a_reissued_row(): void
    {
        $this->ocd(['status' => 'RETURNED', 'returned_at' => now(), 'issued_at' => now()->subDay()]);

        $this->assertCount(0, $this->build()['rows']->where('event_type', 'REISSUED_AFTER_REVISION'));
    }

    public function test_an_order_with_none_of_these_events_produces_no_rows(): void
    {
        $this->oc();
        $this->ocd();

        $this->assertCount(0, $this->build()['rows']);
    }

    public function test_filters_by_event_type(): void
    {
        $cancelled = $this->oc(['status' => 'CANCELLED', 'cancelled_at' => now(), 'cancelled_by' => $this->actor->id, 'cancellation_reason' => 'x']);
        $rejected = $this->oc(['status' => 'REJECTED', 'rejected_at' => now(), 'rejected_by' => $this->actor->id]);
        PurchaseOrderApproval::create(['purchase_order_id' => $rejected->id, 'approver_user_id' => $this->actor->id, 'action' => 'REJECTED', 'comments' => 'y', 'approved_at' => now()]);

        $rows = $this->build(['event_type' => ['CANCELLED']])['rows'];

        $this->assertCount(1, $rows);
        $this->assertSame($cancelled->id, $rows->first()['order_id']);
    }

    public function test_filters_by_order_type(): void
    {
        $this->oc(['status' => 'CANCELLED', 'cancelled_at' => now(), 'cancelled_by' => $this->actor->id, 'cancellation_reason' => 'x']);
        $ocd = $this->ocd(['status' => 'CANCELLED', 'cancelled_at' => now(), 'cancelled_by' => $this->actor->id, 'cancellation_reason' => 'y']);

        $rows = $this->build(['order_type' => ['OCD']])['rows'];

        $this->assertCount(1, $rows);
        $this->assertSame($ocd->id, $rows->first()['order_id']);
    }

    public function test_filters_by_company(): void
    {
        $otherCompany = Company::factory()->create();
        $here = $this->oc(['status' => 'CANCELLED', 'cancelled_at' => now(), 'cancelled_by' => $this->actor->id, 'cancellation_reason' => 'x'], $this->company);
        $elsewhere = $this->oc(['status' => 'CANCELLED', 'cancelled_at' => now(), 'cancelled_by' => $this->actor->id, 'cancellation_reason' => 'y'], $otherCompany);

        $rows = $this->build(['company_ids' => [$this->company->id]], $this->fullScopeUserFor($this->company, $otherCompany))['rows'];

        $this->assertTrue($rows->contains('order_id', $here->id));
        $this->assertFalse($rows->contains('order_id', $elsewhere->id));
    }

    public function test_filters_by_supplier(): void
    {
        $otherSupplier = Supplier::factory()->create();
        $mine = $this->oc(['status' => 'CANCELLED', 'cancelled_at' => now(), 'cancelled_by' => $this->actor->id, 'cancellation_reason' => 'x']);
        $other = $this->oc(['status' => 'CANCELLED', 'cancelled_at' => now(), 'cancelled_by' => $this->actor->id, 'cancellation_reason' => 'y', 'supplier_id' => $otherSupplier->id]);

        $rows = $this->build(['supplier_id' => $this->supplier->id])['rows'];

        $this->assertTrue($rows->contains('order_id', $mine->id));
        $this->assertFalse($rows->contains('order_id', $other->id));
    }

    public function test_filters_by_buyer(): void
    {
        $otherBuyer = User::factory()->create()->assignRole($this->role());
        $mine = $this->oc(['status' => 'CANCELLED', 'cancelled_at' => now(), 'cancelled_by' => $this->actor->id, 'cancellation_reason' => 'x']);
        $other = $this->oc(['status' => 'CANCELLED', 'cancelled_at' => now(), 'cancelled_by' => $this->actor->id, 'cancellation_reason' => 'y', 'created_by' => $otherBuyer->id]);

        $rows = $this->build(['buyer_id' => $this->buyer->id])['rows'];

        $this->assertTrue($rows->contains('order_id', $mine->id));
        $this->assertFalse($rows->contains('order_id', $other->id));
    }

    public function test_filters_by_date_range_on_occurred_at(): void
    {
        $inRange = $this->oc(['status' => 'CANCELLED', 'cancelled_at' => '2026-10-15 10:00:00', 'cancelled_by' => $this->actor->id, 'cancellation_reason' => 'x']);
        $outOfRange = $this->oc(['status' => 'CANCELLED', 'cancelled_at' => '2026-09-01 10:00:00', 'cancelled_by' => $this->actor->id, 'cancellation_reason' => 'y']);

        $rows = $this->build(['date_from' => '2026-10-01', 'date_to' => '2026-10-31'])['rows'];

        $this->assertTrue($rows->contains('order_id', $inRange->id));
        $this->assertFalse($rows->contains('order_id', $outOfRange->id));
    }

    public function test_a_department_head_only_sees_events_from_their_own_cost_center(): void
    {
        $other = CostCenter::factory()->create(['company_id' => $this->company->id, 'code' => 'CC-N2']);
        $head = User::factory()->create()->assignRole(\Spatie\Permission\Models\Role::findOrCreate('department_head', 'web'));
        $this->center->update(['responsible_user_id' => $head->id]);

        $mine = $this->oc(['status' => 'CANCELLED', 'cancelled_at' => now(), 'cancelled_by' => $this->actor->id, 'cancellation_reason' => 'x']);

        $elsewhere = $this->oc(['status' => 'CANCELLED', 'cancelled_at' => now(), 'cancelled_by' => $this->actor->id, 'cancellation_reason' => 'y']);
        $elseItem = PurchaseOrderItem::where('purchase_order_id', $elsewhere->id)->first();
        RequisitionItem::where('id', $elseItem->requisition_item_id)->update(['cost_center_id' => $other->id]);

        $rows = $this->build([], $head)['rows'];

        $this->assertTrue($rows->contains('order_id', $mine->id));
        $this->assertFalse($rows->contains('order_id', $elsewhere->id));
    }

    public function test_kpis_count_events_by_event_type_and_order_type(): void
    {
        $this->oc(['status' => 'CANCELLED', 'cancelled_at' => now(), 'cancelled_by' => $this->actor->id, 'cancellation_reason' => 'x']);
        $this->ocd(['status' => 'CANCELLED', 'cancelled_at' => now(), 'cancelled_by' => $this->actor->id, 'cancellation_reason' => 'y']);

        $kpis = $this->build()['kpis'];

        $this->assertSame(2, $kpis['total_events']);
        $this->assertSame(2, $kpis['by_event_type']['CANCELLED']);
        $this->assertSame(1, $kpis['by_order_type']['OC']);
        $this->assertSame(1, $kpis['by_order_type']['OCD']);
    }

    public function test_the_ver_and_exportar_permissions_gate_the_routes(): void
    {
        $noPermission = User::factory()->create();

        $this->actingAs($noPermission)->get(route('purchase-order-amendments-reports.index'))->assertForbidden();
        $this->actingAs($this->buyer)->get(route('purchase-order-amendments-reports.index'))->assertOk();
        $this->actingAs($this->buyer)->get(route('purchase-order-amendments-reports.export', 'csv'))->assertOk();
    }

    public function test_csv_export_matches_the_filtered_rows(): void
    {
        $order = $this->oc(['status' => 'CANCELLED', 'cancelled_at' => now(), 'cancelled_by' => $this->actor->id, 'cancellation_reason' => 'x']);

        $response = $this->actingAs($this->buyer)->get(route('purchase-order-amendments-reports.export', 'csv'));

        $response->assertOk();
        $this->assertStringContainsString($order->folio, $response->streamedContent());
    }

    public function test_csv_export_formats_the_event_date_as_text_not_an_excel_serial(): void
    {
        $this->oc(['status' => 'CANCELLED', 'cancelled_at' => '2026-10-20 08:00:00', 'cancelled_by' => $this->actor->id, 'cancellation_reason' => 'x']);

        $response = $this->actingAs($this->buyer)->get(route('purchase-order-amendments-reports.export', 'csv'));

        $response->assertOk();
        $this->assertStringContainsString('20/10/2026', $response->streamedContent());
    }

    private function fullScopeUserFor(Company ...$companies): User
    {
        $user = User::factory()->create()->assignRole($this->role());
        foreach ($companies as $company) {
            $user->companies()->attach($company->id);
        }

        return $user;
    }
}
