<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\RequisitionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseOrderSupplierNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_append_a_supplier_note_without_replacing_the_original_note(): void
    {
        $buyer = User::factory()->create(['name' => 'Comprador de Prueba'])
            ->assignRole(Role::findOrCreate('buyer', 'web'));
        $purchaseOrder = PurchaseOrder::factory()->create(['status' => 'ISSUED']);
        $requisitionItem = RequisitionItem::factory()->create([
            'requisition_id' => $purchaseOrder->requisition_id,
            'notes' => 'Nota original del solicitante.',
        ]);
        $purchaseOrderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'requisition_item_id' => $requisitionItem->id,
        ]);

        $response = $this->actingAs($buyer)->post(
            route('purchase-orders.items.supplier-note', [$purchaseOrder, $purchaseOrderItem]),
            ['note' => 'Entregar con remisión sellada.'],
        );

        $response->assertSessionHas('success');
        $updatedNote = $requisitionItem->fresh()->notes;

        $this->assertStringStartsWith('Nota original del solicitante.', $updatedNote);
        $this->assertStringContainsString('Nota adicional de Compras', $updatedNote);
        $this->assertStringContainsString('Comprador de Prueba', $updatedNote);
        $this->assertStringContainsString('Entregar con remisión sellada.', $updatedNote);
        $this->assertDatabaseHas('activity_log', [
            'event' => 'supplier_note_appended',
            'subject_id' => $purchaseOrder->id,
        ]);
    }

    public function test_non_buyer_cannot_append_a_supplier_note(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create(['status' => 'ISSUED']);
        $requisitionItem = RequisitionItem::factory()->create([
            'requisition_id' => $purchaseOrder->requisition_id,
        ]);
        $purchaseOrderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'requisition_item_id' => $requisitionItem->id,
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('purchase-orders.items.supplier-note', [$purchaseOrder, $purchaseOrderItem]), [
                'note' => 'No debe poder guardarse.',
            ])
            ->assertForbidden();
    }
}
