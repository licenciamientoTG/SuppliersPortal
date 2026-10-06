<?php

namespace App\Http\Controllers;

use App\Http\Requests\BudgetVsActualReportRequest;
use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Reports\Budget\BudgetVsActualReport;
use Illuminate\Http\Request;

/** RP-01 · Presupuesto vs. ejercido por departamento y renglón. */
class BudgetVsActualReportController extends Controller
{
    public function __construct(private readonly BudgetVsActualReport $report) {}

    public function index(Request $request)
    {
        $centers = $this->report->visibleCostCenters($request->user())->with('company:id,name')->orderBy('code')->get(['id', 'code', 'name', 'company_id', 'responsible_user_id']);
        $companies = Company::query()->whereIn('id', $centers->pluck('company_id')->unique())->orderBy('name')->get(['id', 'name']);
        $categories = ExpenseCategory::query()->orderBy('code')->get(['id', 'code', 'name']);
        $responsibles = User::query()->whereIn('id', $centers->pluck('responsible_user_id')->filter()->unique())->orderBy('name')->get(['id', 'name']);

        return view('reports.budget-vs-actual.index', compact('centers', 'companies', 'categories', 'responsibles'));
    }

    public function data(BudgetVsActualReportRequest $request)
    {
        $result = $this->report->build($request->user(), $request->params());
        $perPage = $request->integer('per_page', 25);
        $rows = $result['rows'];
        $lastPage = max(1, (int) ceil($rows->count() / $perPage));
        $page = min($request->integer('page', 1), $lastPage);

        return response()->json([
            'rows' => $rows->forPage($page, $perPage)->values(),
            'cost_centers' => $result['cost_centers'],
            'companies' => $result['companies'],
            'total' => $result['total'],
            'kpis' => $result['kpis'],
            'pagination' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => $perPage, 'total' => $rows->count()],
        ]);
    }

    public function detail(BudgetVsActualReportRequest $request)
    {
        $params = $request->params() + $request->only(['cost_center_id', 'expense_category_id', 'budget_cedula_id', 'bucket']);
        $documents = $this->report->documents($request->user(), $params);

        return response()->json([
            'documents' => $documents,
            'total' => round((float) $documents->sum('amount'), 2),
        ]);
    }
}
