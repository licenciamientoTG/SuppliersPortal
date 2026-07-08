<?php

namespace App\Services;

use App\Models\Subaccount;
use App\Models\User;
use Illuminate\Support\Collection;

class BudgetAccessService
{
    public function subaccountIdsFor(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        if (! $user->department_id) {
            return collect();
        }

        return Subaccount::query()
            ->active()
            ->whereHas('budgetProfiles', function ($query) use ($user) {
                $query
                    ->active()
                    ->whereHas('departments', fn ($departmentQuery) => $departmentQuery
                        ->whereKey($user->department_id)
                        ->where('departments.is_active', true));
            })
            ->pluck('subaccounts.id')
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
