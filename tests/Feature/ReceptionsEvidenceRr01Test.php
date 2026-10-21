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
use App\Models\ReceivingLocation;
use App\Models\Reception;
use App\Models\ReceptionAttachment;
use App\Models\ReceptionItem;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Supplier;
use App\Models\User;
use App\Reports\Purchasing\ReceptionsEvidenceReport;
use App\Services\ReceptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RR-01 · Recepciones y su evidencia: tipo (bien/servicio), conflicto de segregación de
 * funciones, conteo y completitud de evidencia, y alcance por empresa.
 */
class ReceptionsEvidenceRr01Test extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Supplier $supplier;

    private ExpenseCategory $goodCategory;

    private ExpenseCategory $serviceCategory;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(CheckLockScreen::class);
        Notification::fake();

        foreach (['reportes.receptions_evidence.ver', 'reportes.receptions_evidence.exportar'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        Role::findOrCreate('buyer', 'web')->givePermissionTo(['reportes.receptions_evidence.ver', 'reportes.receptions_evidence.exportar']);
        Role::findOrCreate('department_head', 'web')->givePermissionTo('reportes.receptions_evidence.ver');

        $this->company = Company::factory()->create(['name' => 'Gasolinera Norte']);
        $this->supplier = Supplier::factory()->create(['company_name' => 'Proveedor Uno']);
        $this->goodCategory = ExpenseCategory::factory()->create(['code' => 'MAT']);
        $this->serviceCategory = ExpenseCategory::factory()->create(['code' => 'SER']);
        $this->buyer = User::factory()->create()->assignRole('buyer');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array{0: PurchaseOrder, 1: ReceivingLocation, 2: PurchaseOrderItem, 3: User}
     */
    private function createOc(array $orderAttrs = [], ?ExpenseCategory $category = null, ?Company $company = null): array
    {
        $company ??= $this->company;
        $requester = User::factory()->create();
        $requisition = Requisition::factory()->create(['company_id' => $company->id, 'requested_by' => $requester->id]);
        $location = ReceivingLocation::factory()->create(['company_id' => $company->id]);

        $order = PurchaseOrder::create(array_merge([
            'folio' => uniqid('OC-2026-'),
            'requisition_id' => $requisition->id,
            'supplier_id' => $this->supplier->id,
            'receiving_location_id' => $location->id,
            'status' => 'ISSUED',
            'created_by' => $this->buyer->id,
            'issued_at' => now(),
            'subtotal' => 500,
            'iva_amount' => 0,
            'total' => 500,
        ], $orderAttrs));

        $reqItem = RequisitionItem::factory()->create([
            'requisition_id' => $requisition->id,
            'expense_category_id' => ($category ?? $this->goodCategory)->id,
        ]);

        $item = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'requisition_item_id' => $reqItem->id,
            'quantity' => 5,
            'quantity_received' => 0,
            'unit_price' => 100,
            'subtotal' => 500,
            'iva_amount' => 0,
            'total' => 500,
        ]);

        return [$order->fresh(['items', 'supplier', 'receivingLocation', 'requisition']), $location, $item, $requester];
    }

    /**
     * @return array{0: DirectPurchaseOrder, 1: ReceivingLocation, 2: DirectPurchaseOrderItem}
     */
    private function createOcd(array $orderAttrs = [], ?ExpenseCategory $category = null, ?Company $company = null): array
    {
        $company ??= $this->company;
        $costCenter = CostCenter::factory()->create(['company_id' => $company->id]);
        $location = ReceivingLocation::factory()->create(['company_id' => $company->id]);

        $order = DirectPurchaseOrder::create(array_merge([
            'folio' => uniqid('OCD-2026-'),
            'supplier_id' => $this->supplier->id,
            'cost_center_id' => $costCenter->id,
            'receiving_location_id' => $location->id,
            'application_month' => '2026-05',
            'justification' => 'Compra directa de prueba',
            'subtotal' => 500,
            'iva_amount' => 80,
            'total' => 580,
            'currency' => 'MXN',
            'status' => 'ISSUED',
            'created_by' => $this->buyer->id,
            'issued_at' => now(),
        ], $orderAttrs));

        $item = DirectPurchaseOrderItem::factory()->create([
            'direct_purchase_order_id' => $order->id,
            'expense_category_id' => ($category ?? $this->goodCategory)->id,
            'cost_center_id' => $costCenter->id,
            'quantity' => 5,
            'quantity_received' => 0,
            'unit_price' => 100,
            'iva_rate' => 16,
            'subtotal' => 500,
            'iva_amount' => 80,
            'total' => 580,
        ]);

        return [$order->fresh(['items', 'supplier', 'receivingLocation']), $location, $item];
    }

    private function receive(PurchaseOrder|DirectPurchaseOrder $order, $item, User $receiver, array $data = []): Reception
    {
        return app(ReceptionService::class)->receive($order, [[
            'receivable_item_id' => $item->id,
            'quantity_received' => 5,
            'conformity' => ReceptionItem::CONFORMITY_OK,
        ]], $receiver, array_merge([
            'receiving_location_id' => $order->receiving_location_id,
            'received_at' => now(),
            'remission_path' => 'remisiones/remision.pdf',
            'remission_sha256' => hash('sha256', 'contenido remision '.uniqid()),
            'remission_meta' => ['original_name' => 'remision.pdf', 'mime_type' => 'application/pdf', 'size' => 123],
        ], $data));
    }

    private function build(array $params = [], ?User $user = null): array
    {
        $defaults = [
            'company_ids' => [], 'location_ids' => [], 'reception_type' => [],
            'received_by' => null, 'date_from' => null, 'date_to' => null, 'evidence_filter' => 'TODAS',
        ];

        return app(ReceptionsEvidenceReport::class)->build($user ?? $this->buyer, array_merge($defaults, $params));
    }

    public function test_oc_reception_type_is_bien_for_a_non_service_category(): void
    {
        [$order, , $item, $requester] = $this->createOc();
        $receiver = User::factory()->create();

        $reception = $this->receive($order, $item, $receiver);

        $row = $this->build()['rows']->firstWhere('reception_id', $reception->id);
        $this->assertSame('BIEN', $row['reception_type']);
    }

    public function test_oc_reception_type_is_servicio_when_the_requisition_item_category_is_ser(): void
    {
        [$order, , $item] = $this->createOc(category: $this->serviceCategory);
        $receiver = User::factory()->create();

        $reception = $this->receive($order, $item, $receiver);

        $row = $this->build()['rows']->firstWhere('reception_id', $reception->id);
        $this->assertSame('SERVICIO', $row['reception_type']);
    }

    public function test_ocd_reception_type_is_servicio_from_its_own_expense_category(): void
    {
        [$order, , $item] = $this->createOcd(category: $this->serviceCategory);
        $receiver = User::factory()->create();

        $reception = $this->receive($order, $item, $receiver);

        $row = $this->build()['rows']->firstWhere('reception_id', $reception->id);
        $this->assertSame('SERVICIO', $row['reception_type']);
    }

    public function test_sod_flag_is_true_when_the_receiver_is_the_requisition_requester(): void
    {
        [$order, , $item, $requester] = $this->createOc();

        $reception = $this->receive($order, $item, $requester);

        $row = $this->build()['rows']->firstWhere('reception_id', $reception->id);
        $this->assertTrue($row['sod_flag']);
    }

    public function test_sod_flag_is_false_when_the_receiver_is_not_the_requester(): void
    {
        [$order, , $item] = $this->createOc();
        $receiver = User::factory()->create();

        $reception = $this->receive($order, $item, $receiver);

        $row = $this->build()['rows']->firstWhere('reception_id', $reception->id);
        $this->assertFalse($row['sod_flag']);
    }

    public function test_sod_flag_for_ocd_compares_against_its_creator(): void
    {
        [$order, , $item] = $this->createOcd();

        $reception = $this->receive($order, $item, $this->buyer);

        $row = $this->build()['rows']->firstWhere('reception_id', $reception->id);
        $this->assertTrue($row['sod_flag']);
    }

    public function test_validated_by_and_received_by_are_the_same_actor(): void
    {
        [$order, , $item] = $this->createOcd();
        $receiver = User::factory()->create(['name' => 'Juan Receptor']);

        $reception = $this->receive($order, $item, $receiver);

        $row = $this->build()['rows']->firstWhere('reception_id', $reception->id);
        $this->assertSame('Juan Receptor', $row['received_by_name']);
        $this->assertSame('Juan Receptor', $row['validated_by_name']);
    }

    public function test_evidence_is_complete_and_counted_when_a_remission_is_attached(): void
    {
        [$order, , $item] = $this->createOcd();
        $receiver = User::factory()->create();

        $reception = $this->receive($order, $item, $receiver);

        $row = $this->build()['rows']->firstWhere('reception_id', $reception->id);
        $this->assertTrue($row['evidence_complete']);
        $this->assertSame(1, $row['evidence_count']);
        $this->assertCount(1, $row['attachments']);
        $this->assertSame(ReceptionAttachment::HASH_ORIGIN_EN_CARGA, $row['attachments'][0]['hash_origin']);
    }

    public function test_evidence_is_incomplete_when_there_is_no_remission(): void
    {
        [$order, , $item] = $this->createOcd();
        $receiver = User::factory()->create();

        $reception = $this->receive($order, $item, $receiver, [
            'remission_path' => null, 'remission_sha256' => null, 'remission_meta' => null,
        ]);

        $row = $this->build()['rows']->firstWhere('reception_id', $reception->id);
        $this->assertFalse($row['evidence_complete']);
        $this->assertSame(0, $row['evidence_count']);
    }

    public function test_a_full_scope_user_restricted_to_one_company_cannot_see_receptions_of_another_company(): void
    {
        $otherCompany = Company::factory()->create();
        [$orderHere, , $itemHere] = $this->createOcd();
        [$orderElsewhere, , $itemElsewhere] = $this->createOcd([], null, $otherCompany);
        $receiver = User::factory()->create();

        $receptionHere = $this->receive($orderHere, $itemHere, $receiver);
        $receptionElsewhere = $this->receive($orderElsewhere, $itemElsewhere, $receiver);

        $scopedBuyer = User::factory()->create()->assignRole('buyer');
        $scopedBuyer->companies()->attach($this->company->id);

        $ids = $this->build([], $scopedBuyer)['rows']->pluck('reception_id')->all();

        $this->assertContains($receptionHere->id, $ids);
        $this->assertNotContains($receptionElsewhere->id, $ids);
    }

    public function test_department_head_without_a_responsible_cost_center_sees_nothing(): void
    {
        [$order, , $item] = $this->createOcd();
        $receiver = User::factory()->create();
        $this->receive($order, $item, $receiver);

        $head = User::factory()->create()->assignRole('department_head');

        $this->assertCount(0, $this->build([], $head)['rows']);
    }

    public function test_a_zero_quantity_line_does_not_broaden_scope_or_change_the_reception_type(): void
    {
        // Recepción parcial de una OCD con 2 renglones: el de bien se recibe completo, el de
        // servicio queda pendiente (cantidad 0). Ni el centro de costo ni la categoría del
        // renglón pendiente deben contar para este evento — nada se recibió de él todavía.
        $costCenterReceived = CostCenter::factory()->create(['company_id' => $this->company->id]);
        $costCenterPending = CostCenter::factory()->create(['company_id' => $this->company->id]);
        $location = ReceivingLocation::factory()->create(['company_id' => $this->company->id]);

        $order = DirectPurchaseOrder::create([
            'folio' => uniqid('OCD-2026-'), 'supplier_id' => $this->supplier->id,
            'cost_center_id' => $costCenterReceived->id, 'receiving_location_id' => $location->id,
            'application_month' => '2026-05', 'justification' => 'x', 'subtotal' => 200, 'iva_amount' => 32,
            'total' => 232, 'currency' => 'MXN', 'status' => 'ISSUED', 'created_by' => $this->buyer->id, 'issued_at' => now(),
        ]);
        $goodItem = DirectPurchaseOrderItem::factory()->create([
            'direct_purchase_order_id' => $order->id, 'expense_category_id' => $this->goodCategory->id,
            'cost_center_id' => $costCenterReceived->id, 'quantity' => 5, 'quantity_received' => 0, 'unit_price' => 20,
        ]);
        $pendingServiceItem = DirectPurchaseOrderItem::factory()->create([
            'direct_purchase_order_id' => $order->id, 'expense_category_id' => $this->serviceCategory->id,
            'cost_center_id' => $costCenterPending->id, 'quantity' => 3, 'quantity_received' => 0, 'unit_price' => 20,
        ]);

        $receiver = User::factory()->create();
        $reception = app(ReceptionService::class)->receive($order->fresh(), [
            ['receivable_item_id' => $goodItem->id, 'quantity_received' => 5, 'conformity' => ReceptionItem::CONFORMITY_OK],
            ['receivable_item_id' => $pendingServiceItem->id, 'quantity_received' => 0, 'conformity' => ReceptionItem::CONFORMITY_OK],
        ], $receiver, [
            'receiving_location_id' => $location->id, 'received_at' => now(),
            'remission_path' => 'remisiones/remision.pdf', 'remission_sha256' => hash('sha256', 'x'.uniqid()),
        ]);

        $row = $this->build()['rows']->firstWhere('reception_id', $reception->id);
        $this->assertSame('BIEN', $row['reception_type']);

        $headOfPending = User::factory()->create()->assignRole('department_head');
        $costCenterPending->update(['responsible_user_id' => $headOfPending->id]);
        $this->assertCount(0, $this->build([], $headOfPending)['rows']);

        $headOfReceived = User::factory()->create()->assignRole('department_head');
        $costCenterReceived->update(['responsible_user_id' => $headOfReceived->id]);
        $this->assertCount(1, $this->build([], $headOfReceived)['rows']);
    }

    public function test_department_head_sees_receptions_touching_their_own_cost_center(): void
    {
        $head = User::factory()->create()->assignRole('department_head');
        $costCenter = CostCenter::factory()->create(['company_id' => $this->company->id, 'responsible_user_id' => $head->id]);
        $otherCostCenter = CostCenter::factory()->create(['company_id' => $this->company->id]);
        $location = ReceivingLocation::factory()->create(['company_id' => $this->company->id]);

        $mineOrder = DirectPurchaseOrder::create([
            'folio' => uniqid('OCD-2026-'), 'supplier_id' => $this->supplier->id,
            'cost_center_id' => $costCenter->id, 'receiving_location_id' => $location->id,
            'application_month' => '2026-05', 'justification' => 'x', 'subtotal' => 100, 'iva_amount' => 16,
            'total' => 116, 'currency' => 'MXN', 'status' => 'ISSUED', 'created_by' => $this->buyer->id, 'issued_at' => now(),
        ]);
        $mineItem = DirectPurchaseOrderItem::factory()->create([
            'direct_purchase_order_id' => $mineOrder->id, 'expense_category_id' => $this->goodCategory->id,
            'cost_center_id' => $costCenter->id, 'quantity' => 5, 'quantity_received' => 0, 'unit_price' => 20, 'iva_rate' => 16,
        ]);

        [$notMineOrder, , $notMineItem] = $this->createOcd([], null, null);
        DirectPurchaseOrderItem::query()->where('direct_purchase_order_id', $notMineOrder->id)->update(['cost_center_id' => $otherCostCenter->id]);

        $receiver = User::factory()->create();
        $mineReception = $this->receive($mineOrder->fresh(), $mineItem, $receiver);
        $notMineReception = $this->receive($notMineOrder, $notMineItem, $receiver);

        $ids = $this->build([], $head)['rows']->pluck('reception_id')->all();

        $this->assertContains($mineReception->id, $ids);
        $this->assertNotContains($notMineReception->id, $ids);
    }

    public function test_filters_by_reception_type_received_by_and_evidence(): void
    {
        [$goodOrder, , $goodItem] = $this->createOcd();
        [$serviceOrder, , $serviceItem] = $this->createOcd(category: $this->serviceCategory);
        $receiverA = User::factory()->create();
        $receiverB = User::factory()->create();

        $goodReception = $this->receive($goodOrder, $goodItem, $receiverA);
        $serviceReception = $this->receive($serviceOrder, $serviceItem, $receiverB, [
            'remission_path' => null, 'remission_sha256' => null, 'remission_meta' => null,
        ]);

        $byType = $this->build(['reception_type' => ['SERVICIO']])['rows']->pluck('reception_id')->all();
        $this->assertContains($serviceReception->id, $byType);
        $this->assertNotContains($goodReception->id, $byType);

        $byReceiver = $this->build(['received_by' => $receiverA->id])['rows']->pluck('reception_id')->all();
        $this->assertContains($goodReception->id, $byReceiver);
        $this->assertNotContains($serviceReception->id, $byReceiver);

        $incomplete = $this->build(['evidence_filter' => 'INCOMPLETA'])['rows']->pluck('reception_id')->all();
        $this->assertContains($serviceReception->id, $incomplete);
        $this->assertNotContains($goodReception->id, $incomplete);

        $complete = $this->build(['evidence_filter' => 'COMPLETA'])['rows']->pluck('reception_id')->all();
        $this->assertContains($goodReception->id, $complete);
        $this->assertNotContains($serviceReception->id, $complete);
    }

    public function test_kpis_report_evidence_completeness_and_sod_conflicts(): void
    {
        [$order1, , $item1, $requester1] = $this->createOc();
        [$order2, , $item2] = $this->createOcd();

        $this->receive($order1, $item1, $requester1);
        $this->receive($order2, $item2, User::factory()->create(), [
            'remission_path' => null, 'remission_sha256' => null, 'remission_meta' => null,
        ]);

        $kpis = $this->build()['kpis'];

        $this->assertSame(2, $kpis['total_receptions']);
        $this->assertSame(1, $kpis['evidence_complete_count']);
        $this->assertSame(50.0, $kpis['evidence_complete_pct']);
        $this->assertSame(1, $kpis['sod_conflicts_count']);
    }

    public function test_the_ver_and_exportar_permissions_gate_the_routes(): void
    {
        $noPermission = User::factory()->create();

        $this->actingAs($noPermission)->get(route('receptions-evidence-reports.index'))->assertForbidden();
        $this->actingAs($this->buyer)->get(route('receptions-evidence-reports.index'))->assertOk();
        $this->actingAs($this->buyer)->get(route('receptions-evidence-reports.export', 'csv'))->assertOk();
    }

    public function test_csv_export_matches_the_filtered_rows(): void
    {
        [$order, , $item] = $this->createOcd();
        $reception = $this->receive($order, $item, User::factory()->create());

        $response = $this->actingAs($this->buyer)->get(route('receptions-evidence-reports.export', 'csv'));

        $response->assertOk();
        $this->assertStringContainsString($reception->folio, $response->streamedContent());
    }

    public function test_verify_hash_endpoint_enforces_company_scope(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('remisiones/remision.pdf', 'contenido remision ajeno');

        $otherCompany = Company::factory()->create();
        [$order, , $item] = $this->createOcd([], null, $otherCompany);
        $reception = $this->receive($order, $item, User::factory()->create(), [
            'remission_path' => 'remisiones/remision.pdf',
            'remission_sha256' => hash('sha256', 'contenido remision ajeno'),
        ]);
        $attachment = ReceptionAttachment::where('reception_id', $reception->id)->firstOrFail();

        $scopedBuyer = User::factory()->create()->assignRole('buyer');
        $scopedBuyer->companies()->attach($this->company->id);

        $this->actingAs($scopedBuyer)
            ->postJson(route('receptions-evidence-reports.verify-hash', $attachment))
            ->assertForbidden();
    }

    public function test_verify_hash_endpoint_confirms_a_matching_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('remisiones/remision.pdf', 'contenido remision verificable');

        [$order, , $item] = $this->createOcd();
        $reception = $this->receive($order, $item, User::factory()->create(), [
            'remission_path' => 'remisiones/remision.pdf',
            'remission_sha256' => hash('sha256', 'contenido remision verificable'),
        ]);
        $attachment = ReceptionAttachment::where('reception_id', $reception->id)->firstOrFail();

        $response = $this->actingAs($this->buyer)->postJson(route('receptions-evidence-reports.verify-hash', $attachment));

        $response->assertOk()->assertJson(['matches' => true]);
    }

    public function test_xlsx_export_with_its_attachments_sheet_responds_ok(): void
    {
        [$order, , $item] = $this->createOcd();
        $this->receive($order, $item, User::factory()->create());

        $this->actingAs($this->buyer)
            ->get(route('receptions-evidence-reports.export', 'xlsx'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_verify_hash_endpoint_detects_a_missing_file(): void
    {
        Storage::fake('public');

        [$order, , $item] = $this->createOcd();
        $reception = $this->receive($order, $item, User::factory()->create(), [
            'remission_path' => 'remisiones/no-esta-en-disco.pdf',
            'remission_sha256' => hash('sha256', 'lo que sea'),
        ]);
        $attachment = ReceptionAttachment::where('reception_id', $reception->id)->firstOrFail();

        $response = $this->actingAs($this->buyer)->postJson(route('receptions-evidence-reports.verify-hash', $attachment));

        $response->assertStatus(404)->assertJson(['matches' => false]);
    }

    public function test_building_the_report_runs_a_fixed_number_of_queries_regardless_of_reception_count(): void
    {
        [$order1, , $item1] = $this->createOcd();
        $this->receive($order1, $item1, User::factory()->create());
        $this->build();

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->build();
        $queriesForOneReception = count(DB::getQueryLog());

        [$order2, , $item2] = $this->createOcd();
        $this->receive($order2, $item2, User::factory()->create());
        [$order3, , $item3] = $this->createOcd();
        $this->receive($order3, $item3, User::factory()->create());

        DB::flushQueryLog();
        $this->build();
        $queriesForThreeReceptions = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($queriesForOneReception, $queriesForThreeReceptions);
    }

    public function test_xlsx_export_writes_an_uploaded_file_name_as_text_so_it_cannot_run_as_a_formula(): void
    {
        [$order, , $item] = $this->createOcd();
        $this->receive($order, $item, User::factory()->create(), [
            'remission_meta' => ['original_name' => '=1+1', 'mime_type' => 'application/pdf', 'size' => 123],
        ]);

        $response = $this->actingAs($this->buyer)->get(route('receptions-evidence-reports.export', 'xlsx'));
        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'rr01-xlsx-').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $sheet = IOFactory::load($path)->getSheetByName('Archivos');
        unlink($path);

        $cell = $sheet->getCell('D4');
        $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
        $this->assertSame('=1+1', $cell->getValue());
    }
}
