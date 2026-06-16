<?php

namespace App\Services;

use App\Models\DirectPurchaseOrder;
use App\Models\Employee;
use App\Models\QuotationSummary;
use App\Models\Requisition;
use App\Models\User;
use RuntimeException;

class AuthorizerResolutionService
{
    public function resolveForSummary(QuotationSummary $summary): array
    {
        $requester = $summary->requester ?? $summary->requisition?->requester;

        if (! $requester) {
            throw new RuntimeException('La requisición no tiene solicitante asignado.');
        }

        return $this->resolveForRequester($requester, (float) $summary->total);
    }

    public function resolveForRequisition(Requisition $requisition, float $amount): array
    {
        if (! $requisition->requester) {
            throw new RuntimeException('La requisición no tiene usuario solicitante.');
        }

        return $this->resolveForRequester($requisition->requester, $amount);
    }

    public function resolveForDirectPurchaseOrder(DirectPurchaseOrder $directPurchaseOrder): array
    {
        if (! $directPurchaseOrder->creator) {
            throw new RuntimeException('La orden directa no tiene usuario creador asignado.');
        }

        return $this->resolveForRequester($directPurchaseOrder->creator, (float) $directPurchaseOrder->total);
    }

    public function resolveForRequester(User $requester, float $amount): array
    {
        $requesterEmployee = $this->resolveEmployeeForUser($requester);

        if (! $requesterEmployee) {
            throw new RuntimeException("No se encontró empleado ligado al usuario {$requester->email}.");
        }

        $chain = [];
        $currentEmployee = $requesterEmployee;
        $visited = [];

        while ($this->hasLeaderReference($currentEmployee)) {
            [$candidates, $visitedKey, $chainReference] = $this->resolveLeaderCandidates($currentEmployee);

            if ($visitedKey === null || isset($visited[$visitedKey])) {
                break;
            }

            $visited[$visitedKey] = true;

            if ($candidates->isEmpty()) {
                $chain[] = [
                    'employee_number' => $chainReference,
                    'status' => 'leader_not_found',
                ];
                break;
            }

            $selectedEmployee = $this->pickPreferredEmployee($candidates);
            $bossUser = $selectedEmployee->user;
            $roleAssignment = $bossUser?->authorizerAssignment?->loadMissing('authorizerRole');
            $role = $roleAssignment?->authorizerRole;
            $exception = $bossUser?->activeAuthorizerException;
            $effectiveLimit = $exception?->approval_limit ?? $role?->approval_limit;

            $chain[] = [
                'employee_id' => $selectedEmployee->id,
                'employee_number' => $selectedEmployee->employee_number,
                'employee_name' => $selectedEmployee->full_name,
                'leader_number' => $selectedEmployee->leader,
                'user_id' => $bossUser?->id,
                'user_name' => $bossUser?->name,
                'role_name' => $role?->name,
                'role_limit' => $role?->approval_limit !== null ? (float) $role->approval_limit : null,
                'effective_limit' => $effectiveLimit !== null ? (float) $effectiveLimit : null,
                'exception_id' => $exception?->id,
                'exception_reason' => $exception?->reason,
                'candidate_count' => $candidates->count(),
                'status' => $this->describeCandidateStatus($bossUser, $role, $effectiveLimit, $amount),
            ];

            if (
                $bossUser
                && $bossUser->is_active
                && $role
                && (
                    $this->isUnlimitedCouncilRole($role)
                    || (
                        $effectiveLimit !== null
                        && (float) $effectiveLimit + 0.000001 >= $amount
                    )
                )
            ) {
                return [
                    'requester_employee' => $requesterEmployee,
                    'approver_employee' => $selectedEmployee,
                    'approver_user' => $bossUser,
                    'authorizer_role' => $role,
                    'effective_limit' => (float) $effectiveLimit,
                    'chain' => $chain,
                    'resolution_notes' => $candidates->count() > 1
                        ? 'Se detectaron múltiples empleados para el mismo employee_number y se aplicó desempate determinístico.'
                        : null,
                ];
            }

            $currentEmployee = $selectedEmployee;
        }

        throw new RuntimeException('No se encontró ningún superior con rol autorizador suficiente para este monto.');
    }

    private function hasLeaderReference(Employee $employee): bool
    {
        return ! empty($employee->leader_id) || trim((string) $employee->leader) !== '';
    }

    public function resolveEmployeeForUser(User $user): ?Employee
    {
        return Employee::query()
            ->where('user_id', $user->id)
            ->orderByRaw("CASE WHEN is_active = 'SI' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->first();
    }

    private function resolveLeaderCandidates(Employee $employee): array
    {
        if (! empty($employee->leader_id)) {
            $candidates = Employee::query()
                ->whereKey($employee->leader_id)
                ->get();

            if ($candidates->isNotEmpty()) {
                return [
                    $candidates,
                    'id:' . $employee->leader_id,
                    $candidates->first()->employee_number ?? ('id:' . $employee->leader_id),
                ];
            }
        }

        $leaderNumber = trim((string) $employee->leader);

        if ($leaderNumber === '') {
            return [collect(), null, null];
        }

        return [
            Employee::query()
                ->where('employee_number', $leaderNumber)
                ->get(),
            'leader:' . $leaderNumber,
            $leaderNumber,
        ];
    }

    private function pickPreferredEmployee($candidates): Employee
    {
        return $candidates
            ->sortByDesc('id')
            ->sortBy(fn (Employee $employee) => $employee->user_id ? 0 : 1)
            ->sortBy(fn (Employee $employee) => $employee->is_active === 'SI' ? 0 : 1)
            ->first();
    }

    private function describeCandidateStatus(?User $user, $role, $effectiveLimit, float $amount): string
    {
        if (! $user) {
            return 'no_portal_user';
        }

        if (! $user->is_active) {
            return 'inactive_portal_user';
        }

        if (! $role) {
            return 'no_authorizer_role';
        }

        if ($this->isUnlimitedCouncilRole($role)) {
            return 'eligible';
        }

        if ($effectiveLimit === null) {
            return 'role_without_limit';
        }

        if ((float) $effectiveLimit + 0.000001 < $amount) {
            return 'insufficient_limit';
        }

        return 'eligible';
    }

    private function isUnlimitedCouncilRole($role): bool
    {
        return mb_strtolower((string) $role?->name) === mb_strtolower('Consejo de Administración');
    }
}
