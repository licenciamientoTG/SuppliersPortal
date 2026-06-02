<?php

namespace App\Http\Controllers;

use App\Enum\RequisitionStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\QuotationSummary;
use App\Models\RfqResponse;
use App\Notifications\QuotationApprovalApprovedNotification;
use App\Notifications\QuotationApprovalRejectedNotification;
use App\Services\BudgetAllocationService;
use App\Services\QuotationRejectionWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuotationApprovalController extends Controller
{
    public function __construct(
        private BudgetAllocationService $budgetAllocationService,
        private QuotationRejectionWorkflowService $quotationRejectionWorkflowService,
    ) {}

    public function index()
    {
        $pendingApprovals = QuotationSummary::with([
            'requisition',
            'rfq.rfqResponses.requisitionItem',
            'selectedSupplier',
            'authorizerRole',
            'requester',
        ])
            ->pending()
            ->assignedTo(Auth::id())
            ->orderByDesc('created_at')
            ->get();

        return view('quotations.index', compact('pendingApprovals'));
    }

    public function handle(Request $request, QuotationSummary $summary)
    {
        if ($summary->isApproved()) {
            return redirect()
                ->route('approvals.quotations.index')
                ->with('status', 'La adjudicación ya había sido autorizada previamente.');
        }

        if ($summary->isRejected()) {
            return redirect()
                ->route('approvals.quotations.index')
                ->with('warning', 'La adjudicación ya había sido rechazada previamente.');
        }

        abort_unless((int) $summary->current_approver_user_id === (int) Auth::id(), 403);

        $request->validate([
            'status' => 'required|in:approved,rejected',
            'reason' => 'required_if:status,rejected|nullable|string|min:10',
        ]);

        $summary->loadMissing('rfq.rfqResponses.requisitionItem', 'requisition.requester', 'selector', 'selectedSupplier', 'currentApprover');

        try {
            DB::transaction(function () use ($request, $summary) {
                $rfq = $summary->rfq;

                if ($request->status === 'approved') {
                    $summary->approve(Auth::id(), $summary->notes);

                    $rfq->rfqResponses()
                        ->where('supplier_id', $summary->selected_supplier_id)
                        ->update(['status' => 'SELECTED']);

                    $rfq->rfqResponses()
                        ->where('supplier_id', '!=', $summary->selected_supplier_id)
                        ->update(['status' => 'REJECTED']);

                    $rfq->update(['status' => 'COMPLETED']);

                    $purchaseOrder = $this->generatePurchaseOrder($summary);
                    $this->budgetAllocationService->transferQuotationSummaryToPurchaseOrder($summary, $purchaseOrder);

                    $this->quotationRejectionWorkflowService->refreshRequisitionStatus($summary->requisition_id);
                } else {
                    $this->quotationRejectionWorkflowService->handleApprovalRejection(
                        $summary,
                        Auth::id(),
                        $request->string('reason')->toString()
                    );
                }
            });

            if ($request->status === 'approved') {
                $this->notifyApprovalOutcome($summary->fresh(['requisition.requester', 'selector', 'rfq', 'selectedSupplier']), true);

                return redirect()
                    ->route('approvals.quotations.index')
                    ->with('status', 'Adjudicación autorizada y Orden de Compra generada.');
            }

            $this->notifyApprovalOutcome($summary->fresh(['requisition.requester', 'selector', 'rfq', 'selectedSupplier']), false);

            return redirect()
                ->route('approvals.quotations.index')
                ->with('status', 'Adjudicación rechazada. Compras puede re-adjudicar, cancelar la cotización o cancelar la requisición.');
        } catch (\Throwable $exception) {
            Log::error('Error en flujo de aprobación de cotización: '.$exception->getMessage());

            return back()->with('error', 'No fue posible procesar la aprobación: '.$exception->getMessage());
        }
    }

    private function generatePurchaseOrder(QuotationSummary $summary): PurchaseOrder
    {
        $issuedAt = now();

        $winningResponses = RfqResponse::with('requisitionItem')
            ->where('rfq_id', $summary->rfq_id)
            ->where('supplier_id', $summary->selected_supplier_id)
            ->get();

        // A regular purchase order enters its operational cycle immediately
        // after the quotation approval, so it must be issued right away.
        $purchaseOrder = PurchaseOrder::create([
            'folio' => 'OC-'.now()->format('Y').'-'.str_pad((string) $summary->id, 5, '0', STR_PAD_LEFT),
            'requisition_id' => $summary->requisition_id,
            'supplier_id' => $summary->selected_supplier_id,
            'quotation_summary_id' => $summary->id,
            'receiving_location_id' => $summary->requisition->receiving_location_id,
            'subtotal' => $summary->subtotal,
            'iva_amount' => $summary->iva_amount,
            'total' => $summary->total,
            'payment_terms' => $winningResponses->first()->payment_terms ?? 'Crédito',
            'estimated_delivery_days' => $winningResponses->max('delivery_days') ?? 0,
            'status' => 'ISSUED',
            'approved_at' => $issuedAt,
            'issued_at' => $issuedAt,
            'created_by' => Auth::id(),
        ]);

        foreach ($winningResponses as $response) {
            PurchaseOrderItem::create([
                'purchase_order_id' => $purchaseOrder->id,
                'requisition_item_id' => $response->requisition_item_id,
                'description' => $response->requisitionItem->description ?? 'Sin descripción',
                'quantity' => $response->quantity,
                'unit_price' => $response->unit_price,
                'subtotal' => $response->subtotal,
                'iva_amount' => $response->iva_amount,
                'total' => $response->total,
            ]);
        }

        return $purchaseOrder;
    }

    private function notifyApprovalOutcome(QuotationSummary $summary, bool $approved): void
    {
        $notification = $approved
            ? new QuotationApprovalApprovedNotification($summary)
            : new QuotationApprovalRejectedNotification($summary);

        collect([$summary->selector, $summary->requisition?->requester])
            ->filter()
            ->unique('id')
            ->each
            ->notify($notification);
    }
}
