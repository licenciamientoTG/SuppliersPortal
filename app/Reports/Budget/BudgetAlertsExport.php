<?php

namespace App\Reports\Budget;

use App\Models\Company;
use App\Models\CostCenter;
use App\Models\User;
use App\Reports\Support\ReportSheet;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Exportación de RP-03: Excel (renglones en riesgo + excepciones) y CSV (renglones en riesgo). */
class BudgetAlertsExport
{
    private const STATUS_LABELS = ['PENDING' => 'Pendiente', 'APPROVED' => 'Aprobada', 'REJECTED' => 'Rechazada'];

    public function __construct(private readonly BudgetAlertsReport $report) {}

    /** @return array{response: StreamedResponse, rows: int, exception_rows: int} */
    public function download(User $user, array $params, string $format): array
    {
        $result = $this->report->build($user, $params);
        $filename = sprintf('alertas-presupuestales-%d-umbral-%d', $params['fiscal_year'], $params['threshold']);

        if ($format === 'csv') {
            return ['response' => ReportSheet::csv($this->lineColumns(), $result['lines'], $filename.'.csv'), 'rows' => $result['lines']->count(), 'exception_rows' => 0];
        }

        $header = $this->headerLines($user, $params);
        $book = new Spreadsheet;
        ReportSheet::write($book->getActiveSheet(), 'Renglones en riesgo', $header, $this->lineColumns(), $result['lines'], 8);
        ReportSheet::write($book->createSheet(), 'Excepciones', $header, $this->exceptionColumns(), $result['exceptions'], 3);
        $book->setActiveSheetIndex(0);

        $response = response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), $filename.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

        return ['response' => $response, 'rows' => $result['lines']->count(), 'exception_rows' => $result['exceptions']->count()];
    }

    private function lineColumns(): array
    {
        $value = fn (string $key) => fn (array $row) => $row[$key] ?? null;

        return [
            ['Empresa', $value('company_name'), null],
            ['Centro de costo', $value('cost_center_code'), null],
            ['Nombre del centro', $value('cost_center_name'), null],
            ['Responsable', $value('responsible_name'), null],
            ['Clave del renglón', $value('budget_line_code'), null],
            ['Renglón', $value('budget_line_name'), null],
            ['Subcuenta', $value('budget_cedula_name'), null],
            ['Ejercicio', $value('fiscal_year'), null],
            ['Mes', $value('month'), null],
            ['Nivel de alerta', fn (array $row) => $row['alert_level'] ? $row['alert_level'].' %' : 'Excepción', null],
            ['Vigente', $value('current_budget'), ReportSheet::MONEY],
            ['Reservado', $value('reserved'), ReportSheet::MONEY],
            ['Comprometido', $value('committed'), ReportSheet::MONEY],
            ['Devengado', $value('accrued'), ReportSheet::MONEY],
            ['Consumido', $value('consumed_total'), ReportSheet::MONEY],
            ['% consumido', $value('progress_pct'), ReportSheet::PERCENT],
            ['Disponible', $value('available'), ReportSheet::MONEY],
            ['Ritmo de gasto (3 meses)', $value('burn_rate_3m'), ReportSheet::MONEY],
            ['Disponible restante del año', $value('remaining_year_available'), ReportSheet::MONEY],
            ['Meses para agotarse', $value('months_to_exhaustion'), '0.0'],
            ['Mes proyectado de agotamiento', fn (array $row) => $row['projected_exhaustion_month'] ? ExcelDate::PHPToExcel(Carbon::parse($row['projected_exhaustion_month'].'-01')) : null, ReportSheet::MONTH],
            ['Documentos en trámite', $value('pending_docs_count'), null],
            ['Importe en trámite', $value('pending_docs_amount'), ReportSheet::MONEY],
            ['Disponible si se rechazan', $value('available_if_rejected'), ReportSheet::MONEY],
            ['Folios en trámite', fn (array $row) => collect($row['pending_documents'] ?? [])->map(fn ($d) => $d['folio'] ?? 'sin folio')->implode(', '), null],
            ['Excepciones aprobadas', $value('approved_exceptions'), null],
        ];
    }

    private function exceptionColumns(): array
    {
        $value = fn (string $key) => fn (array $row) => $row[$key] ?? null;
        $date = fn (string $key) => fn (array $row) => $row[$key] ? ExcelDate::PHPToExcel(Carbon::parse($row[$key])->setTimezone(config('app.timezone'))) : null;

        return [
            ['Documento', $value('document_type'), null],
            ['Folio', $value('document_folio'), null],
            ['Partida', $value('document_line_id'), null],
            ['Empresa', $value('company_name'), null],
            ['Centro de costo', $value('cost_center'), null],
            ['Renglón', $value('budget_line'), null],
            ['Subcuenta', $value('budget_cedula'), null],
            ['Mes de aplicación', $value('application_month'), null],
            ['Importe de la partida', $value('line_amount'), ReportSheet::MONEY],
            ['Disponible al solicitar', $value('available_at_request'), ReportSheet::MONEY],
            ['Excedente solicitado', $value('requested_excess'), ReportSheet::MONEY],
            ['Excedente aprobado', $value('approved_excess'), ReportSheet::MONEY],
            ['Motivo', $value('reason'), null],
            ['Solicitó', $value('requester'), null],
            ['Fecha de solicitud', $date('requested_at'), ReportSheet::DATETIME],
            ['Estatus', fn (array $row) => self::STATUS_LABELS[$row['status']] ?? $row['status'], null],
            ['Autorizó', $value('decider'), null],
            ['Fecha de decisión', $date('decided_at'), ReportSheet::DATETIME],
            ['Comentario de la decisión', $value('decision_comment'), null],
            ['Registro completo', fn (array $row) => $row['complete'] ? 'Sí' : 'No: falta autorizador, motivo o fecha', null],
        ];
    }

    private function headerLines(User $user, array $params): array
    {
        $names = fn ($model, array $ids, string $column) => $ids ? $model::query()->whereIn('id', $ids)->orderBy($column)->pluck($column)->implode(', ') : null;
        $filters = array_filter([
            'Meses' => $params['months'] ? implode(', ', $params['months']) : null,
            'Centros' => $names(CostCenter::class, $params['cost_center_ids'], 'code'),
            'Responsable' => $params['responsible_user_id'] ? User::query()->whereKey($params['responsible_user_id'])->value('name') : null,
            'Estatus de excepciones' => $params['exception_status'] ? self::STATUS_LABELS[$params['exception_status']] : null,
        ]);

        return [
            'RP-03 · Alertas de agotamiento, sobregiro y excepciones autorizadas',
            'Empresa(s): '.($names(Company::class, $params['company_ids'], 'name') ?? 'todas las de tu alcance'),
            'Ejercicio '.$params['fiscal_year'].' · umbral '.$params['threshold'].' % · excepciones del '
                .Carbon::parse($params['exceptions_from'])->format('d/m/Y').' al '.Carbon::parse($params['exceptions_to'])->format('d/m/Y'),
            'Filtros: '.($filters ? collect($filters)->map(fn ($value, $key) => "{$key}: {$value}")->implode('; ') : 'ninguno'),
            'Generado por '.$user->name.' el '.now(config('app.timezone'))->format('d/m/Y H:i'),
            'Los documentos en trámite ya apartan presupuesto: están incluidos en el consumo.',
        ];
    }
}
