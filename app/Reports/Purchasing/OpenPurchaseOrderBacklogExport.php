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

/** Exportación de RC-03: Excel y CSV (una fila por renglón con saldo pendiente de recibir). */
class OpenPurchaseOrderBacklogExport
{
    public function __construct(private readonly OpenPurchaseOrderBacklogReport $report) {}

    /** @return array{response: StreamedResponse, rows: int} */
    public function download(User $user, array $params, string $format): array
    {
        $result = $this->report->build($user, $params);
        $filename = sprintf('backlog-oc-abiertas-al-%s', $params['as_of_date']);

        if ($format === 'csv') {
            return ['response' => ReportSheet::csv($this->columns(), $result['rows'], $filename.'.csv'), 'rows' => $result['rows']->count()];
        }

        $book = new Spreadsheet;
        ReportSheet::write($book->getActiveSheet(), 'Backlog OC', $this->headerLines($user, $params), $this->columns(), $result['rows'], 3);
        $response = response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), $filename.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

        return ['response' => $response, 'rows' => $result['rows']->count()];
    }

    private function columns(): array
    {
        $value = fn (string $key) => fn (array $row) => $row[$key] ?? null;

        return [
            ['Tipo', $value('type'), null],
            ['Folio', $value('folio'), null],
            ['Proveedor', $value('supplier_name'), null],
            ['Descripción', $value('description'), null],
            ['Centro de costo', $value('cost_center_label'), null],
            ['Cuenta de gasto', $value('expense_category_label'), null],
            ['Subcuenta', $value('budget_cedula_label'), null],
            ['Cantidad ordenada', $value('ordered_qty'), '#,##0.000'],
            ['Cantidad recibida', $value('received_qty'), '#,##0.000'],
            ['Cantidad pendiente', $value('pending_qty'), '#,##0.000'],
            ['Precio unitario', $value('unit_price'), ReportSheet::MONEY],
            ['Importe pendiente (sin IVA)', $value('pending_amount'), ReportSheet::MONEY],
            ['Fecha de emisión', $this->date('issued_at'), ReportSheet::DATE],
            ['Fecha prometida', fn (array $row) => $row['promised_date'] ? Carbon::parse($row['promised_date']) : null, ReportSheet::DATE],
            ['Días de atraso', fn (array $row) => $row['days_late'], null],
            ['Vencida', fn (array $row) => $row['is_expired'] ? 'Sí' : 'No', null],
            ['Última recepción', $this->date('last_received_at'), ReportSheet::DATE],
            ['Zombi', fn (array $row) => $row['is_zombie'] ? 'Sí' : 'No', null],
        ];
    }

    private function date(string $key): callable
    {
        return fn (array $row) => ! empty($row[$key]) ? ExcelDate::PHPToExcel(Carbon::parse($row[$key])->setTimezone(config('app.timezone'))) : null;
    }

    private function headerLines(User $user, array $params): array
    {
        $names = fn ($model, array $ids, string $column) => $ids ? $model::query()->whereIn('id', $ids)->orderBy($column)->pluck($column)->implode(', ') : null;
        $filters = array_filter([
            'Empresa(s)' => $names(Company::class, $params['company_ids'], 'name'),
            'Proveedor' => $params['supplier_id'] ? Supplier::query()->whereKey($params['supplier_id'])->value('company_name') : null,
            'Solo atrasadas' => $params['only_late'] ? 'Sí' : null,
            'Solo zombi' => $params['only_zombie'] ? 'Sí' : null,
        ], fn ($value) => $value !== null && $value !== '');

        return [
            'RC-03 · Backlog de órdenes de compra abiertas y OC zombi',
            'Fecha de corte: '.Carbon::parse($params['as_of_date'])->format('d/m/Y').'. Umbral de zombi: '.$params['zombie_days'].' días.',
            'Filtros: '.($filters ? collect($filters)->map(fn ($value, $key) => "{$key}: {$value}")->implode('; ') : 'ninguno'),
            'Generado por '.$user->name.' el '.now(config('app.timezone'))->format('d/m/Y H:i'),
            'Importe pendiente sin IVA (mismo criterio que el comprometido de RP-01).',
        ];
    }
}
