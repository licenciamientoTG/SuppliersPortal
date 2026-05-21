<?php

namespace App\Http\Controllers;

use App\Models\AnnualBudget;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Services\BudgetCategorySummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ExpenseCategoryController extends Controller
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected const ERROR_MESSAGES = [
        'create_error' => 'Error al crear la categoría: ',
        'no_distribution' => 'No existe distribución presupuestal para este mes y categoría.',
    ];

    protected const SUCCESS_MESSAGES = [
        'created' => 'Categoría creada exitosamente.',
    ];

    public function __construct(
        private readonly BudgetCategorySummaryService $categorySummaryService
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:3|unique:expense_categories,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $category = ExpenseCategory::create([
                'code' => strtoupper($request->code),
                'name' => $request->name,
                'description' => $request->description,
                'status' => 'ACTIVO',
                'created_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => self::SUCCESS_MESSAGES['created'],
                'data' => $category,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => self::ERROR_MESSAGES['create_error'] . $e->getMessage(),
            ], 500);
        }
    }

    public function getForSelect(): JsonResponse
    {
        try {
            $categories = ExpenseCategory::select('id', 'code', 'name')
                ->where('status', 'ACTIVO')
                ->orderBy('name')
                ->get()
                ->map(fn ($category) => [
                    'id' => $category->id,
                    'text' => "[{$category->code}] {$category->name}",
                ]);

            return response()->json([
                'success' => true,
                'data' => $categories,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las categorías: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function byBudget(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cost_center_id' => ['required', 'integer', 'exists:cost_centers,id'],
            'fiscal_year' => ['required', 'integer', 'min:2024', 'max:2030'],
        ]);

        $costCenter = CostCenter::where('id', $validated['cost_center_id'])
            ->where('status', 'ACTIVO')
            ->first();

        if (! $costCenter || ! $costCenter->isAnnual()) {
            return response()->json([
                'success' => false,
                'message' => 'Centro de costo inválido o no es de tipo ANUAL.',
            ], 400);
        }

        $annualBudget = AnnualBudget::where('cost_center_id', $costCenter->id)
            ->where('fiscal_year', $validated['fiscal_year'])
            ->where('status', 'APROBADO')
            ->with(['monthlyDistributions.budgetCedula.expenseCategory'])
            ->first();

        if (! $annualBudget) {
            return response()->json([
                'success' => false,
                'message' => "No existe un presupuesto aprobado para el año {$validated['fiscal_year']}.",
            ], 404);
        }

        $categories = $this->categorySummaryService->forBudget($annualBudget)
            ->filter(fn (array $summary) => $summary['available_amount'] > 0)
            ->map(fn (array $summary) => [
                'id' => $summary['category_id'],
                'code' => $summary['category_code'],
                'name' => $summary['category_name'],
                'total_assigned' => $summary['assigned_amount'],
                'total_consumed' => $summary['consumed_amount'],
                'total_committed' => $summary['committed_amount'],
                'total_available' => $summary['available_amount'],
            ])
            ->values();

        return response()->json([
            'success' => true,
            'categories' => $categories,
            'cost_center' => [
                'id' => $costCenter->id,
                'name' => $costCenter->name,
                'budget_type' => $costCenter->budget_type,
            ],
            'fiscal_year' => (int) $validated['fiscal_year'],
        ]);
    }

    public function checkBudget(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cost_center_id' => 'required|exists:cost_centers,id',
            'fiscal_year' => 'required|integer|min:2000|max:2100',
            'expense_category_id' => 'required|exists:expense_categories,id',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $costCenter = CostCenter::find($validated['cost_center_id']);
        if (! $costCenter || ! $costCenter->isAnnual()) {
            return response()->json([
                'success' => false,
                'message' => 'Centro de costo no válido o no es anual.',
            ], 400);
        }

        $annualBudget = AnnualBudget::where('cost_center_id', $validated['cost_center_id'])
            ->where('fiscal_year', $validated['fiscal_year'])
            ->where('status', 'APROBADO')
            ->with(['monthlyDistributions.budgetCedula.expenseCategory'])
            ->first();

        if (! $annualBudget) {
            return response()->json([
                'success' => false,
                'message' => 'No hay presupuesto aprobado.',
            ], 404);
        }

        $summary = $this->categorySummaryService->forBudgetMonthAndCategory(
            $annualBudget,
            (int) $validated['month'],
            (int) $validated['expense_category_id']
        );

        if (! $summary) {
            return response()->json([
                'success' => false,
                'message' => self::ERROR_MESSAGES['no_distribution'],
            ], 404);
        }

        $status = 'NORMAL';
        if ($summary['available_amount'] <= 0) {
            $status = 'AGOTADO';
        } elseif ($summary['usage_percentage'] >= 70) {
            $status = 'CRITICO';
        } elseif ($summary['usage_percentage'] >= 30) {
            $status = 'ALERTA';
        }

        return response()->json([
            'success' => true,
            'has_budget' => $summary['available_amount'] > 0,
            'assigned_amount' => (float) $summary['assigned_amount'],
            'consumed_amount' => (float) $summary['consumed_amount'],
            'committed_amount' => (float) $summary['committed_amount'],
            'available_amount' => (float) $summary['available_amount'],
            'status' => $status,
            'usage_percentage' => round($summary['usage_percentage'], 2),
            'category' => [
                'id' => $summary['category_id'],
                'name' => $summary['category_name'],
            ],
            'month' => (int) $validated['month'],
        ]);
    }

    public function getByCostCenter(Request $request)
    {
        try {
            $costCenterId = $request->input('cost_center_id');
            if (! $costCenterId) {
                return response()->json([
                    'success' => false,
                    'message' => 'El centro de costo es requerido',
                ], 400);
            }

            $costCenter = CostCenter::query()
                ->whereKey($costCenterId)
                ->where('status', 'ACTIVO')
                ->whereNull('deleted_at')
                ->first();

            if (! $costCenter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Centro de costo no encontrado o inactivo',
                ], 404);
            }

            $user = $request->user();
            $assignedToUser = $user?->costCenters()
                ->where('cost_centers.id', $costCenter->id)
                ->where('cost_centers.status', 'ACTIVO')
                ->whereNull('cost_centers.deleted_at')
                ->where('cost_center_user.is_active', true)
                ->exists();

            if (! $assignedToUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para consultar este centro de costo.',
                    'categories' => [],
                ], 403);
            }

            if ($costCenter->budget_type === 'FREE_CONSUMPTION') {
                $categories = ExpenseCategory::active()
                    ->orderBy('code')
                    ->get(['id', 'code', 'name', 'description']);

                return response()->json([
                    'success' => true,
                    'categories' => $categories,
                    'budget_type' => 'FREE_CONSUMPTION',
                    'message' => 'Centro de costo de consumo libre - todas las categorías disponibles',
                ]);
            }

            $currentYear = now()->year;
            $annualBudget = AnnualBudget::where('cost_center_id', $costCenterId)
                ->where('fiscal_year', $currentYear)
                ->whereIn('status', ['APROBADO', 'PLANIFICACION'])
                ->with(['monthlyDistributions.budgetCedula.expenseCategory'])
                ->first();

            if (! $annualBudget) {
                return response()->json([
                    'success' => false,
                    'error_type' => 'NO_BUDGET',
                    'message' => 'No hay presupuesto anual configurado para este centro de costo en ' . $currentYear,
                    'instructions' => 'Para poder crear requisiciones, es necesario que el responsable del centro de costo configure el presupuesto anual.',
                    'categories' => [],
                ], 404);
            }

            $categories = $this->categorySummaryService->forBudget($annualBudget)
                ->map(fn (array $summary) => [
                    'id' => $summary['category_id'],
                    'code' => $summary['category_code'],
                    'name' => $summary['category_name'],
                    'description' => null,
                ])
                ->values();

            if ($categories->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error_type' => 'NO_CATEGORIES',
                    'message' => 'El presupuesto existe pero no tiene distribuciones mensuales configuradas',
                    'instructions' => 'Solicita al responsable del centro de costo que configure las distribuciones mensuales del presupuesto.',
                    'categories' => [],
                ], 404);
            }

            return response()->json([
                'success' => true,
                'categories' => $categories,
                'budget_type' => 'ANNUAL',
                'budget_year' => $currentYear,
                'budget_status' => $annualBudget->status,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al obtener categorías por centro de costo', [
                'error' => $e->getMessage(),
                'cost_center_id' => $request->input('cost_center_id'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar las categorías de gasto',
                'categories' => [],
            ], 500);
        }
    }
}
