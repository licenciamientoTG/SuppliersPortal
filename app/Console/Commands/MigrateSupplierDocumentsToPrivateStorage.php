<?php

namespace App\Console\Commands;

use App\Models\SupplierDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateSupplierDocumentsToPrivateStorage extends Command
{
    protected $signature = 'supplier-documents:migrate-private {--dry-run : Reporta los cambios sin mover archivos}';

    protected $description = 'Mueve documentos de proveedores del disco publico al almacenamiento privado.';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk('supplier_documents');
        $dryRun = (bool) $this->option('dry-run');
        $moved = 0;
        $alreadyPrivate = 0;
        $missing = 0;

        SupplierDocument::query()->orderBy('id')->each(function (SupplierDocument $document) use (
            $public,
            $private,
            $dryRun,
            &$moved,
            &$alreadyPrivate,
            &$missing
        ): void {
            if ($private->exists($document->path_file)) {
                $alreadyPrivate++;

                if (! $dryRun && $public->exists($document->path_file)) {
                    $public->delete($document->path_file);
                }

                return;
            }

            if (! $public->exists($document->path_file)) {
                $missing++;
                $this->warn("Documento {$document->id}: archivo no localizado.");

                return;
            }

            if (! $dryRun) {
                $private->put($document->path_file, $public->readStream($document->path_file));
                $public->delete($document->path_file);
            }

            $moved++;
        });

        $this->table(
            ['Resultado', 'Cantidad'],
            [
                [$dryRun ? 'Por mover' : 'Movidos', $moved],
                ['Ya privados', $alreadyPrivate],
                ['No localizados', $missing],
            ]
        );

        return $missing > 0 ? self::FAILURE : self::SUCCESS;
    }
}
