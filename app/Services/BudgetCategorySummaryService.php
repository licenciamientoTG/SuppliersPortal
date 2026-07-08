<?php

namespace App\Services;

use App\Models\AnnualBudget;
use App\Models\BudgetMonthlyDistribution;
use Illuminate\Support\Collection;

class BudgetCategorySummaryService
{
    public function forBudget(AnnualBudget $budget): Collection
    {
        return $this->groupDistributions($budget->monthlyDistributions);
    }

    public function forBudgetMonth(AnnualBudget $budget, int $month): Collection
    {
        return $this->groupDistributions(
            $budget->monthlyDistributions->where('month', $month)->values()
        );
    }

    public function forBudgetMonthAndCategory(AnnualBudget $budget, int $month, int $categoryId): ?array
    {
        return $this->groupDistributions(
            $budget->monthlyDistributions
                ->where('month', $month)
                ->where('expense_category_id', $categoryId)
                ->values()
        )->first();
    }

    public function groupDistributions(Collection $distributions): Collection
    {
        // Los llamadores hacen eager load de budgetCedula.expenseCategory, pero aquí
        // se usa la relación directa expenseCategory: cargarla evita un N+1 por categoría.
        if ($distributions instanceof \Illuminate\Database\Eloquent\Collection) {
            $distributions->loadMissing('expenseCategory');
        }

        return $distributions
            ->groupBy(fn (BudgetMonthlyDistribution $distribution) => $distribution->expense_category_id)
            ->map(function (Collection $items) {
                /** @var BudgetMonthlyDistribution $first */
                $first = $items->first();
                $assigned = (float) $items->sum('assigned_amount');
                $consumed = (float) $items->sum('consumed_amount');
                $committed = (float) $items->sum('committed_amount');
                $available = $assigned - $consumed - $committed;

                return [
                    'category_id' => $first->expense_category_id,
                    'category_code' => $first->expenseCategory?->code,
                    'category_name' => $first->expenseCategory?->name,
                    'assigned_amount' => $assigned,
                    'consumed_amount' => $consumed,
                    'committed_amount' => $committed,
                    'available_amount' => $available,
                    'usage_percentage' => $assigned > 0
                        ? (($consumed + $committed) / $assigned) * 100
                        : 0,
                    'status' => $this->resolveStatus($assigned, $consumed, $committed),
                    'cedulas' => $items
                        ->sortBy(fn (BudgetMonthlyDistribution $distribution) => sprintf(
                            '%s-%02d',
                            $distribution->budgetCedula?->name ?? '',
                            $distribution->month
                        ))
                        ->values(),
                ];
            })
            ->sortBy(fn (array $row) => ($row['category_code'] ?? '') . ' ' . ($row['category_name'] ?? ''))
            ->values();
    }

    private function resolveStatus(float $assigned, float $consumed, float $committed): string
    {
        if ($assigned <= 0) {
            return 'AGOTADO';
        }

        $usagePercentage = (($consumed + $committed) / $assigned) * 100;
        $available = $assigned - $consumed - $committed;

        if ($available <= 0) {
            return 'AGOTADO';
        }

        if ($usagePercentage > 70) {
            return 'CRITICO';
        }

        if ($usagePercentage > 30) {
            return 'ALERTA';
        }

        return 'NORMAL';
    }
}
