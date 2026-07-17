<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Services\InfonavitQrValidationService;
use App\Services\SupplierDocumentRequirementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentReviewController extends Controller
{
    /**
     * Vista unificada con tabs: Bandeja (documentos) y Proveedores.
     * Solo display. Sin acciones.
     */
    public function index()
    {
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

        // (Si quieres solo los revisados por el admin actual, añade ->where('reviewed_by', auth()->id()))

        // Resumen por proveedor (con eager loading para evitar N+1)
        $suppliers = Supplier::with(['documents' => function ($query) {
            $query->select('supplier_id', 'doc_type', 'status', 'uploaded_at');
        }])
            ->select('id', 'company_name', 'rfc')
            ->get();

        $suppliersSummary = $suppliers->map(function ($s) {
            $docs = $s->documents; // Ya cargado, sin consulta adicional
            $requiredTypes = SupplierDocument::requiredTypesFor($s);
            $requiredDocs = $docs->whereIn('doc_type', $requiredTypes);

            return [
                'supplier' => $s,
                'total_required' => count($requiredTypes),
                'uploaded' => $requiredDocs->pluck('doc_type')->unique()->count(),
                'accepted' => $requiredDocs->where('status', 'accepted')->count(),
                'rejected' => $requiredDocs->where('status', 'rejected')->count(),
                'last_activity_at' => optional($requiredDocs->max('uploaded_at'))?->toDateTimeString(),
            ];
        });

        return view('documents.admin.index', [
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
        return $this->index();
    }

    public function suppliers()
    {
        return $this->index();
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
        abort_if($document->hasFailedAutomaticValidation(), 422, 'La validacion automatica detecto RFC distinto u opinion de cumplimiento no positiva. Rechaza el documento y solicita una version valida.');

        $isPeriodic = $document->documentType?->renewal_mode === 'periodic';
        $data = $request->validate([
            'issued_at' => [$isPeriodic ? 'required' : 'nullable', 'nullable', 'date'],
        ]);
        DB::transaction(function () use ($request, $document, $requirements, $data) {
            $document->update([
                'status' => 'accepted',
                'rejection_reason' => null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
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
