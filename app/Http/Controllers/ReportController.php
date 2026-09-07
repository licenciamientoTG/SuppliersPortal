<?php

namespace App\Http\Controllers;

use App\Services\ReportingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

class ReportController extends Controller
{
    public function __construct(private readonly ReportingService $reports) {}

    public function index() { return view('reports.index', ['reports' => ReportingService::REPORTS, 'monthlyStatus' => $this->reports->currentMonthStatusSummary()]); }

    public function show(Request $request, string $report)
    {
        [$title] = $this->reports->definition($report);
        return view('reports.show', ['report' => $report, 'title' => $title, 'meta' => $this->reports->metadata($report), 'filters' => $this->reports->filters(), 'defaultFrom' => now()->startOfYear()->toDateString(), 'defaultTo' => now()->toDateString()]);
    }

    public function data(Request $request, string $report)
    {
        $this->reports->definition($report);
        $request->validate(['date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from']]);
        $result = $this->reports->result($report, $request->all());
        return DataTables::of($result['rows'])->with(['kpis' => $result['kpis'], 'columns' => $result['columns'], 'meta' => $result['meta']])->toJson();
    }

    public function updateValidationSla(Request $request): \Illuminate\Http\RedirectResponse
    {
        abort_unless($request->user()?->hasRole('superadmin'), 403);

        if (! Schema::hasTable('report_settings')) {
            return back()->withErrors(['sla_days' => 'La configuración aún no está disponible. Aplica la migración pendiente.']);
        }

        $data = $request->validate(['sla_days' => ['required', 'integer', 'min:0', 'max:60']]);
        \App\Models\ReportSetting::updateOrCreate(
            ['key' => 'purchasing_validation_sla_days'],
            ['value' => $data['sla_days']],
        );

        return redirect()->route('reports.show', 'purchasing-sla')->with('success', 'Meta de SLA actualizada.');
    }

    public function export(Request $request, string $report, string $format): StreamedResponse|Response
    {
        abort_unless(in_array($format, ['xlsx', 'csv', 'pdf'], true), 404);
        $request->validate(['date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from']]);
        [$title] = $this->reports->definition($report); $result = $this->reports->result($report, $request->all());
        $filename = str($report)->slug('_').'_'.now()->format('Ymd_His');
        if ($format === 'pdf') return Pdf::loadView('reports.pdf', compact('title', 'result'))->setPaper('letter', 'landscape')->download("$filename.pdf");
        return response()->streamDownload(function () use ($result, $title, $format) {
            $sheet=(new Spreadsheet())->getActiveSheet(); $sheet->setTitle('Reporte'); $sheet->fromArray([$title], null, 'A1'); $sheet->fromArray($result['columns'], null, 'A3');
            $sheet->fromArray($result['rows']->map(fn($row) => array_values((array) $row))->all(), null, 'A4'); $sheet->getStyle('A3:Z3')->getFont()->setBold(true);
            $writer=$format === 'csv' ? new Csv($sheet->getParent()) : new Xlsx($sheet->getParent()); $writer->save('php://output');
        }, "$filename.$format", ['Content-Type' => $format === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
