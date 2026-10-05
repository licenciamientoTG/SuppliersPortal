<?php

namespace App\Services;

use App\Models\BudgetMovement;
use App\Models\BudgetMovementDetail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BudgetMovementReportService
{
    public function movements(Request $request, bool $paginate = true): array
    {
        $query = $this->filteredQuery($request)->with([
            'creator:id,name', 'approver:id,name', 'decisions.actor:id,name',
            'details.costCenter.company', 'details.expenseCategory:id,name', 'details.budgetCedula:id,name',
            'reversalOf:id', 'reversals:id,reversal_of_id',
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

    public function reconciliation(Request $request): Collection
    {
        $filters = $this->filters($request);
        $allowedCenterIds = $this->allowedCostCenterIds($request);
        if ($allowedCenterIds->isEmpty()) {
            return collect();
        }
        $lines = collect();

        $baselineQuery = DB::table('budget_distribution_baselines as b')
            ->join('annual_budgets as ab', 'ab.id', '=', 'b.annual_budget_id')
            ->join('cost_centers as cc', 'cc.id', '=', 'ab.cost_center_id')
            ->join('companies as co', 'co.id', '=', 'cc.company_id')
            ->join('expense_categories as ec', 'ec.id', '=', 'b.expense_category_id')
            ->leftJoin('budget_cedulas as bc', 'bc.id', '=', 'b.budget_cedula_id')
            ->where('ab.fiscal_year', $filters['fiscal_year']);
        $this->applyLineFilters($baselineQuery, $filters);
        $baselineQuery->whereIn('cc.id', $allowedCenterIds);
        foreach ($baselineQuery->select([
            'cc.id as cost_center_id', 'cc.code as cost_center_code', 'cc.name as cost_center_name',
            'co.name as company_name', 'ab.fiscal_year', 'b.month', 'b.expense_category_id',
            'ec.name as expense_category_name', 'b.budget_cedula_id', 'bc.name as budget_cedula_name',
            'b.original_amount',
        ])->get() as $row) {
            $key = $this->lineKey($row->cost_center_id, $row->month, $row->expense_category_id, $row->budget_cedula_id);
            $lines[$key] = $this->emptyLine($row) + ['original_amount' => 0.0, 'movement_total' => 0.0, 'current_amount' => 0.0, 'has_baseline' => true];
            $line = $lines[$key];
            $line['original_amount'] += (float) $row->original_amount;
            $lines[$key] = $line;
        }

        $currentQuery = DB::table('budget_monthly_distributions as d')
            ->join('annual_budgets as ab', 'ab.id', '=', 'd.annual_budget_id')
            ->join('cost_centers as cc', 'cc.id', '=', 'ab.cost_center_id')
            ->join('companies as co', 'co.id', '=', 'cc.company_id')
            ->join('expense_categories as ec', 'ec.id', '=', 'd.expense_category_id')
            ->leftJoin('budget_cedulas as bc', 'bc.id', '=', 'd.budget_cedula_id')
            ->whereNull('d.deleted_at')->whereNull('ab.deleted_at')
            ->where('ab.status', 'APROBADO')->where('ab.fiscal_year', $filters['fiscal_year']);
        $this->applyLineFilters($currentQuery, $filters, 'd.month');
        $currentQuery->whereIn('cc.id', $allowedCenterIds);
        foreach ($currentQuery->select([
            'cc.id as cost_center_id', 'cc.code as cost_center_code', 'cc.name as cost_center_name',
            'co.name as company_name', 'ab.fiscal_year', 'd.month', 'd.expense_category_id',
            'ec.name as expense_category_name', 'd.budget_cedula_id', 'bc.name as budget_cedula_name',
            'd.assigned_amount as current_amount',
        ])->get() as $row) {
            $key = $this->lineKey($row->cost_center_id, $row->month, $row->expense_category_id, $row->budget_cedula_id);
            if (! isset($lines[$key])) {
                $lines[$key] = $this->emptyLine($row) + ['original_amount' => null, 'movement_total' => 0.0, 'current_amount' => 0.0, 'has_baseline' => false];
            }
            $line = $lines[$key];
            $line['current_amount'] += (float) $row->current_amount;
            $lines[$key] = $line;
        }

        $movementQuery = BudgetMovement::query()->where('fiscal_year', $filters['fiscal_year'])->approved()->with(['details.costCenter.company', 'details.expenseCategory', 'details.budgetCedula']);
        $this->applyMovementVisibility($movementQuery, $request->user());
        foreach ($movementQuery->get() as $movement) {
            foreach ($movement->details as $detail) {
                if (! $this->detailMatchesFilters($detail, $filters)) {
                    continue;
                }
                $center = $detail->costCenter;
                if (! $center) {
                    continue;
                }
                $key = $this->lineKey($center->id, $detail->month, $detail->expense_category_id, $detail->budget_cedula_id);
                if (! isset($lines[$key])) {
                    $lines[$key] = [
                        'company_name' => $center->company?->name, 'cost_center_id' => $center->id,
                        'cost_center_code' => $center->code, 'cost_center_name' => $center->name,
                        'fiscal_year' => $filters['fiscal_year'], 'month' => $detail->month,
                        'expense_category_id' => $detail->expense_category_id,
                        'expense_category_name' => $detail->expenseCategory?->name,
                        'budget_cedula_id' => $detail->budget_cedula_id,
                        'budget_cedula_name' => $detail->budgetCedula?->name,
                        'original_amount' => null, 'movement_total' => 0.0, 'current_amount' => 0.0, 'has_baseline' => false,
                    ];
                }
                $line = $lines[$key];
                $line['movement_total'] += (float) $detail->amount;
                $lines[$key] = $line;
            }
        }

        return $lines->map(function (array $line) {
            $line['reconstructed_amount'] = $line['original_amount'] === null
                ? null : round($line['original_amount'] + $line['movement_total'], 2);
            $line['difference'] = $line['reconstructed_amount'] === null
                ? null : round($line['reconstructed_amount'] - $line['current_amount'], 2);
            $line['reconciliation_status'] = $line['original_amount'] === null
                ? 'SIN_BASE' : (abs($line['difference']) < 0.005 ? 'CONCILIA' : 'DIFERENCIA');

            return $line;
        })->sortBy(['company_name', 'cost_center_code', 'month', 'expense_category_name', 'budget_cedula_name'])->values();
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
            'attachments' => $movement->attachments->map(fn ($a) => ['id' => $a->id, 'name' => $a->original_name, 'sha256' => $a->sha256])->values(),
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

    private function applyMovementVisibility(Builder $query, $user): void
    {
        $settings = \App\Models\BudgetMovementApprovalSetting::query()->first();
        if ($user->hasRole('superadmin') || $settings?->canApprove($user)) {
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

    private function detailMatchesFilters($detail, array $filters): bool
    {
        if ($filters['month'] && (int) $detail->month !== (int) $filters['month']) {
            return false;
        }
        if ($filters['company_ids'] && ! in_array((int) $detail->costCenter?->company_id, $filters['company_ids'], true)) {
            return false;
        }

        return true;
    }

    private function lineKey($center, $month, $category, $cedula): string
    {
        return implode(':', [$center, $month, $category, $cedula ?: 0]);
    }

    private function emptyLine($row): array
    {
        return [
            'company_name' => $row->company_name, 'cost_center_id' => $row->cost_center_id,
            'cost_center_code' => $row->cost_center_code, 'cost_center_name' => $row->cost_center_name,
            'fiscal_year' => $row->fiscal_year, 'month' => (int) $row->month,
            'expense_category_id' => $row->expense_category_id, 'expense_category_name' => $row->expense_category_name,
            'budget_cedula_id' => $row->budget_cedula_id, 'budget_cedula_name' => $row->budget_cedula_name,
        ];
    }
}
