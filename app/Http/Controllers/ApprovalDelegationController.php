<?php

namespace App\Http\Controllers;

use App\Models\ApprovalDelegation;
use App\Models\User;
use App\Notifications\ApprovalDelegationSummaryNotification;
use App\Services\ApprovalDelegationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApprovalDelegationController extends Controller
{
    public function index(Request $request, ApprovalDelegationService $service)
    {
        $user = $request->user();

        abort_unless($user->authorizerAssignment()->exists(), 403, 'Solo los autorizadores pueden configurar delegaciones.');

        return view('approval-delegations.index', [
            'activeDelegation' => $service->currentFor($user),
            'draftDelegation' => $service->draftFor($user),
            'eligibleDelegates' => $service->eligibleDelegates($user),
            'history' => ApprovalDelegation::query()
                ->with(['members.delegate', 'deactivatedBy'])
                ->where('delegator_user_id', $user->id)
                ->whereIn('status', ['ENDED'])
                ->latest('starts_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function activate(Request $request, ApprovalDelegationService $service)
    {
        $user = $request->user();
        abort_unless($user->authorizerAssignment()->exists(), 403);

        $validated = $request->validate([
            'delegate_ids' => ['required', 'array', 'min:1'],
            'delegate_ids.*' => ['integer', 'distinct'],
            'ends_at' => ['nullable', 'date', 'after:now'],
        ]);
        $delegateIds = $service->validateDelegateIds($user, $validated['delegate_ids']);

        $delegation = DB::transaction(function () use ($user, $validated, $delegateIds, $service) {
            User::query()->lockForUpdate()->findOrFail($user->id);

            if ($service->currentFor($user)) {
                abort(422, 'Ya tienes una delegación activa.');
            }

            $delegation = $service->draftFor($user) ?? ApprovalDelegation::create([
                'delegator_user_id' => $user->id,
                'status' => 'DRAFT',
            ]);

            $delegation->update([
                'status' => 'ACTIVE',
                'starts_at' => now(),
                'ends_at' => $validated['ends_at'] ?? null,
                'deactivated_at' => null,
                'deactivated_by_user_id' => null,
                'deactivation_reason' => null,
            ]);
            $service->syncMembers($delegation, $delegateIds);

            return $delegation->fresh(['delegator', 'activeMembers.delegate']);
        });

        $counts = $service->pendingCounts($user->id);
        $this->sendActivationSummaries($delegation, $counts);

        return back()->with('success', 'Modo Delegar activado. Tus pendientes ya están disponibles para los delegados seleccionados.');
    }

    public function update(Request $request, ApprovalDelegationService $service)
    {
        $user = $request->user();
        abort_unless($user->authorizerAssignment()->exists(), 403);

        $validated = $request->validate([
            'delegate_ids' => ['required', 'array', 'min:1'],
            'delegate_ids.*' => ['integer', 'distinct'],
            'ends_at' => ['nullable', 'date', 'after:now'],
        ]);
        $delegateIds = $service->validateDelegateIds($user, $validated['delegate_ids']);
        $delegation = $service->currentFor($user) ?? $service->draftFor($user);

        if (! $delegation) {
            $delegation = ApprovalDelegation::create([
                'delegator_user_id' => $user->id,
                'status' => 'DRAFT',
            ]);
        }

        $changes = DB::transaction(function () use ($delegation, $validated, $delegateIds, $service) {
            ApprovalDelegation::query()->lockForUpdate()->findOrFail($delegation->id);
            $delegation->update(['ends_at' => $validated['ends_at'] ?? null]);

            return $service->syncMembers($delegation, $delegateIds);
        });

        if ($delegation->isEffective() && $changes['added']) {
            $counts = $service->pendingCounts($user->id);
            $this->sendActivationSummaries(
                $delegation->fresh(['delegator', 'activeMembers.delegate']),
                $counts,
                $changes['added']
            );
        }

        return back()->with('success', $delegation->isEffective()
            ? 'Delegación activa actualizada.'
            : 'Delegados guardados. Activa el modo cuando los necesites.');
    }

    public function deactivate(Request $request, ApprovalDelegationService $service)
    {
        $user = $request->user();
        $delegation = $service->currentFor($user);
        abort_unless($delegation, 422, 'No tienes una delegación activa.');

        $delegateIds = $delegation->activeMembers
            ->pluck('delegate_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        DB::transaction(function () use ($delegation, $user, $delegateIds, $service): void {
            $service->deactivate($delegation, $user, 'Desactivada por el titular.');

            // El periodo terminado permanece como historial, pero la selección
            // queda lista para que el titular pueda reactivar el modo después.
            $draft = $service->draftFor($user) ?? ApprovalDelegation::create([
                'delegator_user_id' => $user->id,
                'status' => 'DRAFT',
            ]);
            $service->syncMembers($draft, $delegateIds);
        });

        return back()->with('success', 'Modo Delegar desactivado. Solo tú conservas acceso a tus pendientes.');
    }

    /**
     * @param  array<int>|null  $delegateIds
     */
    private function sendActivationSummaries(ApprovalDelegation $delegation, array $counts, ?array $delegateIds = null): void
    {
        $delegates = $delegateIds === null
            ? $delegation->activeMembers->pluck('delegate')->filter()
            : User::query()->whereIn('id', $delegateIds)->get();

        app(\App\Services\SafeNotificationService::class)->notify(
            new ApprovalDelegationSummaryNotification($delegation, $counts),
            $delegates,
            'de resumen de delegación',
            (string) $delegation->id,
        );
    }
}
