<?php

namespace App\Http\Controllers;

use App\Enum\RequisitionStatus;
use App\Models\QuotationSummary;
use App\Models\Rfq;
use App\Notifications\BuyerWorkflowNotification;
use App\Notifications\QuotationApprovalRequestNotification;
use App\Services\ApprovalService;
use App\Services\AuthorizerResolutionService;
use App\Services\BuyerNotificationService;
use App\Services\BudgetAllocationService;
use App\Services\QuotationRejectionWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RfqComparisonController extends Controller
{
    public function __construct(
        protected ApprovalService $approvalService,
        protected BudgetAllocationService $budgetAllocationService,
        protected AuthorizerResolutionService $authorizerResolutionService,
        protected BuyerNotificationService $buyerNotificationService,
        protected QuotationRejectionWorkflowService $quotationRejectionWorkflowService
    ) {}

    public function index(Rfq $rfq)
    {
        $rfq->load([
            'requisition.requester',
            'requisition.items.costCenter',
            'suppliers',
            'rfqResponses' => fn ($query) => $query->whereIn('status', ['SUBMITTED', 'SELECTED', 'REJECTED']),
            'quotationSummary.rejector',
            'activities',
        ]);

        $items = $rfq->getItemsToQuote();
        $approvalLevels = $this->approvalService->getAllLevels();
        $itemsNobodyQuoted = $rfq->itemsQuotedByNoSupplier();
        $approvedSuppliers = \App\Models\Supplier::approved()->orderBy('company_name')->get();
        $supplierDiagnostics = $rfq->suppliers
            ->mapWithKeys(fn ($supplier) => [
                $supplier->id => $this->buildSupplierDiagnostics($rfq, $supplier->id),
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

        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'justification' => 'required|string|min:15',
        ]);

        $rfq->loadMissing('requisition.requester', 'requisition.items.costCenter', 'rfqResponses.requisitionItem');

        $totals = $rfq->rfqResponses()
            ->where('supplier_id', $request->integer('supplier_id'))
            ->where('status', 'SUBMITTED')
            ->where('not_available', false)
            ->selectRaw('SUM(subtotal) as subtotal, SUM(iva_amount) as iva, SUM(total) as total')
            ->first();

        if (! $totals || (float) ($totals->total ?? 0) <= 0) {
            return back()->with('error', 'El proveedor seleccionado no tiene cotizaciones enviadas para esta RFQ.');
        }

        $diagnostics = $this->buildSupplierDiagnostics($rfq, $request->integer('supplier_id'));

        if (! $diagnostics['allowed']) {
            return back()->with('error', 'No se puede adjudicar esta oferta: '.implode(' ', $diagnostics['reasons']));
        }

        try {
            $summary = DB::transaction(function () use ($request, $rfq, $totals) {
                $summary = QuotationSummary::updateOrCreate(
                    ['rfq_id' => $rfq->id],
                    [
                        'requisition_id' => $rfq->requisition_id,
                        'subtotal' => (float) $totals->subtotal,
                        'iva_amount' => (float) $totals->iva,
                        'total' => (float) $totals->total,
                        'selected_supplier_id' => $request->integer('supplier_id'),
                        'requested_by_user_id' => $rfq->requisition->requested_by,
                        'selected_by_user_id' => Auth::id(),
                        'approval_status' => 'pending',
                        'justification' => $request->string('justification')->toString(),
                        'notes' => $request->input('notes'),
                        'approved_by' => null,
                        'approved_at' => null,
                        'rejected_by' => null,
                        'rejected_at' => null,
                        'rejection_reason' => null,
                    ]
                );

                $summary->loadMissing('requester', 'requisition.requester');

                $resolution = $this->authorizerResolutionService->resolveForSummary($summary);

                $summary->update([
                    'current_approver_user_id' => $resolution['approver_user']->id,
                    'authorizer_role_id' => $resolution['authorizer_role']->id,
                    'effective_authorization_limit' => $resolution['effective_limit'],
                    'approval_chain_snapshot' => $resolution['chain'],
                    'resolution_notes' => $resolution['resolution_notes'],
                ]);

                $this->budgetAllocationService->reserveQuotationSummary($summary);

                $rfq->update(['status' => 'EVALUATED']);
                $rfq->requisition->update(['status' => RequisitionStatus::QUOTED->value]);

                return $summary->fresh(['currentApprover', 'selectedSupplier', 'rfq', 'requisition']);
            });

            $escalated = collect($summary->approval_chain_snapshot)->contains(fn ($step) => ($step['status'] ?? null) !== 'eligible');
            $summary->currentApprover?->notify(new QuotationApprovalRequestNotification($summary, $escalated));
            $this->notifyBuyersQuotationPendingApproval($summary, false);

            return redirect()
                ->route('rfq.index')
                ->with('status', 'Adjudicación registrada y enviada a aprobación de '.($summary->currentApprover?->name ?? 'aprobador asignado').'.');
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

        $diagnostics = $this->buildSupplierDiagnostics($rfq, $request->integer('supplier_id'));

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

            $escalated = collect($summary->approval_chain_snapshot)->contains(fn ($step) => ($step['status'] ?? null) !== 'eligible');
            $summary->currentApprover?->notify(new QuotationApprovalRequestNotification($summary, $escalated));
            $this->notifyBuyersQuotationPendingApproval($summary, true);

            return redirect()
                ->route('rfq.comparison.index', $summary->rfq_id)
                ->with('status', 'Nueva vuelta de adjudicación registrada y enviada a aprobación de '.($summary->currentApprover?->name ?? 'aprobador asignado').'.');
        } catch (Throwable $exception) {
            Log::error("Error en re-adjudicación RFQ {$rfq->id}: {$exception->getMessage()}");

            return back()->with('error', 'No fue posible crear la nueva vuelta de adjudicación: '.$exception->getMessage());
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

    private function buildSupplierDiagnostics(Rfq $rfq, int $supplierId): array
    {
        $responses = $rfq->rfqResponses
            ->where('supplier_id', $supplierId)
            ->where('status', 'SUBMITTED')
            ->where('not_available', false)
            ->values();

        $reasons = [];
        $budgetMessages = [];

        if ($responses->isEmpty()) {
            $reasons[] = 'El proveedor no tiene cotizaciones enviadas para esta RFQ.';
        }

        $quotationDate = $responses->whereNotNull('quotation_date')->min('quotation_date');
        $minValidityDays = $responses->whereNotNull('validity_days')->min('validity_days');

        if ($quotationDate && $minValidityDays) {
            $expiryDate = now()->parse($quotationDate)->addDays((int) $minValidityDays);

            if ($expiryDate->isPast()) {
                $reasons[] = 'La oferta está vencida desde el '.$expiryDate->format('d/m/Y').'.';
            }
        }

        if ($responses->isNotEmpty()) {
            $summary = new QuotationSummary([
                'requisition_id' => $rfq->requisition_id,
                'rfq_id' => $rfq->id,
                'subtotal' => (float) $responses->sum('subtotal'),
                'iva_amount' => (float) $responses->sum('iva_amount'),
                'total' => (float) $responses->sum('total'),
                'selected_supplier_id' => $supplierId,
                'requested_by_user_id' => $rfq->requisition->requested_by,
            ]);

            $summary->setRelation('requisition', $rfq->requisition);
            $summary->setRelation('requester', $rfq->requisition->requester);
            $summary->setRelation('rfq', $rfq);

            try {
                $this->authorizerResolutionService->resolveForSummary($summary);
            } catch (Throwable $exception) {
                $reasons[] = $exception->getMessage();
            }

            try {
                collect($this->budgetAllocationService->buildQuotationSummaryBudgetLines($summary))
                    ->each(function (array $line) use (&$reasons, &$budgetMessages) {
                        $budgetCheck = $this->budgetAllocationService->checkAvailability(
                            (int) $line['cost_center_id'],
                            (int) $line['year'],
                            (int) $line['month'],
                            (int) $line['expense_category_id'],
                            (float) $line['amount'],
                            $line['budget_cedula_id'] ?? null
                        );

                        if (! $budgetCheck['available']) {
                            $budgetMessages[] = $budgetCheck['message'];
                            $reasons[] = $budgetCheck['message'];
                        }
                    });
            } catch (Throwable $exception) {
                $budgetMessages[] = $exception->getMessage();
                $reasons[] = $exception->getMessage();
            }
        }

        return [
            'allowed' => empty($reasons),
            'reasons' => array_values(array_unique($reasons)),
            'budget_blocked' => ! empty($budgetMessages),
            'budget_messages' => array_values(array_unique($budgetMessages)),
        ];
    }

    private function notifyBuyersQuotationPendingApproval(QuotationSummary $summary, bool $reawarded): void
    {
        $summary->loadMissing(['rfq', 'requisition.requester', 'selectedSupplier', 'currentApprover']);

        $heading = $reawarded ? 'Re-adjudicación enviada a aprobación' : 'Adjudicación enviada a aprobación';
        $messagePrefix = $reawarded ? 'La re-adjudicación' : 'La adjudicación';

        $this->buyerNotificationService->notify(
            new BuyerWorkflowNotification(
                type: 'buyer_quotation_pending_approval',
                subject: $heading.' - '.($summary->rfq?->folio ?? 'RFQ'),
                heading: $heading,
                intro: 'la cotización adjudicada fue enviada al flujo de aprobación.',
                details: [
                    'RFQ' => $summary->rfq?->folio ?? 'N/A',
                    'Requisición' => $summary->requisition?->folio ?? 'N/A',
                    'Solicitante' => $summary->requisition?->requester?->name ?? 'N/A',
                    'Proveedor adjudicado' => $summary->selectedSupplier?->company_name ?? 'N/A',
                    'Monto total con IVA' => '$'.number_format((float) $summary->total, 2),
                    'Aprobador actual' => $summary->currentApprover?->name ?? 'N/A',
                ],
                url: route('rfq.comparison.index', $summary->rfq_id),
                buttonLabel: 'Ver comparativo',
                message: $messagePrefix.' de la RFQ '.($summary->rfq?->folio ?? 'N/A').' fue enviada a aprobación.',
                context: [
                    'summary_id' => $summary->id,
                    'rfq_id' => $summary->rfq_id,
                    'rfq_folio' => $summary->rfq?->folio,
                    'requisition_id' => $summary->requisition_id,
                    'requisition_folio' => $summary->requisition?->folio,
                    'reawarded' => $reawarded,
                ],
            ),
        );
    }
}
