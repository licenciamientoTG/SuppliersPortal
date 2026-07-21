<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupplierDocumentUploadPreparationService
{
    /**
     * @param  list<UploadedFile>  $files
     * @return array{file: UploadedFile, temporary_path: ?string}
     */
    public function prepare(array $files, int $maxKb): array
    {
        if ($files === []) {
            throw ValidationException::withMessages(['files' => 'Selecciona un archivo para cargar.']);
        }

        if (count($files) > 5) {
            throw ValidationException::withMessages(['files' => 'Puedes cargar un PDF o un máximo de cinco fotografías.']);
        }

        $extensions = array_map(
            fn (UploadedFile $file) => strtolower($file->getClientOriginalExtension()),
            $files
        );

        if (in_array('pdf', $extensions, true)) {
            if (count($files) !== 1 || $extensions[0] !== 'pdf') {
                throw ValidationException::withMessages(['files' => 'Carga un solo PDF o únicamente fotografías JPG/PNG.']);
            }

            return ['file' => $files[0], 'temporary_path' => null];
        }

        if (array_diff($extensions, ['jpg', 'jpeg', 'png']) !== []) {
            throw ValidationException::withMessages(['files' => 'Las fotografías deben estar en formato JPG o PNG.']);
        }

        $html = implode('', array_map(function (UploadedFile $file): string {
            $contents = file_get_contents((string) $file->getRealPath());
            if ($contents === false) {
                throw ValidationException::withMessages(['files' => 'No fue posible leer una de las fotografías seleccionadas.']);
            }

            $mimeType = $file->getMimeType() ?: 'image/jpeg';

            return '<section><img src="data:'.e($mimeType).';base64,'.base64_encode($contents).'" alt="Documento del proveedor"></section>';
        }, $files));

        $pdfContents = Pdf::loadHtml('<!doctype html><html><head><style>
            @page { margin: 0; size: letter portrait; }
            body { margin: 0; padding: 0; }
            section { width: 100%; height: 100%; page-break-after: always; }
            section:last-child { page-break-after: auto; }
            img { display: block; width: 100%; height: 100%; object-fit: contain; }
        </style></head><body>'.$html.'</body></html>')
            ->setPaper('letter', 'portrait')
            ->output();

        $directory = storage_path('app/private/tmp/supplier-document-uploads');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory.'/'.Str::uuid().'.pdf';
        file_put_contents($path, $pdfContents);

        if (filesize($path) > $maxKb * 1024) {
            @unlink($path);

            throw ValidationException::withMessages(['files' => 'El PDF consolidado supera el limite permitido para este documento.']);
        }

        return [
            'file' => new UploadedFile($path, 'documento-consolidado.pdf', 'application/pdf', null, true),
            'temporary_path' => $path,
        ];
    }
}
