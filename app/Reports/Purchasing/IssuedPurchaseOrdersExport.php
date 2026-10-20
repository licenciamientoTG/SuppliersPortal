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

/** Exportación de RC-02: Excel y CSV (una fila por OC/OCD emitida). */
class IssuedPurchaseOrdersExport
{
    public function __construct(private readonly IssuedPurchaseOrdersReport $report) {}

    /** @return array{response: StreamedResponse, rows: int} */
    public function download(User $user, array $params, string $format): array
    {
        $result = $this->report->build($user, $params);
        $filename = 'oc-emitidas-'.now(config('app.timezone'))->format('Y-m-d');

        if ($format === 'csv') {
            return ['response' => ReportSheet::csv($this->columns('csv'), $result['rows'], $filename.'.csv'), 'rows' => $result['rows']->count()];
        }

        $book = new Spreadsheet;
        ReportSheet::write($book->getActiveSheet(), 'OC emitidas', $this->headerLines($user, $params), $this->columns('xlsx'), $result['rows'], 3);
        $response = response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), $filename.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

        return ['response' => $response, 'rows' => $result['rows']->count()];
    }

    private function columns(string $format): array
    {
        $value = fn (string $key) => fn (array $row) => $row[$key] ?? null;

        return [
            ['Tipo', $value('type'), null],
            ['Origen', $value('origin'), null],
            ['Folio', $value('folio'), null],
            ['Proveedor', $value('supplier_name'), null],
            ['RFC', $value('supplier_rfc'), null],
            ['Centro de costo', $value('cost_center_label'), null],
            ['Comprador', $value('buyer_name'), null],
            ['Estatus', $value('status_label'), null],
            ['Moneda', $value('currency'), null],
            ['Subtotal', $value('subtotal'), ReportSheet::MONEY],
            ['IVA', $value('transferred_taxes'), ReportSheet::MONEY],
            ['Total', $value('total'), ReportSheet::MONEY],
            ['Total MXN', $value('total_mxn'), ReportSheet::MONEY],
            ['Fecha de emisión (envío)', $this->date('sent_at', $format), ReportSheet::DATE],
            ['Acuse del proveedor', $this->date('supplier_ack_at', $format), ReportSheet::DATE],
            ['Horas a acuse', $value('hours_to_ack'), null],
            ['Excepción', fn (array $row) => $row['is_exception'] ? 'Sí' : 'No', null],
        ];
    }

    /**
     * ReportSheet::csv() escribe el valor devuelto tal cual con fputcsv, sin aplicar el formato
     * de columna (ese formato solo lo usa ReportSheet::write() para Excel); por eso csv necesita
     * texto legible aquí en vez del serial de ExcelDate::PHPToExcel() que sí entiende xlsx.
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
            'Origen' => $params['origin'] ? implode(', ', $params['origin']) : null,
            'Estatus' => $params['status'] ? implode(', ', $params['status']) : null,
            'Del' => $params['date_from'],
            'Al' => $params['date_to'],
        ], fn ($value) => $value !== null && $value !== '');

        return [
            'RC-02 · Órdenes de compra emitidas',
            'Filtros: '.($filters ? collect($filters)->map(fn ($value, $key) => "{$key}: {$value}")->implode('; ') : 'ninguno'),
            'Generado por '.$user->name.' el '.now(config('app.timezone'))->format('d/m/Y H:i'),
            'Total MXN ya incluye la conversión de tipo de cambio donde aplica. El acuse se mide solo en órdenes emitidas a partir del '.IssuedPurchaseOrdersReport::ACK_TRACKING_SINCE.'.',
        ];
    }
}
