<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Services\InfonavitQrValidationService;
use App\Services\SupplierDocumentRequirementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // Bandeja: solo pendientes (para mostrar)
        $pendingDocs = SupplierDocument::with(['supplier:id,company_name,rfc', 'uploader:id,name', 'documentType'])
            ->where('status', 'pending_review')
            ->orderByDesc('uploaded_at')
            ->limit(50)
            ->get();

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
            ? $this->supplierReviewSummary()
            : collect();

        return view('documents.admin.index', [
            'activeTab' => $activeTab,
            'pendingDocs' => $pendingDocs,
            'suppliersSummary' => $suppliersSummary,
            'requiredTypes' => $requiredTypes,
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

    private function supplierReviewSummary(): \Illuminate\Support\Collection
    {
        $suppliers = Supplier::with(['documents' => function ($query) {
            $query->select('supplier_id', 'doc_type', 'status', 'uploaded_at');
        }])
            ->select('id', 'company_name', 'rfc')
            ->get();

        return $suppliers->map(function ($s) {
            $docs = $s->documents;
            $requiredTypes = SupplierDocument::requiredTypesFor($s);
            $requiredDocs = $docs->whereIn('doc_type', $requiredTypes);
            $totalRequired = count($requiredTypes);
            $uploaded = $requiredDocs->pluck('doc_type')->unique()->count();
            $accepted = $requiredDocs->where('status', 'accepted')->count();
            $rejected = $requiredDocs->where('status', 'rejected')->count();
            $pending = max($totalRequired - $accepted - $rejected, 0);
            $progress = $totalRequired > 0 ? round(($uploaded / $totalRequired) * 100) : 0;

            return [
                'supplier' => $s,
                'total_required' => $totalRequired,
                'uploaded' => $uploaded,
                'accepted' => $accepted,
                'rejected' => $rejected,
                'pending' => $pending,
                'progress_percent' => max(0, min(100, $progress)),
                'last_activity_at' => optional($requiredDocs->max('uploaded_at'))?->toDateTimeString(),
            ];
        });
    }

    /**
     * Ficha simple por proveedor (solo display).
     */
    public function showSupplier(Supplier $supplier)
    {
        // Documentos agrupados por tipo (con eager loading para evitar N+1)
        $docs = $supplier->documents()
            ->select('id', 'supplier_id', 'doc_type', 'status', 'uploaded_at', 'path_file', 'rejection_reason', 'reviewed_by', 'reviewed_at')
            ->orderByDesc('uploaded_at')
            ->get()
            ->groupBy('doc_type');

        $requiredTypes = SupplierDocument::requiredTypesFor($supplier);

        return view('documents.admin.show_supplier', [
            'supplier' => $supplier,
            'docsByType' => $docs,
            'requiredTypes' => $requiredTypes,
        ]);
    }

    public function accept(Request $request, SupplierDocument $document, SupplierDocumentRequirementService $requirements)
    {
        $document->loadMissing('supplier', 'documentType');
        $isPeriodic = $document->documentType?->renewal_mode === 'periodic';
        $data = $request->validate([
            'issued_at' => [$isPeriodic ? 'required' : 'nullable', 'nullable', 'date'],
            'validated_rfc' => ['nullable', 'string', 'max:13'],
            'compliance_status' => ['nullable', Rule::in(['POSITIVA', 'NEGATIVA', 'SIN OPINION', 'NO APLICA'])],
        ]);

        $this->validateReviewerConfirmation($document, $data);

        DB::transaction(function () use ($request, $document, $requirements, $data) {
            $metadata = $this->confirmedValidationMetadata($document, $data, $request->user()->id);

            $document->update([
                'status' => 'accepted',
                'rejection_reason' => null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'issue_date_extraction_data' => $metadata,
            ]);
            $requirements->accept($document, $data['issued_at'] ?? null, $request->user()->id);
            $document->supplier?->recalculateDocumentStatus();
        });

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

    public function revalidate(SupplierDocument $document, InfonavitQrValidationService $infonavit)
    {
        abort_unless($document->doc_type === 'opinion_infonavit', 422, 'Este documento no utiliza la consulta de INFONAVIT.');

        if (! $infonavit->validateDocument($document)) {
            return response()->json([
                'ok' => false,
                'message' => 'INFONAVIT no respondio o aun no mostro el resultado. Puedes reintentar.',
            ], 202);
        }

        $document->refresh();

        return response()->json([
            'ok' => true,
            'issued_at' => $document->issued_at?->format('Y-m-d'),
            'validation' => $document->issue_date_extraction_data,
        ]);
    }

    public function reject(Request $request, SupplierDocument $document, SupplierDocumentRequirementService $requirements)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ], [], ['reason' => 'motivo de rechazo']);

        DB::transaction(function () use ($request, $document, $data, $requirements) {
            $document->update([
                'status' => 'rejected',
                'rejection_reason' => $data['reason'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
            $requirements->reject($document);
            $document->supplier?->recalculateDocumentStatus();
        });

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
