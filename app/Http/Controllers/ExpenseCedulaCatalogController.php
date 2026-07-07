<?php

namespace App\Http\Controllers;

use App\Models\BudgetCedula;
use App\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseCedulaCatalogController extends Controller
{
    public function index(): View
    {
        $categories = ExpenseCategory::query()
            ->notDeleted()
            ->withCount('cedulas')
            ->orderBy('code')
            ->get();

        return view('expense_cedulas_catalog.index', compact('categories'));
    }

    public function categoriesData(): JsonResponse
    {
        $categories = ExpenseCategory::query()
            ->notDeleted()
            ->withCount('cedulas')
            ->orderBy('code')
            ->get();

        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->categoryRules(), $this->categoryMessages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['code'] = strtoupper($data['code']);
        $data['created_by'] = Auth::id();

        $category = ExpenseCategory::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Categoría creada correctamente.',
            'data' => $category->loadCount('cedulas'),
        ], 201);
    }

    public function updateCategory(Request $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            $this->categoryRules($expenseCategory->id),
            $this->categoryMessages()
        );

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['code'] = strtoupper($data['code']);
        $data['updated_by'] = Auth::id();

        try {
            $expenseCategory->update($data);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Categoría actualizada correctamente.',
            'data' => $expenseCategory->fresh()->loadCount('cedulas'),
        ]);
    }

    public function destroyCategory(ExpenseCategory $expenseCategory): JsonResponse
    {
        if ($expenseCategory->hasCedulas()) {
            return response()->json([
                'error' => 'No se puede eliminar la categoría "' . $expenseCategory->name . '" porque tiene cédulas asociadas. Elimina o reasigna sus cédulas primero.',
            ], 409);
        }

        if ($expenseCategory->isInUse()) {
            return response()->json([
                'error' => 'No se puede eliminar la categoría "' . $expenseCategory->name . '" porque tiene movimientos presupuestales asociados.',
            ], 409);
        }

        $expenseCategory->delete();

        return response()->json(['success' => true, 'message' => 'Categoría eliminada correctamente.']);
    }

    public function cedulasData(ExpenseCategory $expenseCategory): JsonResponse
    {
        $cedulas = $expenseCategory->cedulas()
            ->notDeleted()
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $cedulas]);
    }

    public function storeCedula(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->cedulaRules(), $this->cedulaMessages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['created_by'] = Auth::id();

        $cedula = BudgetCedula::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Cédula creada correctamente.',
            'data' => $cedula,
        ], 201);
    }

    public function updateCedula(Request $request, BudgetCedula $budgetCedula): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->cedulaRules(), $this->cedulaMessages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        // No se permite reasignar la categoría de una cédula desde este modal.
        unset($data['expense_category_id']);
        $data['updated_by'] = Auth::id();

        try {
            $budgetCedula->update($data);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cédula actualizada correctamente.',
            'data' => $budgetCedula->fresh(),
        ]);
    }

    public function destroyCedula(BudgetCedula $budgetCedula): JsonResponse
    {
        if ($budgetCedula->isInUse()) {
            return response()->json([
                'error' => 'No se puede eliminar la cédula "' . $budgetCedula->name . '" porque tiene movimientos asociados (distribución, requisición o compromiso presupuestal).',
            ], 409);
        }

        $budgetCedula->delete();

        return response()->json(['success' => true, 'message' => 'Cédula eliminada correctamente.']);
    }

    private function categoryRules(?int $ignoreId = null): array
    {
        $unique = Rule::unique('expense_categories', 'code');
        if ($ignoreId) {
            $unique->ignore($ignoreId);
        }

        return [
            'code' => ['required', 'string', 'max:3', $unique],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }

    private function categoryMessages(): array
    {
        return [
            'code.required' => 'El código es obligatorio.',
            'code.max' => 'El código no puede exceder 3 caracteres.',
            'code.unique' => 'Ya existe una categoría con ese código.',
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no puede exceder 200 caracteres.',
            'status.required' => 'El estado es obligatorio.',
            'status.in' => 'El estado debe ser ACTIVO o INACTIVO.',
        ];
    }

    private function cedulaRules(): array
    {
        return [
            'expense_category_id' => [
                'required',
                'integer',
                Rule::exists('expense_categories', 'id')->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:200'],
            'status' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }

    private function cedulaMessages(): array
    {
        return [
            'expense_category_id.required' => 'La categoría es obligatoria.',
            'expense_category_id.exists' => 'La categoría seleccionada no es válida.',
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no puede exceder 200 caracteres.',
            'status.required' => 'El estado es obligatorio.',
            'status.in' => 'El estado debe ser ACTIVO o INACTIVO.',
        ];
    }
}
