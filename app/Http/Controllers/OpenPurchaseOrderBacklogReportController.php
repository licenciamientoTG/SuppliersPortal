<?php

namespace App\Http\Controllers;

use App\Http\Requests\OpenPurchaseOrderBacklogReportRequest;
use App\Models\Company;
use App\Models\Supplier;
use App\Reports\Purchasing\OpenPurchaseOrderBacklogExport;
use App\Reports\Purchasing\OpenPurchaseOrderBacklogReport;
use Illuminate\Http\Request;

/** RC-03 · Backlog de órdenes de compra abiertas y OC zombi. */
class OpenPurchaseOrderBacklogReportController extends Controller
{
    public function __construct(private readonly OpenPurchaseOrderBacklogReport $report) {}

    public function index(Request $request)
    {
        $centerIds = $this->report->visibleCostCenters($request->user())->pluck('id');
        $companies = Company::query()->whereIn('id', \App\Models\CostCenter::query()->whereIn('id', $centerIds)->distinct()->pluck('company_id'))->orderBy('name')->get(['id', 'name']);
        $suppliers = Supplier::query()->orderBy('company_name')->get(['id', 'company_name']);

        return view('reports.open-po-backlog.index', compact('companies', 'suppliers'));
    }

    public function data(OpenPurchaseOrderBacklogReportRequest $request)
    {
        $result = $this->report->build($request->user(), $request->params());

        return response()->json($result);
    }

    public function export(OpenPurchaseOrderBacklogReportRequest $request, OpenPurchaseOrderBacklogExport $export, string $format)
    {
        $params = $request->params();
        $result = $export->download($request->user(), $params, $format);

        activity('reportes')->causedBy($request->user())
            ->withProperties(['format' => $format, 'filters' => $params, 'rows' => $result['rows']])
            ->log('Exportación RC-03 '.$format);

        return $result['response'];
    }
}
