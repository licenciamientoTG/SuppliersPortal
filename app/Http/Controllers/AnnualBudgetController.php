<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveBudgetRequest;
use App\Http\Requests\SaveAnnualBudgetRequest;
use App\Models\AnnualBudget;
use App\Models\BudgetMonthlyDistribution;
use App\Models\CostCenter;
use App\Models\User;
use App\Services\BudgetCategorySummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class AnnualBudgetController extends Controller
{
    public function __construct(
        private readonly BudgetCategorySummaryService $categorySummaryService
    ) {
    }

    public function index(): View
    {
        return view('annual_budgets.index');
    }

    public function datatable(Request $request)
    {
        $query = AnnualBudget::query()
            ->with([
                'costCenter:id,code,name,company_id',
                'costCenter.company:id,name',
                'approvedBy:id,name',
                'createdBy:id,name',
            ])
            ->withCount('monthlyDistributions')
            ->notDeleted();

        if ($request->filled('fiscal_year')) {
            $query->where('fiscal_year', (int) $request->input('fiscal_year'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('cost_center_id')) {
            $query->where('cost_center_id', (int) $request->input('cost_center_id'));
        }

        return DataTables::of($query)
            ->addColumn('company_name', fn (AnnualBudget $row) => e($row->costCenter?->company?->name ?? '—'))
            ->addColumn('cost_center_label', function (AnnualBudget $row) {
                if (! $row->costCenter) {
                    return '—';
                }

                $code = $row->costCenter->code ? '[' . $row->costCenter->code . '] ' : '';
                return e($code . ($row->costCenter->name ?? '—'));
            })
            ->editColumn('fiscal_year', fn (AnnualBudget $row) => (int) $row->fiscal_year)
            ->editColumn('total_annual_amount', fn (AnnualBudget $row) => '$' . number_format((float) $row->total_annual_amount, 2))
            ->addColumn('status_label', function (AnnualBudget $row) {
                $badges = [
                    'PLANIFICACION' => '<span class="badge bg-info">En Planificación</span>',
                    'APROBADO' => '<span class="badge bg-success">Aprobado</span>',
                    'CERRADO' => '<span class="badge bg-secondary">Cerrado</span>',
                ];

                return $badges[$row->status] ?? '<span class="badge bg-secondary">' . e($row->status) . '</span>';
            })
            ->addColumn('approved_by_name', fn (AnnualBudget $row) => e($row->approvedBy?->name ?? '—'))
            ->addColumn('actions', function (AnnualBudget $row) {
                $editUrl = route('annual_budgets.edit', $row->id);
                $deleteUrl = route('annual_budgets.destroy', $row->id);
                $showUrl = route('annual_budgets.show', $row->id);

                $actions = '<div class="d-flex justify-content-end gap-1">'
                    . '<a class="btn btn-sm btn-outline-secondary" href="' . $showUrl . '" title="Ver detalle"><i class="ti ti-eye"></i></a>';

                if ($row->monthly_distributions_count > 0) {
                    $actions .= '<a class="btn btn-sm btn-outline-secondary" href="' . route('budget_monthly_distributions.edit', $row->id) . '" title="Ver/Editar Distribuciones"><i class="ti ti-calendar-stats"></i></a>';
                } elseif ($row->status === 'PLANIFICACION') {
                    $actions .= '<a class="btn btn-sm btn-outline-secondary" href="' . route('budget_monthly_distributions.create', $row->id) . '" title="Crear Distribuciones"><i class="ti ti-calendar-plus"></i></a>';
                }

                if ($row->status === 'PLANIFICACION') {
                    $actions .= '<a class="btn btn-sm btn-outline-primary" href="' . $editUrl . '" title="Editar"><i class="ti ti-pencil"></i></a>';
                    $actions .= '<a class="btn btn-sm btn-outline-success" href="' . route('annual_budgets.approve', $row->id) . '" title="Aprobar"><i class="ti ti-check"></i></a>';
                }

                $actions .= '<form action="' . $deleteUrl . '" method="POST" class="js-delete-form d-inline">'
                    . csrf_field()
                    . method_field('DELETE')
                    . '<button type="button" class="btn btn-sm btn-outline-danger js-delete-btn" data-entity="Presupuesto ' . $row->fiscal_year . '" title="Eliminar"><i class="ti ti-trash"></i></button>'
                    . '</form></div>';

                return $actions;
            })
            ->rawColumns(['status_label', 'actions'])
            ->make(true);
    }

    public function show(AnnualBudget $annual_budget): View
    {
        $annual_budget->load([
            'costCenter.company',
            'monthlyDistributions.budgetCedula.expenseCategory',
            'monthlyDistributions.expenseCategory',
            'approvedBy',
            'createdBy',
            'updatedBy',
        ]);

        $summary = [
            'total_assigned' => $annual_budget->monthlyDistributions->sum('assigned_amount'),
            'total_consumed' => $annual_budget->monthlyDistributions->sum('consumed_amount'),
            'total_committed' => $annual_budget->monthlyDistributions->sum('committed_amount'),
        ];
        $summary['total_available'] = $summary['total_assigned'] - $summary['total_consumed'] - $summary['total_committed'];
        $summary['usage_percentage'] = $summary['total_assigned'] > 0
            ? (($summary['total_consumed'] + $summary['total_committed']) / $summary['total_assigned']) * 100
            : 0;

        $categorySummaries = $this->categorySummaryService->forBudget($annual_budget);

        return view('annual_budgets.show', compact('annual_budget', 'summary', 'categorySummaries'));
    }

    public function create(): View
    {
        $annualBudget = new AnnualBudget([
            'fiscal_year' => (int) date('Y'),
            'total_annual_amount' => 0,
            'status' => 'PLANIFICACION',
        ]);

        $costCenters = CostCenter::where('budget_type', 'ANNUAL')
            ->where('status', 'ACTIVO')
            ->with('company', 'category')
            ->orderBy('name')
            ->get();

        return view('annual_budgets.create', compact('annualBudget', 'costCenters'));
    }

    public function store(SaveAnnualBudgetRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = Auth::id();
        $data['status'] = 'PLANIFICACION';

        try {
            AnnualBudget::create($data);
        } catch (QueryException $exception) {
            $isDuplicateBudget = ($exception->errorInfo[0] ?? null) === '23000'
                && str_contains($exception->getMessage(), 'UX_annual_budgets_center_year');

            if (! $isDuplicateBudget) {
                throw $exception;
            }

            return back()
                ->withInput()
                ->withErrors([
                    'cost_center_id' => 'Ya existe un presupuesto para este centro de costo y año fiscal.',
                ]);
        }

        return redirect()->route('annual_budgets.index')
            ->with('success', 'Presupuesto anual creado correctamente.');
    }

    public function edit(AnnualBudget $annual_budget): View|RedirectResponse
    {
        if ($annual_budget->status !== 'PLANIFICACION') {
            return redirect()->route('annual_budgets.index')
                ->with('warning', 'Solo se pueden editar presupuestos en estado "En Planificación".');
        }

        $costCenters = CostCenter::where('budget_type', 'ANNUAL')
            ->where('status', 'ACTIVO')
            ->whereDoesntHave('annualBudgets', function ($query) use ($annual_budget) {
                $query->where('fiscal_year', $annual_budget->fiscal_year)
                    ->where('id', '!=', $annual_budget->id);
            })
            ->with('company', 'category')
            ->orderBy('name')
            ->get();

        return view('annual_budgets.edit', compact('annual_budget', 'costCenters'));
    }

    public function update(SaveAnnualBudgetRequest $request, AnnualBudget $annual_budget): RedirectResponse
    {
        if ($annual_budget->status !== 'PLANIFICACION') {
            return redirect()->route('annual_budgets.index')
                ->with('warning', 'Solo se pueden editar presupuestos en estado "En Planificación".');
        }

        $data = $request->validated();
        $data['updated_by'] = Auth::id();
        $annual_budget->update($data);

        return redirect()->route('annual_budgets.index')
            ->with('success', 'Presupuesto anual actualizado correctamente.');
    }

    public function approve(AnnualBudget $annual_budget): View|RedirectResponse
    {
        if ($annual_budget->status !== 'PLANIFICACION') {
            return redirect()->route('annual_budgets.index')
                ->with('warning', 'Solo se pueden aprobar presupuestos en estado "En Planificación".');
        }

        $annual_budget->load('costCenter.company', 'monthlyDistributions.budgetCedula.expenseCategory');

        return view('annual_budgets.approve', compact('annual_budget'));
    }

    public function approveStore(ApproveBudgetRequest $request, AnnualBudget $annual_budget): RedirectResponse
    {
        if ($annual_budget->status !== 'PLANIFICACION') {
            return redirect()->route('annual_budgets.index')
                ->with('warning', 'Solo se pueden aprobar presupuestos en estado "En Planificación".');
        }

        if (! $annual_budget->monthlyDistributions()->exists()) {
            return back()->with('danger', 'No se puede aprobar un presupuesto sin distribuciones mensuales. Por favor, crea primero las distribuciones.');
        }

        $annual_budget->update([
            'status' => 'APROBADO',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('annual_budgets.index')
            ->with('success', 'Presupuesto anual aprobado correctamente. Está listo para usar en requisiciones.');
    }

    public function destroy(AnnualBudget $annual_budget): RedirectResponse
    {
        if ($annual_budget->monthlyDistributions()->exists()) {
            $annual_budget->update(['deleted_by' => Auth::id()]);
        }

        $annual_budget->delete();

        return redirect()->route('annual_budgets.index')
            ->with('success', 'Presupuesto anual eliminado correctamente.');
    }

    public function checkAvailability(Request $request)
    {
        $costCenterId = $request->input('cost_center_id');
        $categoryId = $request->input('expense_category_id');
        $month = $request->input('application_month');
        $fiscalYear = $request->input('fiscal_year');

        if (! $costCenterId || ! $categoryId || ! $month || ! $fiscalYear) {
            return response()->json([
                'success' => false,
                'message' => 'Faltan parámetros requeridos'
            ], 400);
        }

        $annualBudget = AnnualBudget::where('cost_center_id', $costCenterId)
            ->where('fiscal_year', $fiscalYear)
            ->with(['monthlyDistributions.budgetCedula.expenseCategory'])
            ->first();

        if (! $annualBudget) {
            return response()->json([
                'success' => false,
                'message' => 'No hay presupuesto asignado para este centro de costos.',
                'has_budget' => false
            ]);
        }

        $costCenter = CostCenter::find($costCenterId);
        if ($costCenter && $costCenter->budget_type === 'FREE_CONSUMPTION') {
            return response()->json([
                'success' => true,
                'message' => 'Centro de consumo libre - Sin límite presupuestal',
                'has_budget' => true,
                'is_free_consumption' => true,
                'budget_type' => 'free'
            ]);
        }

        $summary = $this->categorySummaryService->forBudgetMonthAndCategory($annualBudget, (int) $month, (int) $categoryId);

        if (! $summary) {
            return response()->json([
                'success' => false,
                'message' => 'No hay presupuesto asignado para esta cuenta.',
                'has_budget' => false
            ]);
        }

        return response()->json([
            'success' => true,
            'has_budget' => true,
            'is_free_consumption' => false,
            'budget_type' => 'normal',
            'data' => [
                'assigned' => number_format($summary['assigned_amount'], 2),
                'consumed' => number_format($summary['consumed_amount'], 2),
                'committed' => number_format($summary['committed_amount'], 2),
                'available' => number_format($summary['available_amount'], 2),
                'available_raw' => $summary['available_amount'],
                'percentage' => round($summary['assigned_amount'] > 0 ? ($summary['available_amount'] / $summary['assigned_amount']) * 100 : 0, 2),
                'is_sufficient' => $summary['available_amount'] > 0,
                'currency' => 'MXN',
            ],
            'message' => $summary['available_amount'] > 0
                ? 'Presupuesto disponible'
                : 'Presupuesto agotado para este mes',
        ]);
    }

    public function getAvailableCategoriesForMonth(Request $request)
    {
        try {
            $costCenterId = $request->input('cost_center_id');
            $applicationMonth = $request->input('application_month');

            if (! $costCenterId || ! $applicationMonth) {
                return response()->json([
                    'success' => false,
                    'message' => 'Faltan parámetros requeridos (cost_center_id, application_month)',
                    'has_budget' => false,
                    'categories' => []
                ], 400);
            }

            $costCenter = CostCenter::find($costCenterId);
            if (! $costCenter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Centro de costo no encontrado',
                    'has_budget' => false,
                    'categories' => []
                ], 404);
            }

            try {
                $date = \Carbon\Carbon::createFromFormat('Y-m', $applicationMonth);
            } catch (\Throwable) {
                return response()->json([
                    'success' => false,
                    'message' => 'Formato de mes inválido. Use YYYY-MM',
                    'has_budget' => false,
                    'categories' => []
                ], 400);
            }

            $fiscalYear = $date->year;
            $month = $date->month;

            if ($costCenter->budget_type === 'FREE_CONSUMPTION') {
                $allCategories = \App\Models\ExpenseCategory::active()
                    ->orderBy('code')
                    ->get(['id', 'code', 'name'])
                    ->map(fn ($category) => [
                        'id' => $category->id,
                        'code' => $category->code,
                        'name' => $category->name,
                        'is_free_consumption' => true,
                        'assigned' => 0,
                        'consumed' => 0,
                        'committed' => 0,
                        'available' => 999999999,
                        'available_formatted' => 'Sin límite',
                    ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Centro de consumo libre - Sin límite presupuestal',
                    'has_budget' => true,
                    'is_free_consumption' => true,
                    'budget_type' => 'FREE_CONSUMPTION',
                    'categories' => $allCategories,
                    'budget_info' => [
                        'cost_center' => $costCenter->name,
                        'year' => $fiscalYear,
                        'month' => $month,
                        'month_name' => $date->locale('es')->isoFormat('MMMM'),
                    ],
                ]);
            }

            $annualBudget = AnnualBudget::where('cost_center_id', $costCenterId)
                ->where('fiscal_year', $fiscalYear)
                ->whereIn('status', ['APROBADO', 'PLANIFICACION'])
                ->with(['monthlyDistributions.budgetCedula.expenseCategory'])
                ->first();

            if (! $annualBudget) {
                return response()->json([
                    'success' => false,
                    'message' => 'No existe presupuesto configurado para este centro de costo en el año ' . $fiscalYear,
                    'error_type' => 'NO_BUDGET',
                    'has_budget' => false,
                    'categories' => []
                ]);
            }

            $summaries = $this->categorySummaryService->forBudgetMonth($annualBudget, $month)
                ->filter(fn (array $summary) => $summary['available_amount'] > 0)
                ->map(fn (array $summary) => [
                    'id' => $summary['category_id'],
                    'code' => $summary['category_code'],
                    'name' => $summary['category_name'],
                    'is_free_consumption' => false,
                    'assigned' => $summary['assigned_amount'],
                    'consumed' => $summary['consumed_amount'],
                    'committed' => $summary['committed_amount'],
                    'available' => $summary['available_amount'],
                    'available_formatted' => '$' . number_format($summary['available_amount'], 2, '.', ','),
                ])
                ->values();

            if ($summaries->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay presupuesto disponible en ninguna categoría para este mes',
                    'error_type' => 'NO_AVAILABLE_BUDGET',
                    'has_budget' => false,
                    'categories' => []
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Presupuesto disponible',
                'has_budget' => true,
                'is_free_consumption' => false,
                'budget_type' => 'ANNUAL',
                'categories' => $summaries,
                'budget_info' => [
                    'cost_center' => $costCenter->name,
                    'year' => $fiscalYear,
                    'month' => $month,
                    'month_name' => $date->locale('es')->isoFormat('MMMM'),
                    'budget_status' => $annualBudget->status,
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('Error al obtener categorías disponibles para el mes', [
                'error' => $e->getMessage(),
                'cost_center_id' => $request->input('cost_center_id'),
                'application_month' => $request->input('application_month'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al verificar el presupuesto disponible',
                'has_budget' => false,
                'categories' => []
            ], 500);
        }
    }
}
