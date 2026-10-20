<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseOrderAmendmentsReportRequest;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Supplier;
use App\Models\User;
use App\Reports\Purchasing\PurchaseOrderAmendmentsExport;
use App\Reports\Purchasing\PurchaseOrderAmendmentsReport;
use Illuminate\Http\Request;

/** RC-04 · Modificaciones, adendas y cancelaciones de OC. */
class PurchaseOrderAmendmentsReportController extends Controller
{
    public function __construct(private readonly PurchaseOrderAmendmentsReport $report) {}

    public function index(Request $request)
    {
        $centerIds = $this->report->visibleCostCenters($request->user())->pluck('id');
        $companies = Company::query()->whereIn('id', CostCenter::query()->whereIn('id', $centerIds)->distinct()->pluck('company_id'))->orderBy('name')->get(['id', 'name']);
        $suppliers = Supplier::query()->orderBy('company_name')->get(['id', 'company_name']);
        $buyers = User::query()->role('buyer')->orderBy('name')->get(['id', 'name']);

        return view('reports.purchase-order-amendments.index', compact('companies', 'suppliers', 'buyers'));
    }

    public function data(PurchaseOrderAmendmentsReportRequest $request)
    {
        $result = $this->report->build($request->user(), $request->params());

        return response()->json($result);
    }

    public function export(PurchaseOrderAmendmentsReportRequest $request, PurchaseOrderAmendmentsExport $export, string $format)
    {
        $params = $request->params();
        $result = $export->download($request->user(), $params, $format);

        activity('reportes')->causedBy($request->user())
            ->withProperties(['format' => $format, 'filters' => $params, 'rows' => $result['rows']])
            ->log('Exportación RC-04 '.$format);

        return $result['response'];
    }
}
