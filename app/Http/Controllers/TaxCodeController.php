<?php

namespace App\Http\Controllers;

use App\Models\TaxCode;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class TaxCodeController extends Controller
{
    public function index(): View
    {
        $codes = TaxCode::query();

        return view('tax_codes.index', [
            'totalCodes' => (clone $codes)->count(),
            'withholdings' => (clone $codes)->where('is_withholding', true)->count(),
            'iepsCodes' => (clone $codes)->where('one_goal_tax_type_id', 15004)->count(),
            'selectableCodes' => (clone $codes)->selectable()->count(),
        ]);
    }

    public function datatable(): JsonResponse
    {
        $query = TaxCode::query()->withCount('satRetenciones');

        return DataTables::of($query)
            ->addColumn('classification', fn (TaxCode $taxCode) => $this->classificationBadge($taxCode))
            ->editColumn('rate', function (TaxCode $taxCode): string {
                return $taxCode->calculation_type === 'fixed_quota'
                    ? '$'.number_format((float) $taxCode->rate, 4)
                    : number_format((float) $taxCode->rate, 4).'%';
            })
            ->editColumn('calculation_type', fn (TaxCode $taxCode) => $taxCode->calculation_type === 'fixed_quota'
                ? '<span class="badge bg-info">Cuota</span>'
                : '<span class="badge bg-primary">Porcentaje</span>')
            ->addColumn('sat_links', fn (TaxCode $taxCode) => $taxCode->sat_retenciones_count > 0
                ? '<span class="badge bg-success">'.$taxCode->sat_retenciones_count.' SAT</span>'
                : '<span class="text-muted">—</span>')
            ->addColumn('status', function (TaxCode $taxCode): string {
                if (! $taxCode->is_active) {
                    return '<span class="badge bg-secondary">Inactivo</span>';
                }

                return $taxCode->is_selectable
                    ? '<span class="badge bg-success">Disponible</span>'
                    : '<span class="badge bg-secondary">No seleccionable</span>';
            })
            ->rawColumns(['classification', 'calculation_type', 'sat_links', 'status'])
            ->make(true);
    }

    private function classificationBadge(TaxCode $taxCode): string
    {
        if ($taxCode->one_goal_id === 0) {
            return '<span class="badge bg-secondary">Sistema</span>';
        }

        if ($taxCode->is_exempt) {
            return '<span class="badge bg-success">Exento</span>';
        }

        if ($taxCode->is_withholding) {
            return $taxCode->is_vat
                ? '<span class="badge bg-warning text-dark">Retención IVA</span>'
                : '<span class="badge bg-danger">Retención ISR</span>';
        }

        if ($taxCode->one_goal_tax_type_id === 15004) {
            return '<span class="badge bg-purple">IEPS</span>';
        }

        return '<span class="badge bg-info">IVA</span>';
    }
}
