<?php

namespace App\Http\Controllers;

use App\Reports\Fiscal\CfdiValidationLogReport;
use Illuminate\Support\Facades\Auth;

/** RF-01 (vista del proveedor) · "Mis cargas": solo sus propios intentos de CFDI. */
class SupplierCfdiValidationLogController extends Controller
{
    public function __construct(private readonly CfdiValidationLogReport $report) {}

    public function index()
    {
        $supplier = Auth::guard('supplier')->user();
        abort_if(! $supplier, 403);

        $result = $this->report->buildForSupplier($supplier, []);

        return view('supplier.cfdi-log.index', ['rows' => $result['rows'], 'kpis' => $result['kpis']]);
    }
}
