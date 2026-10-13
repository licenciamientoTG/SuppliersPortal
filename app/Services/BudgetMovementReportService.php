<?php

namespace App\Services;

use App\Models\BudgetCedula;
use App\Models\BudgetMovement;
use App\Models\BudgetMovementDetail;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BudgetMovementReportService
{
    public function __construct(private readonly BudgetPositionService $positions) {}

    public function movements(Request $request, bool $paginate = true): array
    {
        $query = $this->filteredQuery($request)->with([
            'creator:id,name', 'approver:id,name', 'decisions.actor:id,name',
            'details.costCenter.company', 'details.expenseCategory:id,name', 'details.budgetCedula:id,name',
            'reversalOf:id', 'reversals:id,reversal_of_id', 'attachments',
        ])->orderBy('movement_date')->orderBy('id');

        $summary = $this->filteredQuery($request)
            ->select('movement_type')->selectRaw('COUNT(*) as movement_count, SUM(total_amount) as total_amount')
            ->groupBy('movement_type')->get();

        if ($paginate) {
            $page = $query->paginate(min(100, max(10, (int) $request->input('per_page', 25))))->withQueryString();
            $rows = $page->getCollection();
        } else {
            $rows = $query->get();
            $page = null;
        }

        return [
            'rows' => $rows->map(fn (BudgetMovement $movement) => $this->movementRow($movement))->values(),
            'pagination' => $page ? [
                'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(), 'total' => $page->total(),
            ] : null,
            'summary' => $summary->map(fn ($row) => [
                'type' => $row->movement_type, 'count' => (int) $row->movement_count,
                'amount' => (float) $row->total_amount,
            ])->values(),
        ];
    }

    /**
     * Conciliación por renglón: misma fórmula que RP-01 (`BudgetPositionService`), sin duplicarla.
     * Agrega el caso de línea con base capturada pero sin distribución vigente (dato inconsistente),
     * que `BudgetPositionService` no puede ver porque parte de las distribuciones.
     */
    public function reconciliation(Request $request): Collection
    {
        $filters = $this->filters($request);
        $allowedCenterIds = $this->allowedCostCenterIds($request);
        if ($allowedCenterIds->isEmpty()) {
            return collect();
        }

        $positions = $this->positions->positions([
            'cost_center_ids' => $allowedCenterIds->all(),
            'fiscal_year' => $filters['fiscal_year'],
            'company_ids' => $filters['company_ids'],
            'months' => $filters['month'] ? [$filters['month']] : [],
        ]);

        $lines = $positions
            ->groupBy(fn ($p) => $this->lineKey($p['cost_center_id'], $p['month'], $p['expense_category_id'], $p['budget_cedula_id']))
            ->map(function (Collection $rows) {
                $first = $rows->first();

                return $this->positions->summarize($rows) + [
                    'cost_center_id' => $first['cost_center_id'], 'fiscal_year' => $first['fiscal_year'],
                    'month' => $first['month'], 'expense_category_id' => $first['expense_category_id'],
                    'budget_cedula_id' => $first['budget_cedula_id'],
                ];
            });

        $merged = collect($lines->all() + $this->orphanBaselineLines($filters, $allowedCenterIds)->all());

        return $this->withLineNames($merged)
            ->sortBy(['company_name', 'cost_center_code', 'month', 'expense_category_name', 'budget_cedula_name'])
            ->values();
    }

    /** Líneas con base capturada que no tienen ninguna distribución vigente para esa llave. */
    private function orphanBaselineLines(array $filters, Collection $allowedCenterIds): Collection
    {
        $query = DB::table('budget_distribution_baselines as b')
            ->join('annual_budgets as ab', 'ab.id', '=', 'b.annual_budget_id')
            ->join('cost_centers as cc', 'cc.id', '=', 'ab.cost_center_id')
            ->where('ab.fiscal_year', $filters['fiscal_year'])
            ->whereIn('cc.id', $allowedCenterIds)
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('budget_monthly_distributions as d')
                    ->whereColumn('d.annual_budget_id', 'ab.id')
                    ->whereColumn('d.month', 'b.month')
                    ->whereColumn('d.expense_category_id', 'b.expense_category_id')
                    ->where(fn ($w) => $w->whereColumn('d.budget_cedula_id', 'b.budget_cedula_id')
                        ->orWhere(fn ($w2) => $w2->whereNull('d.budget_cedula_id')->whereNull('b.budget_cedula_id')))
                    ->whereNull('d.deleted_at');
            });
        $this->applyLineFilters($query, $filters);

        return collect($query->select(['ab.cost_center_id', 'b.month', 'b.expense_category_id', 'b.budget_cedula_id', 'b.original_amount'])->get())
            ->groupBy(fn ($row) => $this->lineKey($row->cost_center_id, $row->month, $row->expense_category_id, $row->budget_cedula_id))
            ->map(function (Collection $rows) use ($filters) {
                $first = $rows->first();
                $authorized = round((float) $rows->sum('original_amount'), 2);

                return [
                    'cost_center_id' => $first->cost_center_id, 'fiscal_year' => $filters['fiscal_year'],
                    'month' => (int) $first->month, 'expense_category_id' => $first->expense_category_id,
                    'budget_cedula_id' => $first->budget_cedula_id, 'current_budget' => 0.0,
                    'increases' => 0.0, 'decreases' => 0.0, 'authorized_amount' => $authorized,
                    'budget_difference' => $authorized,
                    'baseline_status' => abs($authorized) < 0.005 ? 'CONCILIA' : 'DIFERENCIA',
                ];
            });
    }

    /** Agrega nombres para mostrar y traduce al contrato de salida de RP-02. */
    private function withLineNames(Collection $lines): Collection
    {
        $centers = CostCenter::with('company')->whereIn('id', $lines->pluck('cost_center_id')->unique())->get()->keyBy('id');
        $categories = ExpenseCategory::whereIn('id', $lines->pluck('expense_category_id')->unique())->pluck('name', 'id');
        $cedulaIds = $lines->pluck('budget_cedula_id')->filter()->unique();
        $cedulas = $cedulaIds->isEmpty() ? collect() : BudgetCedula::whereIn('id', $cedulaIds)->pluck('name', 'id');

        return $lines->map(function (array $line) use ($centers, $categories, $cedulas) {
            $center = $centers->get($line['cost_center_id']);
            $hasBaseline = $line['authorized_amount'] !== null;

            return [
                'company_name' => $center?->company?->name, 'cost_center_id' => $line['cost_center_id'],
                'cost_center_code' => $center?->code, 'cost_center_name' => $center?->name,
                'fiscal_year' => $line['fiscal_year'], 'month' => $line['month'],
                'expense_category_id' => $line['expense_category_id'],
                'expense_category_name' => $categories->get($line['expense_category_id']),
                'budget_cedula_id' => $line['budget_cedula_id'],
                'budget_cedula_name' => $line['budget_cedula_id'] ? $cedulas->get($line['budget_cedula_id']) : null,
                'original_amount' => $line['authorized_amount'],
                'movement_total' => round($line['increases'] - $line['decreases'], 2),
                'current_amount' => $line['current_budget'],
                'has_baseline' => $hasBaseline,
                'reconstructed_amount' => $hasBaseline ? round($line['current_budget'] + $line['budget_difference'], 2) : null,
                'difference' => $line['budget_difference'],
                'reconciliation_status' => $line['baseline_status'],
            ];
        });
    }

    public function filteredQuery(Request $request): Builder
    {
        $filters = $this->filters($request);
        $query = BudgetMovement::query()->where('fiscal_year', $filters['fiscal_year']);
        $this->applyMovementVisibility($query, $request->user());

        $status = $filters['status'];
        if ($status === 'APROBADO') {
            $query->approved();
        } elseif ($status !== 'TODOS') {
            $query->where('status', $status);
        }
        if ($filters['date_from']) {
            $query->whereDate('movement_date', '>=', $filters['date_from']);
        }
        if ($filters['date_to']) {
            $query->whereDate('movement_date', '<=', $filters['date_to']);
        }
        if ($filters['types']) {
            $query->whereIn('movement_type', $filters['types']);
        }
        if ($filters['amount_min'] !== null) {
            $query->where('total_amount', '>=', $filters['amount_min']);
        }
        if ($filters['authorized_by']) {
            $query->whereHas('decisions', fn ($q) => $q->where('actor_user_id', $filters['authorized_by'])->where('action', 'APROBADO'));
        }
        if ($filters['company_ids']) {
            $query->whereHas('details.costCenter', fn ($q) => $q->whereIn('company_id', $filters['company_ids']));
        }
        if ($filters['origin_cost_center_id']) {
            $query->whereHas('details', fn ($q) => $q->where('detail_type', 'ORIGEN')->where('cost_center_id', $filters['origin_cost_center_id']));
        }
        if ($filters['destination_cost_center_id']) {
            $query->whereHas('details', fn ($q) => $q->where('detail_type', 'DESTINO')->where('cost_center_id', $filters['destination_cost_center_id']));
        }
        if ($filters['only_level_violations']) {
            // Traspaso entre centros de costo distintos (incluye empresas distintas) aprobado sin el titular de Dirección.
            $query->approved()
                ->where(fn ($q) => $q->whereNull('approval_level')->orWhere('approval_level', '<>', BudgetMovement::LEVEL_DIRECTION))
                ->whereHas('details', function ($q) {
                    $q->where('detail_type', BudgetMovementDetail::TYPE_ORIGIN)->whereExists(function ($dest) {
                        $dest->selectRaw('1')->from('budget_movement_details as dst')
                            ->whereColumn('dst.budget_movement_id', 'budget_movement_details.budget_movement_id')
                            ->where('dst.detail_type', BudgetMovementDetail::TYPE_DESTINATION)
                            ->whereColumn('dst.cost_center_id', '<>', 'budget_movement_details.cost_center_id');
                    });
                });
        }

        return $query;
    }

    private function movementRow(BudgetMovement $movement): array
    {
        $origin = $movement->details->firstWhere('detail_type', 'ORIGEN');
        $destination = $movement->details->firstWhere('detail_type', 'DESTINO');
        $decisionApproved = $movement->decisions->first(fn ($decision) => $decision->stage === 'DIRECCION' && $decision->action === 'APROBADO');
        $crossCostCenter = $origin && $destination && (int) $origin->cost_center_id !== (int) $destination->cost_center_id;
        $crossCompany = $crossCostCenter && $origin->costCenter?->company_id !== $destination->costCenter?->company_id;
        $levelRequired = $crossCostCenter ? BudgetMovement::LEVEL_DIRECTION : null;

        return [
            'id' => $movement->id, 'folio' => 'MP-'.$movement->id,
            'movement_date' => optional($movement->movement_date)->format('Y-m-d'),
            'movement_type' => $movement->movement_type, 'fiscal_year' => $movement->fiscal_year,
            'total_amount' => (float) $movement->total_amount, 'status' => $movement->status,
            'justification' => $movement->justification, 'requester' => $movement->creator?->name,
            'final_authorizer' => $movement->approver?->name ?? $decisionApproved?->actor?->name,
            'authorization_level_applied' => $movement->approval_level,
            'cross_cost_center' => (bool) $crossCostCenter,
            'cross_company' => (bool) $crossCompany,
            'authorization_level_required' => $levelRequired,
            // El suplente no cuenta como Dirección; solo se evalúa una vez aprobado el movimiento.
            'authorization_violation' => $levelRequired !== null && $movement->isApproved()
                && $movement->approval_level !== BudgetMovement::LEVEL_DIRECTION,
            'reversal_of' => $movement->reversal_of_id,
            'reversed_by' => $movement->reversals->first()?->id,
            'origin' => $origin ? $this->detailRow($origin) : null,
            'destination' => $destination ? $this->detailRow($destination) : null,
            'adjustment' => $movement->details->firstWhere('detail_type', 'AJUSTE') ? $this->detailRow($movement->details->firstWhere('detail_type', 'AJUSTE')) : null,
            'attachments' => $movement->attachments->map(fn ($a) => [
                'id' => $a->id, 'name' => $a->original_name, 'sha256' => $a->sha256,
                'url' => route('budget_movements.attachments.download', $a),
            ])->values(),
            'decisions' => $movement->decisions->map(fn ($d) => ['stage' => $d->stage, 'action' => $d->action, 'actor' => $d->actor?->name, 'at' => $d->created_at?->format('Y-m-d H:i'), 'comments' => $d->comments])->values(),
        ];
    }

    private function detailRow($detail): array
    {
        return [
            'company' => $detail->costCenter?->company?->name,
            'cost_center_id' => $detail->cost_center_id,
            'cost_center' => $detail->costCenter?->code.' · '.$detail->costCenter?->name,
            'month' => (int) $detail->month,
            'account' => $detail->expenseCategory?->name,
            'subaccount' => $detail->budgetCedula?->name,
            'amount' => (float) $detail->amount,
        ];
    }

    private function filters(Request $request): array
    {
        $ids = $request->input('company_ids', $request->input('company_id', []));
        if (! is_array($ids)) {
            $ids = $ids ? [$ids] : [];
        }

        return [
            'fiscal_year' => (int) $request->input('fiscal_year', now()->year),
            'status' => $request->input('status', 'APROBADO'),
            'date_from' => $request->input('date_from'), 'date_to' => $request->input('date_to'),
            'types' => array_values(array_filter((array) $request->input('movement_type', []))),
            'company_ids' => array_map('intval', $ids),
            'origin_cost_center_id' => $request->input('origin_cost_center_id'),
            'destination_cost_center_id' => $request->input('destination_cost_center_id'),
            'authorized_by' => $request->input('authorized_by'),
            'amount_min' => $request->filled('amount_min') ? (float) $request->input('amount_min') : null,
            'only_level_violations' => $request->boolean('only_level_violations'),
            'month' => $request->input('month'),
        ];
    }

    /** Roles que ven todo acotado a sus empresas asignadas, igual que RP-01 (superadmin aparte). */
    private const FULL_SCOPE_ROLES = ['accounting', 'general_director'];

    private function applyMovementVisibility(Builder $query, $user): void
    {
        $settings = \App\Models\BudgetMovementApprovalSetting::query()->first();
        if ($user->hasRole('superadmin') || $settings?->canApprove($user)) {
            return;
        }
        if ($user->hasRole(self::FULL_SCOPE_ROLES)) {
            $companyIds = $user->companies()->pluck('companies.id');
            if ($companyIds->isNotEmpty()) {
                $query->whereHas('details.costCenter', fn ($q) => $q->whereIn('company_id', $companyIds));
            }

            return;
        }
        $query->where(fn ($q) => $q->where('created_by', $user->id)
            ->orWhereHas('details.costCenter', fn ($centers) => $centers->where('responsible_user_id', $user->id)));
    }

    private function allowedCostCenterIds(Request $request): Collection
    {
        $user = $request->user();
        $settings = \App\Models\BudgetMovementApprovalSetting::query()->first();
        if ($user->hasRole('superadmin') || $settings?->canApprove($user)) {
            return DB::table('cost_centers')->pluck('id');
        }
        if ($user->hasRole(self::FULL_SCOPE_ROLES)) {
            $companyIds = $user->companies()->pluck('companies.id');

            return $companyIds->isEmpty() ? DB::table('cost_centers')->pluck('id')
                : DB::table('cost_centers')->whereIn('company_id', $companyIds)->pluck('id');
        }
        $movementIds = BudgetMovement::query()
            ->where(fn ($q) => $q->where('created_by', $user->id)->orWhereHas('details.costCenter', fn ($cc) => $cc->where('responsible_user_id', $user->id)))
            ->pluck('id');

        return DB::table('cost_centers')->where('responsible_user_id', $user->id)
            ->orWhereIn('id', DB::table('budget_movement_details')->whereIn('budget_movement_id', $movementIds)->select('cost_center_id'))
            ->pluck('id');
    }

    private function applyLineFilters($query, array $filters, string $monthColumn = 'b.month'): void
    {
        if ($filters['company_ids']) {
            $query->whereIn('cc.company_id', $filters['company_ids']);
        }
        if ($filters['month']) {
            $query->where($monthColumn, $filters['month']);
        }
    }

    private function lineKey($center, $month, $category, $cedula): string
    {
        return implode(':', [$center, $month, $category, $cedula ?: 0]);
    }
}
