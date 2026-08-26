<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveBudgetMovementRequest;
use App\Models\AnnualBudget;
use App\Models\BudgetCedula;
use App\Models\BudgetMonthlyDistribution;
use App\Models\BudgetMovement;
use App\Models\BudgetMovementApprovalSetting;
use App\Models\BudgetMovementDecision;
use App\Models\BudgetMovementDetail;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Notifications\BudgetMovementWorkflowNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BudgetMovementWorkflowController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();
        $movements = $this->visibleTo($actor)->with(['creator', 'details.costCenter'])->latest()->paginate(20);
        $settings = BudgetMovementApprovalSetting::query()->first();

        return view('budget_movements.workflow.index', compact('movements', 'settings'));
    }

    public function dashboard(Request $request): View
    {
        $actor = $request->user();
        $query = $this->visibleTo($actor);
        $pendingOrigin = (clone $query)->where('status', BudgetMovement::STATUS_PENDING_ORIGIN)->count();
        $pendingExecutive = (clone $query)->where('status', BudgetMovement::STATUS_PENDING_EXECUTIVE)->count();
        $returned = (clone $query)->where('status', BudgetMovement::STATUS_RETURNED)->count();
        $recent = $query->with(['creator', 'details.costCenter'])->latest()->limit(8)->get();

        return view('budget_movements.workflow.dashboard', compact('pendingOrigin', 'pendingExecutive', 'returned', 'recent'));
    }

    public function create(Request $request): View
    {
        $ownedCostCenters = $this->ownedCenters($request->user())->get();
        abort_if($ownedCostCenters->isEmpty(), 403, 'No tienes centros de costo asignados como responsable.');

        return view('budget_movements.workflow.create', $this->formData($ownedCostCenters));
    }

    /**
     * Returns the live balance for the selected budget line.  The form uses this
     * strictly as a preview; the same balance is revalidated under a lock when
     * the executive approval is applied.
     */
    public function budgetSnapshot(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cost_center_id' => ['required', 'integer', Rule::exists('cost_centers', 'id')],
            'fiscal_year' => ['required', 'integer', 'between:2020,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')],
            'budget_cedula_id' => ['required', 'integer', Rule::exists('budget_cedulas', 'id')],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'effect' => ['required', Rule::in(['INCREASE', 'DECREASE'])],
            'context' => ['required', Rule::in(['single', 'origin', 'destination'])],
        ]);

        $actor = $request->user();
        $isOwner = $this->ownedCenters($actor)->whereKey($data['cost_center_id'])->exists();
        if ($data['context'] === 'origin') {
            abort_unless($this->ownedCenters($actor)->exists(), 403, 'No tienes centros de costo asignados como responsable.');
        } else {
            abort_unless($isOwner, 403, 'Solo puedes consultar el presupuesto de centros de costo de los que eres responsable.');
        }

        $budget = AnnualBudget::query()
            ->where('cost_center_id', $data['cost_center_id'])
            ->where('fiscal_year', $data['fiscal_year'])
            ->first();
        $distributions = $budget
            ? BudgetMonthlyDistribution::query()
                ->where('annual_budget_id', $budget->id)
                ->where('month', $data['month'])
                ->where('expense_category_id', $data['expense_category_id'])
                ->where('budget_cedula_id', $data['budget_cedula_id'])
                ->get()
            : collect();

        $assigned = (float) $distributions->sum('assigned_amount');
        $consumed = (float) $distributions->sum('consumed_amount');
        $committed = (float) $distributions->sum('committed_amount');
        $available = (float) $distributions->sum(fn (BudgetMonthlyDistribution $distribution) => $distribution->getAvailableAmount());
        $amount = (float) ($data['amount'] ?? 0);
        $sign = $data['effect'] === 'INCREASE' ? 1 : -1;

        return response()->json([
            'success' => true,
            'has_budget' => $budget !== null && $distributions->isNotEmpty(),
            'message' => $budget === null
                ? 'No hay presupuesto anual registrado para este centro y año.'
                : ($distributions->isEmpty() ? 'No hay presupuesto asignado a esta subcuenta en el mes seleccionado.' : null),
            'assigned_amount' => $assigned,
            'consumed_amount' => $consumed,
            'committed_amount' => $committed,
            'available_amount' => $available,
            'movement_amount' => $amount,
            'projected_assigned_amount' => $assigned + ($sign * $amount),
            'projected_available_amount' => $available + ($sign * $amount),
            'has_sufficient_available' => $data['effect'] === 'INCREASE' || $available >= $amount,
        ]);
    }

    public function store(SaveBudgetMovementRequest $request): RedirectResponse
    {
        $actor = $request->user();
        $data = $request->validated();
        $this->ensureCanSubmit($actor, $data);
        $settings = $this->settingsOrFail();

        $movement = DB::transaction(function () use ($data, $actor) {
            $status = $data['movement_type'] === BudgetMovement::TYPE_TRANSFER
                ? BudgetMovement::STATUS_PENDING_ORIGIN
                : BudgetMovement::STATUS_PENDING_EXECUTIVE;
            $movement = BudgetMovement::create([
                'movement_type' => $data['movement_type'], 'fiscal_year' => $data['fiscal_year'],
                'movement_date' => $data['movement_date'], 'total_amount' => $data['total_amount'],
                'justification' => $data['justification'], 'status' => $status, 'created_by' => $actor->id,
            ]);
            $this->syncDetails($movement, $data);
            $this->record($movement, BudgetMovementDecision::STAGE_EXECUTIVE, BudgetMovementDecision::ACTION_SUBMITTED, $actor);

            return $movement->fresh(['details.costCenter']);
        });

        if ($movement->isTransfer()) {
            $originOwner = $movement->originDetails()->with('costCenter.responsible')->first()?->costCenter?->responsible;
            $this->notify($originOwner, $movement, 'Tienes una transferencia presupuestal pendiente de validar como centro de costo origen.');
        } else {
            $this->notifyExecutives($settings, $movement, 'Tienes una solicitud de movimiento presupuestal pendiente de aprobación.');
        }

        return redirect()->route('budget_movements.show', $movement)->with('success', 'Solicitud enviada correctamente.');
    }

    public function show(Request $request, BudgetMovement $budgetMovement): View
    {
        $this->ensureVisible($request->user(), $budgetMovement);
        $budgetMovement->load(['details.costCenter.responsible', 'details.expenseCategory', 'details.budgetCedula', 'creator', 'approver', 'decisions.actor']);

        return view('budget_movements.workflow.show', [
            'budgetMovement' => $budgetMovement,
            'canOriginApprove' => $this->canOriginApprove($request->user(), $budgetMovement),
            'canExecutiveApprove' => $this->approvalSettings()?->canApprove($request->user()) && $budgetMovement->status === BudgetMovement::STATUS_PENDING_EXECUTIVE,
        ]);
    }

    public function edit(Request $request, BudgetMovement $budgetMovement): View
    {
        abort_unless($budgetMovement->created_by === $request->user()->id && $budgetMovement->isReturned(), 403);
        $ownedCostCenters = $this->ownedCenters($request->user())->get();
        $budgetMovement->load('details');

        return view('budget_movements.workflow.edit', array_merge($this->formData($ownedCostCenters), compact('budgetMovement')));
    }

    public function update(SaveBudgetMovementRequest $request, BudgetMovement $budgetMovement): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($budgetMovement->created_by === $actor->id && $budgetMovement->isReturned(), 403);
        $data = $request->validated();
        $this->ensureCanSubmit($actor, $data);
        $settings = $this->settingsOrFail();

        $movement = DB::transaction(function () use ($budgetMovement, $data, $actor) {
            $status = $data['movement_type'] === BudgetMovement::TYPE_TRANSFER ? BudgetMovement::STATUS_PENDING_ORIGIN : BudgetMovement::STATUS_PENDING_EXECUTIVE;
            $budgetMovement->update(array_merge(collect($data)->only(['movement_type', 'fiscal_year', 'movement_date', 'total_amount', 'justification'])->all(), ['status' => $status]));
            $budgetMovement->details()->delete();
            $this->syncDetails($budgetMovement, $data);
            $this->record($budgetMovement, BudgetMovementDecision::STAGE_EXECUTIVE, BudgetMovementDecision::ACTION_SUBMITTED, $actor, 'Solicitud corregida y reenviada.');

            return $budgetMovement->fresh(['details.costCenter']);
        });

        if ($movement->isTransfer()) {
            $this->notify($movement->originDetails()->with('costCenter.responsible')->first()?->costCenter?->responsible, $movement, 'La transferencia fue corregida y requiere tu validación como origen.');
        } else {
            $this->notifyExecutives($settings, $movement, 'La solicitud corregida requiere aprobación ejecutiva.');
        }

        return redirect()->route('budget_movements.show', $movement)->with('success', 'Solicitud corregida y reenviada.');
    }

    public function approveOrigin(Request $request, BudgetMovement $budgetMovement): RedirectResponse
    {
        abort_unless($this->canOriginApprove($request->user(), $budgetMovement), 403);
        $settings = $this->settingsOrFail();
        DB::transaction(function () use ($budgetMovement, $request) {
            $movement = BudgetMovement::query()->lockForUpdate()->findOrFail($budgetMovement->id);
            abort_unless($movement->status === BudgetMovement::STATUS_PENDING_ORIGIN, 422, 'La solicitud ya no está pendiente de tu validación.');
            $movement->update(['status' => BudgetMovement::STATUS_PENDING_EXECUTIVE]);
            $this->record($movement, BudgetMovementDecision::STAGE_ORIGIN, BudgetMovementDecision::ACTION_APPROVED, $request->user());
        });
        $this->notifyExecutives($settings, $budgetMovement->fresh(), 'Una transferencia fue validada por el centro origen y requiere aprobación ejecutiva.');

        return back()->with('success', 'Transferencia validada y enviada a Dirección General.');
    }

    public function returnToRequester(Request $request, BudgetMovement $budgetMovement): RedirectResponse
    {
        $data = $request->validate(['comments' => ['required', 'string', 'min:10', 'max:1000']]);
        abort_unless($this->canOriginApprove($request->user(), $budgetMovement), 403);
        DB::transaction(function () use ($budgetMovement, $request, $data) {
            $movement = BudgetMovement::query()->lockForUpdate()->findOrFail($budgetMovement->id);
            abort_unless($movement->status === BudgetMovement::STATUS_PENDING_ORIGIN, 422);
            $movement->update(['status' => BudgetMovement::STATUS_RETURNED]);
            $this->record($movement, BudgetMovementDecision::STAGE_ORIGIN, BudgetMovementDecision::ACTION_RETURNED, $request->user(), $data['comments']);
        });
        $this->notify($budgetMovement->creator, $budgetMovement->fresh(), 'Tu transferencia fue devuelta para corrección: '.$data['comments']);

        return back()->with('success', 'Transferencia devuelta al solicitante.');
    }

    public function approveExecutive(Request $request, BudgetMovement $budgetMovement): RedirectResponse
    {
        abort_unless($this->approvalSettings()?->canApprove($request->user()), 403);
        DB::transaction(function () use ($budgetMovement, $request) {
            $movement = BudgetMovement::query()->lockForUpdate()->with(['details.costCenter', 'details.expenseCategory'])->findOrFail($budgetMovement->id);
            abort_unless($movement->status === BudgetMovement::STATUS_PENDING_EXECUTIVE, 422, 'La solicitud ya no está pendiente de aprobación ejecutiva.');
            $this->applyMovement($movement, $request->user()->id);
            $movement->update(['status' => BudgetMovement::STATUS_APPROVED, 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            $this->record($movement, BudgetMovementDecision::STAGE_EXECUTIVE, BudgetMovementDecision::ACTION_APPROVED, $request->user());
        });
        $this->notify($budgetMovement->creator, $budgetMovement->fresh(), 'Tu movimiento presupuestal fue aprobado y aplicado al presupuesto.');

        return back()->with('success', 'Movimiento aprobado y aplicado al presupuesto.');
    }

    public function rejectExecutive(Request $request, BudgetMovement $budgetMovement): RedirectResponse
    {
        $data = $request->validate(['comments' => ['required', 'string', 'min:10', 'max:1000']]);
        abort_unless($this->approvalSettings()?->canApprove($request->user()), 403);
        DB::transaction(function () use ($budgetMovement, $request, $data) {
            $movement = BudgetMovement::query()->lockForUpdate()->findOrFail($budgetMovement->id);
            abort_unless($movement->status === BudgetMovement::STATUS_PENDING_EXECUTIVE, 422);
            $movement->update(['status' => BudgetMovement::STATUS_REJECTED, 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            $this->record($movement, BudgetMovementDecision::STAGE_EXECUTIVE, BudgetMovementDecision::ACTION_REJECTED, $request->user(), $data['comments']);
        });
        $this->notify($budgetMovement->creator, $budgetMovement->fresh(), 'Tu movimiento presupuestal fue rechazado: '.$data['comments']);

        return back()->with('success', 'Movimiento rechazado.');
    }

    public function settings(Request $request): View
    {
        abort_unless($request->user()->hasRole('superadmin'), 403);

        return view('budget_movements.workflow.settings', ['settings' => $this->approvalSettings(), 'directors' => User::role('general_director')->where('is_active', true)->orderBy('name')->get(), 'users' => User::where('is_active', true)->orderBy('name')->get()]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('superadmin'), 403);
        $data = $request->validate([
            'director_user_id' => ['required', Rule::exists('users', 'id')],
            'substitute_user_id' => ['nullable', 'different:director_user_id', Rule::exists('users', 'id')],
            'substitute_starts_at' => ['nullable', 'required_with:substitute_user_id', 'date'],
            'substitute_ends_at' => ['nullable', 'required_with:substitute_user_id', 'date', 'after:substitute_starts_at'],
        ]);
        abort_unless(User::role('general_director')->whereKey($data['director_user_id'])->where('is_active', true)->exists(), 422, 'El titular debe ser un Director General activo.');
        if (! empty($data['substitute_user_id'])) {
            abort_unless(User::whereKey($data['substitute_user_id'])->where('is_active', true)->exists(), 422, 'El suplente debe ser un usuario activo.');
        }
        BudgetMovementApprovalSetting::query()->firstOrNew()->fill(array_merge($data, ['updated_by' => $request->user()->id]))->save();

        return back()->with('success', 'Configuración de autorización guardada.');
    }

    private function formData(Collection $ownedCostCenters): array
    {
        return ['ownedCostCenters' => $ownedCostCenters, 'originCostCenters' => CostCenter::active()->with('responsible:id,name')->orderBy('name')->get(), 'expenseCategories' => ExpenseCategory::orderBy('name')->get(), 'budgetCedulas' => BudgetCedula::active()->notDeleted()->orderBy('name')->get(['id', 'expense_category_id', 'name']), 'currentYear' => now()->year];
    }

    private function syncDetails(BudgetMovement $movement, array $data): void
    {
        $rows = match ($data['movement_type']) {
            BudgetMovement::TYPE_TRANSFER => [
                [BudgetMovementDetail::TYPE_ORIGIN, 'origin'], [BudgetMovementDetail::TYPE_DESTINATION, 'destination'],
            ],
            default => [[BudgetMovementDetail::TYPE_ADJUSTMENT, null]],
        };
        foreach ($rows as [$type, $prefix]) {
            $key = $prefix ? $prefix.'_' : '';
            BudgetMovementDetail::create(['budget_movement_id' => $movement->id, 'detail_type' => $type, 'cost_center_id' => $data[$key.'cost_center_id'], 'month' => $data[$key.'month'], 'expense_category_id' => $data[$key.'expense_category_id'], 'budget_cedula_id' => $data[$key.'budget_cedula_id'], 'amount' => $type === BudgetMovementDetail::TYPE_ORIGIN || $data['movement_type'] === BudgetMovement::TYPE_DECREASE ? -abs($data['total_amount']) : abs($data['total_amount'])]);
        }
    }

    private function ensureCanSubmit(User $actor, array $data): void
    {
        $centerId = $data['movement_type'] === BudgetMovement::TYPE_TRANSFER ? $data['destination_cost_center_id'] : $data['cost_center_id'];
        abort_unless($this->ownedCenters($actor)->whereKey($centerId)->exists(), 403, 'Solo puedes solicitar movimientos para centros de costo de los que eres responsable.');
        if ($data['movement_type'] === BudgetMovement::TYPE_TRANSFER) {
            abort_if((int) $data['origin_cost_center_id'] === (int) $data['destination_cost_center_id'], 422, 'El centro origen debe ser diferente al destino.');
        }
    }

    private function ownedCenters(User $user)
    {
        return CostCenter::active()->where('responsible_user_id', $user->id);
    }

    private function approvalSettings(): ?BudgetMovementApprovalSetting
    {
        return BudgetMovementApprovalSetting::query()->first();
    }

    private function settingsOrFail(): BudgetMovementApprovalSetting
    {
        $settings = $this->approvalSettings();
        abort_unless($settings?->director_user_id, 422, 'La autorización ejecutiva de movimientos presupuestales no está configurada.');

        return $settings;
    }

    private function record(BudgetMovement $m, string $stage, string $action, User $actor, ?string $comments = null): void
    {
        $m->decisions()->create(['stage' => $stage, 'action' => $action, 'actor_user_id' => $actor->id, 'comments' => $comments]);
    }

    private function canOriginApprove(User $actor, BudgetMovement $m): bool
    {
        return $m->status === BudgetMovement::STATUS_PENDING_ORIGIN && $m->isTransfer() && (int) $m->originDetails()->value('cost_center_id') !== 0 && CostCenter::whereKey($m->originDetails()->value('cost_center_id'))->where('responsible_user_id', $actor->id)->exists();
    }

    private function visibleTo(User $user)
    {
        if ($user->hasRole('superadmin') || $this->approvalSettings()?->canApprove($user)) {
            return BudgetMovement::query();
        }

        return BudgetMovement::query()->where(fn ($q) => $q->where('created_by', $user->id)->orWhereHas('details.costCenter', fn ($centers) => $centers->where('responsible_user_id', $user->id)));
    }

    private function ensureVisible(User $user, BudgetMovement $movement): void
    {
        abort_unless($this->visibleTo($user)->whereKey($movement->id)->exists(), 403);
    }

    private function notify(?User $recipient, BudgetMovement $movement, string $message): void
    {
        if ($recipient) {
            app(\App\Services\SafeNotificationService::class)->notify(
                new BudgetMovementWorkflowNotification($movement, $message),
                [$recipient],
                'de movimiento presupuestal',
                (string) $movement->id,
                route('budget_movements.show', $movement),
            );
        }
    }

    private function notifyExecutives(BudgetMovementApprovalSetting $settings, BudgetMovement $movement, string $message): void
    {
        User::query()->whereIn('id', $settings->executiveApproverIds())->get()->each(fn (User $u) => $this->notify($u, $movement, $message));
    }

    private function applyMovement(BudgetMovement $movement, int $actorId): void
    {
        foreach ($movement->details as $detail) {
            $budget = AnnualBudget::where('cost_center_id', $detail->cost_center_id)->where('fiscal_year', $movement->fiscal_year)->firstOrFail();
            $distributions = BudgetMonthlyDistribution::where('annual_budget_id', $budget->id)->where('month', $detail->month)->where('expense_category_id', $detail->expense_category_id)->when($detail->budget_cedula_id, fn ($q) => $q->where('budget_cedula_id', $detail->budget_cedula_id))->lockForUpdate()->get();
            if ($detail->amount < 0) {
                $available = $distributions->sum(fn ($d) => $d->getAvailableAmount());
                abort_if($available < abs((float) $detail->amount), 422, 'El centro origen ya no cuenta con presupuesto disponible.');
                $remaining = abs((float) $detail->amount);
                foreach ($distributions->sortByDesc(fn ($d) => $d->getAvailableAmount()) as $distribution) {
                    $take = min($remaining, max(0, $distribution->getAvailableAmount()));
                    if ($take > 0) {
                        $distribution->update(['assigned_amount' => (float) $distribution->assigned_amount - $take, 'updated_by' => $actorId]);
                        $remaining -= $take;
                    } if ($remaining <= 0.000001) {
                        break;
                    }
                }
            } else {
                $distribution = $distributions->first();
                if ($distribution) {
                    $distribution->update(['assigned_amount' => (float) $distribution->assigned_amount + (float) $detail->amount, 'updated_by' => $actorId]);
                } else {
                    BudgetMonthlyDistribution::create(['annual_budget_id' => $budget->id, 'budget_cedula_id' => $detail->budget_cedula_id, 'expense_category_id' => $detail->expense_category_id, 'month' => $detail->month, 'assigned_amount' => $detail->amount, 'consumed_amount' => 0, 'committed_amount' => 0, 'created_by' => $actorId, 'updated_by' => $actorId]);
                }
            }
        }
    }
}
