<?php

namespace App\Http\Controllers;

use App\Http\Requests\BudgetAlertsReportRequest;
use App\Models\BudgetException;
use App\Models\BudgetMonthlyDistribution;
use App\Models\Company;
use App\Models\User;
use App\Reports\Budget\BudgetAlertsExport;
use App\Reports\Budget\BudgetAlertsReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BudgetAlertsReportController extends Controller
{
    public function __construct(private readonly BudgetAlertsReport $report) {}

    public function index(Request $request)
    {
        $centers = $this->report->visibleCostCenters($request->user())->with('company:id,name')->orderBy('code')->get(['id', 'code', 'name', 'company_id', 'responsible_user_id']);
        $companies = Company::query()->whereIn('id', $centers->pluck('company_id')->unique())->orderBy('name')->get(['id', 'name']);
        $responsibles = User::query()->whereIn('id', $centers->pluck('responsible_user_id')->filter()->unique())->orderBy('name')->get(['id', 'name']);

        return view('reports.budget-alerts.index', compact('companies', 'centers', 'responsibles'));
    }

    public function data(BudgetAlertsReportRequest $request)
    {
        $result = $this->report->build($request->user(), $request->params());
        $perPage = $request->integer('per_page', 25);
        $lastPage = max(1, (int) ceil($result['lines']->count() / $perPage));
        $page = min($request->integer('page', 1), $lastPage);

        return response()->json([
            'lines' => $result['lines']->forPage($page, $perPage)->values(),
            'pagination' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => $perPage, 'total' => $result['lines']->count()],
            'exceptions' => $result['exceptions']->values(),
            'kpis' => $result['kpis'],
        ]);
    }

    public function export(BudgetAlertsReportRequest $request, BudgetAlertsExport $export, string $format)
    {
        $params = $request->params();
        $result = $export->download($request->user(), $params, $format);

        activity('reportes')->causedBy($request->user())
            ->withProperties(['format' => $format, 'filters' => $params, 'rows' => $result['rows'], 'exception_rows' => $result['exception_rows']])
            ->log('Exportación RP-03 '.$format);

        return $result['response'];
    }

    public function requestException(Request $request)
    {
        abort_unless($request->user()->can('reportes.budget_alerts.excepcion.solicitar'), 403);
        $data = $request->validate(['document_type' => ['required', 'in:direct_purchase_order'], 'document_id' => ['required', 'integer'], 'document_line_id' => ['required', 'integer'], 'reason' => ['required', 'string', 'min:10', 'max:2000']]);
        abort_unless($data['document_type'] === 'direct_purchase_order', 422);
        $document = \App\Models\DirectPurchaseOrder::with('items')->findOrFail($data['document_id']);
        $this->authorize('view', $document);
        $item = $document->items->firstWhere('id', $data['document_line_id']);
        abort_if(! $item, 404);
        abort_if(BudgetException::query()->where('document_type', $data['document_type'])->where('document_id', $document->id)->where('document_line_id', $item->id)->whereIn('status', ['PENDING', 'APPROVED'])->exists(), 409, 'Esta partida ya tiene una excepción pendiente o aprobada.');
        $date = \Carbon\Carbon::parse($document->application_month.'-01');
        $check = app(\App\Services\BudgetAllocationService::class)->checkAvailability((int) $item->cost_center_id, (int) $date->year, (int) $date->month, (int) $item->expense_category_id, (float) $item->total, $item->budget_cedula_id ? (int) $item->budget_cedula_id : null);
        abort_if(($check['available'] ?? false), 422, 'Esta partida ya cuenta con presupuesto suficiente.');

        return DB::transaction(function () use ($data, $document, $item, $check, $request, $date) {
            return BudgetException::create(['document_type' => $data['document_type'], 'document_id' => $document->id, 'document_line_id' => $item->id, 'cost_center_id' => $item->cost_center_id, 'expense_category_id' => $item->expense_category_id, 'budget_cedula_id' => $item->budget_cedula_id, 'application_month' => $date->format('Y-m'), 'line_amount' => $item->total, 'available_at_request' => $check['available_amount'] ?? 0, 'requested_excess' => max(0, (float) $item->total - (float) ($check['available_amount'] ?? 0)), 'reason' => $data['reason'], 'status' => 'PENDING', 'requested_by' => $request->user()->id, 'requested_at' => now()]);
        });
    }

    public function decideException(Request $request, BudgetException $exception)
    {
        abort_unless($request->user()->hasRole('general_director'), 403);
        $data = $request->validate(['decision' => ['required', 'in:APPROVED,REJECTED'], 'comment' => ['nullable', 'string', 'max:2000']]);

        return DB::transaction(function () use ($exception, $request, $data) {
            $exception = BudgetException::query()->lockForUpdate()->findOrFail($exception->id);
            abort_unless($exception->status === 'PENDING', 409);
            $excess = (float) $exception->requested_excess;
            $line = null;
            if ($data['decision'] === 'APPROVED') {
                $line = BudgetMonthlyDistribution::find($exception->budget_monthly_distribution_id);
                if (! $line) {
                    $line = BudgetMonthlyDistribution::query()->whereHas('annualBudget', fn ($q) => $q->where('cost_center_id', $exception->cost_center_id)->where('fiscal_year', (int) substr($exception->application_month, 0, 4)))
                        ->where('month', (int) substr($exception->application_month, 5, 2))->where('expense_category_id', $exception->expense_category_id)
                        ->when($exception->budget_cedula_id, fn ($q) => $q->where('budget_cedula_id', $exception->budget_cedula_id))->first();
                    if ($line) {
                        $exception->budget_monthly_distribution_id = $line->id;
                    }
                }
                if ($line) {
                    $available = $line->getBalanceAmount();
                    $excess = max(0, round((float) $exception->line_amount - $available, 2));
                }
            }
            $exception->update(['budget_monthly_distribution_id' => $line?->id ?? $exception->budget_monthly_distribution_id, 'status' => $data['decision'], 'approved_excess' => $data['decision'] === 'APPROVED' ? $excess : null, 'decided_by' => $request->user()->id, 'decision_comment' => $data['comment'] ?? null, 'decided_at' => now()]);

            return $exception->fresh();
        });
    }
}
