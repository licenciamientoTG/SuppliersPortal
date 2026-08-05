<?php

namespace Tests\Feature;

use App\Http\Controllers\RequisitionController;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RequisitionAttachmentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_requisition_item_attachment_is_served_inline(): void
    {
        $this->withoutMiddleware();
        Storage::fake('local');

        $requisition = Requisition::factory()->create();
        $item = RequisitionItem::factory()->for($requisition)->create();
        $path = "requisition-item-attachments/{$item->id}/ficha-tecnica.pdf";

        Storage::disk('local')->put($path, 'contenido de prueba');
        $item->attachment()->create([
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'ficha-tecnica.pdf',
            'mime_type' => 'application/pdf',
            'size' => 19,
        ]);

        Storage::disk('local')->assertExists($path);
        $this->assertNotNull($item->fresh()->attachment);

        $response = app(RequisitionController::class)->showItemAttachment(
            $requisition->fresh(),
            $item->fresh()
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('inline; filename=ficha-tecnica.pdf', $response->headers->get('content-disposition'));
    }

    public function test_an_attachment_cannot_be_requested_through_another_requisition(): void
    {
        $this->withoutMiddleware();

        $user = User::factory()->create();
        $requisition = Requisition::factory()->create();
        $otherRequisition = Requisition::factory()->create();
        $otherItem = RequisitionItem::factory()->for($otherRequisition)->create();

        $this->actingAs($user)
            ->get(route('requisitions.items.attachment.show', [$requisition, $otherItem]))
            ->assertNotFound();
    }
}
