<?php

namespace App\Http\Controllers;

use App\Enum\RequisitionStatus;
use App\Http\Requests\RequisitionPipelineReportRequest;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\User;
use App\Reports\Purchasing\RequisitionPipelineExport;
use App\Reports\Purchasing\RequisitionPipelineReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** RC-01 · Pipeline de requisiciones y tiempos de ciclo. */
class RequisitionPipelineReportController extends Controller
{
    public function __construct(private readonly RequisitionPipelineReport $report) {}

    public function index(Request $request)
    {
        $visible = $this->report->visibleRequisitions($request->user());
        $companies = Company::query()->whereIn('id', (clone $visible)->distinct()->pluck('r.company_id'))->orderBy('name')->get(['id', 'name']);
        $centers = CostCenter::query()->whereIn('company_id', $companies->pluck('id'))->orderBy('code')->get(['id', 'code', 'name', 'company_id']);
        $approvers = User::query()->whereIn('id', DB::table('quotation_summaries')->whereNotNull('current_approver_user_id')->distinct()->pluck('current_approver_user_id'))
            ->orderBy('name')->get(['id', 'name']);
        $statuses = RequisitionStatus::cases();
        $defaultStatuses = RequisitionPipelineReportRequest::defaultStatuses();

        return view('reports.requisition-pipeline.index', compact('companies', 'centers', 'approvers', 'statuses', 'defaultStatuses'));
    }

    public function data(RequisitionPipelineReportRequest $request)
    {
        $result = $this->report->build($request->user(), $request->params());
        $perPage = $request->integer('per_page', 25);
        $rows = $result['rows'];
        $lastPage = max(1, (int) ceil($rows->count() / $perPage));
        $page = min($request->integer('page', 1), $lastPage);

        return response()->json([
            'rows' => $rows->forPage($page, $perPage)->values(),
            'kpis' => $result['kpis'],
            'stages' => $result['stages'],
            'approvers' => $result['approvers'],
            'pagination' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => $perPage, 'total' => $rows->count()],
        ]);
    }

    public function steps(Request $request, int $requisition)
    {
        $timeline = $this->report->steps($request->user(), $requisition);
        abort_if($timeline === null, 403);

        return response()->json($timeline);
    }

    public function export(RequisitionPipelineReportRequest $request, RequisitionPipelineExport $export, string $format)
    {
        $params = $request->params();
        $result = $export->download($request->user(), $params, $format);

        activity('reportes')->causedBy($request->user())
            ->withProperties(['format' => $format, 'filters' => $params, 'rows' => $result['rows'], 'step_rows' => $result['step_rows']])
            ->log('Exportación RC-01 '.$format);

        return $result['response'];
    }
}
