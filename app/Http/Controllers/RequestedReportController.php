<?php

namespace App\Http\Controllers;

use App\Services\ReportingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Sección temporal con el catálogo de reportes solicitados por Contabilidad y Finanzas
 * (config/requested_reports.php). Cada reporte se irá construyendo por separado.
 */
class RequestedReportController extends Controller
{
    public function index(Request $request): View
    {
        $order = $request->query('order') === 'feasibility' ? 'feasibility' : 'domain';

        $reports = collect(config('requested_reports.reports'))
            ->map(fn (array $report) => $report + [
                'level' => $this->feasibilityLevel($report['feasibility']),
                'existing_title' => $report['existing_report'] ? (ReportingService::REPORTS[$report['existing_report']][0] ?? null) : null,
            ]);

        return view('requested-reports.index', [
            'order' => $order,
            'domains' => config('requested_reports.domains'),
            'groups' => $order === 'feasibility'
                ? $reports->sortBy('feasibility')->groupBy(fn (array $report) => $report['level']['label'])
                : $reports->groupBy('domain'),
            'summary' => [
                'total' => $reports->count(),
                'levels' => $reports->countBy(fn (array $report) => $report['level']['label']),
                'with_base' => $reports->whereNotNull('existing_report')->count(),
            ],
        ]);
    }

    /** 1-9: con datos existentes; 10-18: requiere tablas/servicios internos; 19-31: requiere módulos nuevos. */
    private function feasibilityLevel(int $rank): array
    {
        return match (true) {
            $rank <= 9 => ['label' => 'Factibilidad alta', 'class' => 'success', 'hint' => 'Se puede construir con los datos actuales del portal.'],
            $rank <= 18 => ['label' => 'Factibilidad media', 'class' => 'warning', 'hint' => 'Requiere tablas o servicios internos nuevos.'],
            default => ['label' => 'Factibilidad baja', 'class' => 'danger', 'hint' => 'Requiere módulos que aún no existen (pagos, fiscal, contabilidad o SAT).'],
        };
    }
}
