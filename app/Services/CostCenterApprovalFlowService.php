<?php

namespace App\Services;

use App\Models\CostCenterApprovalStep;
use App\Models\DirectPurchaseOrder;
use App\Models\QuotationSummary;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CostCenterApprovalFlowService
{
    /**
     * Indica si el expediente puede resolverse por responsables de centro de costo,
     * sin crear pasos ni modificar el aprobable.
     */
    public function canResolve(Model $approvable): bool
    {
        return $this->approvalStepsFor($approvable) !== [];
    }

    public function initialize(Model $approvable): bool
    {
        $steps = $this->approvalStepsFor($approvable);

        if ($steps === []) {
            return false;
        }

        DB::transaction(function () use ($approvable, $steps): void {
            $approvable->approvalSteps()->delete();

            foreach ($steps as $index => $step) {
                $approvable->approvalSteps()->create([
                    'step_order' => $index + 1,
                    'cost_center_id' => $step['center']->id,
                    'responsible_user_id' => $step['manager']->id,
                    'principal_user_id' => $step['resolution']['user']->id,
                    'source' => $step['resolution']['source'],
                ]);
            }

            $first = $approvable->approvalSteps()->orderBy('step_order')->first();
            $this->assign($approvable, $first);
        });

        return true;
    }

    public function advance(Model $approvable, User $actor, ?string $comments): bool
    {
        $step = $approvable->approvalSteps()
            ->where('status', 'PENDING')
            ->orderBy('step_order')
            ->firstOrFail();
        $context = app(ApprovalDelegationService::class)->contextFor($actor, $step->principal_user_id);

        if (! $context && ! $actor->hasRole('superadmin')) {
            abort(403);
        }

        $step->update([
            'status' => 'APPROVED',
            'acted_by_user_id' => $actor->id,
            'approval_delegation_id' => $context['delegation']->id ?? null,
            'comments' => $comments,
            'acted_at' => now(),
        ]);

        app(ApprovalDecisionService::class)->record(
            $approvable,
            $step->principal_user_id,
            $actor,
            'APPROVED',
            $comments
        );

        $next = $approvable->approvalSteps()
            ->where('status', 'PENDING')
            ->orderBy('step_order')
            ->first();

        if ($next) {
            $this->assign($approvable, $next);

            return false;
        }

        return true;
    }

    /**
     * @return array<int, array{center:object, manager:User, resolution:array{user:User, source:string}}>
     */
    private function approvalStepsFor(Model $approvable): array
    {
        $items = $approvable instanceof DirectPurchaseOrder
            ? $approvable->items()->with('costCenter.responsible')->get()
            : $approvable->requisition->items()->with('costCenter.responsible')->get();
        $amount = (float) $approvable->total;
        $centers = $items->pluck('costCenter')->filter()->unique('id');

        if ($centers->isEmpty()) {
            return [];
        }

        $steps = [];

        foreach ($centers as $center) {
            $manager = $center->responsible;

            if (! $manager) {
                continue;
            }

            $resolution = $this->resolve($manager, $amount);
            $steps[$resolution['user']->id] ??= [
                'center' => $center,
                'manager' => $manager,
                'resolution' => $resolution,
            ];
        }

        return array_values($steps);
    }

    /**
     * @return array{user:User, source:string}
     */
    private function resolve(User $manager, float $amount): array
    {
        $assignment = $manager->authorizerAssignment?->loadMissing('authorizerRole');
        $role = $assignment?->authorizerRole;
        $limit = $manager->activeAuthorizerException?->approval_limit ?? $role?->approval_limit;

        if (
            $manager->is_active
            && $role
            && ($role->approval_limit === null || ($limit !== null && $limit + 0.000001 >= $amount))
        ) {
            return ['user' => $manager, 'source' => 'COST_CENTER'];
        }

        $resolution = app(AuthorizerResolutionService::class)->resolveForRequester($manager, $amount);

        return ['user' => $resolution['approver_user'], 'source' => 'HIERARCHY'];
    }

    private function assign(Model $approvable, CostCenterApprovalStep $step): void
    {
        if ($approvable instanceof QuotationSummary) {
            $approvable->update(['current_approver_user_id' => $step->principal_user_id]);

            return;
        }

        $approvable->update(['assigned_approver_id' => $step->principal_user_id]);
    }
}
