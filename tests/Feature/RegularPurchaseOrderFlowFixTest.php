<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLockScreen;
use App\Http\Middleware\ModuleAccess;
use App\Models\BudgetCedula;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use App\Models\PurchaseOrder;
use App\Models\ReceivingLocation;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Rfq;
use App\Models\RfqResponse;
use App\Models\QuotationSummary;
use App\Models\User;
use App\Notifications\QuotationApprovalApprovedNotification;
use App\Notifications\QuotationApprovalRejectedNotification;
use App\Services\BudgetAllocationService;
use App\Services\QuotationSummaryItemService;
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
        $this->withoutMiddleware([ModuleAccess::class, CheckLockScreen::class]);

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
        $this->withoutMiddleware([ModuleAccess::class, CheckLockScreen::class]);

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

    public function test_partially_approved_regular_quotation_creates_purchase_order_for_final_quantity(): void
    {
        Notification::fake();
        $this->withoutMiddleware([ModuleAccess::class, CheckLockScreen::class]);

        ['summary' => $summary, 'approver' => $approver] = $this->createRegularApprovalFixture();
        $summaryItem = app(QuotationSummaryItemService::class)->ensureItems($summary)->first();

        $this->actingAs($approver)
            ->post(route('approvals.quotations.handle', $summary), [
                'status' => 'approved',
                'items' => [
                    [
                        'id' => $summaryItem->id,
                        'approved_quantity' => 1,
                        'approver_notes' => 'Se autoriza solo una pieza por disponibilidad presupuestal.',
                    ],
                ],
            ])
            ->assertRedirect(route('approvals.quotations.index'));

        $summary->refresh();
        $purchaseOrder = PurchaseOrder::query()->where('quotation_summary_id', $summary->id)->firstOrFail();
        $purchaseOrderItem = $purchaseOrder->items()->firstOrFail();

        $this->assertSame('partially_approved', $summary->approval_status);
        $this->assertSame(1.0, (float) $purchaseOrderItem->quantity);
        $this->assertSame(100.0, (float) $purchaseOrderItem->subtotal);
        $this->assertSame(116.0, (float) $purchaseOrderItem->total);
        $this->assertDatabaseHas('quotation_summary_items', [
            'id' => $summaryItem->id,
            'approval_status' => 'partially_approved',
        ]);
    }

    public function test_reducing_quantity_requires_line_reason(): void
    {
        Notification::fake();
        $this->withoutMiddleware([ModuleAccess::class, CheckLockScreen::class]);

        ['summary' => $summary, 'approver' => $approver] = $this->createRegularApprovalFixture();
        $summaryItem = app(QuotationSummaryItemService::class)->ensureItems($summary)->first();

        $this->actingAs($approver)
            ->from(route('approvals.quotations.index'))
            ->post(route('approvals.quotations.handle', $summary), [
                'status' => 'approved',
                'items' => [
                    [
                        'id' => $summaryItem->id,
                        'approved_quantity' => 1,
                        'approver_notes' => '',
                    ],
                ],
            ])
            ->assertRedirect(route('approvals.quotations.index'));

        $this->assertDatabaseMissing('purchase_orders', [
            'quotation_summary_id' => $summary->id,
        ]);
    }

    public function test_approved_total_cannot_exceed_current_authorizer_limit(): void
    {
        Notification::fake();
        $this->withoutMiddleware([ModuleAccess::class, CheckLockScreen::class]);

        ['summary' => $summary, 'approver' => $approver] = $this->createRegularApprovalFixture([
            'summary' => [
                'effective_authorization_limit' => 150,
            ],
        ]);
        $summaryItem = app(QuotationSummaryItemService::class)->ensureItems($summary)->first();

        $this->actingAs($approver)
            ->from(route('approvals.quotations.index'))
            ->post(route('approvals.quotations.handle', $summary), [
                'status' => 'approved',
                'items' => [
                    [
                        'id' => $summaryItem->id,
                        'approved_quantity' => 2,
                        'approver_notes' => '',
                    ],
                ],
            ])
            ->assertRedirect(route('approvals.quotations.index'));

        $summary->refresh();
        $this->assertSame('pending', $summary->approval_status);
        $this->assertDatabaseMissing('purchase_orders', [
            'quotation_summary_id' => $summary->id,
        ]);
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

    private function createRegularApprovalFixture(array $overrides = []): array
    {
        Role::findOrCreate('buyer', 'web');

        $approver = User::factory()->create();
        $requester = User::factory()->create();
        $selector = User::factory()->create();
        $buyer = User::factory()->create();
        $buyer->assignRole('buyer');

        $fixture = $this->createRegularPurchaseOrderFixture(array_replace_recursive([
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
        ], $overrides));

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
            'cost_center_id' => $costCenterId,
            'expense_category_id' => $expenseCategoryId,
            'budget_cedula_id' => $budgetCedulaId,
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

        $requisitionId = Requisition::factory()->create($requisitionData)->id;

        $requisitionItemId = RequisitionItem::factory()->create([
            'requisition_id' => $requisitionId,
            'product_service_id' => $productServiceId,
            'line_number' => 1,
            'item_category' => 'producto',
            'product_code' => 'PROD-001',
            'description' => 'Mouse inalambrico Logitech',
            'expense_category_id' => $expenseCategoryId,
            'budget_cedula_id' => $budgetCedulaId,
            'cost_center_id' => $costCenterId,
            'quantity' => 2,
            'unit' => 'PZA',
            'notes' => 'Uso corporativo',
        ])->id;

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

        $rfqId = Rfq::factory()->create($rfqData)->id;

        RfqResponse::factory()->create([
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

        $summaryId = QuotationSummary::factory()->create($summaryData)->id;

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

        $purchaseOrderId = PurchaseOrder::factory()->create($purchaseOrderData)->id;

        return PurchaseOrder::query()
            ->with(['quotationSummary.rfq', 'requisition'])
            ->findOrFail($purchaseOrderId);
    }

    private function createCatalogContext(User $owner): array
    {
        $company = Company::factory()->create(['name' => 'TotalGas QA']);

        $costCenter = CostCenter::factory()->create([
            'name' => 'Centro de costo QA',
            'company_id' => $company->id,
            'responsible_user_id' => $owner->id,
            'budget_type' => 'FREE_CONSUMPTION',
            'created_by' => $owner->id,
        ]);

        $expenseCategory = ExpenseCategory::factory()->create([
            'name' => 'Categoria de gasto QA',
            'created_by' => $owner->id,
        ]);

        $budgetCedula = BudgetCedula::factory()->create([
            'expense_category_id' => $expenseCategory->id,
            'created_by' => $owner->id,
        ]);

        $receivingLocation = ReceivingLocation::factory()->create([
            'company_id' => $company->id,
            'name' => 'Ubicacion QA',
            'type' => 'corporate',
        ]);

        $productService = ProductService::factory()->create([
            'technical_description' => 'Mouse inalambrico ergonomico para uso corporativo en estaciones y oficinas.',
            'short_name' => 'Mouse',
            'unit_of_measure' => 'PZA',
            'created_by' => $owner->id,
        ]);

        return [
            'company_id' => $company->id,
            'cost_center_id' => $costCenter->id,
            'expense_category_id' => $expenseCategory->id,
            'budget_cedula_id' => $budgetCedula->id,
            'product_service_id' => $productService->id,
            'receiving_location_id' => $receivingLocation->id,
        ];
    }

    private function createSupplier(User $supplierUser)
    {
        return \App\Models\Supplier::factory()->create([
            'email' => $supplierUser->email,
            'approval_status' => 'approved',
        ]);
    }
}
