<?php

namespace App\Services;

use App\Models\BudgetCommitment;
use App\Models\DirectPurchaseOrder;
use App\Models\QuotationSummary;
use App\Models\QuotationSummaryItem;
use Illuminate\Support\Collection;
use Throwable;

class BudgetImpactSnapshotService
{
    public function __construct(private BudgetAllocationService $budgetAllocationService) {}

    public function forQuotationSummary(QuotationSummary $summary): array
    {
        try {
            $summary->loadMissing([
                'items.requisitionItem.costCenter',
                'items.requisitionItem.expenseCategory',
                'items.requisitionItem.budgetCedula',
                'items.rfqResponse',
                'selectedSupplier',
                'rfq',
                'requisition',
            ]);

            $lines = collect($this->budgetAllocationService->buildQuotationSummaryBudgetLines($summary))
                ->map(function (array $line) use ($summary) {
                    $summaryItem = $summary->items->first(function (QuotationSummaryItem $item) use ($line) {
                        $requisitionItem = $item->requisitionItem;

                        return (int) $requisitionItem?->cost_center_id === (int) $line['cost_center_id']
                            && (int) $requisitionItem?->expense_category_id === (int) $line['expense_category_id']
                            && (int) ($requisitionItem?->budget_cedula_id ?? 0) === (int) ($line['budget_cedula_id'] ?? 0);
                    });

                    return $this->snapshotLine($line, $summaryItem?->requisitionItem);
                })
                ->values();

            return $this->build(
                $lines,
                fn (BudgetCommitment $commitment) => (int) $commitment->quotation_summary_id === (int) $summary->id,
                function (float $amount) use ($summary): array {
                    return [
                        'type' => 'Cotización en revisión',
                        'folio' => trim(collect([$summary->rfq?->folio, $summary->requisition?->folio])->filter()->implode(' · ')) ?: 'Requisición actual',
                        'supplier' => $summary->selectedSupplier?->company_name ?? 'Proveedor seleccionado',
                        'committed_at' => $summary->budget_reserved_at?->format('d/m/Y') ?? '-',
                        'amount' => $amount,
                        'detail' => $summary->requisition?->description ?: 'Sin título registrado para la requisición.',
                    ];
                },
                (bool) ($summary->isPending() && $summary->budget_reserved_at && ! $summary->budget_released_at)
            );
        } catch (Throwable $exception) {
            return $this->emptySnapshot($exception->getMessage());
        }
    }

    public function forDirectPurchaseOrder(DirectPurchaseOrder $order): array
    {
        try {
            $order->loadMissing([
                'items.costCenter',
                'items.expenseCategory',
                'items.budgetCedula',
                'supplier',
            ]);

            $lines = collect($this->budgetAllocationService->buildDirectPurchaseOrderBudgetLines($order))
                ->map(function (array $line) use ($order) {
                    $item = $order->items->first(fn ($item) => (int) $item->cost_center_id === (int) $line['cost_center_id']
                        && (int) $item->expense_category_id === (int) $line['expense_category_id']
                        && (int) ($item->budget_cedula_id ?? 0) === (int) ($line['budget_cedula_id'] ?? 0));

                    return $this->snapshotLine($line, $item);
                })
                ->values();

            return $this->build(
                $lines,
                fn (BudgetCommitment $commitment) => (int) $commitment->direct_purchase_order_id === (int) $order->id,
                function (float $amount) use ($order): array {
                    return [
                        'type' => 'OCD en revisión',
                        'folio' => $order->folio ?? 'OCD actual',
                        'supplier' => $order->supplier?->company_name ?? 'Proveedor seleccionado',
                        'committed_at' => $order->budget_reserved_at?->format('d/m/Y') ?? '-',
                        'amount' => $amount,
                        'detail' => $order->justification ?: 'Compra directa sin justificación registrada.',
                    ];
                },
                (bool) ($order->budget_reserved_at && ! $order->budget_released_at)
            );
        } catch (Throwable $exception) {
            return $this->emptySnapshot($exception->getMessage());
        }
    }

    private function snapshotLine(array $line, mixed $item): array
    {
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
            'cost_center' => trim(collect([$item?->costCenter?->code, $item?->costCenter?->name])->filter()->implode(' - ')),
            'expense_category' => $item?->expenseCategory?->name ?? 'Sin cuenta',
            'budget_cedula' => $item?->budgetCedula?->name ?? 'Sin subcuenta',
            'requested_amount' => '$'.number_format((float) $line['amount'], 2),
            'assigned_amount' => array_key_exists('assigned_amount', $budgetCheck) ? '$'.number_format((float) $budgetCheck['assigned_amount'], 2) : 'Consumo libre',
            'committed_amount' => array_key_exists('committed_amount', $budgetCheck) ? '$'.number_format((float) $budgetCheck['committed_amount'], 2) : '-',
            'available_amount' => array_key_exists('available_amount', $budgetCheck) ? '$'.number_format((float) $budgetCheck['available_amount'], 2) : 'No limitado',
            'is_available' => (bool) ($budgetCheck['available'] ?? false),
            'message' => $budgetCheck['message'] ?? null,
            'assigned_raw' => isset($budgetCheck['assigned_amount']) ? (float) $budgetCheck['assigned_amount'] : null,
            'committed_raw' => isset($budgetCheck['committed_amount']) ? (float) $budgetCheck['committed_amount'] : null,
            'available_raw' => isset($budgetCheck['available_amount']) ? (float) $budgetCheck['available_amount'] : null,
            'requested_raw' => (float) $line['amount'],
            'cost_center_id' => (int) $line['cost_center_id'],
            'expense_category_id' => (int) $line['expense_category_id'],
            'budget_cedula_id' => $line['budget_cedula_id'] ? (int) $line['budget_cedula_id'] : null,
        ];
    }

    private function build(Collection $lines, callable $isCurrent, callable $currentComponent, bool $hasActiveReservation): array
    {
        $commitments = BudgetCommitment::query()
            ->with([
                'purchaseOrder.supplier',
                'directPurchaseOrder.supplier',
                'quotationSummary.selectedSupplier',
                'quotationSummary.rfq',
                'quotationSummary.requisition',
            ])
            ->where('status', 'COMMITTED')
            ->whereIn('cost_center_id', $lines->pluck('cost_center_id')->unique())
            ->whereIn('expense_category_id', $lines->pluck('expense_category_id')->unique())
            ->whereIn('application_month', $lines->pluck('application_month')->unique())
            ->get();

        return [
            'lines' => $lines->map(function (array $line) use ($commitments, $isCurrent, $currentComponent, $hasActiveReservation) {
                $matchingCommitments = $commitments->filter(fn (BudgetCommitment $commitment) => (int) $commitment->cost_center_id === $line['cost_center_id']
                    && (int) $commitment->expense_category_id === $line['expense_category_id']
                    && (int) ($commitment->budget_cedula_id ?? 0) === (int) ($line['budget_cedula_id'] ?? 0)
                    && $commitment->application_month === $line['application_month']);

                $components = $matchingCommitments->map(function (BudgetCommitment $commitment) use ($isCurrent) {
                    $order = $commitment->purchaseOrder ?? $commitment->directPurchaseOrder ?? $commitment->quotationSummary;
                    $supplier = $commitment->purchaseOrder?->supplier
                        ?? $commitment->directPurchaseOrder?->supplier
                        ?? $commitment->quotationSummary?->selectedSupplier;

                    return [
                        'type' => $commitment->getOrderType(),
                        'folio' => $commitment->getOrderFolio() ?? $order?->folio ?? 'Sin folio',
                        'supplier' => $supplier?->company_name ?? 'Sin proveedor',
                        'committed_at' => $commitment->committed_at?->format('d/m/Y') ?? '-',
                        'amount' => '$'.number_format((float) $commitment->committed_amount, 2),
                        'detail' => $commitment->quotationSummary?->requisition?->description,
                        'raw_amount' => (float) $commitment->committed_amount,
                        'is_untraced' => false,
                        'is_current' => $isCurrent($commitment),
                    ];
                })->values();

                $untracedAmount = (float) ($line['committed_raw'] ?? 0) - $components->sum('raw_amount');
                $currentIsTraced = $matchingCommitments->contains($isCurrent);

                if ($hasActiveReservation && ! $currentIsTraced && $untracedAmount > 0.000001) {
                    $currentReservation = min((float) $line['requested_raw'], $untracedAmount);
                    $component = $currentComponent($currentReservation);
                    $components->prepend([
                        ...$component,
                        'amount' => '$'.number_format($currentReservation, 2),
                        'raw_amount' => $currentReservation,
                        'is_untraced' => false,
                        'is_current' => true,
                    ]);
                    $untracedAmount -= $currentReservation;
                }

                if ($untracedAmount > 0.000001) {
                    $components->push([
                        'type' => 'Histórico',
                        'folio' => 'Saldo comprometido sin traza',
                        'supplier' => 'Este importe existe en la distribución presupuestal, pero no tiene una orden registrada en la bitácora de compromisos.',
                        'committed_at' => '-',
                        'amount' => '$'.number_format($untracedAmount, 2),
                        'detail' => null,
                        'raw_amount' => $untracedAmount,
                        'is_untraced' => true,
                        'is_current' => false,
                    ]);
                }

                return collect($line)->except([
                    'assigned_raw', 'committed_raw', 'available_raw', 'requested_raw',
                    'cost_center_id', 'expense_category_id', 'budget_cedula_id',
                ])->put('committed_components', $components
                    ->map(fn (array $component) => collect($component)->except('raw_amount')->all())
                    ->all())->all();
            })->all(),
            'assigned_total' => '$'.number_format((float) $lines->sum(fn (array $line) => $line['assigned_raw'] ?? 0), 2),
            'committed_total' => '$'.number_format((float) $lines->sum(fn (array $line) => $line['committed_raw'] ?? 0), 2),
            'available_total' => '$'.number_format((float) $lines->sum(fn (array $line) => $line['available_raw'] ?? 0), 2),
            'requested_total' => '$'.number_format((float) $lines->sum(fn (array $line) => $line['requested_raw'] ?? 0), 2),
            'has_budget_totals' => $lines->contains(fn (array $line) => $line['assigned_raw'] !== null),
            'error' => null,
        ];
    }

    private function emptySnapshot(string $error): array
    {
        return [
            'lines' => [],
            'assigned_total' => '-',
            'committed_total' => '-',
            'available_total' => '-',
            'requested_total' => '-',
            'has_budget_totals' => false,
            'error' => $error,
        ];
    }
}
