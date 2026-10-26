<?php

namespace App\Http\Controllers;

use App\Http\Requests\CfdiValidationLogReportRequest;
use App\Models\CfdiRejectionCode;
use App\Models\Supplier;
use App\Reports\Fiscal\CfdiValidationLogExport;
use App\Reports\Fiscal\CfdiValidationLogReport;
use Illuminate\Http\Request;

/** RF-01 · Bitácora de validación de CFDI. */
class CfdiValidationLogReportController extends Controller
{
    public function __construct(private readonly CfdiValidationLogReport $report) {}

    public function index(Request $request)
    {
        $suppliers = Supplier::query()->orderBy('company_name')->get(['id', 'company_name']);
        $rejectionCodes = CfdiRejectionCode::query()->where('active', true)->orderBy('code')->get(['code', 'rule_description']);

        return view('reports.cfdi-validation-log.index', compact('suppliers', 'rejectionCodes'));
    }

    public function data(CfdiValidationLogReportRequest $request)
    {
        $result = $this->report->build($request->user(), $request->params());

        return response()->json($result);
    }

    public function export(CfdiValidationLogReportRequest $request, CfdiValidationLogExport $export, string $format)
    {
        $params = $request->params();
        $result = $export->download($request->user(), $params, $format);

        activity('reportes')->causedBy($request->user())
            ->withProperties(['format' => $format, 'filters' => $params, 'rows' => $result['rows']])
            ->log('Exportación RF-01 '.$format);

        return $result['response'];
    }
}
