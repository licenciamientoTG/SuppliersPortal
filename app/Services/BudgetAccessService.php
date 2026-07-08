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
                    ->where('budget_profiles.department_id', $user->department_id)
                    ->whereHas('department', fn ($departmentQuery) => $departmentQuery->where('is_active', true))
                    ->whereHas('users', fn ($userQuery) => $userQuery->whereKey($user->id));
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
