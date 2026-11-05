<?php

namespace App\Services;

use App\Models\ApprovalDecision;
use App\Models\User;
use App\Notifications\DelegatedApprovalActionNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalDecisionService
{
    public function __construct(private ApprovalDelegationService $delegations) {}

    public function record(
        Model $approvable,
        int $principalUserId,
        User $actor,
        string $action,
        ?string $comments = null
    ): ApprovalDecision {
        $context = $this->delegations->contextFor($actor, $principalUserId);

        if (! $context && $actor->hasRole('superadmin')) {
            $context = [
                'principal_user_id' => $principalUserId,
                'is_delegated' => false,
                'delegation' => null,
            ];
        }

        if (! $context) {
            throw ValidationException::withMessages([
                'authorization' => 'La delegación ya no está vigente o no tienes permiso para resolver esta autorización.',
            ]);
        }

        $decision = ApprovalDecision::create([
            'approvable_type' => $approvable->getMorphClass(),
            'approvable_id' => $approvable->getKey(),
            'assigned_principal_user_id' => $principalUserId,
            'acted_by_user_id' => $actor->id,
            'approval_delegation_id' => $context['delegation']?->id,
            'action' => strtoupper($action),
            'comments' => $comments,
            'acted_at' => now(),
        ]);

        if ($context['is_delegated']) {
            DB::afterCommit(function () use ($principalUserId, $decision, $approvable) {
                // Corre dentro de commit(): una excepción aquí haría que la decisión ya
                // confirmada se reportara como error al usuario.
                try {
                    $principal = User::find($principalUserId);
                    app(SafeNotificationService::class)->notify(
                        new DelegatedApprovalActionNotification($decision, $approvable),
                        $principal ? [$principal] : [],
                        'de acción delegada de autorización',
                    );
                } catch (\Throwable $exception) {
                    \Illuminate\Support\Facades\Log::error('No se pudo notificar al titular la acción delegada.', [
                        'approval_decision_id' => $decision->id,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            });
        }

        return $decision;
    }
}
