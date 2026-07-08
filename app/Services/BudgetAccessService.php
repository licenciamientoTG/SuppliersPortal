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

        $ids = collect();

        if ($user->department_id) {
            $ids = $ids->merge(
                Subaccount::query()
                    ->active()
                    ->whereHas('budgetProfiles', function ($query) use ($user) {
                        $query
                            ->active()
                            ->where(function ($q) use ($user) {
                                $q->whereHas('department', fn ($dq) => $dq->whereKey($user->department_id)->where('is_active', true))
                                  ->orWhereHas('departments', fn ($dq) => $dq->whereKey($user->department_id)->where('is_active', true));
                            })
                            ->whereHas('users', fn ($userQuery) => $userQuery->whereKey($user->id));
                    })
                    ->pluck('subaccounts.id')
            );
        }

        $ids = $ids->merge(
            $user->subaccounts()
                ->active()
                ->pluck('subaccounts.id')
        );

        return $ids
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
