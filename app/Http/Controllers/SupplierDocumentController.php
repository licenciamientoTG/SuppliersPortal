<?php

namespace App\Http\Controllers;

use App\Mail\SupplierFeedbackMail;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\SupplierDocumentType;
use App\Services\DocumentIssueDateExtractionService;
use App\Services\SupplierDocumentRequirementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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

    public function store(Request $request, Supplier $supplier, SupplierDocumentRequirementService $requirements, DocumentIssueDateExtractionService $issueDates)
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
            'file' => [
                'required',
                'file',
                "max:$maxKb",
                'mimes:jpg,jpeg,png,pdf',
            ],
        ]);

        $file = $request->file('file');
        $path = $file->store("suppliers/{$supplier->id}/documents", 'public');
        $issueDateExtraction = $type->validity_source === 'qr'
            ? $issueDates->extract($file, $type)
            : ['issued_at' => null, 'metadata' => null];

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
            'issued_at_source' => $issueDateExtraction['issued_at'] ? 'qr' : null,
            'issue_date_extraction_data' => $issueDateExtraction['metadata'],
        ]);

        if ($requirement) {
            $requirements->markSubmitted($requirement);
        }

        $supplier->recalculateDocumentStatus();

        $url = Storage::disk('public')->url($doc->path_file);

        return response()->json([
            'id' => $doc->id,
            'doc_type' => $doc->doc_type,
            'status' => $doc->status,
            'uploaded_at' => $doc->uploaded_at?->format('Y-m-d H:i'),
            'url' => $url,
            'destroy_url' => route($request->user('supplier') ? 'supplier.documents.destroy' : 'documents.suppliers.destroy', [$supplier, $doc->id]),
        ]);
    }

    public function review(Request $request, Supplier $supplier, SupplierDocument $document, SupplierDocumentRequirementService $requirements)
    {
        $this->authorize('review', $document);

        $isPeriodic = $document->documentType?->renewal_mode === 'periodic';
        $data = $request->validate([
            'action' => ['required', Rule::in(['accept', 'reject'])],
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
            'issued_at' => [$isPeriodic && $request->input('action') === 'accept' ? 'required' : 'nullable', 'nullable', 'date'],
        ]);

        if ($request->action === 'accept') {
            $document->update([
                'status' => 'accepted',
                'rejection_reason' => null,
                'reviewed_by' => $request->user('web')?->id,
                'reviewed_at' => now(),
            ]);
        } else {
            $document->update([
                'status' => 'rejected',
                'rejection_reason' => $request->rejection_reason ?: 'Documento no aceptado.',
                'reviewed_by' => $request->user('web')?->id,
                'reviewed_at' => now(),
            ]);
        }

        if ($request->action === 'accept') {
            $requirements->accept($document, $data['issued_at'] ?? null, $request->user('web')?->id);
        } else {
            $requirements->reject($document);
        }
        $supplier->recalculateDocumentStatus();

        return back()->with('success', 'Revisión registrada.');
    }

    public function destroy(Request $request, Supplier $supplier, $documentId, SupplierDocumentRequirementService $requirements)
    {
        $authenticatedSupplier = $request->user('supplier');
        if ($authenticatedSupplier) {
            abort_unless((int) $authenticatedSupplier->id === (int) $supplier->id, 403);
        }

        $document = $supplier->documents()->whereKey($documentId)->firstOrFail();

        abort_if($document->requirement?->current_document_id === $document->id, 422, 'No puedes eliminar el documento vigente. Carga una nueva versión para reemplazarlo.');

        if ($document->path_file && Storage::disk('public')->exists($document->path_file)) {
            Storage::disk('public')->delete($document->path_file);
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

        $mail = Mail::to($toEmail);

        if ($sender && filled($sender->email) && strcasecmp($sender->email, $toEmail) !== 0) {
            $mail->cc($sender->email);
        }

        if (filled(config('mail.feedback_cc'))) {
            $mail->cc(config('mail.feedback_cc'));
        }

        $mail->send($mailable);

        return response()->json(['ok' => true]);
    }
}
