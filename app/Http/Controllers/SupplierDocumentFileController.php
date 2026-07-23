<?php

namespace App\Http\Controllers;

use App\Models\SupplierDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierDocumentFileController extends Controller
{
    public function show(Request $request, SupplierDocument $document): StreamedResponse
    {
        $actor = $request->user('supplier') ?? $request->user('web');
        abort_unless($actor, 403);

        Gate::forUser($actor)->authorize('view', $document);

        $disk = Storage::disk('supplier_documents');
        abort_unless($disk->exists($document->path_file), 404);

        $filename = ($document->documentType?->code ?? $document->doc_type).'-'.$document->id.'.'.pathinfo($document->path_file, PATHINFO_EXTENSION);

        return $disk->response($document->path_file, $filename, [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
