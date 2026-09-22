<?php

namespace Tests\Feature;

use App\Models\DirectPurchaseOrder;
use App\Models\DirectPurchaseOrderItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseOrderPdfViewTest extends TestCase
{
    use RefreshDatabase;

    private function buyer(): User
    {
        return User::factory()->create()->assignRole(Role::findOrCreate('buyer', 'web'));
    }

    public function test_issued_purchase_order_pdf_opens_inline_in_the_browser(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create(['status' => 'ISSUED']);
        PurchaseOrderItem::factory()->create(['purchase_order_id' => $purchaseOrder->id]);

        $response = $this->actingAs($this->buyer())->get(route('purchase-orders.pdf.view', $purchaseOrder));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_purchase_order_pdf_is_not_available_before_issue(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create(['status' => 'PENDING_APPROVAL']);

        $this->actingAs($this->buyer())
            ->get(route('purchase-orders.pdf.view', $purchaseOrder))
            ->assertStatus(422);
    }

    public function test_issued_direct_purchase_order_pdf_opens_inline_in_the_browser(): void
    {
        $directPurchaseOrder = DirectPurchaseOrder::factory()->create(['status' => 'ISSUED']);
        DirectPurchaseOrderItem::factory()->create(['direct_purchase_order_id' => $directPurchaseOrder->id]);

        $response = $this->actingAs($this->buyer())->get(route('direct-purchase-orders.pdf.view', $directPurchaseOrder));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
    }

    public function test_regular_datatable_links_issued_orders_to_the_inline_pdf_in_a_new_tab(): void
    {
        $issued = PurchaseOrder::factory()->create(['status' => 'ISSUED']);
        $pending = PurchaseOrder::factory()->create(['status' => 'PENDING_APPROVAL']);

        $rows = collect($this->actingAs($this->buyer())
            ->getJson(route('purchase-orders.datatable.regular'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json('data'))
            ->keyBy('id');

        $this->assertStringContainsString(route('purchase-orders.pdf.view', $issued->id), $rows[$issued->id]['actions']);
        $this->assertStringContainsString('target="_blank"', $rows[$issued->id]['actions']);
        $this->assertStringNotContainsString('/pdf/view', $rows[$pending->id]['actions']);
    }
}
