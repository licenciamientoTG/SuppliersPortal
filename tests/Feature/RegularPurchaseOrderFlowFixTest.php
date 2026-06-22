<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\QuotationSummary;
use App\Models\User;
use App\Notifications\QuotationApprovalApprovedNotification;
use App\Notifications\QuotationApprovalRejectedNotification;
use App\Services\BudgetAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegularPurchaseOrderFlowFixTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_regular_quotation_creates_an_issued_purchase_order(): void
    {
        Notification::fake();
        $this->withoutMiddleware();

        ['summary' => $summary, 'approver' => $approver, 'buyer' => $buyer] = $this->createRegularApprovalFixture();

        $this->actingAs($approver)
            ->post(route('approvals.quotations.handle', $summary), [
                'status' => 'approved',
            ])
            ->assertRedirect(route('approvals.quotations.index'));

        $summary->refresh();
        $purchaseOrder = PurchaseOrder::query()->where('quotation_summary_id', $summary->id)->firstOrFail();

        $this->assertSame('approved', $summary->approval_status);
        $this->assertSame('COMPLETED', $summary->rfq->fresh()->status);
        $this->assertSame('ISSUED', $purchaseOrder->status);
        $this->assertNotNull($purchaseOrder->approved_at);
        $this->assertNotNull($purchaseOrder->issued_at);
        $this->assertTrue($purchaseOrder->canBeReceived());
        $this->assertTrue($purchaseOrder->canReceiveSupplierDelivery());
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $purchaseOrder->id,
            'requisition_item_id' => $summary->rfq->rfqResponses()->first()->requisition_item_id,
        ]);
        Notification::assertSentTo($buyer, QuotationApprovalApprovedNotification::class);
    }

    public function test_rejected_regular_quotation_notifies_buyers(): void
    {
        Notification::fake();
        $this->withoutMiddleware();

        ['summary' => $summary, 'approver' => $approver, 'buyer' => $buyer] = $this->createRegularApprovalFixture();

        $this->actingAs($approver)
            ->post(route('approvals.quotations.handle', $summary), [
                'status' => 'rejected',
                'reason' => 'Se rechaza por falta de justificación financiera suficiente.',
            ])
            ->assertRedirect(route('approvals.quotations.index'));

        $summary->refresh();

        $this->assertSame('rejected', $summary->approval_status);
        $this->assertNotNull($summary->rejected_at);
        Notification::assertSentTo($buyer, QuotationApprovalRejectedNotification::class);
    }

    public function test_close_inactive_command_uses_issued_purchase_orders_instead_of_open_ones(): void
    {
        Notification::fake();

        $budgetAllocation = Mockery::mock(BudgetAllocationService::class);
        $budgetAllocation->shouldReceive('releaseOrder')->once();
        $this->app->instance(BudgetAllocationService::class, $budgetAllocation);

        $issuedOrder = $this->createRegularPurchaseOrderFixture([
            'purchase_order' => [
                'status' => 'ISSUED',
                'issued_at' => now()->subDays(PurchaseOrder::INACTIVITY_DAYS + 1),
                'approved_at' => now()->subDays(PurchaseOrder::INACTIVITY_DAYS + 1),
            ],
            'summary' => [
                'approval_status' => 'approved',
                'approved_at' => now()->subDays(PurchaseOrder::INACTIVITY_DAYS + 1),
            ],
            'rfq' => [
                'status' => 'COMPLETED',
            ],
            'requisition' => [
                'status' => 'COMPLETED',
            ],
        ]);

        $openOrder = $this->createRegularPurchaseOrderFixture([
            'purchase_order' => [
                'status' => 'OPEN',
                'created_at' => now()->subDays(PurchaseOrder::INACTIVITY_DAYS + 1),
                'updated_at' => now()->subDays(PurchaseOrder::INACTIVITY_DAYS + 1),
            ],
            'summary' => [
                'approval_status' => 'pending',
            ],
            'rfq' => [
                'status' => 'EVALUATED',
            ],
            'requisition' => [
                'status' => 'QUOTED',
            ],
        ]);

        $this->artisan('purchase-orders:close-inactive')
            ->expectsOutputToContain('Procesando Ordenes de Compra Estandar...')
            ->assertExitCode(0);

        $this->assertSame('CLOSED_BY_INACTIVITY', $issuedOrder->fresh()->status);
        $this->assertNotNull($issuedOrder->fresh()->closed_at);
        $this->assertSame('OPEN', $openOrder->fresh()->status);
    }

    public function test_backfill_command_promotes_only_eligible_open_regular_purchase_orders(): void
    {
        $eligible = $this->createRegularPurchaseOrderFixture([
            'purchase_order' => [
                'status' => 'OPEN',
            ],
            'summary' => [
                'approval_status' => 'approved',
                'approved_at' => now()->subHour(),
            ],
            'rfq' => [
                'status' => 'COMPLETED',
            ],
            'requisition' => [
                'status' => 'COMPLETED',
            ],
        ]);

        $notEligible = $this->createRegularPurchaseOrderFixture([
            'purchase_order' => [
                'status' => 'OPEN',
            ],
            'summary' => [
                'approval_status' => 'pending',
                'approved_at' => null,
            ],
            'rfq' => [
                'status' => 'EVALUATED',
            ],
            'requisition' => [
                'status' => 'QUOTED',
            ],
        ]);

        $this->artisan('purchase-orders:backfill-issued-regular')
            ->expectsOutputToContain('OCs regulares elegibles para correccion: 1')
            ->assertExitCode(0);

        $this->assertSame('ISSUED', $eligible->fresh()->status);
        $this->assertNotNull($eligible->fresh()->approved_at);
        $this->assertNotNull($eligible->fresh()->issued_at);
        $this->assertSame('OPEN', $notEligible->fresh()->status);
    }

    private function createRegularApprovalFixture(): array
    {
        Role::findOrCreate('buyer', 'web');

        $approver = User::factory()->create();
        $requester = User::factory()->create();
        $selector = User::factory()->create();
        $buyer = User::factory()->create();
        $buyer->assignRole('buyer');

        $fixture = $this->createRegularPurchaseOrderFixture([
            'create_purchase_order' => false,
            'requester' => $requester,
            'selector' => $selector,
            'approver' => $approver,
            'summary' => [
                'approval_status' => 'pending',
                'current_approver_user_id' => $approver->id,
                'selected_by_user_id' => $selector->id,
                'requested_by_user_id' => $requester->id,
            ],
            'rfq' => [
                'status' => 'EVALUATED',
            ],
            'requisition' => [
                'status' => 'QUOTED',
                'requested_by' => $requester->id,
            ],
        ]);

        return [
            'summary' => QuotationSummary::query()->with('rfq')->findOrFail($fixture['summary_id']),
            'approver' => $approver,
            'buyer' => $buyer,
        ];
    }

    private function createRegularPurchaseOrderFixture(array $overrides = []): PurchaseOrder|array
    {
        $owner = User::factory()->create();
        $requester = $overrides['requester'] ?? $owner;
        $selector = $overrides['selector'] ?? $owner;
        $approver = $overrides['approver'] ?? $owner;
        $supplierUser = User::factory()->create();
        $supplier = $this->createSupplier($supplierUser);

        [
            'company_id' => $companyId,
            'category_id' => $categoryId,
            'cost_center_id' => $costCenterId,
            'expense_category_id' => $expenseCategoryId,
            'product_service_id' => $productServiceId,
            'receiving_location_id' => $receivingLocationId,
        ] = $this->createCatalogContext($owner);

        $requisitionData = array_merge([
            'company_id' => $companyId,
            'receiving_location_id' => $receivingLocationId,
            'department_id' => null,
            'folio' => uniqid('REQ-2026-'),
            'requested_by' => $requester->id,
            'required_date' => now()->toDateString(),
            'description' => 'Requisicion de prueba para flujo de OC regular',
            'status' => 'COMPLETED',
            'validation_specs_clear' => true,
            'validation_time_feasible' => true,
            'validation_alternatives_evaluated' => true,
            'validated_at' => now(),
            'validated_by' => $owner->id,
            'created_by' => $requester->id,
            'updated_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides['requisition'] ?? []);

        $requisitionId = DB::table('requisitions')->insertGetId($requisitionData);

        $requisitionItemId = DB::table('requisition_items')->insertGetId([
            'requisition_id' => $requisitionId,
            'product_service_id' => $productServiceId,
            'line_number' => 1,
            'item_category' => 'producto',
            'product_code' => 'PROD-001',
            'description' => 'Mouse inalambrico Logitech',
            'expense_category_id' => $expenseCategoryId,
            'cost_center_id' => $costCenterId,
            'quantity' => 2,
            'unit' => 'PZA',
            'notes' => 'Uso corporativo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rfqData = array_merge([
            'folio' => uniqid('RFQ-2026-'),
            'requisition_id' => $requisitionId,
            'requisition_item_id' => $requisitionItemId,
            'supplier_id' => $supplier->id,
            'source' => 'portal',
            'status' => 'COMPLETED',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides['rfq'] ?? []);

        $rfqId = DB::table('rfqs')->insertGetId($rfqData);

        DB::table('rfq_responses')->insert([
            'rfq_id' => $rfqId,
            'supplier_id' => $supplier->id,
            'requisition_item_id' => $requisitionItemId,
            'quotation_date' => now()->toDateString(),
            'validity_days' => 30,
            'supplier_quotation_number' => uniqid('COT-'),
            'unit_price' => 100,
            'quantity' => 2,
            'subtotal' => 200,
            'iva_rate' => 16,
            'iva_amount' => 32,
            'total' => 232,
            'currency' => 'MXN',
            'delivery_days' => 5,
            'payment_terms' => 'Credito',
            'brand' => 'Logitech',
            'model' => 'M650',
            'specifications' => 'Bluetooth corporativo',
            'status' => 'SUBMITTED',
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $summaryData = array_merge([
            'requisition_id' => $requisitionId,
            'rfq_id' => $rfqId,
            'subtotal' => 200,
            'iva_amount' => 32,
            'total' => 232,
            'selected_supplier_id' => $supplier->id,
            'requested_by_user_id' => $requester->id,
            'selected_by_user_id' => $selector->id,
            'current_approver_user_id' => $approver->id,
            'approval_status' => 'approved',
            'justification' => 'Adjudicacion de prueba',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides['summary'] ?? []);

        $summaryId = DB::table('quotation_summaries')->insertGetId($summaryData);

        if (($overrides['create_purchase_order'] ?? true) === false) {
            return [
                'summary_id' => $summaryId,
                'rfq_id' => $rfqId,
                'requisition_id' => $requisitionId,
            ];
        }

        $purchaseOrderData = array_merge([
            'folio' => uniqid('OC-2026-'),
            'requisition_id' => $requisitionId,
            'supplier_id' => $supplier->id,
            'quotation_summary_id' => $summaryId,
            'receiving_location_id' => $receivingLocationId,
            'subtotal' => 200,
            'iva_amount' => 32,
            'total' => 232,
            'currency' => 'MXN',
            'payment_terms' => 'Credito',
            'estimated_delivery_days' => 5,
            'status' => 'OPEN',
            'created_by' => $owner->id,
            'approved_at' => null,
            'issued_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides['purchase_order'] ?? []);

        $purchaseOrderId = DB::table('purchase_orders')->insertGetId($purchaseOrderData);

        return PurchaseOrder::query()
            ->with(['quotationSummary.rfq', 'requisition'])
            ->findOrFail($purchaseOrderId);
    }

    private function createCatalogContext(User $owner): array
    {
        $companyId = DB::table('companies')->insertGetId([
            'code' => uniqid('EMP-'),
            'name' => 'TotalGas QA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $categoryId = DB::table('categories')->insertGetId([
            'name' => uniqid('Categoria '),
            'is_active' => true,
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $costCenterId = DB::table('cost_centers')->insertGetId([
            'code' => uniqid('CC-'),
            'name' => 'Centro de costo QA',
            'purchase_type' => 'Gasto Operativo',
            'company_id' => $companyId,
            'category_id' => $categoryId,
            'responsible_user_id' => $owner->id,
            'budget_type' => 'FREE_CONSUMPTION',
            'status' => 'ACTIVO',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $expenseCategoryId = DB::table('expense_categories')->insertGetId([
            'code' => uniqid('EXP-'),
            'name' => 'Categoria de gasto QA',
            'status' => 'ACTIVO',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $receivingLocationId = DB::table('receiving_locations')->insertGetId([
            'code' => uniqid('LOC-'),
            'name' => 'Ubicacion QA',
            'type' => 'corporate',
            'is_active' => true,
            'portal_blocked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productServiceId = DB::table('products_services')->insertGetId([
            'code' => uniqid('PROD-'),
            'technical_description' => 'Mouse inalambrico ergonomico para uso corporativo en estaciones y oficinas.',
            'short_name' => 'Mouse',
            'product_type' => 'PRODUCTO',
            'category_id' => $categoryId,
            'cost_center_id' => $costCenterId,
            'company_id' => $companyId,
            'unit_of_measure' => 'PZA',
            'estimated_price' => 100,
            'currency_code' => 'MXN',
            'status' => 'ACTIVE',
            'is_active' => true,
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'company_id' => $companyId,
            'category_id' => $categoryId,
            'cost_center_id' => $costCenterId,
            'expense_category_id' => $expenseCategoryId,
            'product_service_id' => $productServiceId,
            'receiving_location_id' => $receivingLocationId,
        ];
    }

    private function createSupplier(User $supplierUser)
    {
        return \App\Models\Supplier::factory()->create([
            'user_id' => $supplierUser->id,
            'email' => $supplierUser->email,
            'status' => 'approved',
        ]);
    }
}
