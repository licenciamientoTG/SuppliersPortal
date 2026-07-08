<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class BudgetAccessService
{
    public function subaccountIdsFor(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        $departmentIds = $user->department
            ? $user->department->subaccounts()->pluck('subaccounts.id')
            : collect();

        $profileIds = $user->budgetProfile
            ? $user->budgetProfile->subaccounts()->pluck('subaccounts.id')
            : collect();

        $directIds = $user->subaccounts()->pluck('subaccounts.id');

        return $departmentIds
            ->merge($profileIds)
            ->merge($directIds)
            ->filter()
            ->unique()
            ->values();
    }

    public function userCanUseProduct(User $user, int $productServiceId): bool
    {
        $subaccountIds = $this->subaccountIdsFor($user);

        if ($subaccountIds->isEmpty()) {
            return false;
        }

        return \App\Models\ProductService::query()
            ->whereKey($productServiceId)
            ->whereHas('subaccounts', fn ($query) => $query->whereIn('subaccounts.id', $subaccountIds))
            ->exists();
    }
}
