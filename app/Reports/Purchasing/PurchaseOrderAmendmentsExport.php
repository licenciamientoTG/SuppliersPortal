<?php

namespace App\Reports\Purchasing;

use App\Models\Company;
use App\Models\Supplier;
use App\Models\User;
use App\Reports\Support\ReportSheet;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Exportación de RC-04: Excel y CSV (una fila por evento de cancelación/rechazo/revisión). */
class PurchaseOrderAmendmentsExport
{
    public function __construct(private readonly PurchaseOrderAmendmentsReport $report) {}

    /** @return array{response: StreamedResponse, rows: int} */
    public function download(User $user, array $params, string $format): array
    {
        $result = $this->report->build($user, $params);
        $filename = 'modificaciones-oc-'.now(config('app.timezone'))->format('Y-m-d');

        if ($format === 'csv') {
            return ['response' => ReportSheet::csv($this->columns('csv'), $result['rows'], $filename.'.csv'), 'rows' => $result['rows']->count()];
        }

        $book = new Spreadsheet;
        ReportSheet::write($book->getActiveSheet(), 'Modificaciones OC', $this->headerLines($user, $params), $this->columns('xlsx'), $result['rows'], 3);
        $response = response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), $filename.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

        return ['response' => $response, 'rows' => $result['rows']->count()];
    }

    private function columns(string $format): array
    {
        $value = fn (string $key) => fn (array $row) => $row[$key] ?? null;

        return [
            ['Tipo', $value('order_type'), null],
            ['Evento', fn (array $row) => $this->eventLabel($row['event_type']), null],
            ['Folio', $value('folio'), null],
            ['Proveedor', $value('supplier_name'), null],
            ['Centro de costo', $value('cost_center_label'), null],
            ['Comprador', $value('buyer_name'), null],
            ['Fecha del evento', $this->date('occurred_at', $format), ReportSheet::DATE],
            ['Responsable', $value('actor_name'), null],
            ['Motivo', $value('reason'), null],
            ['Monto actual (MXN)', $value('total_mxn_current'), ReportSheet::MONEY],
        ];
    }

    private function eventLabel(string $eventType): string
    {
        return match ($eventType) {
            'CANCELLED' => 'Cancelación',
            'REJECTED' => 'Rechazo',
            'RETURNED_TO_REVISION' => 'Devolución a revisión',
            'REISSUED_AFTER_REVISION' => 'Reemisión tras revisión',
            default => $eventType,
        };
    }

    /**
     * ReportSheet::csv() escribe el valor devuelto tal cual con fputcsv, sin aplicar el formato
     * de columna (gotcha ya documentado en RC-02); por eso csv necesita texto legible aquí en vez
     * del serial de ExcelDate::PHPToExcel() que sí entiende xlsx.
     */
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
        $names = fn ($model, array $ids, string $column) => $ids ? $model::query()->whereIn('id', $ids)->orderBy($column)->pluck($column)->implode(', ') : null;
        $filters = array_filter([
            'Empresa(s)' => $names(Company::class, $params['company_ids'], 'name'),
            'Proveedor' => $params['supplier_id'] ? Supplier::query()->whereKey($params['supplier_id'])->value('company_name') : null,
            'Tipo de orden' => $params['order_type'] ? implode(', ', $params['order_type']) : null,
            'Evento' => $params['event_type'] ? implode(', ', $params['event_type']) : null,
            'Del' => $params['date_from'],
            'Al' => $params['date_to'],
        ], fn ($value) => $value !== null && $value !== '');

        return [
            'RC-04 · Modificaciones, adendas y cancelaciones de OC',
            'Filtros: '.($filters ? collect($filters)->map(fn ($value, $key) => "{$key}: {$value}")->implode('; ') : 'ninguno'),
            'Generado por '.$user->name.' el '.now(config('app.timezone'))->format('d/m/Y H:i'),
            'Una fila por evento, no por orden. El monto mostrado es el actual de la orden, no un histórico: '
                .'no existe hoy un control que capture el precio/cantidad/proveedor antes de una edición en revisión '
                .'(ver docs/reportes/RC-04_mapeo_y_huecos.md). Las OC regulares no tienen ruta de edición en el '
                .'sistema, así que solo aparecen cancelaciones y rechazos; devolución a revisión y reemisión solo '
                .'aplican a OCD.',
        ];
    }
}
