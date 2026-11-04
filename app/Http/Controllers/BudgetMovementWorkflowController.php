<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveBudgetMovementRequest;
use App\Models\AnnualBudget;
use App\Models\BudgetCedula;
use App\Models\BudgetMonthlyDistribution;
use App\Models\BudgetMovement;
use App\Models\BudgetMovementApprovalSetting;
use App\Models\BudgetMovementAttachment;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    /**
     * Disponible por subcuenta de una cuenta, para mostrarlo como badge en el select de
     * subcuentas. Mismas reglas de acceso que budgetSnapshot.
     */
    public function cedulaBalances(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cost_center_id' => ['required', 'integer', Rule::exists('cost_centers', 'id')],
            'fiscal_year' => ['required', 'integer', 'between:2020,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')],
            'context' => ['required', Rule::in(['single', 'origin', 'destination'])],
        ]);

        $actor = $request->user();
        if ($data['context'] === 'origin') {
            abort_unless($this->ownedCenters($actor)->exists(), 403, 'No tienes centros de costo asignados como responsable.');
        } else {
            abort_unless($this->ownedCenters($actor)->whereKey($data['cost_center_id'])->exists(), 403, 'Solo puedes consultar el presupuesto de centros de costo de los que eres responsable.');
        }

        $budget = AnnualBudget::query()
            ->where('cost_center_id', $data['cost_center_id'])
            ->where('fiscal_year', $data['fiscal_year'])
            ->first();
        $balances = $budget
            ? BudgetMonthlyDistribution::query()
                ->where('annual_budget_id', $budget->id)
                ->where('month', $data['month'])
                ->where('expense_category_id', $data['expense_category_id'])
                ->whereNotNull('budget_cedula_id')
                ->get()
                ->groupBy('budget_cedula_id')
                ->map(fn (Collection $rows) => round((float) $rows->sum(fn (BudgetMonthlyDistribution $row) => $row->getAvailableAmount()), 2))
            : collect();

        return response()->json(['has_budget' => $budget !== null, 'balances' => $balances]);
    }

    public function store(SaveBudgetMovementRequest $request): RedirectResponse|JsonResponse
    {
        $actor = $request->user();
        $data = $request->validated();
        $this->ensureCanSubmit($actor, $data);
        $settings = $this->settingsForSubmission();

        $movement = DB::transaction(function () use ($data, $actor, $request) {
            $status = $data['movement_type'] === BudgetMovement::TYPE_TRANSFER
                ? BudgetMovement::STATUS_PENDING_ORIGIN
                : BudgetMovement::STATUS_PENDING_EXECUTIVE;
            $movement = BudgetMovement::create([
                'movement_type' => $data['movement_type'], 'fiscal_year' => $data['fiscal_year'],
                'movement_date' => $data['movement_date'], 'total_amount' => $data['total_amount'],
                'justification' => $data['justification'], 'status' => $status, 'created_by' => $actor->id,
            ]);
            $this->syncDetails($movement, $data);
            $this->storeSupportFile($movement, $request);
            $this->record($movement, BudgetMovementDecision::STAGE_EXECUTIVE, BudgetMovementDecision::ACTION_SUBMITTED, $actor);

            return $movement->fresh(['details.costCenter']);
        });

        if ($movement->isTransfer()) {
            $originOwner = $movement->originDetails()->with('costCenter.responsible')->first()?->costCenter?->responsible;
            $this->notify($originOwner, $movement, 'Tienes una transferencia presupuestal pendiente de validar como centro de costo origen.');
        } else {
            $this->notifyExecutives($settings, $movement, 'Tienes una solicitud de movimiento presupuestal pendiente de aprobación.');
        }

        return $this->submitted($request, $movement, 'Solicitud enviada correctamente.');
    }

    public function show(Request $request, BudgetMovement $budgetMovement): View
    {
        $this->ensureVisible($request->user(), $budgetMovement);
        $budgetMovement->load(['details.costCenter.company', 'details.costCenter.responsible', 'details.expenseCategory', 'details.budgetCedula', 'creator', 'approver', 'decisions.actor', 'attachments.uploader', 'reversalOf', 'reversals']);

        return view('budget_movements.workflow.show', [
            'budgetMovement' => $budgetMovement,
            'canOriginApprove' => $this->canOriginApprove($request->user(), $budgetMovement),
            'canExecutiveApprove' => $this->approvalSettings()?->canApprove($request->user()) && $budgetMovement->status === BudgetMovement::STATUS_PENDING_EXECUTIVE,
            'canReverse' => $budgetMovement->isApproved() && $budgetMovement->reversals->isEmpty() && $this->visibleTo($request->user())->whereKey($budgetMovement->id)->exists(),
            'responsibility' => $this->responsibility($budgetMovement, $request->user()),
        ]);
    }

    /**
     * Quién tiene la solicitud en su paso actual (o quién la cerró), para mostrarlo en el detalle.
     * Usa las mismas reglas que canOriginApprove y BudgetMovementApprovalSetting::canApprove.
     */
    private function responsibility(BudgetMovement $movement, User $viewer): array
    {
        $person = fn (?User $user, string $role, ?string $note = null) => $user
            ? ['name' => $user->name, 'role' => $role, 'note' => $note, 'is_you' => $user->id === $viewer->id]
            : null;
        $lastDecision = fn (string $action) => $movement->decisions->where('action', $action)->sortByDesc('created_at')->first();
        $settings = $this->approvalSettings()?->loadMissing(['director', 'substitute']);
        $executives = function () use ($settings, $person) {
            $people = [$person($settings?->director, 'Director General · titular')];
            $note = null;
            $substitute = $settings?->substitute;
            if ($substitute) {
                $period = $settings->substitute_starts_at?->format('d/m/Y H:i').' al '.$settings->substitute_ends_at?->format('d/m/Y H:i');
                if (in_array($substitute->id, $settings->executiveApproverIds(), true)) {
                    $people[] = $person($substitute, 'Suplente de Dirección', 'Suplencia vigente del '.$period.'.');
                } else {
                    $note = $substitute->name.' es suplente del '.$period.'; fuera de ese periodo no puede autorizar.';
                }
            }

            return [array_values(array_filter($people)), $note];
        };

        switch ($movement->status) {
            case BudgetMovement::STATUS_PENDING_ORIGIN:
                $originCenter = $movement->details->firstWhere('detail_type', BudgetMovementDetail::TYPE_ORIGIN)?->costCenter;
                [$directors] = $executives();

                return [
                    'tone' => 'info', 'title' => 'Esperando la validación del centro origen',
                    'verb' => 'Puede validar o devolver',
                    'people' => array_values(array_filter([$person($originCenter?->responsible, 'Responsable de '.($originCenter ? $originCenter->code.' · '.$originCenter->name : 'el centro origen'))])),
                    'also' => 'También puede validarla o devolverla cualquier Superadministrador'.($viewer->hasRole('superadmin') ? ' (tú tienes ese rol).' : '.'),
                    'alert' => $originCenter?->responsible ? null : 'El centro origen no tiene responsable asignado; mientras no se asigne, solo un Superadministrador puede validar esta transferencia.',
                    'next' => 'Después pasa a Dirección General'.($directors ? ': '.collect($directors)->pluck('name')->join(', ', ' o ') : '').'.',
                ];
            case BudgetMovement::STATUS_PENDING_EXECUTIVE:
                [$directors, $note] = $executives();

                return [
                    'tone' => 'info', 'title' => 'Esperando la autorización de Dirección General',
                    'verb' => count($directors) > 1 ? 'Cualquiera de estas personas puede autorizar o rechazar' : 'Puede autorizar o rechazar',
                    'people' => $directors,
                    'alert' => $directors ? null : 'No hay un Director General configurado, así que nadie puede autorizar esta solicitud. Un superadministrador debe configurarlo en Movimientos presupuestales > Configuración.',
                    'next' => $note,
                ];
            case BudgetMovement::STATUS_RETURNED:
                $returned = $lastDecision(BudgetMovementDecision::ACTION_RETURNED);

                return [
                    'tone' => 'warning', 'title' => 'Devuelta para corrección',
                    'verb' => 'Debe corregirla y reenviarla',
                    'people' => array_values(array_filter([$person($movement->creator, 'Solicitante')])),
                    'alert' => null,
                    'reason' => $returned ? ['by' => $returned->actor?->name, 'text' => $returned->comments, 'at' => $returned->created_at] : null,
                    'next' => null,
                ];
            case BudgetMovement::STATUS_APPROVED:
                return [
                    'tone' => 'success', 'title' => 'Autorizada y aplicada al presupuesto',
                    'verb' => 'Autorizó',
                    'people' => array_values(array_filter([$person($movement->approver, BudgetMovement::LEVEL_LABELS[$movement->approval_level] ?? 'Dirección', $movement->approved_at ? 'El '.$movement->approved_at->format('d/m/Y \a \l\a\s H:i').'.' : null)])),
                    'alert' => null, 'next' => null,
                ];
            case BudgetMovement::STATUS_REJECTED:
                $rejected = $lastDecision(BudgetMovementDecision::ACTION_REJECTED);

                return [
                    'tone' => 'danger', 'title' => 'Rechazada',
                    'verb' => 'Rechazó',
                    'people' => array_values(array_filter([$person($rejected?->actor, 'Dirección General', $rejected ? 'El '.$rejected->created_at->format('d/m/Y \a \l\a\s H:i').'.' : null)])),
                    'alert' => null,
                    'reason' => $rejected ? ['by' => $rejected->actor?->name, 'text' => $rejected->comments, 'at' => $rejected->created_at] : null,
                    'next' => null,
                ];
            default:
                return ['tone' => 'info', 'title' => 'Estado: '.$movement->status, 'verb' => null, 'people' => [], 'alert' => null, 'next' => null];
        }
    }

    public function edit(Request $request, BudgetMovement $budgetMovement): View
    {
        abort_unless($budgetMovement->created_by === $request->user()->id && $budgetMovement->isReturned(), 403);
        $ownedCostCenters = $this->ownedCenters($request->user())->get();
        $budgetMovement->load('details');

        $originIds = $budgetMovement->details->where('detail_type', BudgetMovementDetail::TYPE_ORIGIN)->pluck('cost_center_id')->all();

        return view('budget_movements.workflow.edit', array_merge($this->formData($ownedCostCenters, $originIds), compact('budgetMovement')));
    }

    public function update(SaveBudgetMovementRequest $request, BudgetMovement $budgetMovement): RedirectResponse|JsonResponse
    {
        $actor = $request->user();
        abort_unless($budgetMovement->created_by === $actor->id && $budgetMovement->isReturned(), 403);
        $data = $request->validated();
        $this->ensureCanSubmit($actor, $data);
        $settings = $this->settingsForSubmission();

        $movement = DB::transaction(function () use ($budgetMovement, $data, $actor, $request) {
            $status = $data['movement_type'] === BudgetMovement::TYPE_TRANSFER ? BudgetMovement::STATUS_PENDING_ORIGIN : BudgetMovement::STATUS_PENDING_EXECUTIVE;
            $budgetMovement->update(array_merge(collect($data)->only(['movement_type', 'fiscal_year', 'movement_date', 'total_amount', 'justification'])->all(), ['status' => $status]));
            $budgetMovement->details()->delete();
            $this->syncDetails($budgetMovement, $data);
            if ($request->hasFile('supporting_document')) {
                $this->storeSupportFile($budgetMovement, $request);
            }
            $this->record($budgetMovement, BudgetMovementDecision::STAGE_EXECUTIVE, BudgetMovementDecision::ACTION_SUBMITTED, $actor, 'Solicitud corregida y reenviada.');

            return $budgetMovement->fresh(['details.costCenter']);
        });

        if ($movement->isTransfer()) {
            $this->notify($movement->originDetails()->with('costCenter.responsible')->first()?->costCenter?->responsible, $movement, 'La transferencia fue corregida y requiere tu validación como origen.');
        } else {
            $this->notifyExecutives($settings, $movement, 'La solicitud corregida requiere aprobación ejecutiva.');
        }

        return $this->submitted($request, $movement, 'Solicitud corregida y reenviada.');
    }

    public function reverse(Request $request, BudgetMovement $budgetMovement): RedirectResponse
    {
        $this->ensureVisible($request->user(), $budgetMovement);
        $this->requireOr($budgetMovement->isApproved(), 'Solo se pueden revertir movimientos aprobados.');
        $this->requireOr(! $budgetMovement->reversals()->exists(), 'Este movimiento ya tiene una reversa registrada.');
        $data = $request->validate(['justification' => ['required', 'string', 'min:10', 'max:1000']], $this->textMessages('justification', 'el motivo de la reversa'));
        $budgetMovement->load('details');
        $settings = $this->settingsForSubmission();

        $reversal = DB::transaction(function () use ($budgetMovement, $request, $data) {
            $type = match ($budgetMovement->movement_type) {
                BudgetMovement::TYPE_INCREASE => BudgetMovement::TYPE_DECREASE,
                BudgetMovement::TYPE_DECREASE => BudgetMovement::TYPE_INCREASE,
                default => BudgetMovement::TYPE_TRANSFER,
            };
            $status = $type === BudgetMovement::TYPE_TRANSFER
                ? BudgetMovement::STATUS_PENDING_ORIGIN
                : BudgetMovement::STATUS_PENDING_EXECUTIVE;
            $movement = BudgetMovement::create([
                'movement_type' => $type,
                'fiscal_year' => $budgetMovement->fiscal_year,
                'movement_date' => now()->toDateString(),
                'total_amount' => $budgetMovement->total_amount,
                'justification' => 'Reversa del movimiento #'.$budgetMovement->id.'. '.$data['justification'],
                'status' => $status,
                'created_by' => $request->user()->id,
                'reversal_of_id' => $budgetMovement->id,
            ]);

            foreach ($budgetMovement->details as $detail) {
                $isTransfer = $type === BudgetMovement::TYPE_TRANSFER;
                $detailType = $isTransfer
                    ? ($detail->detail_type === BudgetMovementDetail::TYPE_ORIGIN ? BudgetMovementDetail::TYPE_DESTINATION : BudgetMovementDetail::TYPE_ORIGIN)
                    : BudgetMovementDetail::TYPE_ADJUSTMENT;
                BudgetMovementDetail::create([
                    'budget_movement_id' => $movement->id,
                    'detail_type' => $detailType,
                    'cost_center_id' => $detail->cost_center_id,
                    'month' => $detail->month,
                    'expense_category_id' => $detail->expense_category_id,
                    'budget_cedula_id' => $detail->budget_cedula_id,
                    'amount' => -1 * (float) $detail->amount,
                ]);
            }
            $this->record($movement, BudgetMovementDecision::STAGE_EXECUTIVE, BudgetMovementDecision::ACTION_SUBMITTED, $request->user(), 'Reversa del movimiento #'.$budgetMovement->id);

            return $movement;
        });

        if ($reversal->isTransfer()) {
            $this->notify($reversal->originDetails()->with('costCenter.responsible')->first()?->costCenter?->responsible, $reversal, 'Hay una reversa de transferencia pendiente de validar.');
        } else {
            $this->notifyExecutives($settings, $reversal, 'Hay una reversa presupuestal pendiente de aprobación.');
        }

        return redirect()->route('budget_movements.show', $reversal)->with('success', 'La reversa se registró y seguirá el flujo normal de aprobación.');
    }

    public function downloadAttachment(Request $request, BudgetMovementAttachment $attachment)
    {
        abort_unless($this->visibleTo($request->user())->whereKey($attachment->budget_movement_id)->exists(), 403);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->file_path), 404);

        return Storage::disk($attachment->disk)->download($attachment->file_path, $attachment->original_name);
    }

    public function approveOrigin(Request $request, BudgetMovement $budgetMovement): RedirectResponse
    {
        $this->ensureVisible($request->user(), $budgetMovement);
        $this->requireOr($budgetMovement->status === BudgetMovement::STATUS_PENDING_ORIGIN, 'Esta solicitud ya no está pendiente de validación del origen; probablemente alguien más ya la atendió. Revisa el historial.');
        abort_unless($this->canOriginApprove($request->user(), $budgetMovement), 403);
        $settings = $this->settingsForSubmission();
        DB::transaction(function () use ($budgetMovement, $request) {
            $movement = BudgetMovement::query()->lockForUpdate()->findOrFail($budgetMovement->id);
            $this->requireOr($movement->status === BudgetMovement::STATUS_PENDING_ORIGIN, 'Esta solicitud ya no está pendiente de validación del origen; probablemente alguien más ya la atendió. Revisa el historial.');
            $movement->update(['status' => BudgetMovement::STATUS_PENDING_EXECUTIVE]);
            $this->record($movement, BudgetMovementDecision::STAGE_ORIGIN, BudgetMovementDecision::ACTION_APPROVED, $request->user());
        });
        $this->notifyExecutives($settings, $budgetMovement->fresh(), 'Una transferencia fue validada por el centro origen y requiere aprobación ejecutiva.');

        return back()->with('success', 'Transferencia validada y enviada a Dirección General.');
    }

    public function returnToRequester(Request $request, BudgetMovement $budgetMovement): RedirectResponse
    {
        $data = $request->validate(['comments' => ['required', 'string', 'min:10', 'max:1000']], $this->textMessages('comments', 'qué debe corregirse'));
        $this->ensureVisible($request->user(), $budgetMovement);
        $this->requireOr($budgetMovement->status === BudgetMovement::STATUS_PENDING_ORIGIN, 'Esta solicitud ya no está pendiente de validación del origen, así que no se puede devolver. Revisa el historial.');
        abort_unless($this->canOriginApprove($request->user(), $budgetMovement), 403);
        DB::transaction(function () use ($budgetMovement, $request, $data) {
            $movement = BudgetMovement::query()->lockForUpdate()->findOrFail($budgetMovement->id);
            $this->requireOr($movement->status === BudgetMovement::STATUS_PENDING_ORIGIN, 'Esta solicitud ya no está pendiente de validación del origen, así que no se puede devolver. Revisa el historial.');
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
            $this->requireOr($movement->status === BudgetMovement::STATUS_PENDING_EXECUTIVE, 'Esta solicitud ya no está pendiente de autorización; probablemente alguien más ya la atendió. Revisa el historial.');
            if ($this->isCrossCompanyTransfer($movement)) {
                $this->requireOr($movement->decisions()->where('stage', BudgetMovementDecision::STAGE_ORIGIN)->where('action', BudgetMovementDecision::ACTION_APPROVED)->exists(), 'Una transferencia entre empresas requiere primero la validación del centro de costo origen.');
                abort_unless($this->approvalSettings()?->canApprove($request->user()), 403, 'La transferencia entre empresas requiere aprobación de Dirección.');
            }
            $this->applyMovement($movement, $request->user()->id);
            $level = (int) $this->approvalSettings()?->director_user_id === (int) $request->user()->id
                ? BudgetMovement::LEVEL_DIRECTION
                : BudgetMovement::LEVEL_SUBSTITUTE;
            $movement->update(['status' => BudgetMovement::STATUS_APPROVED, 'approved_by' => $request->user()->id, 'approved_at' => now(), 'approval_level' => $level]);
            $this->record($movement, BudgetMovementDecision::STAGE_EXECUTIVE, BudgetMovementDecision::ACTION_APPROVED, $request->user());
        });
        $this->notify($budgetMovement->creator, $budgetMovement->fresh(), 'Tu movimiento presupuestal fue aprobado y aplicado al presupuesto.');

        return back()->with('success', 'Movimiento aprobado y aplicado al presupuesto.');
    }

    public function rejectExecutive(Request $request, BudgetMovement $budgetMovement): RedirectResponse
    {
        $data = $request->validate(['comments' => ['required', 'string', 'min:10', 'max:1000']], $this->textMessages('comments', 'el motivo del rechazo'));
        abort_unless($this->approvalSettings()?->canApprove($request->user()), 403);
        DB::transaction(function () use ($budgetMovement, $request, $data) {
            $movement = BudgetMovement::query()->lockForUpdate()->findOrFail($budgetMovement->id);
            $this->requireOr($movement->status === BudgetMovement::STATUS_PENDING_EXECUTIVE, 'Esta solicitud ya no está pendiente de autorización, así que no se puede rechazar. Revisa el historial.');
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
        ], [
            'director_user_id.required' => 'Selecciona al Director General titular.',
            'director_user_id.exists' => 'El titular seleccionado no existe.',
            'substitute_user_id.different' => 'El suplente debe ser una persona distinta al titular.',
            'substitute_user_id.exists' => 'El suplente seleccionado no existe.',
            'substitute_starts_at.required_with' => 'Indica desde cuándo es válida la suplencia.',
            'substitute_starts_at.date' => 'La fecha de inicio de la suplencia no es válida.',
            'substitute_ends_at.required_with' => 'Indica hasta cuándo es válida la suplencia.',
            'substitute_ends_at.date' => 'La fecha de fin de la suplencia no es válida.',
            'substitute_ends_at.after' => 'El fin de la suplencia debe ser posterior a su inicio.',
        ]);
        $this->requireOr(User::role('general_director')->whereKey($data['director_user_id'])->where('is_active', true)->exists(), 'El titular debe ser un usuario activo con el rol de Director General (general_director).', 'director_user_id');
        if (! empty($data['substitute_user_id'])) {
            $this->requireOr(User::whereKey($data['substitute_user_id'])->where('is_active', true)->exists(), 'El suplente debe ser un usuario activo.', 'substitute_user_id');
        }
        BudgetMovementApprovalSetting::query()->firstOrNew()->fill(array_merge($data, ['updated_by' => $request->user()->id]))->save();

        return back()->with('success', 'Configuración de autorización guardada.');
    }

    /**
     * Centros que se ofrecen como origen de una transferencia: los activos de las empresas
     * a las que pertenecen los centros del usuario (más los propios y, al editar, el origen ya
     * guardado para no perderlo). Es un filtro de captura; la autorización no cambia.
     */
    private function originCenterOptions(Collection $ownedCostCenters, array $includeIds = []): Collection
    {
        $companyIds = $ownedCostCenters->pluck('company_id')->filter()->unique()->values();

        return CostCenter::query()
            ->where(fn ($query) => $query
                ->where(fn ($active) => $active->active()->whereIn('company_id', $companyIds))
                ->orWhereIn('id', $ownedCostCenters->pluck('id'))
                ->orWhereIn('id', array_filter($includeIds)))
            ->with('responsible:id,name')
            ->orderBy('name')
            ->get();
    }

    private function formData(Collection $ownedCostCenters, array $includeOriginIds = []): array
    {
        return ['ownedCostCenters' => $ownedCostCenters, 'originCostCenters' => $this->originCenterOptions($ownedCostCenters, $includeOriginIds), 'expenseCategories' => ExpenseCategory::orderBy('name')->get(), 'budgetCedulas' => BudgetCedula::active()->notDeleted()->orderBy('name')->get(['id', 'expense_category_id', 'name']), 'currentYear' => now()->year];
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
        // Transferir entre cuentas del mismo centro está permitido; SaveBudgetMovementRequest
        // solo exige que origen y destino difieran en centro, mes, cuenta o subcuenta.
        abort_unless($this->ownedCenters($actor)->whereKey($centerId)->exists(), 403, 'Solo puedes solicitar movimientos para centros de costo de los que eres responsable.');
    }

    /**
     * Centros sobre los que el usuario puede solicitar movimientos: los que tiene como
     * responsable, o todos los activos si es superadmin.
     */
    private function ownedCenters(User $user)
    {
        if ($user->hasRole('superadmin')) {
            return CostCenter::active();
        }

        return CostCenter::active()->where('responsible_user_id', $user->id);
    }

    private function approvalSettings(): ?BudgetMovementApprovalSetting
    {
        return BudgetMovementApprovalSetting::query()->first();
    }

    /**
     * Al capturar una solicitud, la falta de configuración se reporta como error de
     * formulario (no como página 422) para que el usuario no pierda lo capturado.
     */
    private function settingsForSubmission(): BudgetMovementApprovalSetting
    {
        $settings = $this->approvalSettings();
        if (! $settings?->director_user_id) {
            throw ValidationException::withMessages(['approval_settings' => 'No se puede continuar: no hay un Director General configurado para autorizar movimientos presupuestales. Pide a un superadministrador que lo configure en Movimientos presupuestales > Configuración.']);
        }

        return $settings;
    }

    private function submitted(Request $request, BudgetMovement $movement, string $message): RedirectResponse|JsonResponse
    {
        $url = route('budget_movements.show', $movement);
        if ($request->expectsJson()) {
            session()->flash('success', $message);

            return response()->json(['message' => $message, 'redirect' => $url]);
        }

        return redirect($url)->with('success', $message);
    }

    /**
     * Regla de negocio de una acción del flujo. Si no se cumple, regresa a la pantalla anterior
     * con el mensaje (o lo devuelve como JSON 422) en lugar de mostrar una página de error;
     * dentro de una transacción, la excepción además revierte lo que se hubiera aplicado.
     */
    /** Mensajes en español para un campo de texto libre de 10 a 1000 caracteres. */
    private function textMessages(string $field, string $label): array
    {
        return [
            "{$field}.required" => "Escribe {$label}.",
            "{$field}.min" => 'Explica '.$label.' con al menos 10 caracteres.',
            "{$field}.max" => ucfirst($label).' no debe exceder 1000 caracteres.',
        ];
    }

    private function requireOr(bool $condition, string $message, string $field = 'workflow'): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    private function record(BudgetMovement $m, string $stage, string $action, User $actor, ?string $comments = null): void
    {
        $m->decisions()->create(['stage' => $stage, 'action' => $action, 'actor_user_id' => $actor->id, 'comments' => $comments]);
    }

    private function storeSupportFile(BudgetMovement $movement, Request $request): void
    {
        $file = $request->file('supporting_document');
        if (! $file) {
            return;
        }

        $path = $file->store('budget-movements/'.$movement->id, 'local');
        BudgetMovementAttachment::create([
            'budget_movement_id' => $movement->id,
            'disk' => 'local',
            'file_path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'sha256' => hash_file('sha256', Storage::disk('local')->path($path)),
            'uploaded_by' => $request->user()->id,
        ]);
    }

    private function canOriginApprove(User $actor, BudgetMovement $m): bool
    {
        if ($m->status !== BudgetMovement::STATUS_PENDING_ORIGIN || ! $m->isTransfer()) {
            return false;
        }
        // El superadministrador también puede validar (o devolver) en nombre del centro origen.
        if ($actor->hasRole('superadmin')) {
            return true;
        }
        $originCenterId = (int) $m->originDetails()->value('cost_center_id');

        return $originCenterId !== 0 && CostCenter::whereKey($originCenterId)->where('responsible_user_id', $actor->id)->exists();
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
            $budget = AnnualBudget::where('cost_center_id', $detail->cost_center_id)->where('fiscal_year', $movement->fiscal_year)->first();
            $this->requireOr($budget !== null, sprintf('No se aplicó el movimiento: %s no tiene presupuesto anual registrado para %d. Registra el presupuesto o rechaza la solicitud.', $detail->costCenter?->name ?? 'el centro de costo', $movement->fiscal_year));
            $distributions = BudgetMonthlyDistribution::where('annual_budget_id', $budget->id)->where('month', $detail->month)->where('expense_category_id', $detail->expense_category_id)->when($detail->budget_cedula_id, fn ($q) => $q->where('budget_cedula_id', $detail->budget_cedula_id))->lockForUpdate()->get();
            if ($detail->amount < 0) {
                $available = $distributions->sum(fn ($d) => $d->getAvailableAmount());
                $this->requireOr($available >= abs((float) $detail->amount), sprintf(
                    'No se aplicó el movimiento: %s ya no tiene saldo suficiente en %s (%s). Disponible: $%s; se requieren $%s. Puedes rechazarlo o pedir que se corrija el monto.',
                    $detail->costCenter?->name ?? 'el centro origen', $detail->budgetCedula?->name ?? $detail->expenseCategory?->name ?? 'la subcuenta', $detail->month_name,
                    number_format($available, 2), number_format(abs((float) $detail->amount), 2),
                ));
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

    private function isCrossCompanyTransfer(BudgetMovement $movement): bool
    {
        if (! $movement->isTransfer()) {
            return false;
        }
        $details = $movement->details->loadMissing('costCenter:id,company_id');
        $origin = $details->firstWhere('detail_type', BudgetMovementDetail::TYPE_ORIGIN);
        $destination = $details->firstWhere('detail_type', BudgetMovementDetail::TYPE_DESTINATION);

        return $origin?->costCenter?->company_id !== null
            && $destination?->costCenter?->company_id !== null
            && (int) $origin->costCenter->company_id !== (int) $destination->costCenter->company_id;
    }
}
