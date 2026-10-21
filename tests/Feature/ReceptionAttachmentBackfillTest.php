<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ReceivingLocation;
use App\Models\Reception;
use App\Models\ReceptionAttachment;
use App\Models\ReceptionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceptionAttachmentBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_creates_remission_and_photo_attachments_from_disk(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('remisiones/vieja.pdf', 'contenido remision vieja');
        Storage::disk('public')->put('recepciones/fotos/vieja.jpg', 'contenido foto vieja');

        $reception = $this->createReception(['remission_path' => 'remisiones/vieja.pdf']);
        $item = $this->createReceptionItem($reception, ['photos' => ['recepciones/fotos/vieja.jpg']]);

        $this->artisan('receptions:backfill-attachment-hashes')->assertExitCode(0);

        $remission = ReceptionAttachment::where('reception_id', $reception->id)
            ->where('attachable_type', ReceptionAttachment::TYPE_REMISSION)
            ->firstOrFail();
        $this->assertSame(hash('sha256', 'contenido remision vieja'), $remission->sha256);
        $this->assertSame(ReceptionAttachment::HASH_ORIGIN_BACKFILL, $remission->hash_origin);
        $this->assertSame($reception->received_by, $remission->uploaded_by);

        $photo = ReceptionAttachment::where('reception_item_id', $item->id)
            ->where('attachable_type', ReceptionAttachment::TYPE_PHOTO)
            ->firstOrFail();
        $this->assertSame(hash('sha256', 'contenido foto vieja'), $photo->sha256);
        $this->assertSame(ReceptionAttachment::HASH_ORIGIN_BACKFILL, $photo->hash_origin);
    }

    public function test_backfill_is_idempotent_and_does_not_duplicate_rows(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('remisiones/vieja.pdf', 'contenido remision vieja');

        $this->createReception(['remission_path' => 'remisiones/vieja.pdf']);

        $this->artisan('receptions:backfill-attachment-hashes')->assertExitCode(0);
        $this->artisan('receptions:backfill-attachment-hashes')->assertExitCode(0);

        $this->assertDatabaseCount('reception_attachments', 1);
    }

    public function test_backfill_reports_missing_files_without_crashing_or_creating_rows(): void
    {
        Storage::fake('public');

        $reception = $this->createReception(['remission_path' => 'remisiones/no-existe.pdf']);

        $this->artisan('receptions:backfill-attachment-hashes')
            ->assertExitCode(0)
            ->expectsOutputToContain($reception->folio);

        $this->assertDatabaseCount('reception_attachments', 0);
    }

    private function createReception(array $attributes = []): Reception
    {
        $receiver = User::factory()->create();
        $company = Company::factory()->create();
        $location = ReceivingLocation::factory()->create(['company_id' => $company->id]);

        return Reception::create(array_merge([
            'folio' => Reception::generateNextFolio(),
            'receivable_type' => 'App\\Models\\DirectPurchaseOrder',
            'receivable_id' => 1,
            'receiving_location_id' => $location->id,
            'received_by' => $receiver->id,
            'status' => Reception::STATUS_COMPLETED,
            'received_at' => now(),
        ], $attributes));
    }

    private function createReceptionItem(Reception $reception, array $attributes = []): ReceptionItem
    {
        return ReceptionItem::create(array_merge([
            'reception_id' => $reception->id,
            'receivable_item_type' => 'App\\Models\\DirectPurchaseOrderItem',
            'receivable_item_id' => 1,
            'quantity_received' => 1,
            'conformity' => ReceptionItem::CONFORMITY_OK,
        ], $attributes));
    }
}
