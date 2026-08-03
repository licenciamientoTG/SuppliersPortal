<?php

namespace App\Http\Controllers;

use App\Exceptions\Rfq\AwardNotAllowedException;
use App\Exceptions\Rfq\RfqAlreadyRejectedException;
use App\Models\QuotationGroup;
use App\Models\Rfq;
use App\Services\ApprovalService;
use App\Services\QuotationRejectionWorkflowService;
use App\Services\Rfq\RfqAwardService;
use App\Services\Rfq\RfqFolioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class RfqComparisonController extends Controller
{
    public function __construct(
        protected ApprovalService $approvalService,
        protected QuotationRejectionWorkflowService $quotationRejectionWorkflowService,
        protected RfqAwardService $awardService,
        protected RfqFolioService $folioService
    ) {}

    public function index(Rfq $rfq)
    {
        $rfq->load([
            'requisition.requester',
            'requisition.items.costCenter',
            'suppliers',
            'rfqResponses' => fn ($query) => $query->whereIn('status', ['SUBMITTED', 'SELECTED', 'REJECTED']),
            'quotationSummary.rejector',
            'quotationSummary.currentApprover',
            'activities',
        ]);

        $items = $rfq->getItemsToQuote();
        $approvalLevels = $this->approvalService->getAllLevels();
        $itemsNobodyQuoted = $rfq->itemsQuotedByNoSupplier();
        $approvedSuppliers = $itemsNobodyQuoted->isNotEmpty()
            ? \App\Models\Supplier::approved()->orderBy('company_name')->get()
            : collect();
        $supplierDiagnostics = $rfq->suppliers
            ->mapWithKeys(fn ($supplier) => [
                $supplier->id => $this->awardService->supplierDiagnostics($rfq, $supplier->id),
            ])
            ->all();

        return view('rfq.comparison.index', [
            'rfq' => $rfq,
            'items' => $items,
            'presupuestoDisponible' => null,
            'approvalLevels' => $approvalLevels,
            'supplierDiagnostics' => $supplierDiagnostics,
            'itemsNobodyQuoted' => $itemsNobodyQuoted,
            'approvedSuppliers' => $approvedSuppliers,
        ]);
    }

    public function select(Request $request, Rfq $rfq)
    {
        if ($rfq->isRejected()) {
            return back()->with('error', 'Esta RFQ ya fue rechazada en autorización. Usa la opción de re-adjudicar para crear una nueva vuelta.');
        }

        $rfq->loadMissing('quotationSummary');

        if ($rfq->quotationSummary?->isPending()) {
            return back()->with('error', 'Esta RFQ ya tiene una adjudicación pendiente de autorización. Espera la resolución del aprobador antes de continuar.');
        }

        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'justification' => 'required|string|min:15',
        ]);

        try {
            $summary = $this->awardService->award(
                $rfq,
                $request->integer('supplier_id'),
                $request->string('justification')->toString(),
                $request->input('notes'),
                Auth::id()
            );

            return redirect()
                ->route('rfq.index')
                ->with('status', 'Adjudicación registrada y enviada a aprobación de '.($summary->currentApprover?->name ?? 'aprobador asignado').'.');
        } catch (RfqAlreadyRejectedException|AwardNotAllowedException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            Log::error("Error en adjudicación RFQ {$rfq->id}: {$exception->getMessage()}");

            return back()->with('error', 'No fue posible registrar la adjudicación: '.$exception->getMessage());
        }
    }

    public function reaward(Request $request, Rfq $rfq)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'justification' => 'required|string|min:15',
        ]);

        $diagnostics = $this->awardService->supplierDiagnostics($rfq, $request->integer('supplier_id'));

        if (! $diagnostics['allowed']) {
            return back()->with('error', 'No se puede re-adjudicar esta oferta: '.implode(' ', $diagnostics['reasons']));
        }

        try {
            $summary = $this->quotationRejectionWorkflowService->reawardRejectedQuotation(
                $rfq,
                $request->integer('supplier_id'),
                $request->string('justification')->toString(),
                Auth::id(),
                $request->input('notes')
            );

            $this->awardService->notifyQuotationSentForApproval($summary, true);

            return redirect()
                ->route('rfq.comparison.index', $summary->rfq_id)
                ->with('status', 'Nueva vuelta de adjudicación registrada y enviada a aprobación de '.($summary->currentApprover?->name ?? 'aprobador asignado').'.');
        } catch (Throwable $exception) {
            Log::error("Error en re-adjudicación RFQ {$rfq->id}: {$exception->getMessage()}");

            return back()->with('error', 'No fue posible crear la nueva vuelta de adjudicación: '.$exception->getMessage());
        }
    }

    public function generateComplementaryRfq(Request $request, Rfq $rfq)
    {
        $validated = $request->validate([
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('requisition_items', 'id')->where('requisition_id', $rfq->requisition_id),
            ],
            'supplier_ids' => 'required|array|min:1',
            'supplier_ids.*' => [
                'integer',
                Rule::exists('suppliers', 'id')->where('approval_status', 'approved'),
            ],
            'response_deadline' => 'required|date|after:today',
            'message' => 'nullable|string',
        ]);

        try {
            $newRfq = DB::transaction(function () use ($validated, $rfq) {
                $group = QuotationGroup::create([
                    'requisition_id' => $rfq->requisition_id,
                    'name' => 'Complemento de '.$rfq->folio,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);

                $attach = [];
                foreach (array_values($validated['item_ids']) as $i => $itemId) {
                    $attach[$itemId] = ['sort_order' => $i + 1];
                }
                $group->items()->attach($attach);

                $newRfq = Rfq::create([
                    'folio' => $this->folioService->next(),
                    'requisition_id' => $rfq->requisition_id,
                    'quotation_group_id' => $group->id,
                    'supersedes_rfq_id' => $rfq->id,
                    'source' => 'portal',
                    'status' => 'SENT',
                    'sent_at' => now(),
                    'response_deadline' => $validated['response_deadline'],
                    'message' => $validated['message'] ?? null,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);

                $supplierData = [];
                foreach ($validated['supplier_ids'] as $supplierId) {
                    $supplierData[$supplierId] = ['invited_at' => now()];
                }
                $newRfq->suppliers()->attach($supplierData);

                foreach ($validated['supplier_ids'] as $supplierId) {
                    // El envío real de correo sigue siendo TODO en el sistema (igual que sendRFQ).
                    Log::info('📧 RFQ complementaria enviada', [
                        'rfq_id' => $newRfq->id,
                        'folio' => $newRfq->folio,
                        'supersedes_rfq_id' => $rfq->id,
                        'supplier_id' => $supplierId,
                    ]);
                }

                return $newRfq;
            });

            return redirect()
                ->route('rfq.comparison.index', $rfq)
                ->with('status', "RFQ complementaria {$newRfq->folio} generada y enviada a ".count($validated['supplier_ids']).' proveedor(es).');
        } catch (Throwable $exception) {
            Log::error("Error al generar RFQ complementaria desde {$rfq->id}: {$exception->getMessage()}");

            return back()->with('error', 'No fue posible generar la RFQ complementaria: '.$exception->getMessage());
        }
    }

    public function cancelRejected(Request $request, Rfq $rfq)
    {
        $request->validate([
            'reason' => 'required|string|min:10|max:500',
        ]);

        try {
            $this->quotationRejectionWorkflowService->cancelRejectedRfq(
                $rfq,
                Auth::id(),
                $request->string('reason')->toString()
            );

            return redirect()
                ->route('quotes.index')
                ->with('status', 'Cotización cerrada sin borrar registros. El expediente quedó conservado.');
        } catch (Throwable $exception) {
            Log::error("Error al cerrar RFQ rechazada {$rfq->id}: {$exception->getMessage()}");

            return back()->with('error', 'No fue posible cerrar la cotización: '.$exception->getMessage());
        }
    }
}
