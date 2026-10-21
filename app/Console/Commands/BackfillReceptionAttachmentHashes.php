<?php

namespace App\Console\Commands;

use App\Models\Reception;
use App\Models\ReceptionAttachment;
use App\Models\ReceptionItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackfillReceptionAttachmentHashes extends Command
{
    protected $signature = 'receptions:backfill-attachment-hashes';

    protected $description = 'Calcula el SHA-256 de la remisión y las fotos de recepciones ya existentes, y las registra en reception_attachments (hash_origin=BACKFILL). No simula datos: los archivos que ya no existen en disco se reportan y se omiten.';

    public function handle(): int
    {
        $disk = Storage::disk('public');

        $this->backfillRemissions($disk);
        $this->backfillPhotos($disk);

        return self::SUCCESS;
    }

    private function backfillRemissions($disk): void
    {
        Reception::whereNotNull('remission_path')->chunkById(100, function ($receptions) use ($disk) {
            foreach ($receptions as $reception) {
                $alreadyRecorded = ReceptionAttachment::where('reception_id', $reception->id)
                    ->where('attachable_type', ReceptionAttachment::TYPE_REMISSION)
                    ->exists();

                if ($alreadyRecorded) {
                    continue;
                }

                if (! $disk->exists($reception->remission_path)) {
                    $this->warn("Remisión no encontrada en disco para {$reception->folio}: {$reception->remission_path}");

                    continue;
                }

                ReceptionAttachment::create([
                    'reception_id' => $reception->id,
                    'attachable_type' => ReceptionAttachment::TYPE_REMISSION,
                    'reception_item_id' => null,
                    'disk' => 'public',
                    'path' => $reception->remission_path,
                    'original_name' => basename($reception->remission_path),
                    'mime_type' => $disk->mimeType($reception->remission_path) ?: null,
                    'size' => $disk->size($reception->remission_path),
                    'sha256' => hash('sha256', $disk->get($reception->remission_path)),
                    'hash_origin' => ReceptionAttachment::HASH_ORIGIN_BACKFILL,
                    'uploaded_by' => $reception->received_by,
                    'uploaded_at' => $reception->received_at,
                ]);
            }
        });
    }

    private function backfillPhotos($disk): void
    {
        ReceptionItem::whereNotNull('photos')->chunkById(100, function ($items) use ($disk) {
            foreach ($items as $item) {
                $recordedPaths = ReceptionAttachment::where('reception_item_id', $item->id)
                    ->where('attachable_type', ReceptionAttachment::TYPE_PHOTO)
                    ->pluck('path')
                    ->all();

                foreach ((array) $item->photos as $path) {
                    if (in_array($path, $recordedPaths, true)) {
                        continue;
                    }

                    if (! $disk->exists($path)) {
                        $this->warn("Foto no encontrada en disco para la recepción {$item->reception_id}, ítem {$item->id}: {$path}");

                        continue;
                    }

                    ReceptionAttachment::create([
                        'reception_id' => $item->reception_id,
                        'attachable_type' => ReceptionAttachment::TYPE_PHOTO,
                        'reception_item_id' => $item->id,
                        'disk' => 'public',
                        'path' => $path,
                        'original_name' => basename($path),
                        'mime_type' => $disk->mimeType($path) ?: null,
                        'size' => $disk->size($path),
                        'sha256' => hash('sha256', $disk->get($path)),
                        'hash_origin' => ReceptionAttachment::HASH_ORIGIN_BACKFILL,
                        'uploaded_by' => $item->reception->received_by,
                        'uploaded_at' => $item->reception->received_at,
                    ]);
                }
            }
        });
    }
}
