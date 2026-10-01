<?php

namespace App\Services;

use App\Models\BudgetException;
use App\Models\BudgetMonthlyDistribution;
use Illuminate\Http\Request;

class BudgetAlertsReportService
{
    public function lines(Request $request)
    {
        $query = BudgetMonthlyDistribution::query()->with(['annualBudget.costCenter.company', 'annualBudget.costCenter.responsible', 'expenseCategory', 'budgetCedula'])
            ->whereHas('annualBudget', fn ($q) => $q->where('status', 'APROBADO'));
        $this->scope($query, $request);
        $rows = $query->get()->map(function (BudgetMonthlyDistribution $line) {
            $assigned = (float) $line->assigned_amount;
            $total = (float) $line->consumed_amount + (float) $line->committed_amount;
            $cutoff = now()->startOfMonth()->subSecond();
            $history = collect(range(1, 4))->map(function ($monthsBack) use ($line, $cutoff) {
                $month = $cutoff->copy()->subMonthsNoOverflow($monthsBack - 1);

                return \DB::table('budget_line_history')->where('budget_monthly_distribution_id', $line->id)
                    ->whereBetween('captured_on', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                    ->orderByDesc('captured_on')->orderByDesc('id')->first();
            });
            $sufficient = $history->every(fn ($snapshot) => $snapshot !== null);
            $burn = null;
            if ($sufficient) {
                $oldest = $history->last();
                $newest = $history->first();
                $burn = max(0, ((float) $newest->consumed_amount - (float) $oldest->consumed_amount) / 3);
            }
            $center = $line->annualBudget?->costCenter;
            $documents = \DB::table('budget_commitments')->where('cost_center_id', $center?->id)
                ->where('application_month', sprintf('%04d-%02d', $line->annualBudget?->fiscal_year, $line->month))
                ->where('expense_category_id', $line->expense_category_id)->where('status', 'COMMITTED')
                ->when($line->budget_cedula_id, fn ($q) => $q->where('budget_cedula_id', $line->budget_cedula_id), fn ($q) => $q->whereNull('budget_cedula_id'))
                ->get(['direct_purchase_order_id', 'purchase_order_id'])
                ->map(fn ($row) => $row->direct_purchase_order_id ? 'OCD-'.$row->direct_purchase_order_id : 'OC-'.$row->purchase_order_id)->unique()->values();

            return [
                'distribution_id' => $line->id, 'company' => $center?->company?->name, 'cost_center' => $center?->name,
                'responsible' => $center?->responsible?->name, 'year' => $line->annualBudget?->fiscal_year, 'month' => $line->month,
                'account' => $line->expenseCategory?->name, 'subaccount' => $line->budgetCedula?->name,
                'assigned' => round($assigned, 2), 'consumed' => round((float) $line->consumed_amount, 2),
                'committed' => round((float) $line->committed_amount, 2), 'consumed_total' => round($total, 2),
                'available' => round($assigned - $total, 2), 'consumed_pct' => $assigned > 0 ? round($total / $assigned * 100, 2) : null,
                'burn_rate_3m' => $burn, 'projected_exhaustion' => $burn > 0 ? now()->addDays(max(0, (int) floor((($assigned - $total) / $burn) * 30)))->format('Y-m') : null,
                'pending_documents' => $documents->all(),
                'history_status' => $sufficient ? 'Disponible' : 'Sin historial suficiente',
            ];
        });

        return $rows->filter(fn ($r) => ($r['consumed_pct'] !== null && $r['consumed_pct'] >= 80) || $r['available'] < 0 || BudgetException::where('budget_monthly_distribution_id', $r['distribution_id'])->where('status', 'APPROVED')->exists())->values();
    }

    public function exceptions(Request $request)
    {
        $query = BudgetException::query()->with(['requester', 'decider', 'costCenter.company'])->latest('requested_at');
        if (! $request->user()->hasRole(['superadmin', 'general_director', 'accounting', 'controller'])) {
            $query->where(function ($q) use ($request) {
                $q->where('requested_by', $request->user()->id)->orWhereHas('costCenter', fn ($c) => $c->where('responsible_user_id', $request->user()->id));
            });
        }
        if ($request->filled('company_id')) {
            $query->whereHas('costCenter', fn ($q) => $q->where('company_id', $request->integer('company_id')));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('requested_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('requested_at', '<=', $request->date_to);
        }

        return $query;
    }

    private function scope($query, Request $request): void
    {
        $user = $request->user();
        if (! $user->hasRole(['superadmin', 'general_director', 'accounting', 'controller'])) {
            $query->whereHas('annualBudget.costCenter', fn ($q) => $q->where('responsible_user_id', $user->id)->orWhereHas('activeUsers', fn ($u) => $u->where('users.id', $user->id)));
        }
        if ($request->filled('company_id')) {
            $query->whereHas('annualBudget.costCenter', fn ($q) => $q->where('company_id', $request->integer('company_id')));
        }
        if ($request->filled('cost_center_id')) {
            $query->whereHas('annualBudget', fn ($q) => $q->where('cost_center_id', $request->integer('cost_center_id')));
        }
        if ($request->filled('year')) {
            $query->whereHas('annualBudget', fn ($q) => $q->where('fiscal_year', $request->integer('year')));
        }
    }
}
