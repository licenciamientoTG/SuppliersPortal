<?php

namespace App\Services;

use App\Models\AnnualBudget;
use App\Models\BudgetCedula;
use App\Models\CostCenter;
use Illuminate\Support\Collection;

class BudgetCedulaCatalogService
{
    public function getValidCedulas(int $costCenterId, int $expenseCategoryId, int $fiscalYear): Collection
    {
        $costCenter = CostCenter::query()
            ->whereKey($costCenterId)
            ->where('status', 'ACTIVO')
            ->whereNull('deleted_at')
            ->first();

        if (! $costCenter) {
            return collect();
        }

        $baseQuery = BudgetCedula::query()
            ->active()
            ->notDeleted()
            ->where('expense_category_id', $expenseCategoryId)
            ->orderBy('name')
            ->orderBy('id');

        if ($costCenter->isFreeConsumption()) {
            return $baseQuery->get();
        }

        $annualBudget = AnnualBudget::query()
            ->where('cost_center_id', $costCenterId)
            ->where('fiscal_year', $fiscalYear)
            ->whereIn('status', ['APROBADO', 'PLANIFICACION'])
            ->first();

        if (! $annualBudget) {
            return collect();
        }

        $cedulaIds = $annualBudget->monthlyDistributions()
            ->where('expense_category_id', $expenseCategoryId)
            ->whereNotNull('budget_cedula_id')
            ->distinct()
            ->pluck('budget_cedula_id');

        if ($cedulaIds->isEmpty()) {
            return collect();
        }

        return $baseQuery
            ->whereIn('id', $cedulaIds)
            ->get();
    }

    public function isValidCedulaForContext(
        int $costCenterId,
        int $expenseCategoryId,
        int $budgetCedulaId,
        int $fiscalYear
    ): bool {
        return $this->getValidCedulas($costCenterId, $expenseCategoryId, $fiscalYear)
            ->contains('id', $budgetCedulaId);
    }
}
