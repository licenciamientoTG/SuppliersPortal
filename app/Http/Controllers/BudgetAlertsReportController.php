<?php

namespace App\Http\Controllers;

use App\Models\BudgetException;
use App\Models\BudgetMonthlyDistribution;
use App\Models\Company;
use App\Models\CostCenter;
use App\Services\BudgetAlertsReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BudgetAlertsReportController extends Controller
{
    public function __construct(private readonly BudgetAlertsReportService $report) {}

    public function index(Request $request)
    {
        $companies = Company::query()->orderBy('name')->get(['id', 'name']);
        $centers = CostCenter::query()->with('company')->when(! $request->user()->hasRole(['superadmin', 'general_director', 'accounting', 'controller']), fn ($q) => $q->where(fn ($scope) => $scope->where('responsible_user_id', $request->user()->id)->orWhereHas('activeUsers', fn ($users) => $users->where('users.id', $request->user()->id))))->orderBy('name')->get();

        return view('reports.budget-alerts.index', compact('companies', 'centers'));
    }

    public function data(Request $request)
    {
        $this->validateFilters($request);
        $lines = $this->report->lines($request);

        return response()->json(['lines' => $lines->forPage($request->integer('page', 1), $request->integer('per_page', 25))->values(), 'total' => $lines->count(), 'exceptions' => $this->report->exceptions($request)->paginate($request->integer('per_page', 25))]);
    }

    public function export(Request $request, string $format)
    {
        abort_unless(in_array($format, ['csv', 'xlsx'], true), 404);
        $this->validateFilters($request);
        $lines = $this->report->lines($request);
        $exceptions = $this->report->exceptions($request)->get();
        if ($format === 'csv') {
            return response()->streamDownload(function () use ($lines, $exceptions) {
                $out = fopen('php://output', 'wb');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, ['Empresa', 'Centro', 'Responsable', 'Año', 'Mes', 'Cuenta', 'Subcuenta', 'Asignado', 'Consumido', 'Comprometido', 'Ejercido', 'Disponible', '% consumido', 'Ritmo 3m', 'Agotamiento proyectado', 'Historial', 'Documentos comprometidos']);
                foreach ($lines as $r) {
                    fputcsv($out, [$r['company'], $r['cost_center'], $r['responsible'], $r['year'], $r['month'], $r['account'], $r['subaccount'], $r['assigned'], $r['consumed'], $r['committed'], $r['consumed_total'], $r['available'], $r['consumed_pct'], $r['burn_rate_3m'], $r['projected_exhaustion'], $r['history_status'], implode(', ', $r['pending_documents'])]);
                }
                fputcsv($out, []);
                fputcsv($out, ['Excepciones']);
                fputcsv($out, ['Documento', 'Partida', 'Importe partida', 'Excedente solicitado', 'Excedente aprobado', 'Motivo', 'Solicitante', 'Autorizador', 'Estatus', 'Fecha']);
                foreach ($exceptions as $e) {
                    fputcsv($out, [$e->document_type.' #'.$e->document_id, $e->document_line_id, $e->line_amount, $e->requested_excess, $e->approved_excess, $e->reason, $e->requester?->name, $e->decider?->name, $e->status, $e->requested_at]);
                } fclose($out);
            }, 'alertas-presupuestales.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Renglones en riesgo');
        $sheet->fromArray(['Empresa', 'Centro', 'Responsable', 'Año', 'Mes', 'Cuenta', 'Subcuenta', 'Asignado', 'Consumido', 'Comprometido', 'Ejercido', 'Disponible', '% consumido', 'Ritmo 3m', 'Agotamiento proyectado', 'Historial', 'Documentos comprometidos'], null, 'A1');
        foreach ($lines as $i => $r) {
            $sheet->fromArray([$r['company'], $r['cost_center'], $r['responsible'], $r['year'], $r['month'], $r['account'], $r['subaccount'], $r['assigned'], $r['consumed'], $r['committed'], $r['consumed_total'], $r['available'], $r['consumed_pct'], $r['burn_rate_3m'], $r['projected_exhaustion'], $r['history_status'], implode(', ', $r['pending_documents'])], null, 'A'.($i + 2));
        }
        $excSheet = $book->createSheet();
        $excSheet->setTitle('Excepciones');
        $excSheet->fromArray(['Documento', 'Partida', 'Importe partida', 'Excedente solicitado', 'Excedente aprobado', 'Motivo', 'Solicitante', 'Autorizador', 'Estatus', 'Fecha'], null, 'A1');
        foreach ($exceptions as $i => $e) {
            $excSheet->fromArray([$e->document_type.' #'.$e->document_id, $e->document_line_id, $e->line_amount, $e->requested_excess, $e->approved_excess, $e->reason, $e->requester?->name, $e->decider?->name, $e->status, (string) $e->requested_at], null, 'A'.($i + 2));
        }

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), 'alertas-presupuestales.xlsx');
    }

    public function requestException(Request $request)
    {
        abort_unless($request->user()->can('reportes.budget_alerts.excepcion.solicitar'), 403);
        $data = $request->validate(['document_type' => ['required', 'in:direct_purchase_order'], 'document_id' => ['required', 'integer'], 'document_line_id' => ['required', 'integer'], 'reason' => ['required', 'string', 'min:10', 'max:2000']]);
        abort_unless($data['document_type'] === 'direct_purchase_order', 422);
        $document = \App\Models\DirectPurchaseOrder::with('items')->findOrFail($data['document_id']);
        $this->authorize('view', $document);
        $item = $document->items->firstWhere('id', $data['document_line_id']);
        abort_if(! $item, 404);
        abort_if(BudgetException::query()->where('document_type', $data['document_type'])->where('document_id', $document->id)->where('document_line_id', $item->id)->whereIn('status', ['PENDING', 'APPROVED'])->exists(), 409, 'Esta partida ya tiene una excepción pendiente o aprobada.');
        $date = \Carbon\Carbon::parse($document->application_month.'-01');
        $check = app(\App\Services\BudgetAllocationService::class)->checkAvailability((int) $item->cost_center_id, (int) $date->year, (int) $date->month, (int) $item->expense_category_id, (float) $item->total, $item->budget_cedula_id ? (int) $item->budget_cedula_id : null);
        abort_if(($check['available'] ?? false), 422, 'Esta partida ya cuenta con presupuesto suficiente.');

        return DB::transaction(function () use ($data, $document, $item, $check, $request, $date) {
            return BudgetException::create(['document_type' => $data['document_type'], 'document_id' => $document->id, 'document_line_id' => $item->id, 'cost_center_id' => $item->cost_center_id, 'expense_category_id' => $item->expense_category_id, 'budget_cedula_id' => $item->budget_cedula_id, 'application_month' => $date->format('Y-m'), 'line_amount' => $item->total, 'available_at_request' => $check['available_amount'] ?? 0, 'requested_excess' => max(0, (float) $item->total - (float) ($check['available_amount'] ?? 0)), 'reason' => $data['reason'], 'status' => 'PENDING', 'requested_by' => $request->user()->id, 'requested_at' => now()]);
        });
    }

    public function decideException(Request $request, BudgetException $exception)
    {
        abort_unless($request->user()->hasRole('general_director'), 403);
        $data = $request->validate(['decision' => ['required', 'in:APPROVED,REJECTED'], 'comment' => ['nullable', 'string', 'max:2000']]);

        return DB::transaction(function () use ($exception, $request, $data) {
            $exception = BudgetException::query()->lockForUpdate()->findOrFail($exception->id);
            abort_unless($exception->status === 'PENDING', 409);
            $excess = (float) $exception->requested_excess;
            $line = null;
            if ($data['decision'] === 'APPROVED') {
                $line = BudgetMonthlyDistribution::find($exception->budget_monthly_distribution_id);
                if (! $line) {
                    $line = BudgetMonthlyDistribution::query()->whereHas('annualBudget', fn ($q) => $q->where('cost_center_id', $exception->cost_center_id)->where('fiscal_year', (int) substr($exception->application_month, 0, 4)))
                        ->where('month', (int) substr($exception->application_month, 5, 2))->where('expense_category_id', $exception->expense_category_id)
                        ->when($exception->budget_cedula_id, fn ($q) => $q->where('budget_cedula_id', $exception->budget_cedula_id))->first();
                    if ($line) {
                        $exception->budget_monthly_distribution_id = $line->id;
                    }
                }
                if ($line) {
                    $available = (float) $line->assigned_amount - (float) $line->consumed_amount - (float) $line->committed_amount;
                    $excess = max(0, round((float) $exception->line_amount - $available, 2));
                }
            }
            $exception->update(['budget_monthly_distribution_id' => $line?->id ?? $exception->budget_monthly_distribution_id, 'status' => $data['decision'], 'approved_excess' => $data['decision'] === 'APPROVED' ? $excess : null, 'decided_by' => $request->user()->id, 'decision_comment' => $data['comment'] ?? null, 'decided_at' => now()]);

            return $exception->fresh();
        });
    }

    private function validateFilters(Request $request): void
    {
        $request->validate(['year' => ['nullable', 'integer', 'between:2020,2100'], 'company_id' => ['nullable', 'integer', 'exists:companies,id'], 'cost_center_id' => ['nullable', 'integer', 'exists:cost_centers,id'], 'status' => ['nullable', 'in:PENDING,APPROVED,REJECTED'], 'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'between:10,100']]);
    }
}
