<?php

namespace App\Reports\Purchasing;

use App\Models\Company;
use App\Models\User;
use App\Reports\Support\ReportSheet;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Exportación de RR-01: Excel (hoja de recepciones + hoja de archivos) y CSV (solo recepciones). */
class ReceptionsEvidenceExport
{
    public function __construct(private readonly ReceptionsEvidenceReport $report) {}

    /** @return array{response: StreamedResponse, rows: int} */
    public function download(User $user, array $params, string $format): array
    {
        $result = $this->report->build($user, $params);
        $filename = 'recepciones-evidencia-'.now(config('app.timezone'))->format('Y-m-d');

        if ($format === 'csv') {
            return ['response' => ReportSheet::csv($this->receptionColumns('csv'), $result['rows'], $filename.'.csv'), 'rows' => $result['rows']->count()];
        }

        $book = new Spreadsheet;
        ReportSheet::write($book->getActiveSheet(), 'Recepciones', $this->headerLines($user, $params), $this->receptionColumns('xlsx'), $result['rows'], 2);

        $attachmentRows = $result['rows']->flatMap(fn (array $row) => $row['attachments']->map(fn (array $attachment) => array_merge($attachment, [
            'reception_folio' => $row['reception_folio'],
            'po_folio' => $row['po_folio'],
        ])));
        ReportSheet::write($book->createSheet(), 'Archivos', ['RR-01 · Archivos de evidencia por recepción'], $this->attachmentColumns(), $attachmentRows, 2);

        $response = response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), $filename.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

        return ['response' => $response, 'rows' => $result['rows']->count()];
    }

    private function receptionColumns(string $format): array
    {
        $value = fn (string $key) => fn (array $row) => $row[$key] ?? null;

        return [
            ['Folio recepción', $value('reception_folio'), null],
            ['Tipo de orden', $value('order_type'), null],
            ['Folio OC/OCD', $value('po_folio'), null],
            ['Proveedor', $value('supplier_name'), null],
            ['Tipo (bien/servicio)', $value('reception_type'), null],
            ['Fecha de recepción', $this->date('reception_date', $format), ReportSheet::DATETIME],
            ['Ubicación', $value('location_name'), null],
            ['Recibió / validó', $value('received_by_name'), null],
            ['Conflicto de segregación', fn (array $row) => $row['sod_flag'] ? 'Sí' : 'No', null],
            ['Archivos de evidencia', $value('evidence_count'), null],
            ['Evidencia completa', fn (array $row) => $row['evidence_complete'] ? 'Sí' : 'No', null],
        ];
    }

    private function attachmentColumns(): array
    {
        $value = fn (string $key) => fn (array $row) => $row[$key] ?? null;

        return [
            ['Folio recepción', $value('reception_folio'), null],
            ['Folio OC/OCD', $value('po_folio'), null],
            ['Tipo de archivo', $value('attachable_type'), null],
            ['Nombre de archivo', $value('file_name'), null, true],
            ['Tipo MIME', $value('mime_type'), null],
            ['Tamaño (bytes)', $value('file_size'), null],
            ['SHA-256', $value('sha256'), null],
            ['Origen del hash', $value('hash_origin'), null],
            ['Cargado el', fn (array $row) => $row['uploaded_at'] ? Carbon::parse($row['uploaded_at'])->setTimezone(config('app.timezone'))->format('d/m/Y H:i') : null, null],
            ['Cargado por', $value('uploaded_by_name'), null],
        ];
    }

    private function date(string $key, string $format): callable
    {
        return function (array $row) use ($key, $format) {
            if (empty($row[$key])) {
                return null;
            }
            $date = Carbon::parse($row[$key])->setTimezone(config('app.timezone'));

            return $format === 'csv' ? $date->format('d/m/Y H:i') : ExcelDate::PHPToExcel($date);
        };
    }

    private function headerLines(User $user, array $params): array
    {
        $names = fn (array $ids) => $ids ? Company::query()->whereIn('id', $ids)->orderBy('name')->pluck('name')->implode(', ') : null;
        $filters = array_filter([
            'Empresa(s)' => $names($params['company_ids']),
            'Tipo' => $params['reception_type'] ? implode(', ', $params['reception_type']) : null,
            'Del' => $params['date_from'],
            'Al' => $params['date_to'],
            'Evidencia' => $params['evidence_filter'] !== 'TODAS' ? $params['evidence_filter'] : null,
        ], fn ($value) => $value !== null && $value !== '');

        return [
            'RR-01 · Recepciones y su evidencia',
            'Filtros: '.($filters ? collect($filters)->map(fn ($value, $key) => "{$key}: {$value}")->implode('; ') : 'ninguno'),
            'Generado por '.$user->name.' el '.now(config('app.timezone'))->format('d/m/Y H:i'),
            'Validar = recibir (no existe un segundo rol de validación). No existe el tipo HITO.',
        ];
    }
}
