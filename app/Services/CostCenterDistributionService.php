<?php

namespace App\Services;

use App\Models\CostCenter;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CostCenterDistributionService
{
    public function targets(CostCenter|int $costCenter): Collection
    {
        $center = $costCenter instanceof CostCenter ? $costCenter : CostCenter::findOrFail($costCenter);

        return $center->distributionTargets()
            ->with('targetCostCenter')
            ->orderBy('target_cost_center_id')
            ->get();
    }

    public function expand(int $costCenterId, float $amount): array
    {
        $center = CostCenter::findOrFail($costCenterId);
        if (! $center->isDistribution()) {
            return [['cost_center_id' => $center->id, 'amount' => round($amount, 2), 'budget_type' => $center->budget_type]];
        }

        $targets = $this->targets($center);
        $this->assertValidTargets($center, $targets);
        $remaining = round($amount, 2);

        return $targets->values()->map(function ($target, int $index) use (&$remaining, $targets, $amount) {
            $allocated = $index === $targets->count() - 1
                ? $remaining
                : round($amount * ((float) $target->percentage / 100), 2);
            $remaining = round($remaining - $allocated, 2);

            return [
                'cost_center_id' => $target->target_cost_center_id,
                'amount' => $allocated,
                'budget_type' => $target->targetCostCenter->budget_type,
            ];
        })->all();
    }

    public function validateConfiguration(CostCenter $center, array $destinations): void
    {
        if ($center->cost_center_type !== 'DISTRIBUTION') return;
        if (count($destinations) === 0) throw ValidationException::withMessages(['destinations' => 'Configura al menos un centro destino.']);
        $ids = collect($destinations)->pluck('target_cost_center_id')->map(fn ($id) => (int) $id);
        if ($ids->contains($center->id) || $ids->count() !== $ids->unique()->count()) throw ValidationException::withMessages(['destinations' => 'Los destinos deben ser únicos y no pueden ser el mismo centro distribuidor.']);
        $targets = CostCenter::query()->whereIn('id', $ids)->get();
        if ($targets->count() !== $ids->count() || $targets->contains(fn (CostCenter $target) => $target->company_id !== $center->company_id || ! $target->isActive() || $target->isDistribution())) throw ValidationException::withMessages(['destinations' => 'Cada destino debe ser un centro estándar activo de la misma empresa.']);
        if (abs((float) collect($destinations)->sum('percentage') - 100) > 0.0001) throw ValidationException::withMessages(['destinations' => 'Los porcentajes de distribución deben sumar 100%.']);
    }

    public function missingBudgetTargets(int $costCenterId, int $expenseCategoryId, int $cedulaId, int $year): Collection
    {
        $center = CostCenter::findOrFail($costCenterId);
        $targets = $center->isDistribution() ? $this->targets($center)->pluck('targetCostCenter') : collect([$center]);

        return $targets->filter(fn (CostCenter $target) => ! app(BudgetCedulaCatalogService::class)
            ->isValidCedulaForContext($target->id, $expenseCategoryId, $cedulaId, $year));
    }

    private function assertValidTargets(CostCenter $center, Collection $targets): void
    {
        $this->validateConfiguration($center, $targets->map(fn ($target) => ['target_cost_center_id' => $target->target_cost_center_id, 'percentage' => $target->percentage])->all());
    }
}
