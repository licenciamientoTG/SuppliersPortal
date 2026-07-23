<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\SupplierDocumentType;
use App\Services\InfonavitQrValidationService;
use App\Services\SupplierDocumentAutoAcceptanceService;
use App\Services\SupplierDocumentRequirementService;
use App\Services\SupplierDocumentReviewService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DocumentReviewController extends Controller
{
    /**
     * Vista unificada con tabs: Bandeja (documentos) y Proveedores.
     * Solo display. Sin acciones.
     */
    public function index(Request $request)
    {
        $activeTab = $request->query('tab') === 'proveedores' ? 'proveedores' : 'bandeja';
        $requiredTypes = SupplierDocument::ALL_TYPES;
        $queueSearch = trim(substr((string) $request->query('queue_search', ''), 0, 100));
        $supplierSearch = trim(substr((string) $request->query('supplier_search', ''), 0, 100));

        // Bandeja: solo pendientes (para mostrar)
        $pendingDocs = SupplierDocument::with(['supplier:id,company_name,rfc', 'uploader:id,name', 'documentType'])
            ->where('status', 'pending_review')
            ->when($queueSearch !== '', function ($query) use ($queueSearch) {
                $like = '%'.$queueSearch.'%';

                $query->where(function ($searchQuery) use ($like) {
                    $searchQuery->where('doc_type', 'like', $like)
                        ->orWhereHas('supplier', function ($supplierQuery) use ($like) {
                            $supplierQuery->where('company_name', 'like', $like)
                                ->orWhere('rfc', 'like', $like);
                        });
                });
            })
            ->orderByDesc('uploaded_at')
            ->paginate(50)
            ->withQueryString();

        // KPIs (consultas independientes)
        $start = now()->startOfDay();
        $end = now()->endOfDay();

        $kpiPendientes = SupplierDocument::where('status', 'pending_review')->count();
        $kpiAprobadosHoy = SupplierDocument::where('status', 'accepted')
            ->whereBetween('reviewed_at', [$start, $end])
            ->count();
        $kpiRechazadosHoy = SupplierDocument::where('status', 'rejected')
            ->whereBetween('reviewed_at', [$start, $end])
            ->count();

        // El resumen por proveedor es pesado; solo se calcula cuando se abre esa pestana.
        $suppliersSummary = $activeTab === 'proveedores'
            ? $this->supplierReviewSummary($supplierSearch)
            : collect();

        return view('documents.admin.index', [
            'activeTab' => $activeTab,
            'pendingDocs' => $pendingDocs,
            'suppliersSummary' => $suppliersSummary,
            'requiredTypes' => $requiredTypes,
            'queueSearch' => $queueSearch,
            'supplierSearch' => $supplierSearch,
            'kpiPendientes' => $kpiPendientes,
            'kpiAprobadosHoy' => $kpiAprobadosHoy,
            'kpiRechazadosHoy' => $kpiRechazadosHoy,
        ]);
    }

    public function queue()
    {
        return redirect()->route('admin.review.index');
    }

    public function suppliers()
    {
        return redirect()->route('admin.review.index', ['tab' => 'proveedores']);
    }

    private function supplierReviewSummary(string $search = ''): LengthAwarePaginator
    {
        $documentTypes = SupplierDocumentType::query()
            ->where('is_active', true)
            ->where('is_required', true)
            ->get([
                'id',
                'code',
                'applies_to_physical',
                'applies_to_legal',
                'requires_repse',
            ]);
        $documentTypeIds = $documentTypes->pluck('id');

        $suppliers = Supplier::query()
            ->with(['documentRequirements' => function ($query) use ($documentTypeIds) {
                $query->whereIn('supplier_document_type_id', $documentTypeIds)
                    ->with([
                        'documentType',
                        'currentDocument:id,supplier_id,uploaded_at',
                    ]);
            }])
            ->select('id', 'company_name', 'rfc', 'person_type', 'provides_specialized_services')
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(function ($searchQuery) use ($like) {
                    $searchQuery->where('company_name', 'like', $like)
                        ->orWhere('rfc', 'like', $like);
                });
            })
            ->orderBy('company_name')
            ->paginate(50)
            ->withQueryString();

        $suppliers->setCollection($suppliers->getCollection()->map(function (Supplier $supplier) use ($documentTypes) {
            return $this->supplierReviewRow($supplier, $documentTypes);
        }));

        return $suppliers;
    }

    private function supplierReviewRow(Supplier $supplier, Collection $documentTypes): array
    {
        $requiredTypes = $documentTypes
            ->filter(fn (SupplierDocumentType $type) => $type->appliesTo($supplier))
            ->pluck('code')
            ->all();
        $requiredRequirements = $supplier->documentRequirements
            ->where('is_enforced', true)
            ->filter(fn ($requirement) => in_array($requirement->documentType?->code, $requiredTypes, true));
        $totalRequired = $requiredRequirements->count();
        $uploaded = $requiredRequirements->whereIn('status', ['submitted', 'compliant', 'rejected', 'expired'])->count();
        $accepted = $requiredRequirements->where('status', 'compliant')->count();
        $rejected = $requiredRequirements->where('status', 'rejected')->count();
        $inReview = $requiredRequirements->where('status', 'submitted')->count();
        $expired = $requiredRequirements->where('status', 'expired')->count();
        $pending = $requiredRequirements->where('status', 'pending')->count();
        $progress = $totalRequired > 0 ? round(($accepted / $totalRequired) * 100) : 0;

        return [
            'supplier' => $supplier,
            'total_required' => $totalRequired,
            'uploaded' => $uploaded,
            'accepted' => $accepted,
            'rejected' => $rejected,
            'in_review' => $inReview,
            'expired' => $expired,
            'pending' => $pending,
            'progress_percent' => max(0, min(100, $progress)),
            'last_activity_at' => optional(
                $requiredRequirements->pluck('currentDocument.uploaded_at')->filter()->max()
            )?->toDateTimeString(),
        ];
    }

    /**
     * Ficha simple por proveedor (solo display).
     */
    public function showSupplier(Supplier $supplier, SupplierDocumentRequirementService $requirements)
    {
        // Documentos agrupados por tipo (con eager loading para evitar N+1)
        $docs = $supplier->documents()
            ->with(['uploader:id,name', 'reviewer:id,name', 'documentType'])
            ->select('id', 'supplier_id', 'doc_type', 'status', 'uploaded_at', 'path_file', 'rejection_reason', 'uploaded_by', 'reviewed_by', 'reviewed_at', 'supplier_document_type_id')
            ->orderByDesc('uploaded_at')
            ->get()
            ->groupBy('doc_type');

        $requiredTypes = SupplierDocument::requiredTypesFor($supplier);
        $supplierRequirements = $requirements->ensureForSupplier($supplier)
            ->where('is_enforced', true);

        return view('documents.admin.show_supplier', [
            'supplier' => $supplier,
            'docsByType' => $docs,
            'requiredTypes' => $requiredTypes,
            'supplierRequirements' => $supplierRequirements,
        ]);
    }

    public function accept(Request $request, SupplierDocument $document, SupplierDocumentReviewService $reviews)
    {
        $document->loadMissing('supplier', 'documentType');
        $isPeriodic = $document->documentType?->renewal_mode === 'periodic';
        $data = $request->validate([
            'issued_at' => [$isPeriodic ? 'required' : 'nullable', 'nullable', 'date'],
            'validated_rfc' => ['nullable', 'string', 'max:13'],
            'compliance_status' => ['nullable', Rule::in(['POSITIVA', 'NEGATIVA', 'SIN OPINION', 'NO APLICA'])],
        ]);

        $this->validateReviewerConfirmation($document, $data);

        $metadata = $this->confirmedValidationMetadata($document, $data, $request->user()->id);
        $document = $reviews->accept(
            $document,
            $data['issued_at'] ?? null,
            $request->user(),
            $metadata,
        );

        // Respuesta JSON para AJAX
        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'id' => $document->id,
                'new_status' => $document->status,
                'reviewed_by' => $request->user()->name ?? '—',
                'reviewed_at' => optional($document->reviewed_at)->format('Y-m-d H:i'),
            ]);
        }

        // Fallback a navegación tradicional
        return back()->with('success', 'Documento aprobado correctamente.');
    }

    private function validateReviewerConfirmation(SupplierDocument $document, array $data): void
    {
        $expectedRfc = strtoupper((string) ($document->supplier?->rfc ?? ''));
        $confirmedRfc = strtoupper((string) ($data['validated_rfc'] ?? ''));
        $status = strtoupper((string) ($data['compliance_status'] ?? ''));
        $requiresFiscalValidation = in_array($document->doc_type, ['constancia_fiscal', 'opinion_sat', 'opinion_imss', 'opinion_infonavit'], true);

        if ($requiresFiscalValidation && $confirmedRfc === '') {
            throw ValidationException::withMessages([
                'validated_rfc' => 'Confirma el RFC detectado antes de aprobar.',
            ]);
        }

        if ($confirmedRfc !== '' && $expectedRfc !== '' && ! hash_equals($expectedRfc, $confirmedRfc)) {
            throw ValidationException::withMessages([
                'validated_rfc' => 'El RFC confirmado no coincide con el RFC del proveedor.',
            ]);
        }

        if (in_array($document->doc_type, ['opinion_sat', 'opinion_imss', 'opinion_infonavit'], true) && $status !== 'POSITIVA') {
            throw ValidationException::withMessages([
                'compliance_status' => 'Para aprobar una opinión de cumplimiento, la validación debe ser POSITIVA.',
            ]);
        }

        if ($document->documentType?->renewal_mode === 'periodic' && ! empty($data['issued_at'])) {
            $issuedAt = Carbon::parse($data['issued_at'])->startOfDay();
            $expiresAt = $document->documentType->calculateExpiry($issuedAt);

            if (! $document->documentType->isCurrentOn($issuedAt, now()->startOfDay())) {
                throw ValidationException::withMessages([
                    'issued_at' => sprintf(
                        'La fecha de origen ya quedo fuera de vigencia. Este documento vence el %s segun la periodicidad configurada (%s).',
                        $expiresAt?->toDateString(),
                        $document->documentType->periodicityLabel(),
                    ),
                ]);
            }
        }
    }

    private function confirmedValidationMetadata(SupplierDocument $document, array $data, int $reviewerId): array
    {
        $metadata = $document->issue_date_extraction_data ?? [];
        $expectedRfc = strtoupper((string) ($document->supplier?->rfc ?? ''));
        $confirmedRfc = strtoupper((string) ($data['validated_rfc'] ?? ($metadata['rfc'] ?? '')));
        $status = strtoupper((string) ($data['compliance_status'] ?? ($metadata['compliance_status'] ?? '')));

        if ($confirmedRfc !== '') {
            $metadata['rfc'] = $confirmedRfc;
        }

        if ($status !== '' && $status !== 'NO APLICA') {
            $metadata['compliance_status'] = $status;
        }

        if (! empty($data['issued_at'])) {
            $metadata['issued_at'] = $data['issued_at'];
        }

        if ($confirmedRfc !== '' && $expectedRfc !== '') {
            $metadata['rfc_matches_supplier'] = hash_equals($expectedRfc, $confirmedRfc);
        }

        if (isset($metadata['compliance_status'])) {
            $metadata['compliance_is_positive'] = $metadata['compliance_status'] === 'POSITIVA';
        }

        $metadata['reviewer_confirmed'] = true;
        $metadata['reviewer_confirmed_by'] = $reviewerId;
        $metadata['reviewer_confirmed_at'] = now()->toDateTimeString();

        return $metadata;
    }

    public function revalidate(SupplierDocument $document, InfonavitQrValidationService $infonavit, SupplierDocumentAutoAcceptanceService $autoAcceptance, SupplierDocumentRequirementService $requirements)
    {
        abort_unless($document->doc_type === 'opinion_infonavit', 422, 'Este documento no utiliza la consulta de INFONAVIT.');

        if (! $infonavit->validateDocument($document)) {
            return response()->json([
                'ok' => false,
                'message' => 'INFONAVIT no respondio o aun no mostro el resultado. Puedes reintentar.',
            ], 202);
        }

        $document->refresh();
        $autoAcceptance->acceptIfEligible($document, $requirements);
        $document->refresh();

        return response()->json([
            'ok' => true,
            'status' => $document->status,
            'issued_at' => $document->issued_at?->format('Y-m-d'),
            'validation' => $document->issue_date_extraction_data,
        ]);
    }

    public function reject(Request $request, SupplierDocument $document, SupplierDocumentReviewService $reviews)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ], [], ['reason' => 'motivo de rechazo']);

        $document = $reviews->reject($document, $data['reason'], $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'id' => $document->id,
                'new_status' => $document->status,
                'rejection_reason' => $document->rejection_reason,
                'reviewed_by' => $request->user()->name ?? '—',
                'reviewed_at' => optional($document->reviewed_at)->format('Y-m-d H:i'),
            ]);
        }

        return back()->with('success', 'Documento rechazado correctamente.');
    }
}
