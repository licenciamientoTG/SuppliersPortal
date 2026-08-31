<?php

namespace App\Http\Controllers;

use App\Services\ReportingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

class ReportController extends Controller
{
    public function __construct(private readonly ReportingService $reports) {}

    public function index() { return view('reports.index', ['reports' => ReportingService::REPORTS]); }

    public function show(Request $request, string $report)
    {
        [$title] = $this->reports->definition($report);
        return view('reports.show', ['report' => $report, 'title' => $title, 'filters' => $this->reports->filters(), 'defaultFrom' => now()->startOfYear()->toDateString(), 'defaultTo' => now()->endOfYear()->toDateString()]);
    }

    public function data(Request $request, string $report)
    {
        $result = $this->reports->result($report, $request->all());
        return DataTables::of($result['rows'])->with(['kpis' => $result['kpis'], 'columns' => $result['columns']])->toJson();
    }

    public function export(Request $request, string $report, string $format): StreamedResponse|Illuminate\Http\Response
    {
        abort_unless(in_array($format, ['xlsx', 'csv', 'pdf'], true), 404);
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
