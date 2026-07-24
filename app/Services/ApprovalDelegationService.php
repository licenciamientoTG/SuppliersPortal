<?php

namespace App\Services;

use App\Models\ApprovalDelegation;
use App\Models\ApprovalDelegationMember;
use App\Models\DirectPurchaseOrder;
use App\Models\PurchaseOrder;
use App\Models\QuotationSummary;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalDelegationService
{
    public function currentFor(User|int $delegator): ?ApprovalDelegation
    {
        $delegatorId = $delegator instanceof User ? $delegator->id : $delegator;

        return ApprovalDelegation::query()
            ->effective()
            ->with(['activeMembers.delegate', 'delegator'])
            ->where('delegator_user_id', $delegatorId)
            ->latest('starts_at')
            ->first();
    }

    public function draftFor(User|int $delegator): ?ApprovalDelegation
    {
        $delegatorId = $delegator instanceof User ? $delegator->id : $delegator;

        return ApprovalDelegation::query()
            ->with(['activeMembers.delegate'])
            ->where('delegator_user_id', $delegatorId)
            ->where('status', 'DRAFT')
            ->latest()
            ->first();
    }

    public function delegationsForDelegate(User|int $delegate): Collection
    {
        $delegateId = $delegate instanceof User ? $delegate->id : $delegate;

        return ApprovalDelegation::query()
            ->effective()
            ->with('delegator')
            ->whereHas('members', fn ($query) => $query
                ->effective()
                ->where('delegate_user_id', $delegateId))
            ->get();
    }

    public function accessiblePrincipalIds(User $actor): array
    {
        return $this->delegationsForDelegate($actor)
            ->pluck('delegator_user_id')
            ->prepend($actor->id)
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function contextFor(User $actor, int $principalUserId): ?array
    {
        if ((int) $actor->id === $principalUserId) {
            return [
                'principal_user_id' => $principalUserId,
                'is_delegated' => false,
                'delegation' => null,
            ];
        }

        $delegation = ApprovalDelegation::query()
            ->effective()
            ->where('delegator_user_id', $principalUserId)
            ->whereHas('members', fn ($query) => $query
                ->effective()
                ->where('delegate_user_id', $actor->id))
            ->first();

        if (! $delegation) {
            return null;
        }

        return [
            'principal_user_id' => $principalUserId,
            'is_delegated' => true,
            'delegation' => $delegation,
        ];
    }

    public function canAct(User $actor, ?int $principalUserId): bool
    {
        return $principalUserId !== null && $this->contextFor($actor, $principalUserId) !== null;
    }

    public function recipientsForPrincipal(User|int $principal): Collection
    {
        $principalUser = $principal instanceof User ? $principal : User::find($principal);

        if (! $principalUser) {
            return collect();
        }

        $delegation = $this->currentFor($principalUser);

        return collect([$principalUser])
            ->merge($delegation?->activeMembers?->pluck('delegate') ?? collect())
            ->filter(fn (?User $user) => $user?->is_active)
            ->unique('id')
            ->values();
    }

    public function eligibleDelegates(User $delegator): Collection
    {
        return User::query()
            ->whereKeyNot($delegator->id)
            ->where('is_active', true)
            ->whereHas('authorizerAssignment.authorizerRole', fn ($query) => $query->where('is_active', true))
            ->with(['authorizerAssignment.authorizerRole'])
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => app(ModuleAccessService::class)->userCanAccessModule($user, 'quotations')
                && app(ModuleAccessService::class)->userCanAccessModule($user, 'purchase_orders'))
            ->values();
    }

    public function validateDelegateIds(User $delegator, array $delegateIds): array
    {
        $ids = collect($delegateIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $eligibleIds = $this->eligibleDelegates($delegator)->pluck('id');

        if ($ids->isEmpty() || $ids->diff($eligibleIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'delegate_ids' => 'Selecciona al menos un autorizador activo y elegible.',
            ]);
        }

        return $ids->all();
    }

    public function pendingCounts(int $principalUserId): array
    {
        return [
            'quotations' => QuotationSummary::query()
                ->where('approval_status', 'pending')
                ->where('current_approver_user_id', $principalUserId)
                ->count(),
            'direct_orders' => DirectPurchaseOrder::query()
                ->where('status', 'PENDING_APPROVAL')
                ->where('assigned_approver_id', $principalUserId)
                ->count(),
            'contract_orders' => PurchaseOrder::query()
                ->where('status', 'PENDING_APPROVAL')
                ->where('assigned_approver_id', $principalUserId)
                ->count(),
        ];
    }

    public function syncMembers(ApprovalDelegation $delegation, array $delegateIds): array
    {
        $now = now();
        $requested = collect($delegateIds)->map(fn ($id) => (int) $id)->unique();
        $activeMembers = $delegation->members()->effective()->get();
        $activeIds = $activeMembers->pluck('delegate_user_id')->map(fn ($id) => (int) $id);

        $removedIds = $activeIds->diff($requested);
        $addedIds = $requested->diff($activeIds);

        if ($removedIds->isNotEmpty()) {
            $delegation->members()
                ->effective()
                ->whereIn('delegate_user_id', $removedIds)
                ->update(['removed_at' => $now, 'updated_at' => $now]);
        }

        foreach ($addedIds as $delegateId) {
            ApprovalDelegationMember::create([
                'approval_delegation_id' => $delegation->id,
                'delegate_user_id' => $delegateId,
                'added_at' => $now,
            ]);
        }

        return ['added' => $addedIds->all(), 'removed' => $removedIds->all()];
    }

    public function deactivate(ApprovalDelegation $delegation, User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($delegation, $actor, $reason) {
            $locked = ApprovalDelegation::query()->lockForUpdate()->findOrFail($delegation->id);

            if (! $locked->isEffective()) {
                return;
            }

            $locked->update([
                'status' => 'ENDED',
                'deactivated_at' => now(),
                'deactivated_by_user_id' => $actor->id,
                'deactivation_reason' => $reason,
            ]);
        });
    }
}
