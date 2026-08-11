<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveBudgetMovementRequest;
use App\Models\AnnualBudget;
use App\Models\BudgetCedula;
use App\Models\BudgetMonthlyDistribution;
use App\Models\BudgetMovement;
use App\Models\BudgetMovementDetail;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class BudgetMovementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $movements = BudgetMovement::with(['creator', 'approver'])
                ->select('budget_movements.*');

            return DataTables::of($movements)
                ->addColumn('creator_name', function ($movement) {
                    return $movement->creator->name ?? 'N/A';
                })
                ->addColumn('approver_name', function ($movement) {
                    return $movement->approver->name ?? '-';
                })
                ->addColumn('status_badge', function ($movement) {
                    $badges = [
                        'PENDIENTE' => '<span class="badge bg-warning">Pendiente</span>',
                        'APROBADO' => '<span class="badge bg-success">Aprobado</span>',
                        'RECHAZADO' => '<span class="badge bg-danger">Rechazado</span>',
                    ];

                    return $badges[$movement->status] ?? '';
                })
                ->addColumn('type_badge', function ($movement) {
                    $badges = [
                        'TRANSFERENCIA' => '<span class="badge bg-info">Transferencia</span>',
                        'AMPLIACION' => '<span class="badge bg-primary">Ampliación</span>',
                        'REDUCCION' => '<span class="badge bg-secondary">Reducción</span>',
                    ];

                    return $badges[$movement->movement_type] ?? '';
                })
                ->addColumn('formatted_amount', function ($movement) {
                    return '$'.number_format($movement->total_amount, 2);
                })
                ->addColumn('actions', function ($movement) {
                    $actions = '<div class="btn-group" role="group">';

                    // Ver detalles (todos pueden ver)
                    $actions .= '<a href="'.route('budget_movements.show', $movement->id).'"
                                   class="btn btn-sm btn-info" title="Ver detalles">
                                   <i class="ti ti-eye"></i>
                                </a>';

                    // Editar (solo si está pendiente)
                    if ($movement->isPending()) {
                        $actions .= '<a href="'.route('budget_movements.edit', $movement->id).'"
                                       class="btn btn-sm btn-warning" title="Editar">
                                       <i class="ti ti-pencil"></i>
                                    </a>';
                    }

                    // Aprobar/Rechazar (solo si está pendiente y el usuario tiene permiso)
                    // TODO: Agregar verificación de permisos
                    if ($movement->isPending()) {
                        $actions .= '<button type="button" 
                                       class="btn btn-sm btn-success btn-approve" 
                                       data-id="'.$movement->id.'"
                                       title="Aprobar">
                                       <i class="ti ti-check"></i>
                                    </button>';

                        $actions .= '<button type="button" 
                                       class="btn btn-sm btn-danger btn-reject" 
                                       data-id="'.$movement->id.'"
                                       title="Rechazar">
                                       <i class="ti ti-x"></i>
                                    </button>';
                    }

                    // Eliminar (solo si está pendiente)
                    if ($movement->isPending()) {
                        $actions .= '<button type="button" 
                                       class="btn btn-sm btn-outline-danger btn-delete" 
                                       data-id="'.$movement->id.'"
                                       title="Eliminar">
                                       <i class="ti ti-trash"></i>
                                    </button>';
                    }

                    $actions .= '</div>';

                    return $actions;
                })
                ->rawColumns(['status_badge', 'type_badge', 'actions'])
                ->make(true);
        }

        return view('budget_movements.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $costCenters = CostCenter::active()->orderBy('name')->get();
        $expenseCategories = ExpenseCategory::orderBy('name')->get();
        $budgetCedulas = BudgetCedula::query()
            ->active()
            ->notDeleted()
            ->orderBy('name')
            ->get(['id', 'expense_category_id', 'name']);
        $currentYear = date('Y');

        return view('budget_movements.create', compact('costCenters', 'expenseCategories', 'budgetCedulas', 'currentYear'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SaveBudgetMovementRequest $request)
    {
        DB::beginTransaction();
        try {
            // Crear el movimiento
            $movement = BudgetMovement::create([
                'movement_type' => $request->movement_type,
                'fiscal_year' => $request->fiscal_year,
                'movement_date' => $request->movement_date,
                'total_amount' => $request->total_amount,
                'justification' => $request->justification,
                'status' => BudgetMovement::STATUS_PENDING,
                'created_by' => Auth::id(),
            ]);

            // Crear los detalles según el tipo de movimiento
            if ($request->movement_type === 'TRANSFERENCIA') {
                // Detalle ORIGEN (resta)
                BudgetMovementDetail::create([
                    'budget_movement_id' => $movement->id,
                    'detail_type' => BudgetMovementDetail::TYPE_ORIGIN,
                    'cost_center_id' => $request->origin_cost_center_id,
                    'month' => $request->origin_month,
                    'expense_category_id' => $request->origin_expense_category_id,
                    'budget_cedula_id' => $request->origin_budget_cedula_id,
                    'amount' => -abs($request->total_amount), // Negativo porque resta
                ]);

                // Detalle DESTINO (suma)
                BudgetMovementDetail::create([
                    'budget_movement_id' => $movement->id,
                    'detail_type' => BudgetMovementDetail::TYPE_DESTINATION,
                    'cost_center_id' => $request->destination_cost_center_id,
                    'month' => $request->destination_month,
                    'expense_category_id' => $request->destination_expense_category_id,
                    'budget_cedula_id' => $request->destination_budget_cedula_id,
                    'amount' => abs($request->total_amount), // Positivo porque suma
                ]);
            } elseif ($request->movement_type === 'AMPLIACION') {
                // Detalle AJUSTE (suma)
                BudgetMovementDetail::create([
                    'budget_movement_id' => $movement->id,
                    'detail_type' => BudgetMovementDetail::TYPE_ADJUSTMENT,
                    'cost_center_id' => $request->cost_center_id,
                    'month' => $request->month,
                    'expense_category_id' => $request->expense_category_id,
                    'budget_cedula_id' => $request->budget_cedula_id,
                    'amount' => abs($request->total_amount), // Positivo porque suma
                ]);
            } elseif ($request->movement_type === 'REDUCCION') {
                // Detalle AJUSTE (resta)
                BudgetMovementDetail::create([
                    'budget_movement_id' => $movement->id,
                    'detail_type' => BudgetMovementDetail::TYPE_ADJUSTMENT,
                    'cost_center_id' => $request->cost_center_id,
                    'month' => $request->month,
                    'expense_category_id' => $request->expense_category_id,
                    'budget_cedula_id' => $request->budget_cedula_id,
                    'amount' => -abs($request->total_amount), // Negativo porque resta
                ]);
            }

            DB::commit();

            // Return JSON response for AJAX requests
            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Movimiento presupuestal registrado exitosamente.',
                    'redirect' => route('budget_movements.index'),
                ]);
            }

            return redirect()
                ->route('budget_movements.index')
                ->with('success', 'Movimiento presupuestal registrado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            // Return JSON response for AJAX requests
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al registrar el movimiento: '.$e->getMessage(),
                ], 500);
            }

            return back()
                ->withInput()
                ->with('error', 'Error al registrar el movimiento: '.$e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(BudgetMovement $budgetMovement)
    {
        $budgetMovement->load([
            'details.costCenter',
            'details.expenseCategory',
            'creator',
            'approver',
        ]);

        return view('budget_movements.show', compact('budgetMovement'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BudgetMovement $budgetMovement)
    {
        // Solo se pueden editar movimientos pendientes
        if (! $budgetMovement->isPending()) {
            return redirect()
                ->route('budget_movements.index')
                ->with('error', 'Solo se pueden editar movimientos pendientes.');
        }

        $budgetMovement->load('details');
        $costCenters = CostCenter::active()->orderBy('name')->get();
        $expenseCategories = ExpenseCategory::orderBy('name')->get();
        $budgetCedulas = BudgetCedula::query()
            ->active()
            ->notDeleted()
            ->orderBy('name')
            ->get(['id', 'expense_category_id', 'name']);

        return view('budget_movements.edit', compact('budgetMovement', 'costCenters', 'expenseCategories', 'budgetCedulas'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SaveBudgetMovementRequest $request, BudgetMovement $budgetMovement)
    {
        // Solo se pueden actualizar movimientos pendientes
        if (! $budgetMovement->isPending()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden actualizar movimientos pendientes.',
                ], 403);
            }

            return redirect()
                ->route('budget_movements.index')
                ->with('error', 'Solo se pueden actualizar movimientos pendientes.');
        }

        DB::beginTransaction();
        try {
            // Actualizar el movimiento
            $budgetMovement->update([
                'movement_type' => $request->movement_type,
                'fiscal_year' => $request->fiscal_year,
                'movement_date' => $request->movement_date,
                'total_amount' => $request->total_amount,
                'justification' => $request->justification,
            ]);

            // Eliminar detalles antiguos
            $budgetMovement->details()->delete();

            // Crear nuevos detalles según el tipo de movimiento
            if ($request->movement_type === 'TRANSFERENCIA') {
                // Detalle ORIGEN (resta)
                BudgetMovementDetail::create([
                    'budget_movement_id' => $budgetMovement->id,
                    'detail_type' => BudgetMovementDetail::TYPE_ORIGIN,
                    'cost_center_id' => $request->origin_cost_center_id,
                    'month' => $request->origin_month,
                    'expense_category_id' => $request->origin_expense_category_id,
                    'budget_cedula_id' => $request->origin_budget_cedula_id,
                    'amount' => -abs($request->total_amount),
                ]);

                // Detalle DESTINO (suma)
                BudgetMovementDetail::create([
                    'budget_movement_id' => $budgetMovement->id,
                    'detail_type' => BudgetMovementDetail::TYPE_DESTINATION,
                    'cost_center_id' => $request->destination_cost_center_id,
                    'month' => $request->destination_month,
                    'expense_category_id' => $request->destination_expense_category_id,
                    'budget_cedula_id' => $request->destination_budget_cedula_id,
                    'amount' => abs($request->total_amount),
                ]);
            } elseif ($request->movement_type === 'AMPLIACION') {
                // Detalle AJUSTE (suma)
                BudgetMovementDetail::create([
                    'budget_movement_id' => $budgetMovement->id,
                    'detail_type' => BudgetMovementDetail::TYPE_ADJUSTMENT,
                    'cost_center_id' => $request->cost_center_id,
                    'month' => $request->month,
                    'expense_category_id' => $request->expense_category_id,
                    'budget_cedula_id' => $request->budget_cedula_id,
                    'amount' => abs($request->total_amount),
                ]);
            } elseif ($request->movement_type === 'REDUCCION') {
                // Detalle AJUSTE (resta)
                BudgetMovementDetail::create([
                    'budget_movement_id' => $budgetMovement->id,
                    'detail_type' => BudgetMovementDetail::TYPE_ADJUSTMENT,
                    'cost_center_id' => $request->cost_center_id,
                    'month' => $request->month,
                    'expense_category_id' => $request->expense_category_id,
                    'budget_cedula_id' => $request->budget_cedula_id,
                    'amount' => -abs($request->total_amount),
                ]);
            }

            DB::commit();

            // Return JSON response for AJAX requests
            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Movimiento actualizado exitosamente.',
                    'redirect' => route('budget_movements.index'),
                ]);
            }

            return redirect()
                ->route('budget_movements.index')
                ->with('success', 'Movimiento actualizado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();

            // Return JSON response for AJAX requests
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al actualizar el movimiento: '.$e->getMessage(),
                ], 500);
            }

            return back()
                ->withInput()
                ->with('error', 'Error al actualizar el movimiento: '.$e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BudgetMovement $budgetMovement)
    {
        // Solo se pueden eliminar movimientos pendientes
        if (! $budgetMovement->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden eliminar movimientos pendientes.',
            ], 403);
        }

        try {
            $budgetMovement->delete();

            return response()->json([
                'success' => true,
                'message' => 'Movimiento eliminado exitosamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el movimiento: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve a budget movement
     */
    public function approve(BudgetMovement $budgetMovement)
    {
        // Solo se pueden aprobar movimientos pendientes
        if (! $budgetMovement->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden aprobar movimientos pendientes.',
            ], 403);
        }

        return $this->approveUsingCedulaDistribution($budgetMovement);

        DB::beginTransaction();
        try {
            // Cargar los detalles del movimiento
            $budgetMovement->load('details.costCenter', 'details.expenseCategory');

            // VALIDACIÓN PREVIA: Verificar presupuesto disponible ANTES de aplicar cambios
            foreach ($budgetMovement->details as $detail) {
                // Solo validar detalles que RESTAN presupuesto (ORIGEN o AJUSTE negativo)
                if ($detail->amount < 0) {
                    // Buscar el annual_budget del centro de costo
                    $annualBudget = \App\Models\AnnualBudget::where('cost_center_id', $detail->cost_center_id)
                        ->where('fiscal_year', $budgetMovement->fiscal_year)
                        ->first();

                    if (! $annualBudget) {
                        throw new \Exception(
                            "No existe presupuesto anual para el centro de costo '{$detail->costCenter->name}' ".
                                "en el año {$budgetMovement->fiscal_year}."
                        );
                    }

                    // Buscar la distribución mensual
                    $distribution = \App\Models\BudgetMonthlyDistribution::where('annual_budget_id', $annualBudget->id)
                        ->where('month', $detail->month)
                        ->where('expense_category_id', $detail->expense_category_id)
                        ->first();

                    if ($distribution) {
                        $availableAmount = $distribution->getAvailableAmount();
                        $requiredAmount = abs($detail->amount);

                        // Verificar que haya suficiente presupuesto disponible
                        if ($availableAmount < $requiredAmount) {
                            throw new \Exception(
                                "El centro de costo '{$detail->costCenter->name}' no tiene suficiente presupuesto disponible. ".
                                    "Mes: {$detail->month_name}, ".
                                    "Categoría: '{$detail->expenseCategory->name}'. ".
                                    'Disponible: $'.number_format($availableAmount, 2).', '.
                                    'Requerido: $'.number_format($requiredAmount, 2)
                            );
                        }
                    } else {
                        // Si no existe distribución y queremos restar, es un error
                        throw new \Exception(
                            "No existe presupuesto asignado para el centro de costo '{$detail->costCenter->name}' ".
                                "en el mes {$detail->month_name} para la categoría '{$detail->expenseCategory->name}'."
                        );
                    }
                }
            }

            // Aplicar los cambios al presupuesto según cada detalle
            foreach ($budgetMovement->details as $detail) {
                // Buscar el annual_budget del centro de costo para este año fiscal
                $annualBudget = \App\Models\AnnualBudget::where('cost_center_id', $detail->cost_center_id)
                    ->where('fiscal_year', $budgetMovement->fiscal_year)
                    ->first();

                if (! $annualBudget) {
                    throw new \Exception(
                        "No existe presupuesto anual para el centro de costo '{$detail->costCenter->name}' ".
                            "en el año {$budgetMovement->fiscal_year}. ".
                            'Debe crear el presupuesto anual antes de aprobar este movimiento.'
                    );
                }

                // Buscar la distribución mensual correspondiente
                $distribution = \App\Models\BudgetMonthlyDistribution::where('annual_budget_id', $annualBudget->id)
                    ->where('month', $detail->month)
                    ->where('expense_category_id', $detail->expense_category_id)
                    ->first();

                if (! $distribution) {
                    // Si no existe, crear una nueva distribución con el monto
                    \App\Models\BudgetMonthlyDistribution::create([
                        'annual_budget_id' => $annualBudget->id,
                        'expense_category_id' => $detail->expense_category_id,
                        'month' => $detail->month,
                        'assigned_amount' => $detail->amount, // Ya viene con signo correcto
                        'consumed_amount' => 0,
                        'committed_amount' => 0,
                        'created_by' => Auth::id(),
                    ]);
                } else {
                    // Si existe, validar presupuesto DISPONIBLE antes de actualizar
                    $newAssignedAmount = $distribution->assigned_amount + $detail->amount;

                    // Para reducciones/origen (amount negativo), validar que hay suficiente presupuesto DISPONIBLE
                    if ($detail->amount < 0) {
                        $amountToReduce = abs($detail->amount);
                        $availableBudget = $distribution->available_amount; // assigned - consumed - committed

                        if ($availableBudget < $amountToReduce) {
                            throw new \Exception(
                                "El centro de costo '{$detail->costCenter->name}' no tiene suficiente presupuesto DISPONIBLE ".
                                    "en el mes {$detail->month} para la categoría '{$detail->expenseCategory->name}'. ".
                                    'Asignado: $'.number_format($distribution->assigned_amount, 2).' | '.
                                    'Consumido: $'.number_format($distribution->consumed_amount, 2).' | '.
                                    'Comprometido: $'.number_format($distribution->committed_amount, 2).' | '.
                                    'Disponible: $'.number_format($availableBudget, 2).' | '.
                                    'Solicitado: $'.number_format($amountToReduce, 2)
                            );
                        }
                    }

                    // Validar que el asignado no quede negativo
                    if ($newAssignedAmount < 0) {
                        throw new \Exception(
                            "El centro de costo '{$detail->costCenter->name}' no tiene suficiente presupuesto asignado ".
                                "en el mes {$detail->month} para la categoría '{$detail->expenseCategory->name}'. ".
                                'Asignado actual: $'.number_format($distribution->assigned_amount, 2).', '.
                                'Solicitado: $'.number_format(abs($detail->amount), 2)
                        );
                    }

                    $distribution->update([
                        'assigned_amount' => $newAssignedAmount,
                        'updated_by' => Auth::id(),
                    ]);
                }
            }

            // Actualizar el estado del movimiento
            $budgetMovement->update([
                'status' => BudgetMovement::STATUS_APPROVED,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            // TODO: Enviar notificación al usuario que creó el movimiento

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Movimiento aprobado exitosamente. Los cambios se han aplicado al presupuesto.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar el movimiento: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject a budget movement
     */
    public function reject(BudgetMovement $budgetMovement)
    {
        // Solo se pueden rechazar movimientos pendientes
        if (! $budgetMovement->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden rechazar movimientos pendientes.',
            ], 403);
        }

        try {
            $budgetMovement->update([
                'status' => BudgetMovement::STATUS_REJECTED,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            // TODO: Enviar notificación al usuario que creó el movimiento

            return response()->json([
                'success' => true,
                'message' => 'Movimiento rechazado.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al rechazar el movimiento: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check budget availability for a specific cost center, month, and expense category
     */
    public function checkBudgetAvailability(Request $request)
    {
        $request->validate([
            'fiscal_year' => 'required|integer',
            'cost_center_id' => 'required|exists:cost_centers,id',
            'month' => 'required|integer|min:1|max:12',
            'expense_category_id' => 'required|exists:expense_categories,id',
            'budget_cedula_id' => 'nullable|exists:budget_cedulas,id',
        ]);

        return $this->checkBudgetAvailabilityUsingCedulas($request);

        try {
            // Buscar el presupuesto anual del centro de costo
            $annualBudget = \App\Models\AnnualBudget::where('cost_center_id', $request->cost_center_id)
                ->where('fiscal_year', $request->fiscal_year)
                ->first();

            if (! $annualBudget) {
                return response()->json([
                    'success' => false,
                    'has_budget' => false,
                    'message' => 'No existe presupuesto anual para este centro de costo en el año fiscal seleccionado.',
                    'available_amount' => 0,
                    'assigned_amount' => 0,
                    'consumed_amount' => 0,
                    'committed_amount' => 0,
                ]);
            }

            // Buscar la distribución mensual
            $distribution = \App\Models\BudgetMonthlyDistribution::where('annual_budget_id', $annualBudget->id)
                ->where('month', $request->month)
                ->where('expense_category_id', $request->expense_category_id)
                ->first();

            if (! $distribution) {
                return response()->json([
                    'success' => true,
                    'has_budget' => false,
                    'message' => 'No hay presupuesto asignado para este mes y categoría.',
                    'available_amount' => 0,
                    'assigned_amount' => 0,
                    'consumed_amount' => 0,
                    'committed_amount' => 0,
                ]);
            }

            // Obtener montos
            $availableAmount = $distribution->getAvailableAmount();
            $assignedAmount = (float) $distribution->assigned_amount;
            $consumedAmount = (float) $distribution->consumed_amount;
            $committedAmount = (float) $distribution->committed_amount;
            $status = $distribution->status;

            return response()->json([
                'success' => true,
                'has_budget' => $availableAmount > 0,
                'message' => $availableAmount > 0
                    ? 'Presupuesto disponible'
                    : 'No hay presupuesto disponible',
                'available_amount' => $availableAmount,
                'assigned_amount' => $assignedAmount,
                'consumed_amount' => $consumedAmount,
                'committed_amount' => $committedAmount,
                'status' => $status,
                'status_label' => $distribution->status_label,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar presupuesto: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show critical dashboard with pending movements and budget alerts
     */
    public function criticalDashboard()
    {
        // ===== ALERTAS CRÍTICAS =====

        // Movimientos pendientes
        $pendingMovements = BudgetMovement::with(['creator', 'details.costCenter', 'details.expenseCategory'])
            ->pending()
            ->orderBy('created_at', 'desc')
            ->get();

        // Presupuestos críticos y agotados (este año fiscal)
        $currentYear = date('Y');
        $criticalBudgets = \App\Models\BudgetMonthlyDistribution::with(['annualBudget.costCenter', 'expenseCategory'])
            ->whereHas('annualBudget', function ($query) use ($currentYear) {
                $query->where('fiscal_year', $currentYear);
            })
            ->get()
            ->filter(function ($distribution) {
                return in_array($distribution->status, ['CRÍTICO', 'AGOTADO']);
            })
            ->take(10);

        // ===== RESUMEN EJECUTIVO (ESTE MES) =====

        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        // Contar movimientos por estado (este mes)
        $movementsThisMonth = BudgetMovement::whereBetween('created_at', [$startOfMonth, $endOfMonth])->get();

        $summaryByStatus = [
            'pending' => $movementsThisMonth->where('status', 'PENDIENTE')->count(),
            'approved' => $movementsThisMonth->where('status', 'APROBADO')->count(),
            'rejected' => $movementsThisMonth->where('status', 'RECHAZADO')->count(),
        ];

        // Monto total por tipo (este mes)
        $summaryByType = [
            'TRANSFERENCIA' => $movementsThisMonth->where('movement_type', 'TRANSFERENCIA')->sum('total_amount'),
            'AMPLIACION' => $movementsThisMonth->where('movement_type', 'AMPLIACION')->sum('total_amount'),
            'REDUCCION' => $movementsThisMonth->where('movement_type', 'REDUCCION')->sum('total_amount'),
        ];

        $totalAmount = array_sum($summaryByType);

        // ===== ÚLTIMAS ACTIVIDADES =====

        $recentApproved = BudgetMovement::with(['creator', 'approver'])
            ->approved()
            ->orderBy('approved_at', 'desc')
            ->take(5)
            ->get();

        $recentRejected = BudgetMovement::with(['creator', 'approver'])
            ->rejected()
            ->orderBy('approved_at', 'desc')
            ->take(5)
            ->get();

        return view('budget_movements.dashboard', compact(
            'pendingMovements',
            'criticalBudgets',
            'summaryByStatus',
            'summaryByType',
            'totalAmount',
            'recentApproved',
            'recentRejected'
        ));
    }

    private function approveUsingCedulaDistribution(BudgetMovement $budgetMovement)
    {
        DB::beginTransaction();

        try {
            $budgetMovement->load('details.costCenter', 'details.expenseCategory');

            foreach ($budgetMovement->details as $detail) {
                if ((float) $detail->amount >= 0) {
                    continue;
                }

                $annualBudget = $this->resolveAnnualBudgetForDetail($detail, (int) $budgetMovement->fiscal_year);
                $distributions = $this->resolveCategoryDistributions($annualBudget, $detail);

                if ($distributions->isEmpty()) {
                    throw new \Exception(
                        "No existe presupuesto asignado para el centro de costo '{$detail->costCenter->name}' ".
                            "en el mes {$detail->month_name} para la categoría '{$detail->expenseCategory->name}'."
                    );
                }

                $availableAmount = (float) $distributions->sum(
                    fn (BudgetMonthlyDistribution $distribution) => $distribution->getAvailableAmount()
                );
                $requiredAmount = abs((float) $detail->amount);

                if ($availableAmount + 0.000001 < $requiredAmount) {
                    throw new \Exception(
                        "El centro de costo '{$detail->costCenter->name}' no tiene suficiente presupuesto disponible. ".
                            "Mes: {$detail->month_name}, ".
                            "Categoría: '{$detail->expenseCategory->name}'. ".
                            'Disponible: $'.number_format($availableAmount, 2).', '.
                            'Requerido: $'.number_format($requiredAmount, 2)
                    );
                }
            }

            foreach ($budgetMovement->details as $detail) {
                $annualBudget = $this->resolveAnnualBudgetForDetail($detail, (int) $budgetMovement->fiscal_year);
                $this->applyDetailToBudget($annualBudget, $detail);
            }

            $budgetMovement->update([
                'status' => BudgetMovement::STATUS_APPROVED,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Movimiento aprobado exitosamente. Los cambios se han aplicado al presupuesto.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar el movimiento: '.$e->getMessage(),
            ], 500);
        }
    }

    private function checkBudgetAvailabilityUsingCedulas(Request $request)
    {
        try {
            $annualBudget = AnnualBudget::where('cost_center_id', $request->cost_center_id)
                ->where('fiscal_year', $request->fiscal_year)
                ->first();

            if (! $annualBudget) {
                return response()->json([
                    'success' => false,
                    'has_budget' => false,
                    'message' => 'No existe presupuesto anual para este centro de costo en el año fiscal seleccionado.',
                    'available_amount' => 0,
                    'assigned_amount' => 0,
                    'consumed_amount' => 0,
                    'committed_amount' => 0,
                ]);
            }

            $distributions = BudgetMonthlyDistribution::where('annual_budget_id', $annualBudget->id)
                ->where('month', $request->month)
                ->where('expense_category_id', $request->expense_category_id)
                ->when($request->budget_cedula_id, fn ($query) => $query->where('budget_cedula_id', $request->budget_cedula_id))
                ->get();

            if ($distributions->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'has_budget' => false,
                    'message' => 'No hay presupuesto asignado para este mes y categoría.',
                    'available_amount' => 0,
                    'assigned_amount' => 0,
                    'consumed_amount' => 0,
                    'committed_amount' => 0,
                ]);
            }

            $availableAmount = (float) $distributions->sum(
                fn (BudgetMonthlyDistribution $distribution) => $distribution->getAvailableAmount()
            );
            $assignedAmount = (float) $distributions->sum('assigned_amount');
            $consumedAmount = (float) $distributions->sum('consumed_amount');
            $committedAmount = (float) $distributions->sum('committed_amount');
            $usage = $assignedAmount > 0 ? (($consumedAmount + $committedAmount) / $assignedAmount) * 100 : 0;
            $status = $availableAmount <= 0
                ? 'AGOTADO'
                : ($usage > 70 ? 'CRITICO' : 'NORMAL');

            return response()->json([
                'success' => true,
                'has_budget' => $availableAmount > 0,
                'message' => $availableAmount > 0 ? 'Presupuesto disponible' : 'No hay presupuesto disponible',
                'available_amount' => $availableAmount,
                'assigned_amount' => $assignedAmount,
                'consumed_amount' => $consumedAmount,
                'committed_amount' => $committedAmount,
                'status' => $status,
                'status_label' => match ($status) {
                    'NORMAL' => 'Normal',
                    'CRITICO' => 'Crítico',
                    'AGOTADO' => 'Agotado',
                    default => 'Alerta',
                },
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar presupuesto: '.$e->getMessage(),
            ], 500);
        }
    }

    private function resolveAnnualBudgetForDetail(BudgetMovementDetail $detail, int $fiscalYear): AnnualBudget
    {
        $annualBudget = AnnualBudget::where('cost_center_id', $detail->cost_center_id)
            ->where('fiscal_year', $fiscalYear)
            ->first();

        if (! $annualBudget) {
            throw new \Exception(
                "No existe presupuesto anual para el centro de costo '{$detail->costCenter->name}' ".
                    "en el año {$fiscalYear}. Debe crear el presupuesto anual antes de aprobar este movimiento."
            );
        }

        return $annualBudget;
    }

    private function resolveCategoryDistributions(AnnualBudget $annualBudget, BudgetMovementDetail $detail): Collection
    {
        return BudgetMonthlyDistribution::where('annual_budget_id', $annualBudget->id)
            ->where('month', $detail->month)
            ->where('expense_category_id', $detail->expense_category_id)
            ->when($detail->budget_cedula_id, fn ($query) => $query->where('budget_cedula_id', $detail->budget_cedula_id))
            ->orderBy('budget_cedula_id')
            ->orderBy('id')
            ->get();
    }

    private function applyDetailToBudget(AnnualBudget $annualBudget, BudgetMovementDetail $detail): void
    {
        $distributions = $this->resolveCategoryDistributions($annualBudget, $detail);

        if ((float) $detail->amount > 0) {
            $this->applyIncreaseToCategory($annualBudget, $detail, $distributions);

            return;
        }

        if ($distributions->isEmpty()) {
            throw new \Exception(
                "No existe presupuesto asignado para el centro de costo '{$detail->costCenter->name}' ".
                    "en el mes {$detail->month_name} para la categoría '{$detail->expenseCategory->name}'."
            );
        }

        $remaining = abs((float) $detail->amount);

        foreach ($distributions->sortByDesc(fn (BudgetMonthlyDistribution $distribution) => $distribution->getAvailableAmount()) as $distribution) {
            if ($remaining <= 0.000001) {
                break;
            }

            $available = $distribution->getAvailableAmount();
            if ($available <= 0) {
                continue;
            }

            $reduction = min($available, $remaining);
            $distribution->update([
                'assigned_amount' => (float) $distribution->assigned_amount - $reduction,
                'updated_by' => Auth::id(),
            ]);
            $remaining -= $reduction;
        }

        if ($remaining > 0.000001) {
            throw new \Exception(
                "No fue posible aplicar la reducción completa a la categoría '{$detail->expenseCategory->name}'. ".
                    'Monto pendiente: $'.number_format($remaining, 2)
            );
        }
    }

    private function applyIncreaseToCategory(
        AnnualBudget $annualBudget,
        BudgetMovementDetail $detail,
        Collection $distributions
    ): void {
        $amount = (float) $detail->amount;

        if ($detail->budget_cedula_id) {
            $distribution = $distributions->firstWhere('budget_cedula_id', $detail->budget_cedula_id);

            if ($distribution) {
                $distribution->update([
                    'assigned_amount' => (float) $distribution->assigned_amount + $amount,
                    'updated_by' => Auth::id(),
                ]);

                return;
            }

            BudgetMonthlyDistribution::create([
                'annual_budget_id' => $annualBudget->id,
                'budget_cedula_id' => $detail->budget_cedula_id,
                'expense_category_id' => $detail->expense_category_id,
                'month' => $detail->month,
                'assigned_amount' => $amount,
                'consumed_amount' => 0,
                'committed_amount' => 0,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            return;
        }

        if ($distributions->isNotEmpty()) {
            /** @var BudgetMonthlyDistribution $distribution */
            $distribution = $distributions->sortBy('budget_cedula_id')->first();
            $distribution->update([
                'assigned_amount' => (float) $distribution->assigned_amount + $amount,
                'updated_by' => Auth::id(),
            ]);

            return;
        }

        $cedula = BudgetCedula::query()
            ->active()
            ->notDeleted()
            ->where('expense_category_id', $detail->expense_category_id)
            ->orderBy('name')
            ->orderBy('id')
            ->first();

        if (! $cedula) {
            throw new \Exception(
                "No hay cédulas activas configuradas para la categoría '{$detail->expenseCategory->name}'."
            );
        }

        BudgetMonthlyDistribution::create([
            'annual_budget_id' => $annualBudget->id,
            'budget_cedula_id' => $cedula->id,
            'expense_category_id' => $detail->expense_category_id,
            'month' => $detail->month,
            'assigned_amount' => $amount,
            'consumed_amount' => 0,
            'committed_amount' => 0,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);
    }
}
