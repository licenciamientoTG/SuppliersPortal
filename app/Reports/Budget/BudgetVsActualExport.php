<?php

namespace App\Reports\Budget;

use App\Models\Company;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Reports\Support\ReportSheet;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Exportación de RP-01: Excel (resumen + detalle por documento) y CSV (renglones). */
class BudgetVsActualExport
{
    private const BUCKET_LABELS = ['reserved' => 'Reservado', 'committed' => 'Comprometido', 'accrued' => 'Devengado', 'released' => 'OC cancelada'];

    private const LIGHT_LABELS = ['VERDE' => 'Verde', 'AMARILLO' => 'Amarillo', 'ROJO' => 'Rojo'];

    public function __construct(private readonly BudgetVsActualReport $report) {}

    /** @return array{response: StreamedResponse, rows: int, detail_rows: int} */
    public function download(User $user, array $params, string $format): array
    {
        $result = $this->report->build($user, $params);
        $filename = sprintf('presupuesto-vs-ejercido-%d-%02d-%s', $params['fiscal_year'], $params['period_month'], strtolower($params['scope']));

        if ($format === 'csv') {
            return ['response' => ReportSheet::csv($this->columns($params), $result['rows'], $filename.'.csv'), 'rows' => $result['rows']->count(), 'detail_rows' => 0];
        }

        $documents = $this->report->documentLines($params, $result['rows']);
        $header = $this->headerLines($user, $params);
        $book = new Spreadsheet;
        // Se congelan las columnas de identificación (hasta el renglón) en el resumen.
        ReportSheet::write($book->getActiveSheet(), 'Resumen', $header, $this->columns($params), $this->summaryRows($result), 9,
            fn (array $row) => ($row['_kind'] ?? 'Renglón') !== 'Renglón');
        ReportSheet::write($book->createSheet(), 'Detalle por documento', $header, $this->detailColumns(), $documents);
        $book->setActiveSheetIndex(0);

        $response = response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), $filename.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

        return ['response' => $response, 'rows' => $result['rows']->count(), 'detail_rows' => $documents->count()];
    }

    /** Columnas de la hoja resumen y del CSV. */
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
            ['Autorizado', fn (array $row) => $row['authorized_amount'] ?? 'Sin base', ReportSheet::MONEY],
            ['Ampliaciones', $amount('increases'), ReportSheet::MONEY],
            ['Reducciones', $amount('decreases'), ReportSheet::MONEY],
            ['Vigente', $amount('current_budget'), ReportSheet::MONEY],
            ['Reservado', $amount('reserved'), ReportSheet::MONEY],
            ['Comprometido', $amount('committed'), ReportSheet::MONEY],
            ['Devengado', $amount('accrued'), ReportSheet::MONEY],
            // El portal no registra pagos: se informa como no disponible, nunca como cero.
            ['Pagado', fn () => 'N/D', null],
            ['Ejercido', $amount('exercised_total'), ReportSheet::MONEY],
            ['Consumido', $amount('consumed_total'), ReportSheet::MONEY],
            ['Disponible', $amount('available'), ReportSheet::MONEY],
            ['Sin conciliar', $amount('unreconciled'), ReportSheet::MONEY],
        ];
        if ($params['include_cancelled_po']) {
            $columns[] = ['OC canceladas (informativo)', $amount('released'), ReportSheet::MONEY];
        }

        return array_merge($columns, [
            ['% de avance', $amount('progress_pct'), ReportSheet::PERCENT],
            ['Semáforo', fn (array $row) => self::LIGHT_LABELS[$row['traffic_light'] ?? ''] ?? null, null],
            ['Proyección de cierre', $amount('projected_close'), ReportSheet::MONEY],
            ['Conciliación de base', fn (array $row) => match ($row['baseline_status'] ?? null) {
                'CONCILIA' => 'Concilia', 'DIFERENCIA' => 'Diferencia', 'SIN_BASE' => 'Sin base', default => null,
            }, null],
        ]);
    }

    private function detailColumns(): array
    {
        return [
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
            ['Fecha del compromiso', fn ($d) => $d['committed_at'] ? ExcelDate::PHPToExcel($d['committed_at']) : null, ReportSheet::DATE],
            ['Comprometido', fn ($d) => $d['committed_amount'], ReportSheet::MONEY],
            ['Recibido', fn ($d) => $d['consumed_amount'], ReportSheet::MONEY],
            ['Importe en este monto', fn ($d) => $d['amount'], ReportSheet::MONEY],
        ];
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
