<?php

namespace App\Services;

use App\Models\AnnualBudget;
use App\Models\BudgetCommitment;
use App\Models\BudgetMonthlyDistribution;
use App\Models\CostCenter;
use App\Models\DirectPurchaseOrder;
use App\Models\PurchaseOrder;
use App\Models\QuotationSummary;
use App\Models\QuotationSummaryItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BudgetAllocationService
{
    public function checkAvailability(
        int $costCenterId,
        int $year,
        int $month,
        int $categoryId,
        float $requiredAmount,
        ?int $budgetCedulaId = null
    ): array {
        $costCenter = CostCenter::find($costCenterId);

        if (! $costCenter) {
            return ['available' => false, 'message' => 'Centro de costo no encontrado.'];
        }

        if ($costCenter->isFreeConsumption()) {
            return ['available' => true, 'message' => 'Centro de costo de consumo libre.'];
        }

        if ($budgetCedulaId) {
            $distribution = $this->resolveDistributionByCedula(
                $costCenterId,
                $year,
                $month,
                $budgetCedulaId,
                $categoryId
            );

            $available = (float) $distribution->getAvailableAmount();

            return [
                'available' => $available + 0.000001 >= $requiredAmount,
                'message' => $available + 0.000001 >= $requiredAmount
                    ? 'Presupuesto disponible.'
                    : sprintf(
                        'Presupuesto insuficiente en la subcategoría. Disponible: $%s, requerido: $%s.',
                        number_format($available, 2),
                        number_format($requiredAmount, 2)
                    ),
                'available_amount' => $available,
                'assigned_amount' => (float) $distribution->assigned_amount,
                'consumed_amount' => (float) $distribution->consumed_amount,
                'committed_amount' => (float) $distribution->committed_amount,
            ];
        }

        $distributions = $this->resolveDistributionsForCategory($costCenterId, $year, $month, $categoryId);
        $available = (float) $distributions->sum(fn (BudgetMonthlyDistribution $distribution) => $distribution->getAvailableAmount());

        return [
            'available' => $available + 0.000001 >= $requiredAmount,
            'message' => $available + 0.000001 >= $requiredAmount
                ? 'Presupuesto disponible.'
                : sprintf(
                    'Presupuesto insuficiente. Disponible: $%s, requerido: $%s.',
                    number_format($available, 2),
                    number_format($requiredAmount, 2)
                ),
            'available_amount' => $available,
            'assigned_amount' => (float) $distributions->sum('assigned_amount'),
            'consumed_amount' => (float) $distributions->sum('consumed_amount'),
            'committed_amount' => (float) $distributions->sum('committed_amount'),
        ];
    }

    public function commitOrder(Model $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($this->getOrderBudgetLines($order) as $line) {
                $this->commitLine($order, $line);
            }
        });
    }

    public function syncCommitmentTrace(Model $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($this->getOrderBudgetLines($order) as $line) {
                $commitments = $this->findCommitmentsForLine($order, $line);

                foreach ($commitments as $commitment) {
                    if ($commitment->status === 'RECEIVED') {
                        continue;
                    }

                    $commitment->status = 'COMMITTED';
                    $commitment->committed_at = $commitment->committed_at ?? now();
                    $commitment->released_at = null;
                    $commitment->received_at = null;
                    $commitment->save();
                }
            }
        });
    }

    public function releaseOrder(Model $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($this->getOrderBudgetLines($order) as $line) {
                $this->releaseLine($order, $line);
            }
        });
    }

    public function releaseTrace(Model $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($this->getOrderBudgetLines($order) as $line) {
                $commitments = $this->findCommitmentsForLine($order, $line)
                    ->where('status', 'COMMITTED');

                foreach ($commitments as $commitment) {
                    $commitment->update([
                        'status' => 'RELEASED',
                        'released_at' => now(),
                    ]);
                }
            }
        });
    }

    public function consumeOrder(Model $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($this->getOrderBudgetLines($order) as $line) {
                $this->consumeLine($order, $line);
            }
        });
    }

    public function reserveQuotationSummary(QuotationSummary $summary): void
    {
        DB::transaction(function () use ($summary) {
            foreach ($this->buildQuotationSummaryBudgetLines($summary) as $line) {
                $this->commitLine($summary, $line);
            }

            $summary->forceFill([
                'budget_reserved_at' => now(),
                'budget_released_at' => null,
            ])->save();
        });
    }

    public function reserveDirectPurchaseOrder(DirectPurchaseOrder $directPurchaseOrder): void
    {
        DB::transaction(function () use ($directPurchaseOrder) {
            foreach ($this->getOrderBudgetLines($directPurchaseOrder) as $line) {
                $this->commitLine($directPurchaseOrder, $line);
            }

            $directPurchaseOrder->forceFill([
                'budget_reserved_at' => now(),
                'budget_released_at' => null,
            ])->save();
        });
    }

    public function releaseQuotationSummary(QuotationSummary $summary): void
    {
        DB::transaction(function () use ($summary) {
            $this->releaseCommittedQuotationSummaryCommitments($summary);

            $summary->forceFill([
                'budget_released_at' => now(),
            ])->save();
        });
    }

    public function releaseDirectPurchaseOrder(DirectPurchaseOrder $directPurchaseOrder): void
    {
        DB::transaction(function () use ($directPurchaseOrder) {
            foreach ($this->getOrderBudgetLines($directPurchaseOrder) as $line) {
                $this->releaseLine($directPurchaseOrder, $line);
            }

            $directPurchaseOrder->forceFill([
                'budget_released_at' => now(),
            ])->save();
        });
    }

    public function transferQuotationSummaryToPurchaseOrder(QuotationSummary $summary, PurchaseOrder $purchaseOrder): void
    {
        DB::transaction(function () use ($summary, $purchaseOrder) {
            BudgetCommitment::query()
                ->where('quotation_summary_id', $summary->id)
                ->where('status', 'COMMITTED')
                ->get()
                ->each(function (BudgetCommitment $commitment) use ($purchaseOrder) {
                    $commitment->update([
                        'quotation_summary_id' => null,
                        'purchase_order_id' => $purchaseOrder->id,
                    ]);
                });
        });
    }

    public function buildQuotationSummaryBudgetLines(QuotationSummary $summary): array
    {
        $summary->loadMissing('items.requisitionItem.costCenter', 'rfq.rfqResponses.requisitionItem.costCenter');

        if ($summary->items->isNotEmpty()) {
            return $summary->items
                ->filter(fn (QuotationSummaryItem $item) => (float) $item->approved_quantity > 0)
                ->map(function (QuotationSummaryItem $item) {
                    $requisitionItem = $item->requisitionItem;
                    $response = $item->rfqResponse;

                    if (! $requisitionItem?->expense_category_id || ! $requisitionItem?->budget_cedula_id) {
                        throw new RuntimeException("La partida {$item->requisition_item_id} no tiene cuenta y subcuenta presupuestal completas.");
                    }

                    if (! $response?->quotation_date || $response->delivery_days === null) {
                        throw new RuntimeException("La partida {$item->requisition_item_id} del proveedor seleccionado no tiene dÃ­as de entrega capturados.");
                    }

                    $applicationMonth = Carbon::parse($response->quotation_date)
                        ->addDays((int) $response->delivery_days)
                        ->format('Y-m');

                    return $this->expandDistributionLine([
                        'cost_center_id' => (int) $requisitionItem->cost_center_id,
                        'expense_category_id' => (int) $requisitionItem->expense_category_id,
                        'budget_cedula_id' => (int) $requisitionItem->budget_cedula_id,
                        'amount' => (float) $item->total,
                        'year' => (int) substr($applicationMonth, 0, 4),
                        'month' => (int) substr($applicationMonth, 5, 2),
                        'application_month' => $applicationMonth,
                        'budget_type' => $requisitionItem->costCenter?->budget_type ?? 'ANNUAL',
                    ]);
                })
                ->flatten(1)
                ->groupBy(fn (array $line) => implode('|', [
                    $line['cost_center_id'],
                    $line['expense_category_id'],
                    $line['budget_cedula_id'],
                    $line['application_month'],
                ]))
                ->map(function (Collection $lines) {
                    $first = $lines->first();
                    $first['amount'] = (float) $lines->sum('amount');

                    return $first;
                })
                ->values()
                ->all();
        }

        return $summary->rfq->rfqResponses
            ->where('supplier_id', $summary->selected_supplier_id)
            ->where('status', 'SUBMITTED')
            ->where('not_available', false)
            ->map(function ($response) {
                $requisitionItem = $response->requisitionItem;

                if (! $requisitionItem?->expense_category_id || ! $requisitionItem?->budget_cedula_id) {
                    throw new RuntimeException("La partida {$response->requisition_item_id} no tiene cuenta y subcuenta presupuestal completas.");
                }

                if (! $response->quotation_date || $response->delivery_days === null) {
                    throw new RuntimeException("La partida {$response->requisition_item_id} del proveedor seleccionado no tiene días de entrega capturados.");
                }

                $applicationMonth = Carbon::parse($response->quotation_date)
                    ->addDays((int) $response->delivery_days)
                    ->format('Y-m');

                return $this->expandDistributionLine([
                    'cost_center_id' => (int) $requisitionItem->cost_center_id,
                    'expense_category_id' => (int) $requisitionItem->expense_category_id,
                    'budget_cedula_id' => (int) $requisitionItem->budget_cedula_id,
                    'amount' => (float) $response->total,
                    'year' => (int) substr($applicationMonth, 0, 4),
                    'month' => (int) substr($applicationMonth, 5, 2),
                    'application_month' => $applicationMonth,
                    'budget_type' => $requisitionItem->costCenter?->budget_type ?? 'ANNUAL',
                ]);
            })
            ->flatten(1)
            ->groupBy(fn (array $line) => implode('|', [
                $line['cost_center_id'],
                $line['expense_category_id'],
                $line['budget_cedula_id'],
                $line['application_month'],
            ]))
            ->map(function (Collection $lines) {
                $first = $lines->first();
                $first['amount'] = (float) $lines->sum('amount');

                return $first;
            })
            ->values()
            ->all();
    }

    public function buildDirectPurchaseOrderBudgetLines(DirectPurchaseOrder $directPurchaseOrder): array
    {
        return $this->getOrderBudgetLines($directPurchaseOrder);
    }

    private function commitLine(Model $order, array $line): void
    {
        $existing = $this->findCommitmentsForLine($order, $line)
            ->where('status', '!=', 'RELEASED');

        if ($existing->isNotEmpty()) {
            return;
        }

        if ($line['budget_type'] !== 'ANNUAL') {
            $commitment = new BudgetCommitment;
            $this->fillCommitment($commitment, $order, $line, 'COMMITTED', $line['budget_cedula_id'] ?? null, $line['amount']);
            $commitment->committed_at = now();
            $commitment->save();

            return;
        }

        if (! empty($line['budget_cedula_id'])) {
            $distribution = $this->resolveDistributionByCedula(
                $line['cost_center_id'],
                $line['year'],
                $line['month'],
                (int) $line['budget_cedula_id'],
                (int) $line['expense_category_id']
            );

            if (! $distribution->commitAmount((float) $line['amount'])) {
                throw new RuntimeException(
                    "No se pudo comprometer presupuesto para la cédula {$line['budget_cedula_id']}."
                );
            }

            $commitment = new BudgetCommitment;
            $this->fillCommitment(
                $commitment,
                $order,
                $line,
                'COMMITTED',
                (int) $line['budget_cedula_id'],
                (float) $line['amount']
            );
            $commitment->committed_at = now();
            $commitment->save();

            return;
        }

        $distributions = $this->resolveDistributionsForCategory(
            $line['cost_center_id'],
            $line['year'],
            $line['month'],
            $line['expense_category_id']
        );

        $allocations = $this->allocateAmountAcrossDistributions($distributions, (float) $line['amount']);

        foreach ($allocations as $allocation) {
            /** @var BudgetMonthlyDistribution $distribution */
            $distribution = $allocation['distribution'];
            $amount = $allocation['amount'];

            if (! $distribution->commitAmount($amount)) {
                throw new RuntimeException(
                    "No se pudo comprometer presupuesto para la cédula {$distribution->budget_cedula_id}."
                );
            }

            $commitment = new BudgetCommitment;
            $this->fillCommitment(
                $commitment,
                $order,
                $line,
                'COMMITTED',
                $distribution->budget_cedula_id,
                $amount
            );
            $commitment->committed_at = now();
            $commitment->save();
        }
    }

    private function releaseLine(Model $order, array $line): void
    {
        $commitments = $this->findCommitmentsForLine($order, $line)
            ->where('status', 'COMMITTED');

        foreach ($commitments as $commitment) {
            if ($line['budget_type'] === 'ANNUAL' && $commitment->budget_cedula_id) {
                $distribution = $this->resolveDistributionByCedula(
                    $line['cost_center_id'],
                    $line['year'],
                    $line['month'],
                    (int) $commitment->budget_cedula_id,
                    (int) $line['expense_category_id']
                );

                if (! $distribution->releaseCommitment((float) $commitment->committed_amount)) {
                    throw new RuntimeException(
                        "No se pudo liberar presupuesto para la cédula {$commitment->budget_cedula_id}."
                    );
                }
            }

            $commitment->update([
                'status' => 'RELEASED',
                'released_at' => now(),
            ]);
        }
    }

    private function releaseCommittedQuotationSummaryCommitments(QuotationSummary $summary): void
    {
        $commitments = BudgetCommitment::query()
            ->where('quotation_summary_id', $summary->id)
            ->where('status', 'COMMITTED')
            ->with('costCenter')
            ->get();

        foreach ($commitments as $commitment) {
            $budgetType = $commitment->costCenter?->budget_type ?? 'ANNUAL';

            if ($budgetType === 'ANNUAL' && $commitment->budget_cedula_id) {
                $distribution = $this->resolveDistributionByCedula(
                    (int) $commitment->cost_center_id,
                    (int) substr((string) $commitment->application_month, 0, 4),
                    (int) substr((string) $commitment->application_month, 5, 2),
                    (int) $commitment->budget_cedula_id,
                    (int) $commitment->expense_category_id
                );

                if (! $distribution->releaseCommitment((float) $commitment->committed_amount)) {
                    throw new RuntimeException(
                        "No se pudo liberar presupuesto para la cÃ©dula {$commitment->budget_cedula_id}."
                    );
                }
            }

            $commitment->update([
                'status' => 'RELEASED',
                'released_at' => now(),
            ]);
        }
    }

    private function consumeLine(Model $order, array $line): void
    {
        $commitments = $this->findCommitmentsForLine($order, $line)
            ->where('status', 'COMMITTED');

        foreach ($commitments as $commitment) {
            if ($line['budget_type'] === 'ANNUAL' && $commitment->budget_cedula_id) {
                $distribution = $this->resolveDistributionByCedula(
                    $line['cost_center_id'],
                    $line['year'],
                    $line['month'],
                    (int) $commitment->budget_cedula_id,
                    (int) $line['expense_category_id']
                );

                if (! $distribution->commitToConsume((float) $commitment->committed_amount)) {
                    throw new RuntimeException(
                        "No se pudo consumir presupuesto para la cédula {$commitment->budget_cedula_id}."
                    );
                }
            }

            $commitment->update([
                'status' => 'RECEIVED',
                'received_at' => now(),
            ]);
        }
    }

    private function resolveDistributionsForCategory(
        int $costCenterId,
        int $year,
        int $month,
        int $categoryId
    ): Collection {
        $budget = AnnualBudget::where('cost_center_id', $costCenterId)
            ->where('fiscal_year', $year)
            ->where('status', 'APROBADO')
            ->first();

        if (! $budget) {
            throw new RuntimeException("No existe presupuesto aprobado para el centro de costo {$costCenterId} en {$year}.");
        }

        $distributions = BudgetMonthlyDistribution::where('annual_budget_id', $budget->id)
            ->where('month', $month)
            ->where('expense_category_id', $categoryId)
            ->whereNotNull('budget_cedula_id')
            ->orderBy('budget_cedula_id')
            ->get();

        if ($distributions->isEmpty()) {
            throw new RuntimeException("No existe distribución mensual para la categoría {$categoryId} en {$month}/{$year}.");
        }

        return $distributions;
    }

    private function resolveDistributionByCedula(
        int $costCenterId,
        int $year,
        int $month,
        int $cedulaId,
        ?int $categoryId = null
    ): BudgetMonthlyDistribution {
        $budget = AnnualBudget::where('cost_center_id', $costCenterId)
            ->where('fiscal_year', $year)
            ->where('status', 'APROBADO')
            ->first();

        if (! $budget) {
            throw new RuntimeException("No existe presupuesto aprobado para el centro de costo {$costCenterId} en {$year}.");
        }

        $distribution = BudgetMonthlyDistribution::where('annual_budget_id', $budget->id)
            ->where('month', $month)
            ->where('budget_cedula_id', $cedulaId)
            ->when($categoryId, fn ($query) => $query->where('expense_category_id', $categoryId))
            ->first();

        if (! $distribution) {
            throw new RuntimeException("No existe distribución mensual para la cédula {$cedulaId} en {$month}/{$year}.");
        }

        return $distribution;
    }

    private function allocateAmountAcrossDistributions(Collection $distributions, float $amount): array
    {
        $remaining = $amount;
        $allocations = [];

        foreach ($distributions->sortByDesc(fn (BudgetMonthlyDistribution $distribution) => $distribution->getAvailableAmount()) as $distribution) {
            if ($remaining <= 0.000001) {
                break;
            }

            $available = $distribution->getAvailableAmount();
            if ($available <= 0) {
                continue;
            }

            $portion = min($available, $remaining);
            $allocations[] = [
                'distribution' => $distribution,
                'amount' => $portion,
            ];
            $remaining -= $portion;
        }

        if ($remaining > 0.000001) {
            throw new RuntimeException(
                sprintf('Presupuesto insuficiente. Faltó asignar $%s a nivel cédula.', number_format($remaining, 2))
            );
        }

        return $allocations;
    }

    private function getOrderBudgetLines(Model $order): array
    {
        if ($order instanceof DirectPurchaseOrder) {
            $order->loadMissing('items.costCenter');

            return $order->items
                ->groupBy(fn ($item) => implode('|', [
                    $item->cost_center_id,
                    $item->expense_category_id,
                    $item->budget_cedula_id,
                ]))
                ->map(function ($items) use ($order) {
                    $first = $items->first();

                    return collect($items)->flatMap(function ($item) use ($order) {
                        return $this->expandDistributionLine([
                            'cost_center_id' => (int) $item->cost_center_id,
                            'expense_category_id' => (int) $item->expense_category_id,
                            'budget_cedula_id' => $item->budget_cedula_id ? (int) $item->budget_cedula_id : null,
                            'amount' => (float) $item->total,
                            'year' => (int) substr((string) $order->application_month, 0, 4),
                            'month' => (int) substr((string) $order->application_month, 5, 2),
                            'application_month' => $order->application_month,
                            'budget_type' => $item->costCenter?->budget_type ?? 'ANNUAL',
                        ]);
                    })->all();
                })
                ->flatten(1)
                ->groupBy(fn ($line) => implode('|', [$line['cost_center_id'], $line['expense_category_id'], $line['budget_cedula_id'], $line['application_month']]))
                ->map(function ($lines) {
                    $first = $lines->first();
                    $first['amount'] = (float) $lines->sum('amount');

                    return $first;
                })
                ->values()
                ->all();
        }

        if ($order instanceof PurchaseOrder) {
            $order->loadMissing('items.requisitionItem.costCenter');

            $existingCommitments = BudgetCommitment::query()
                ->where('purchase_order_id', $order->id)
                ->orderBy('id')
                ->get();

            if ($existingCommitments->isNotEmpty()) {
                $budgetType = $order->items->first()?->requisitionItem?->costCenter?->budget_type ?? 'ANNUAL';

                return $this->mapCommitmentsToLines($existingCommitments, (string) $budgetType);
            }

            $applicationMonth = $order->created_at->format('Y-m');

            return $order->items
                ->groupBy(fn ($item) => implode('|', [
                    $item->requisitionItem?->cost_center_id,
                    $item->requisitionItem?->expense_category_id,
                    $item->requisitionItem?->budget_cedula_id,
                    $applicationMonth,
                ]))
                ->flatMap(function ($items) use ($applicationMonth) {
                    return $items->flatMap(function ($item) use ($applicationMonth) {
                        return $this->expandDistributionLine([
                            'cost_center_id' => (int) $item->requisitionItem?->cost_center_id,
                            'expense_category_id' => (int) $item->requisitionItem?->expense_category_id,
                            'budget_cedula_id' => $item->requisitionItem?->budget_cedula_id ? (int) $item->requisitionItem->budget_cedula_id : null,
                            'amount' => (float) $item->total,
                            'year' => (int) substr($applicationMonth, 0, 4),
                            'month' => (int) substr($applicationMonth, 5, 2),
                            'application_month' => $applicationMonth,
                            'budget_type' => $item->requisitionItem?->costCenter?->budget_type ?? 'ANNUAL',
                        ]);
                    });
                })
                ->groupBy(fn ($line) => implode('|', [$line['cost_center_id'], $line['expense_category_id'], $line['budget_cedula_id'], $line['application_month']]))
                ->map(function ($lines) {
                    $first = $lines->first();
                    $first['amount'] = (float) $lines->sum('amount');

                    return $first;
                })
                ->filter(fn (array $line) => ! empty($line['expense_category_id']))
                ->values()
                ->all();
        }

        throw new RuntimeException('Tipo de orden no soportado para asignación presupuestal.');
    }

    private function mapCommitmentsToLines(Collection $commitments, string $budgetType): array
    {
        return $commitments
            ->groupBy(fn (BudgetCommitment $commitment) => implode('|', [
                $commitment->cost_center_id,
                $commitment->expense_category_id,
                $commitment->budget_cedula_id ?? 'null',
                $commitment->application_month,
            ]))
            ->map(function (Collection $group) use ($budgetType) {
                $first = $group->first();

                return [
                    'cost_center_id' => (int) $first->cost_center_id,
                    'expense_category_id' => (int) $first->expense_category_id,
                    'budget_cedula_id' => $first->budget_cedula_id ? (int) $first->budget_cedula_id : null,
                    'amount' => (float) $group->sum('committed_amount'),
                    'year' => (int) substr((string) $first->application_month, 0, 4),
                    'month' => (int) substr((string) $first->application_month, 5, 2),
                    'application_month' => (string) $first->application_month,
                    'budget_type' => $budgetType,
                ];
            })
            ->values()
            ->all();
    }

    private function expandDistributionLine(array $line): array
    {
        return collect(app(CostCenterDistributionService::class)->expand((int) $line['cost_center_id'], (float) $line['amount']))
            ->map(function (array $allocation) use ($line) {
                $line['cost_center_id'] = $allocation['cost_center_id'];
                $line['amount'] = $allocation['amount'];
                $line['budget_type'] = $allocation['budget_type'];

                return $line;
            })->all();
    }

    private function findCommitmentsForLine(Model $order, array $line): Collection
    {
        $query = $this->baseCommitmentQueryForOrder($order)
            ->where('expense_category_id', $line['expense_category_id'])
            ->where('application_month', $line['application_month']);

        if (array_key_exists('budget_cedula_id', $line)) {
            if ($line['budget_cedula_id'] === null) {
                $query->whereNull('budget_cedula_id');
            } else {
                $query->where('budget_cedula_id', $line['budget_cedula_id']);
            }
        }

        return $query->orderBy('id')->get();
    }

    private function baseCommitmentQueryForOrder(Model $order)
    {
        $query = BudgetCommitment::query();

        if ($order instanceof DirectPurchaseOrder) {
            return $query->where('direct_purchase_order_id', $order->id);
        }

        if ($order instanceof PurchaseOrder) {
            return $query->where('purchase_order_id', $order->id);
        }

        if ($order instanceof QuotationSummary) {
            return $query->where('quotation_summary_id', $order->id);
        }

        throw new RuntimeException('Tipo de orden no soportado para compromisos.');
    }

    private function fillCommitment(
        BudgetCommitment $commitment,
        Model $order,
        array $line,
        string $status,
        ?int $budgetCedulaId,
        float $amount
    ): void {
        $commitment->direct_purchase_order_id = $order instanceof DirectPurchaseOrder ? $order->id : null;
        $commitment->purchase_order_id = $order instanceof PurchaseOrder ? $order->id : null;
        $commitment->quotation_summary_id = $order instanceof QuotationSummary ? $order->id : null;
        $commitment->cost_center_id = $line['cost_center_id'];
        $commitment->application_month = $line['application_month'];
        $commitment->expense_category_id = $line['expense_category_id'];
        $commitment->budget_cedula_id = $budgetCedulaId;
        $commitment->committed_amount = $amount;
        $commitment->status = $status;
    }
}
