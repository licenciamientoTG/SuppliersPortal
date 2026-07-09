<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\QuotationSummary;
use App\Models\QuotationSummaryItem;
use App\Notifications\QuotationApprovalApprovedNotification;
use App\Notifications\QuotationApprovalRejectedNotification;
use App\Services\BuyerNotificationService;
use App\Services\BudgetAllocationService;
use App\Services\QuotationRejectionWorkflowService;
use App\Services\QuotationSummaryItemService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class QuotationApprovalController extends Controller
{
    public function __construct(
        private BudgetAllocationService $budgetAllocationService,
        private BuyerNotificationService $buyerNotificationService,
        private QuotationRejectionWorkflowService $quotationRejectionWorkflowService,
        private QuotationSummaryItemService $quotationSummaryItemService,
    ) {}

    public function index()
    {
        $query = QuotationSummary::with([
            'requisition',
            'rfq.rfqResponses.requisitionItem.costCenter',
            'rfq.rfqResponses.requisitionItem.expenseCategory',
            'rfq.rfqResponses.requisitionItem.budgetCedula',
            'items.requisitionItem.costCenter',
            'items.requisitionItem.expenseCategory',
            'items.requisitionItem.budgetCedula',
            'items.rfqResponse',
            'selectedSupplier',
            'authorizerRole',
            'requester',
        ])
            ->pending();

        // superadmin visualiza todas las selecciones pendientes, sin importar a quien esten asignadas.
        if (! Auth::user()->hasRole('superadmin')) {
            $query->assignedTo(Auth::id());
        }

        $pendingApprovals = $query
            ->orderByDesc('created_at')
            ->get()
            ->each(function (QuotationSummary $summary) {
                $this->quotationSummaryItemService->ensureItems($summary);
                $summary->load(
                    'items.requisitionItem.costCenter',
                    'items.requisitionItem.expenseCategory',
                    'items.requisitionItem.budgetCedula',
                    'items.rfqResponse'
                );
                $summary->setAttribute('budget_snapshot', $this->buildBudgetSnapshot($summary));
            });

        return view('quotations.index', compact('pendingApprovals'));
    }

    private function buildBudgetSnapshot(QuotationSummary $summary): array
    {
        try {
            $lines = collect($this->budgetAllocationService->buildQuotationSummaryBudgetLines($summary))
                ->map(function (array $line) use ($summary) {
                    $matchingSummaryItem = $summary->items->first(function (QuotationSummaryItem $summaryItem) use ($line) {
                        $item = $summaryItem->requisitionItem;

                        return (int) $item?->cost_center_id === (int) $line['cost_center_id']
                            && (int) $item?->expense_category_id === (int) $line['expense_category_id']
                            && (int) ($item?->budget_cedula_id ?? 0) === (int) ($line['budget_cedula_id'] ?? 0);
                    });

                    $item = $matchingSummaryItem?->requisitionItem;
                    $budgetCheck = $this->budgetAllocationService->checkAvailability(
                        (int) $line['cost_center_id'],
                        (int) $line['year'],
                        (int) $line['month'],
                        (int) $line['expense_category_id'],
                        (float) $line['amount'],
                        $line['budget_cedula_id'] ?? null
                    );

                    return [
                        'application_month' => $line['application_month'],
                        'cost_center' => trim(collect([
                            $item?->costCenter?->code,
                            $item?->costCenter?->name,
                        ])->filter()->implode(' - ')),
                        'expense_category' => $item?->expenseCategory?->name ?? 'Sin cuenta',
                        'budget_cedula' => $item?->budgetCedula?->name ?? 'Sin subcuenta',
                        'requested_amount' => '$' . number_format((float) $line['amount'], 2),
                        'assigned_amount' => array_key_exists('assigned_amount', $budgetCheck)
                            ? '$' . number_format((float) $budgetCheck['assigned_amount'], 2)
                            : 'Consumo libre',
                        'committed_amount' => array_key_exists('committed_amount', $budgetCheck)
                            ? '$' . number_format((float) $budgetCheck['committed_amount'], 2)
                            : '-',
                        'available_amount' => array_key_exists('available_amount', $budgetCheck)
                            ? '$' . number_format((float) $budgetCheck['available_amount'], 2)
                            : 'No limitado',
                        'is_available' => (bool) ($budgetCheck['available'] ?? false),
                        'message' => $budgetCheck['message'] ?? null,
                        'assigned_raw' => isset($budgetCheck['assigned_amount']) ? (float) $budgetCheck['assigned_amount'] : null,
                        'committed_raw' => isset($budgetCheck['committed_amount']) ? (float) $budgetCheck['committed_amount'] : null,
                        'available_raw' => isset($budgetCheck['available_amount']) ? (float) $budgetCheck['available_amount'] : null,
                        'requested_raw' => (float) $line['amount'],
                    ];
                })
                ->values();

            return [
                'lines' => $lines->map(fn (array $line) => collect($line)->except([
                    'assigned_raw',
                    'committed_raw',
                    'available_raw',
                    'requested_raw',
                ])->all())->all(),
                'assigned_total' => '$' . number_format((float) $lines->sum(fn (array $line) => $line['assigned_raw'] ?? 0), 2),
                'committed_total' => '$' . number_format((float) $lines->sum(fn (array $line) => $line['committed_raw'] ?? 0), 2),
                'available_total' => '$' . number_format((float) $lines->sum(fn (array $line) => $line['available_raw'] ?? 0), 2),
                'requested_total' => '$' . number_format((float) $lines->sum(fn (array $line) => $line['requested_raw'] ?? 0), 2),
                'has_budget_totals' => $lines->contains(fn (array $line) => $line['assigned_raw'] !== null),
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return [
                'lines' => [],
                'assigned_total' => '-',
                'committed_total' => '-',
                'available_total' => '-',
                'requested_total' => '-',
                'has_budget_totals' => false,
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function handle(Request $request, QuotationSummary $summary)
    {
        if ($summary->isApproved()) {
            return redirect()
                ->route('approvals.quotations.index')
                ->with('status', 'La seleccion ya habia sido autorizada previamente.');
        }

        if ($summary->isRejected()) {
            return redirect()
                ->route('approvals.quotations.index')
                ->with('warning', 'La seleccion ya habia sido rechazada previamente.');
        }

        // superadmin puede autorizar/rechazar cualquier seleccion pendiente, no solo las asignadas a el.
        abort_unless(
            Auth::user()->hasRole('superadmin') || (int) $summary->current_approver_user_id === (int) Auth::id(),
            403
        );

        if ($request->input('status') === 'approved' && ! $request->has('items')) {
            $items = $this->quotationSummaryItemService->ensureItems($summary)
                ->map(fn (QuotationSummaryItem $item) => [
                    'id' => $item->id,
                    'approved_quantity' => (float) $item->approved_quantity,
                    'approver_notes' => $item->approver_notes,
                ])
                ->values()
                ->all();

            $request->merge(['items' => $items]);
        }

        $request->validate([
            'status' => 'required|in:approved,rejected',
            'reason' => 'required_if:status,rejected|nullable|string|min:10',
            'items' => 'required_if:status,approved|array',
            'items.*.id' => 'required_if:status,approved|integer|exists:quotation_summary_items,id',
            'items.*.approved_quantity' => 'required_if:status,approved|numeric|min:0',
            'items.*.approver_notes' => 'nullable|string|max:1000',
        ]);

        $summary->loadMissing(
            'rfq.rfqResponses.requisitionItem',
            'requisition.requester',
            'selector',
            'selectedSupplier',
            'currentApprover',
            'items.requisitionItem',
            'items.rfqResponse'
        );

        try {
            DB::transaction(function () use ($request, $summary) {
                $rfq = $summary->rfq;

                if ($request->status === 'approved') {
                    $this->quotationSummaryItemService->applyApproval($summary, $request->input('items', []));
                    $summary->load('items.requisitionItem', 'items.rfqResponse');
                    $summary->syncTotalsFromItems();

                    if ((float) $summary->total <= 0) {
                        $reason = $this->buildZeroApprovalReason($summary);
                        $this->quotationRejectionWorkflowService->handleApprovalRejection($summary, Auth::id(), $reason);

                        return;
                    }

                    if (
                        $summary->effective_authorization_limit !== null
                        && (float) $summary->total > (float) $summary->effective_authorization_limit + 0.000001
                    ) {
                        throw ValidationException::withMessages([
                            'items' => 'El total aprobado ($'.number_format((float) $summary->total, 2).') excede tu limite efectivo ($'.number_format((float) $summary->effective_authorization_limit, 2).'). Reduce cantidades o solicita reasignacion.',
                        ]);
                    }

                    $this->budgetAllocationService->releaseQuotationSummary($summary);
                    $summary->save();
                    $this->budgetAllocationService->reserveQuotationSummary($summary);
                    $summary->approve(Auth::id(), $summary->notes);
                    $summary->update([
                        'approval_status' => $summary->items->contains(fn (QuotationSummaryItem $item) => $item->approval_status === 'partially_approved')
                            ? 'partially_approved'
                            : 'approved',
                    ]);

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

            $summary->refresh();

            if ($request->status === 'approved' && ! $summary->isRejected()) {
                $this->notifyApprovalOutcome($summary->fresh(['requisition.requester', 'selector', 'rfq', 'selectedSupplier']), true);

                return redirect()
                    ->route('approvals.quotations.index')
                    ->with('status', $summary->isPartiallyApproved()
                        ? 'Seleccion autorizada parcialmente y Orden de Compra generada por cantidades finales.'
                        : 'Seleccion autorizada y Orden de Compra generada.');
            }

            $this->notifyApprovalOutcome($summary->fresh(['requisition.requester', 'selector', 'rfq', 'selectedSupplier']), false);

            return redirect()
                ->route('approvals.quotations.index')
                ->with('status', 'Seleccion rechazada. Compras puede re-seleccionar, cancelar la cotizacion o cancelar la requisicion.');
        } catch (Throwable $exception) {
            Log::error('Error en flujo de aprobacion de cotizacion: '.$exception->getMessage());

            return back()->with('error', 'No fue posible procesar la aprobacion: '.$exception->getMessage());
        }
    }

    private function generatePurchaseOrder(QuotationSummary $summary): PurchaseOrder
    {
        $issuedAt = now();
        $approvedItems = $summary->items()
            ->with(['requisitionItem', 'rfqResponse'])
            ->where('approved_quantity', '>', 0)
            ->get();

        $purchaseOrder = PurchaseOrder::create([
            'folio' => 'OC-'.now()->format('Y').'-'.str_pad((string) $summary->id, 5, '0', STR_PAD_LEFT),
            'requisition_id' => $summary->requisition_id,
            'supplier_id' => $summary->selected_supplier_id,
            'quotation_summary_id' => $summary->id,
            'receiving_location_id' => $summary->requisition->receiving_location_id,
            'subtotal' => $summary->subtotal,
            'iva_amount' => $summary->iva_amount,
            'total' => $summary->total,
            'payment_terms' => $approvedItems->first()?->rfqResponse?->payment_terms ?? 'Credito',
            'estimated_delivery_days' => $approvedItems->max(fn (QuotationSummaryItem $item) => (int) ($item->rfqResponse?->delivery_days ?? 0)),
            'status' => 'ISSUED',
            'approved_at' => $issuedAt,
            'issued_at' => $issuedAt,
            'created_by' => Auth::id(),
        ]);

        foreach ($approvedItems as $item) {
            PurchaseOrderItem::create([
                'purchase_order_id' => $purchaseOrder->id,
                'requisition_item_id' => $item->requisition_item_id,
                'description' => $item->requisitionItem->description ?? 'Sin descripcion',
                'quantity' => $item->approved_quantity,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
                'iva_amount' => $item->iva_amount,
                'total' => $item->total,
            ]);
        }

        return $purchaseOrder;
    }

    private function buildZeroApprovalReason(QuotationSummary $summary): string
    {
        $reasons = $summary->items
            ->map(fn (QuotationSummaryItem $item) => 'Partida '.$item->requisition_item_id.': '.($item->approver_notes ?: 'Sin motivo capturado'))
            ->implode(' | ');

        return 'Todas las partidas fueron autorizadas con cantidad 0. '.$reasons;
    }

    private function notifyApprovalOutcome(QuotationSummary $summary, bool $approved): void
    {
        $notification = $approved
            ? new QuotationApprovalApprovedNotification($summary)
            : new QuotationApprovalRejectedNotification($summary);

        $this->buyerNotificationService->notify(
            $notification,
            [$summary->selector, $summary->requisition?->requester]
        );
    }
}
