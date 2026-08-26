<?php

namespace App\Http\Controllers;

use App\Jobs\SendSafeMailJob;
use App\Mail\SupplierFeedbackMail;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\SupplierDocumentType;
use App\Services\DocumentIssueDateExtractionService;
use App\Services\SupplierDocumentAutoAcceptanceService;
use App\Services\SupplierDocumentRequirementService;
use App\Services\SupplierDocumentUploadPreparationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SupplierDocumentController extends Controller
{
    public function index(Request $request, SupplierDocumentRequirementService $requirements)
    {
        $supplier = $request->user('supplier') ?? Supplier::whereKey(optional($request->user())->supplier?->id)->firstOrFail();

        $docs = $supplier->documents()
            ->latest('uploaded_at')
            ->get()
            ->groupBy('doc_type');

        $supplierRequirements = $requirements->ensureForSupplier($supplier)->where('is_enforced', true);

        return view('documents.suppliers.index', [
            'supplier' => $supplier,
            'requiredTypes' => SupplierDocument::requiredTypesFor($supplier),
            'docsByType' => $docs,
            'supplierRequirements' => $supplierRequirements,
        ]);
    }

    public function store(Request $request, Supplier $supplier, SupplierDocumentRequirementService $requirements, DocumentIssueDateExtractionService $issueDates, SupplierDocumentAutoAcceptanceService $autoAcceptance, SupplierDocumentUploadPreparationService $uploadPreparation)
    {
        $authenticatedSupplier = $request->user('supplier');
        if ($authenticatedSupplier) {
            abort_unless((int) $authenticatedSupplier->id === (int) $supplier->id, 403);
        }

        $docType = (string) $request->input('doc_type');
        $type = SupplierDocumentType::query()->where('code', $docType)->where('is_active', true)->firstOrFail();
        $maxKb = SupplierDocument::maxKbFor($docType);

        $request->validate([
            'doc_type' => ['required', Rule::in(SupplierDocumentType::query()->where('is_active', true)->pluck('code')->all())],
            'files' => ['required_without:file', 'array', 'max:5'],
            'files.*' => [
                'file',
                "max:$maxKb",
                'mimes:jpg,jpeg,png,pdf',
            ],
            'file' => [
                'nullable',
                'file',
                "max:$maxKb",
                'mimes:jpg,jpeg,png,pdf',
            ],
        ]);

        $files = $request->file('files') ?: array_filter([($request->file('file'))]);
        $preparedUpload = $uploadPreparation->prepare($files, $maxKb);
        $file = $preparedUpload['file'];
        $temporaryPath = $preparedUpload['temporary_path'];

        try {
            $path = $file->store("suppliers/{$supplier->id}/documents", 'supplier_documents');
            $issueDateExtraction = $type->validity_source === 'qr'
                ? $issueDates->extract($file, $type, $supplier)
                : ['issued_at' => null, 'metadata' => null];

            if ($docType === 'constancia_fiscal' && ! $this->hasValidatedCsfQrPair($issueDateExtraction['metadata'] ?? [])) {
                Storage::disk('supplier_documents')->delete($path);

                return response()->json([
                    'message' => $issueDateExtraction['metadata']['message'] ?? 'La constancia debe incluir y validar el QR de la cedula fiscal y el QR de validacion.',
                ], 422);
            }

            $issuedAtSource = $this->issuedAtSourceFromExtraction($issueDateExtraction);

            [$doc, $replacedPaths] = DB::transaction(function () use ($supplier, $type, $docType, $request, $path, $file, $issueDateExtraction, $issuedAtSource, $requirements, $autoAcceptance) {
                $previousPendingDocuments = SupplierDocument::query()
                    ->where('supplier_id', $supplier->id)
                    ->where('doc_type', $docType)
                    ->where('status', 'pending_review')
                    ->lockForUpdate()
                    ->get();

                $requirement = $requirements->requirementForUpload($supplier, $type);
                $doc = SupplierDocument::create([
                    'supplier_id' => $supplier->id,
                    'uploaded_by' => $request->user('web')?->id,
                    'doc_type' => $docType,
                    'supplier_document_type_id' => $type->id,
                    'supplier_document_requirement_id' => $requirement?->id,
                    'path_file' => $path,
                    'size_bytes' => $file->getSize(),
                    'mime_type' => $file->getClientMimeType(),
                    'status' => 'pending_review',
                    'uploaded_at' => now(),
                    'issued_at' => $issueDateExtraction['issued_at'],
                    'issued_at_source' => $issuedAtSource,
                    'issue_date_extraction_data' => $issueDateExtraction['metadata'],
                ]);

                if (! $autoAcceptance->acceptIfEligible($doc, $requirements) && $requirement) {
                    $requirements->markSubmitted($requirement);
                }

                $replacedPaths = $previousPendingDocuments->pluck('path_file')->filter()->all();
                $previousPendingDocuments->each->delete();

                return [$doc, $replacedPaths];
            });
        } catch (\Throwable $exception) {
            if (isset($path)) {
                Storage::disk('supplier_documents')->delete($path);
            }

            throw $exception;
        } finally {
            if ($temporaryPath && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }

        foreach ($replacedPaths as $replacedPath) {
            Storage::disk('supplier_documents')->delete($replacedPath);
        }

        $supplier->recalculateDocumentStatus();
        $doc->loadMissing('requirement');

        $url = route('supplier-documents.file', $doc);

        return response()->json([
            'id' => $doc->id,
            'doc_type' => $doc->doc_type,
            'status' => $doc->status,
            'requirement_status' => $doc->requirement?->status,
            'has_current_document' => $doc->requirement?->current_document_id !== null,
            'uploaded_at' => $doc->uploaded_at?->format('Y-m-d H:i'),
            'url' => $url,
            'destroy_url' => route($request->user('supplier') ? 'supplier.documents.destroy' : 'documents.suppliers.destroy', [$supplier, $doc->id]),
            'can_delete' => $doc->requirement?->current_document_id !== $doc->id,
        ]);
    }

    private function issuedAtSourceFromExtraction(array $issueDateExtraction): ?string
    {
        if (empty($issueDateExtraction['issued_at'])) {
            return null;
        }

        $method = (string) ($issueDateExtraction['metadata']['validation_method'] ?? '');

        return match ($method) {
            'infonavit_pdftotext' => 'pdf_text',
            'infonavit_ocr' => 'ocr',
            default => 'qr',
        };
    }

    private function hasValidatedCsfQrPair(array $metadata): bool
    {
        return ($metadata['csf_cedula_qr_validated'] ?? false) === true
            && ($metadata['csf_validation_qr_validated'] ?? false) === true
            && ($metadata['csf_qr_rfc_matches'] ?? false) === true;
    }

    public function destroy(Request $request, Supplier $supplier, $documentId, SupplierDocumentRequirementService $requirements)
    {
        $authenticatedSupplier = $request->user('supplier');
        if ($authenticatedSupplier) {
            abort_unless((int) $authenticatedSupplier->id === (int) $supplier->id, 403);
        }

        $document = $supplier->documents()->whereKey($documentId)->firstOrFail();

        abort_if($document->requirement?->current_document_id === $document->id, 422, 'No puedes eliminar el documento vigente. Carga una nueva versión para reemplazarlo.');

        if ($document->path_file && Storage::disk('supplier_documents')->exists($document->path_file)) {
            Storage::disk('supplier_documents')->delete($document->path_file);
        }

        $document->delete();
        if ($document->requirement) {
            $requirements->refreshRequirement($document->requirement);
        }
        $supplier->recalculateDocumentStatus();

        return response()->json(['ok' => true]);
    }

    public function feedback(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:100'],
            'doc_id' => ['nullable', 'integer'],
            'feedback' => ['required', 'string', 'min:5'],
            'supplier' => ['nullable', 'integer'],
        ]);

        $supplier = null;
        $doc = null;

        if (! empty($data['doc_id'])) {
            $doc = SupplierDocument::with('supplier')->find($data['doc_id']);
            $supplier = $doc?->supplier;
        }

        if (! $supplier && $request->filled('supplier')) {
            $supplier = Supplier::find($request->input('supplier'));
        }

        if (! $supplier) {
            return response()->json(['message' => 'Proveedor no encontrado.'], 404);
        }

        $toEmail = $supplier->email ?? null;
        if (! $toEmail) {
            return response()->json(['message' => 'El proveedor no tiene correo configurado.'], 422);
        }

        $sender = $request->user('web');

        $mailable = new SupplierFeedbackMail(
            $supplier,
            $data['type'],
            $data['feedback'],
            $sender,
            $doc
        );

        if ($sender && filled($sender->email)) {
            $mailable->replyTo($sender->email, $sender->name ?? null);
        }

        $cc = [];

        if ($sender && filled($sender->email) && strcasecmp($sender->email, $toEmail) !== 0) {
            $cc[] = $sender->email;
        }

        if (filled(config('mail.feedback_cc'))) {
            $cc[] = config('mail.feedback_cc');
        }

        SendSafeMailJob::dispatch(
            $toEmail,
            $mailable,
            array_unique($cc),
            'de retroalimentación documental al proveedor',
            $supplier->company_name,
        );

        return response()->json(['ok' => true]);
    }
}
