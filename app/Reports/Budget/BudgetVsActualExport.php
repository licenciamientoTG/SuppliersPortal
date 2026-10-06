<?php

namespace App\Reports\Budget;

use App\Models\Company;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Exportación de RP-01: Excel (resumen + detalle por documento) y CSV (renglones). */
class BudgetVsActualExport
{
    private const MONEY = '$#,##0.00';

    private const PERCENT = '0.0%';

    private const BUCKET_LABELS = ['reserved' => 'Reservado', 'committed' => 'Comprometido', 'accrued' => 'Devengado', 'released' => 'OC cancelada'];

    private const LIGHT_LABELS = ['VERDE' => 'Verde', 'AMARILLO' => 'Amarillo', 'ROJO' => 'Rojo'];

    public function __construct(private readonly BudgetVsActualReport $report) {}

    /** @return array{response: StreamedResponse, rows: int, detail_rows: int} */
    public function download(User $user, array $params, string $format): array
    {
        $result = $this->report->build($user, $params);
        $filename = sprintf('presupuesto-vs-ejercido-%d-%02d-%s', $params['fiscal_year'], $params['period_month'], strtolower($params['scope']));

        if ($format === 'csv') {
            return ['response' => $this->csv($result['rows'], $params, $filename.'.csv'), 'rows' => $result['rows']->count(), 'detail_rows' => 0];
        }

        $documents = $this->report->documentLines($params, $result['rows']);
        $book = new Spreadsheet;
        $header = $this->headerLines($user, $params);
        $this->summarySheet($book->getActiveSheet(), $header, $result, $params);
        $this->detailSheet($book->createSheet(), $header, $documents);
        $book->setActiveSheetIndex(0);

        $response = response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), $filename.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

        return ['response' => $response, 'rows' => $result['rows']->count(), 'detail_rows' => $documents->count()];
    }

    /** Columnas de la hoja resumen y del CSV: [encabezado, valor, formato]. */
    private function columns(array $params): array
    {
        $amount = fn (string $key) => fn (array $row) => $row[$key] ?? null;

        $columns = [
            ['Tipo de fila', fn (array $row) => $row['_kind'] ?? 'Renglón', null],
            ['Empresa', $amount('company_name'), null],
            ['RFC', $amount('company_rfc'), null],
            ['Centro de costo', $amount('cost_center_code'), null],
            ['Nombre del centro', $amount('cost_center_name'), null],
            ['Responsable', $amount('responsible_name'), null],
            ['Clave del renglón', $amount('budget_line_code'), null],
            ['Renglón', $amount('budget_line_name'), null],
            ['Subcuenta', $amount('budget_cedula_name'), null],
            ['Cuenta contable', fn () => 'N/D', null],
            ['Ejercicio', fn () => $params['fiscal_year'], null],
            ['Mes', fn () => $params['period_month'], null],
            ['Vista', fn () => $params['scope'] === 'ACU' ? 'Acumulado' : 'Mes', null],
            ['Autorizado', fn (array $row) => $row['authorized_amount'] ?? 'Sin base', self::MONEY],
            ['Ampliaciones', $amount('increases'), self::MONEY],
            ['Reducciones', $amount('decreases'), self::MONEY],
            ['Vigente', $amount('current_budget'), self::MONEY],
            ['Reservado', $amount('reserved'), self::MONEY],
            ['Comprometido', $amount('committed'), self::MONEY],
            ['Devengado', $amount('accrued'), self::MONEY],
            // El portal no registra pagos: se informa como no disponible, nunca como cero.
            ['Pagado', fn () => 'N/D', null],
            ['Ejercido', $amount('exercised_total'), self::MONEY],
            ['Consumido', $amount('consumed_total'), self::MONEY],
            ['Disponible', $amount('available'), self::MONEY],
            ['Sin conciliar', $amount('unreconciled'), self::MONEY],
        ];
        if ($params['include_cancelled_po']) {
            $columns[] = ['OC canceladas (informativo)', $amount('released'), self::MONEY];
        }

        return array_merge($columns, [
            ['% de avance', $amount('progress_pct'), self::PERCENT],
            ['Semáforo', fn (array $row) => self::LIGHT_LABELS[$row['traffic_light'] ?? ''] ?? null, null],
            ['Proyección de cierre', $amount('projected_close'), self::MONEY],
            ['Conciliación de base', fn (array $row) => match ($row['baseline_status'] ?? null) {
                'CONCILIA' => 'Concilia', 'DIFERENCIA' => 'Diferencia', 'SIN_BASE' => 'Sin base', default => null,
            }, null],
        ]);
    }

    private function summarySheet(Worksheet $sheet, array $header, array $result, array $params): void
    {
        $sheet->setTitle('Resumen');
        $columns = $this->columns($params);
        $first = $this->writeHeader($sheet, $header, array_column($columns, 0));
        $line = $first;

        foreach ($this->summaryRows($result) as $row) {
            $sheet->fromArray(array_map(fn ($column) => $column[1]($row), $columns), null, 'A'.$line, true);
            if (($row['_kind'] ?? 'Renglón') !== 'Renglón') {
                $sheet->getStyle('A'.$line.':'.Coordinate::stringFromColumnIndex(count($columns)).$line)->getFont()->setBold(true);
            }
            $line++;
        }

        $this->formatColumns($sheet, $columns, $first, max($first, $line - 1));
        $sheet->freezePane('H'.$first);
        $sheet->setAutoFilter('A'.($first - 1).':'.Coordinate::stringFromColumnIndex(count($columns)).max($first, $line - 1));
    }

    /** Renglones con su subtotal de centro y de empresa intercalados, y el total general al final. */
    private function summaryRows(array $result): Collection
    {
        $rows = collect();
        foreach ($result['rows'] as $row) {
            $rows->push($row);
            if ($row['last_of_cost_center']) {
                $rows->push($result['cost_centers'][$row['cost_center_id']] + [
                    '_kind' => 'Subtotal centro', 'company_name' => $row['company_name'], 'company_rfc' => $row['company_rfc'],
                    'cost_center_code' => $row['cost_center_code'], 'cost_center_name' => $row['cost_center_name'], 'responsible_name' => $row['responsible_name'],
                ]);
            }
            if ($row['last_of_company']) {
                $rows->push($result['companies'][$row['company_id']] + ['_kind' => 'Subtotal empresa', 'company_name' => $row['company_name'], 'company_rfc' => $row['company_rfc']]);
            }
        }
        if ($result['rows']->isNotEmpty()) {
            $rows->push($result['total'] + ['_kind' => 'Total general']);
        }

        return $rows;
    }

    private function detailSheet(Worksheet $sheet, array $header, Collection $documents): void
    {
        $sheet->setTitle('Detalle por documento');
        $columns = [
            ['Empresa', fn ($d) => $d['company_name'], null],
            ['Centro de costo', fn ($d) => $d['cost_center_code'], null],
            ['Nombre del centro', fn ($d) => $d['cost_center_name'], null],
            ['Clave del renglón', fn ($d) => $d['budget_line_code'], null],
            ['Renglón', fn ($d) => $d['budget_line_name'], null],
            ['Subcuenta', fn ($d) => $d['budget_cedula_name'], null],
            ['Monto', fn ($d) => self::BUCKET_LABELS[$d['bucket']], null],
            ['Documento', fn ($d) => $d['type'], null],
            ['Folio', fn ($d) => $d['folio'], null],
            ['Mes de aplicación', fn ($d) => $d['application_month'], null],
            ['Estatus del compromiso', fn ($d) => $d['status'], null],
            ['Fecha del compromiso', fn ($d) => $d['committed_at'] ? ExcelDate::PHPToExcel($d['committed_at']) : null, 'dd/mm/yyyy'],
            ['Comprometido', fn ($d) => $d['committed_amount'], self::MONEY],
            ['Recibido', fn ($d) => $d['consumed_amount'], self::MONEY],
            ['Importe en este monto', fn ($d) => $d['amount'], self::MONEY],
        ];
        $first = $this->writeHeader($sheet, $header, array_column($columns, 0));
        $line = $first;
        foreach ($documents as $document) {
            $sheet->fromArray(array_map(fn ($column) => $column[1]($document), $columns), null, 'A'.$line++, true);
        }

        $this->formatColumns($sheet, $columns, $first, max($first, $line - 1));
        $sheet->freezePane('A'.$first);
        $sheet->setAutoFilter('A'.($first - 1).':'.Coordinate::stringFromColumnIndex(count($columns)).max($first, $line - 1));
    }

    private function csv(Collection $rows, array $params, string $filename): StreamedResponse
    {
        $columns = $this->columns($params);

        return response()->streamDownload(function () use ($rows, $columns) {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_column($columns, 0));
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($column) => $column[1]($row), $columns));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Escribe el bloque de título y los encabezados de columna; regresa la primera fila de datos. */
    private function writeHeader(Worksheet $sheet, array $header, array $columns): int
    {
        foreach ($header as $index => $text) {
            $sheet->setCellValue('A'.($index + 1), $text);
        }
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $headingRow = count($header) + 2;
        $sheet->fromArray($columns, null, 'A'.$headingRow);
        $sheet->getStyle('A'.$headingRow.':'.Coordinate::stringFromColumnIndex(count($columns)).$headingRow)->getFont()->setBold(true);

        return $headingRow + 1;
    }

    private function formatColumns(Worksheet $sheet, array $columns, int $first, int $last): void
    {
        foreach ($columns as $index => [$label, $value, $format]) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            if ($format) {
                $sheet->getStyle("{$letter}{$first}:{$letter}{$last}")->getNumberFormat()->setFormatCode($format);
            }
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }
    }

    private function headerLines(User $user, array $params): array
    {
        $names = fn ($model, array $ids, string $column) => $ids ? $model::query()->whereIn('id', $ids)->orderBy($column)->pluck($column)->implode(', ') : null;
        $month = ucfirst(now()->setDate($params['fiscal_year'], $params['period_month'], 1)->locale('es')->monthName);
        $filters = array_filter([
            'Centros' => $names(CostCenter::class, $params['cost_center_ids'], 'code'),
            'Cuentas' => $names(ExpenseCategory::class, $params['expense_category_ids'], 'code'),
            'Responsable' => $params['responsible_user_id'] ? User::query()->whereKey($params['responsible_user_id'])->value('name') : null,
            'OC canceladas' => $params['include_cancelled_po'] ? 'incluidas (informativo)' : null,
        ]);

        return [
            'RP-01 · Presupuesto vs. ejercido por departamento y renglón',
            'Empresa(s): '.($names(Company::class, $params['company_ids'], 'name') ?? 'todas las de tu alcance'),
            'Periodo: '.$month.' '.$params['fiscal_year'].' · '.($params['scope'] === 'ACU' ? 'acumulado del ejercicio al mes' : 'solo el mes'),
            'Filtros: '.($filters ? collect($filters)->map(fn ($value, $key) => "{$key}: {$value}")->implode('; ') : 'ninguno'),
            'Generado por '.$user->name.' el '.now(config('app.timezone'))->format('d/m/Y H:i'),
            'Pagado no disponible: el portal no registra pagos a proveedores; lo recibido permanece en Devengado.',
        ];
    }
}
