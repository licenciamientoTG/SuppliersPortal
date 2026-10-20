<?php

namespace App\Http\Controllers;

use App\Http\Requests\IssuedPurchaseOrdersReportRequest;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Supplier;
use App\Models\User;
use App\Reports\Purchasing\IssuedPurchaseOrdersExport;
use App\Reports\Purchasing\IssuedPurchaseOrdersReport;
use Illuminate\Http\Request;

/** RC-02 · Órdenes de compra emitidas. */
class IssuedPurchaseOrdersReportController extends Controller
{
    public function __construct(private readonly IssuedPurchaseOrdersReport $report) {}

    public function index(Request $request)
    {
        $centerIds = $this->report->visibleCostCenters($request->user())->pluck('id');
        $companies = Company::query()->whereIn('id', CostCenter::query()->whereIn('id', $centerIds)->distinct()->pluck('company_id'))->orderBy('name')->get(['id', 'name']);
        $suppliers = Supplier::query()->orderBy('company_name')->get(['id', 'company_name']);
        $buyers = User::query()->role('buyer')->orderBy('name')->get(['id', 'name']);

        return view('reports.issued-purchase-orders.index', compact('companies', 'suppliers', 'buyers'));
    }

    public function data(IssuedPurchaseOrdersReportRequest $request)
    {
        $result = $this->report->build($request->user(), $request->params());

        return response()->json($result);
    }

    public function export(IssuedPurchaseOrdersReportRequest $request, IssuedPurchaseOrdersExport $export, string $format)
    {
        $params = $request->params();
        $result = $export->download($request->user(), $params, $format);

        activity('reportes')->causedBy($request->user())
            ->withProperties(['format' => $format, 'filters' => $params, 'rows' => $result['rows']])
            ->log('Exportación RC-02 '.$format);

        return $result['response'];
    }
}
