<?php

namespace App\Reports\Purchasing;

use App\Enum\RequisitionStatus;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\User;
use App\Reports\Support\ReportSheet;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Exportación de RC-01: Excel (requisiciones, etapas y resumen) y CSV (requisiciones). */
class RequisitionPipelineExport
{
    private const HOURS = '#,##0.0';

    public function __construct(private readonly RequisitionPipelineReport $report) {}

    /** @return array{response: StreamedResponse, rows: int, step_rows: int} */
    public function download(User $user, array $params, string $format): array
    {
        $result = $this->report->build($user, $params);
        $filename = sprintf('pipeline-requisiciones-%s-al-%s', $params['date_from'], $params['date_to']);

        if ($format === 'csv') {
            return ['response' => ReportSheet::csv($this->rowColumns(), $result['rows'], $filename.'.csv'), 'rows' => $result['rows']->count(), 'step_rows' => 0];
        }

        $folios = $result['rows']->pluck('folio', 'id');
        $steps = $result['timelines']->flatMap(fn (array $timeline, int $id) => collect($timeline['steps'])
            ->map(fn (array $step) => $step + ['folio' => $folios->get($id)]))->values();

        $header = $this->headerLines($user, $params);
        $book = new Spreadsheet;
        ReportSheet::write($book->getActiveSheet(), 'Requisiciones', $header, $this->rowColumns(), $result['rows'], 2);
        ReportSheet::write($book->createSheet(), 'Etapas', $header, $this->stepColumns(), $steps, 3);
        ReportSheet::write($book->createSheet(), 'Resumen por etapa', $header, $this->summaryColumns('Etapa'), $result['stages'], 2);
        ReportSheet::write($book->createSheet(), 'Resumen por persona', $header, $this->summaryColumns('Resolvió'), $result['approvers'], 2);
        $book->setActiveSheetIndex(0);

        $response = response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), $filename.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

        return ['response' => $response, 'rows' => $result['rows']->count(), 'step_rows' => $steps->count()];
    }

    private function rowColumns(): array
    {
        $value = fn (string $key) => fn (array $row) => $row[$key] ?? null;

        return [
            ['Folio', $value('folio'), null],
            ['Empresa', $value('company_name'), null],
            ['Fecha de creación', $this->date('created_at'), ReportSheet::DATETIME],
            ['Solicitante', $value('requester_name'), null],
            ['Centro(s) de costo', $value('cost_centers'), null],
            ['Importe cotizado', $value('estimated_amount'), ReportSheet::MONEY],
            ['REPSE', fn (array $row) => $row['is_repse'] ? 'Sí' : 'No', null],
            ['Estatus', $value('status_label'), null],
            ['Etapa actual', $value('current_step'), null],
            ['Pendiente con', $value('pending_approver'), null],
            ['Horas en la etapa actual', $value('hours_in_current_step'), self::HOURS],
            ['Horas de ciclo', $value('total_cycle_hours'), self::HOURS],
            ['Historial', fn (array $row) => $row['has_history'] ? 'Completo' : 'Sin historial (creada antes del '.Carbon::parse(RequisitionTimeline::HISTORY_SINCE)->format('d/m/Y').')', null],
            ['Motivo de rechazo o cancelación', $value('outcome_reason'), null],
            ['OC', $value('po_folios'), null],
        ];
    }

    private function stepColumns(): array
    {
        $value = fn (string $key) => fn (array $row) => $row[$key] ?? null;

        return [
            ['Folio', $value('folio'), null],
            ['Paso', $value('step_order'), null],
            ['Etapa', $value('step_name'), null],
            ['Entrada', $this->date('entered_at'), ReportSheet::DATETIME],
            ['Salida', $this->date('exited_at'), ReportSheet::DATETIME],
            ['Horas', $value('hours'), self::HOURS],
            ['Abierta', fn (array $row) => $row['is_open'] ? 'Sí' : 'No', null],
            ['Movió a esta etapa', $value('entered_by'), null],
            ['Resolvió', $value('resolved_by'), null],
            ['Salió hacia', $value('exit_to'), null],
            ['Decisiones de autorización', fn (array $row) => collect($row['decisions'])->map(fn (array $d) => trim($d['action'].' · '.($d['actor'] ?? '').($d['principal'] && $d['principal'] !== $d['actor'] ? ' por '.$d['principal'] : '').' · '.Carbon::parse($d['acted_at'])->setTimezone(config('app.timezone'))->format('d/m/Y H:i')))->implode('; '), null],
        ];
    }

    private function summaryColumns(string $label): array
    {
        $value = fn (string $key) => fn (array $row) => $row[$key] ?? null;

        return [
            [$label, $value('name'), null],
            ['Etapas', $value('steps'), null],
            ['Horas promedio', $value('avg_hours'), self::HOURS],
            ['Mediana de horas', $value('median_hours'), self::HOURS],
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
            'Estatus' => collect($params['statuses'])->map(fn ($status) => RequisitionStatus::tryFrom($status)?->label() ?? $status)->implode(', '),
            'Centros' => $names(CostCenter::class, $params['cost_center_ids'], 'code'),
            'Pendiente con' => $params['pending_approver_id'] ? User::query()->whereKey($params['pending_approver_id'])->value('name') : null,
            'Antigüedad mayor a' => $params['older_than_days'] ? $params['older_than_days'].' días' : null,
            'Importe desde' => $params['amount_from'],
            'Importe hasta' => $params['amount_to'],
        ], fn ($value) => $value !== null && $value !== '');

        return [
            'RC-01 · Pipeline de requisiciones y tiempos de ciclo',
            'Empresa(s): '.($names(Company::class, $params['company_ids'], 'name') ?? 'todas las de tu alcance'),
            'Creadas del '.Carbon::parse($params['date_from'])->format('d/m/Y').' al '.Carbon::parse($params['date_to'])->format('d/m/Y'),
            'Filtros: '.($filters ? collect($filters)->map(fn ($value, $key) => "{$key}: {$value}")->implode('; ') : 'ninguno'),
            'Generado por '.$user->name.' el '.now(config('app.timezone'))->format('d/m/Y H:i'),
            'Horas naturales. Horas de ciclo = suma de las horas por etapa. El importe es lo cotizado: la requisición no lleva precio.',
        ];
    }
}
