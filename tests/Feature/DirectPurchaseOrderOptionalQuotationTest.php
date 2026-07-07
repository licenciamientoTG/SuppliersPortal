<?php

namespace Tests\Feature;

use App\Models\AuthorizerRole;
use App\Models\Category;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\DirectPurchaseOrder;
use App\Models\ExpenseCategory;
use App\Models\ReceivingLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\NewDirectPurchaseOrderNotification;
use App\Services\AuthorizerResolutionService;
use App\Services\BudgetAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class DirectPurchaseOrderOptionalQuotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_purchase_order_can_be_created_without_quotation_file(): void
    {
        Notification::fake();
        $this->withoutMiddleware();

        $user = User::factory()->create();
        $approver = User::factory()->create();
        $company = Company::factory()->create();
        $category = Category::factory()->create();
        $costCenter = CostCenter::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'responsible_user_id' => $user->id,
            'budget_type' => 'FREE_CONSUMPTION',
            'global_amount' => 999999,
            'status' => 'ACTIVO',
            'purchase_type' => 'Gasto Operativo',
        ]);
        $expenseCategory = ExpenseCategory::factory()->create();
        $supplier = Supplier::factory()->create();
        $receivingLocation = ReceivingLocation::factory()->create(['company_id' => $company->id]);
        $authorizerRole = AuthorizerRole::query()->create([
            'name' => 'Gerencia',
            'approval_limit' => 500000,
            'display_order' => 1,
            'is_active' => true,
        ]);

        $user->costCenters()->attach($costCenter->id, [
            'is_active' => true,
            'is_default' => false,
            'created_by' => $user->id,
        ]);

        $this->instance(AuthorizerResolutionService::class, Mockery::mock(AuthorizerResolutionService::class, function ($mock) use ($approver, $authorizerRole) {
            $mock->shouldReceive('resolveForDirectPurchaseOrder')
                ->once()
                ->andReturn([
                    'approver_user' => $approver,
                    'authorizer_role' => $authorizerRole,
                    'effective_limit' => 500000,
                    'chain' => [],
                    'resolution_notes' => null,
                ]);
        }));

        $this->instance(BudgetAllocationService::class, Mockery::mock(BudgetAllocationService::class, function ($mock) {
            $mock->shouldReceive('reserveDirectPurchaseOrder')
                ->once()
                ->andReturnNull();
        }));

        $payload = [
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'purchase_type' => 'Gasto Operativo',
            'receiving_location_id' => $receivingLocation->id,
            'justification' => str_repeat('Compra directa justificada para mantener la operacion y atender una necesidad urgente. ', 2),
            'payment_terms' => 'Contado',
            'estimated_delivery_days' => 5,
            'items' => [
                [
                    'description' => 'Servicio puntual',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'cost_center_id' => $costCenter->id,
                    'expense_category_id' => $expenseCategory->id,
                    'iva_rate' => 16,
                ],
            ],
        ];

        $response = $this->actingAs($user)->post(route('direct-purchase-orders.store'), $payload);

        $response->assertRedirect(route('purchase-orders.index'));
        $response->assertSessionHasNoErrors();

        $order = DirectPurchaseOrder::query()->firstOrFail();

        $this->assertSame($supplier->id, $order->supplier_id);
        $this->assertSame('PENDING_APPROVAL', $order->status);
        $this->assertSame($approver->id, $order->assigned_approver_id);
        $this->assertCount(0, $order->documents()->where('document_type', 'quotation')->get());

        Notification::assertSentTo($approver, NewDirectPurchaseOrderNotification::class);
    }
}
