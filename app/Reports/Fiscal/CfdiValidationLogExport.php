<?php

namespace App\Reports\Fiscal;

use App\Models\Supplier;
use App\Models\User;
use App\Reports\Support\ReportSheet;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Exportación de RF-01: Excel y CSV (una fila por intento de carga). */
class CfdiValidationLogExport
{
    public function __construct(private readonly CfdiValidationLogReport $report) {}

    /** @return array{response: StreamedResponse, rows: int} */
    public function download(User $user, array $params, string $format): array
    {
        $result = $this->report->build($user, $params);
        $filename = 'bitacora-cfdi-'.now(config('app.timezone'))->format('Y-m-d');

        if ($format === 'csv') {
            return ['response' => ReportSheet::csv($this->columns('csv'), $result['rows'], $filename.'.csv'), 'rows' => $result['rows']->count()];
        }

        $book = new Spreadsheet;
        ReportSheet::write($book->getActiveSheet(), 'Bitácora CFDI', $this->headerLines($user, $params), $this->columns('xlsx'), $result['rows'], 3);
        $response = response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), $filename.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

        return ['response' => $response, 'rows' => $result['rows']->count()];
    }

    private function columns(string $format): array
    {
        $value = fn (string $key) => fn (array $row) => $row[$key] ?? null;

        return [
            ['Fecha/hora', $this->date('attempted_at', $format), ReportSheet::DATE],
            ['UUID', $value('uuid'), null],
            ['RFC emisor', $value('issuer_rfc'), null],
            ['Emisor', $value('issuer_name'), null],
            ['Empresa receptora', $value('company_name'), null],
            ['Tipo', $value('cfdi_type'), null],
            ['Total', $value('total'), ReportSheet::MONEY],
            ['Moneda', $value('currency'), null],
            ['Resultado', $value('result'), null],
            ['Código de rechazo', $value('rejection_code'), null],
            ['Regla incumplida', $value('rule_description'), null],
            ['Mensaje al proveedor', $value('supplier_message'), null],
            ['No. de intento', $value('attempt_no'), null],
            ['Canal', $value('channel'), null],
            ['Proveedor', $value('supplier_name'), null],
            ['Usuario', $value('user_name'), null],
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
        $filters = array_filter([
            'Resultado' => $params['result'] ?? null,
            'Código de rechazo' => $params['rejection_code'] ? implode(', ', $params['rejection_code']) : null,
            'Proveedor' => $params['supplier_id'] ? Supplier::query()->whereKey($params['supplier_id'])->value('company_name') : null,
            'Del' => $params['date_from'] ?? null,
            'Al' => $params['date_to'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        return [
            'RF-01 · Bitácora de validación de CFDI',
            'Filtros: '.($filters ? collect($filters)->map(fn ($value, $key) => "{$key}: {$value}")->implode('; ') : 'ninguno'),
            'Generado por '.$user->name.' el '.now(config('app.timezone'))->format('d/m/Y H:i'),
            'sat_status (estatus en el SAT) no está disponible: el portal no consulta hoy el webservice de verificación de CFDI.',
        ];
    }
}
