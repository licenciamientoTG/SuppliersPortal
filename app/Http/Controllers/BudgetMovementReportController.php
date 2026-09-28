<?php

namespace App\Http\Controllers;

use App\Models\BudgetMovementApprovalSetting;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\User;
use App\Services\BudgetMovementReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BudgetMovementReportController extends Controller
{
    public function __construct(private readonly BudgetMovementReportService $report) {}

    public function index(Request $request)
    {
        $centers = $this->visibleCostCenters($request);
        $companies = Company::query()->whereIn('id', $centers->pluck('company_id')->unique())->orderBy('name')->get(['id', 'name']);
        $visibleMovementIds = $this->visibleMovements($request)->select('budget_movements.id');
        $users = User::query()->whereIn('id', DB::table('budget_movement_decisions')->select('actor_user_id')->whereIn('budget_movement_id', $visibleMovementIds)->distinct()->whereNotNull('actor_user_id'))->orderBy('name')->get(['id', 'name']);

        return view('reports.budget-movements.index', compact('companies', 'centers', 'users'));
    }

    public function data(Request $request)
    {
        $this->validateFilters($request);

        return response()->json([
            'movements' => $this->report->movements($request),
            'reconciliation' => $this->report->reconciliation($request)->values(),
        ]);
    }

    public function export(Request $request, string $format)
    {
        abort_unless(in_array($format, ['xlsx', 'csv'], true), 404);
        $this->validateFilters($request);
        $result = $this->report->movements($request, false);
        $reconciliation = $this->report->reconciliation($request);
        $filename = 'movimientos-presupuestales-'.$request->input('fiscal_year', now()->year);

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($result) {
                $stream = fopen('php://output', 'wb');
                fwrite($stream, "\xEF\xBB\xBF");
                fputcsv($stream, ['Folio', 'Fecha', 'Tipo', 'Estatus', 'Empresa origen', 'Centro origen', 'Mes origen', 'Importe origen', 'Empresa destino', 'Centro destino', 'Mes destino', 'Importe destino', 'Importe', 'Solicitante', 'Autorizador Dirección', 'Cruza empresas', 'Violación de autorización', 'Movimiento inverso de', 'Justificación']);
                foreach ($result['rows'] as $row) {
                    fputcsv($stream, [$row['folio'], $row['movement_date'], $row['movement_type'], $row['status'], $row['origin']['company'] ?? '', $row['origin']['cost_center'] ?? '', $row['origin']['month'] ?? '', $row['origin']['amount'] ?? '', $row['destination']['company'] ?? '', $row['destination']['cost_center'] ?? '', $row['destination']['month'] ?? '', $row['destination']['amount'] ?? '', $row['total_amount'], $row['requester'], $row['final_authorizer'], $row['cross_company'] ? 'Sí' : 'No', $row['authorization_violation'] ? 'Sí' : 'No', $row['reversal_of'] ?? '', $row['justification']]);
                }
                fclose($stream);
            }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Movimientos');
        $sheet->fromArray(['Folio', 'Fecha', 'Tipo', 'Estatus', 'Empresa origen', 'Centro origen', 'Mes origen', 'Importe origen', 'Empresa destino', 'Centro destino', 'Mes destino', 'Importe destino', 'Importe', 'Solicitante', 'Autorizador Dirección', 'Cruza empresas', 'Violación de autorización', 'Reversa de', 'Justificación'], null, 'A1');
        $line = 2;
        foreach ($result['rows'] as $row) {
            $sheet->fromArray([$row['folio'], $row['movement_date'], $row['movement_type'], $row['status'], $row['origin']['company'] ?? '', $row['origin']['cost_center'] ?? '', $row['origin']['month'] ?? '', $row['origin']['amount'] ?? null, $row['destination']['company'] ?? '', $row['destination']['cost_center'] ?? '', $row['destination']['month'] ?? '', $row['destination']['amount'] ?? null, $row['total_amount'], $row['requester'], $row['final_authorizer'], $row['cross_company'] ? 'Sí' : 'No', $row['authorization_violation'] ? 'Sí' : 'No', $row['reversal_of'] ?? '', $row['justification']], null, 'A'.$line++);
        }
        foreach (['H', 'L', 'M'] as $column) {
            $sheet->getStyle($column.'2:'.$column.max(2, $line - 1))->getNumberFormat()->setFormatCode('$#,##0.00');
        }
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:S'.max(1, $line - 1));

        $reconSheet = $book->createSheet();
        $reconSheet->setTitle('Reconstrucción');
        $reconSheet->fromArray(['Empresa', 'Centro de costo', 'Año', 'Mes', 'Cuenta', 'Subcuenta', 'Base original', 'Movimientos aprobados', 'Reconstruido', 'Vigente', 'Diferencia', 'Estado'], null, 'A1');
        $line = 2;
        foreach ($reconciliation as $row) {
            $reconSheet->fromArray([$row['company_name'], $row['cost_center_code'].' · '.$row['cost_center_name'], $row['fiscal_year'], $row['month'], $row['expense_category_name'], $row['budget_cedula_name'], $row['original_amount'], $row['movement_total'], $row['reconstructed_amount'], $row['current_amount'], $row['difference'], $row['reconciliation_status']], null, 'A'.$line++);
        }
        $reconSheet->getStyle('G2:K'.max(2, $line - 1))->getNumberFormat()->setFormatCode('$#,##0.00');
        $reconSheet->freezePane('A2');
        $reconSheet->setAutoFilter('A1:L'.max(1, $line - 1));

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $filename.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function visibleCostCenters(Request $request)
    {
        $user = $request->user();
        $settings = BudgetMovementApprovalSetting::query()->first();
        $query = CostCenter::query()->with('company')->orderBy('name');
        if (! $user->hasRole('superadmin') && ! $settings?->canApprove($user)) {
            $movementIds = $this->visibleMovements($request)->select('budget_movements.id');
            $query->where(fn ($q) => $q->where('responsible_user_id', $user->id)->orWhereIn('id', DB::table('budget_movement_details')->whereIn('budget_movement_id', $movementIds)->select('cost_center_id')));
        }

        return $query->get();
    }

    private function validateFilters(Request $request): void
    {
        $request->validate([
            'fiscal_year' => ['nullable', 'integer', 'between:2020,2100'],
            'status' => ['nullable', 'in:APROBADO,PENDIENTE,PENDIENTE_ORIGEN,PENDIENTE_DIRECCION,DEVUELTO,RECHAZADO,TODOS'],
            'movement_type' => ['nullable', 'array'], 'movement_type.*' => ['in:TRANSFERENCIA,AMPLIACION,REDUCCION'],
            'company_ids' => ['nullable', 'array'], 'company_ids.*' => ['integer', 'exists:companies,id'],
            'origin_cost_center_id' => ['nullable', 'integer', 'exists:cost_centers,id'],
            'destination_cost_center_id' => ['nullable', 'integer', 'exists:cost_centers,id'],
            'authorized_by' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'amount_min' => ['nullable', 'numeric', 'min:0'], 'month' => ['nullable', 'integer', 'between:1,12'],
            'only_level_violations' => ['nullable', 'boolean'], 'per_page' => ['nullable', 'integer', 'between:10,100'],
        ]);
    }

    private function visibleMovements(Request $request)
    {
        $user = $request->user();
        $settings = BudgetMovementApprovalSetting::query()->first();
        $query = \App\Models\BudgetMovement::query();
        if (! $user->hasRole('superadmin') && ! $settings?->canApprove($user)) {
            $query->where(fn ($q) => $q->where('created_by', $user->id)->orWhereHas('details.costCenter', fn ($cc) => $cc->where('responsible_user_id', $user->id)));
        }

        return $query;
    }
}
