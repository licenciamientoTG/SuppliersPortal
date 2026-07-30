<?php

namespace App\Http\Controllers;

use App\Enum\RequisitionStatus;
use App\Exceptions\Rfq\GroupDoesNotBelongToRequisitionException;
use App\Exceptions\Rfq\ItemsNotInRequisitionException;
use App\Models\Requisition;
use App\Models\QuotationGroup;
use App\Services\Rfq\QuotationGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class QuotationPlannerController extends Controller
{
    public function __construct(private QuotationGroupService $groupService) {}

    /**
     * Muestra el planificador de cotización para una requisición.
     * 
     * @param Requisition $requisition
     * @return View
     */
    public function show(Requisition $requisition): View
    {
        // Validar que la requisición esté en estado IN_QUOTATION
        if ($requisition->status !== RequisitionStatus::IN_QUOTATION) {
            abort(403, 'Esta requisición no está en estado de cotización.');
        }

        // Cargar relaciones necesarias
        $requisition->load([
            'items.productService',
            'items.expenseCategory',
            'quotationGroups.items',
            'costCenter',
            'department',
            'requester'
        ]);

        // Obtener partidas que NO están en ningún grupo
        $unassignedItems = $requisition->items()
            ->whereDoesntHave('quotationGroups', fn ($query) => $query->active())
            ->with(['productService', 'expenseCategory'])
            ->get();

        // Obtener grupos existentes con sus partidas
        $groups = $requisition->quotationGroups()
            ->active()
            ->with(['items.productService', 'items.expenseCategory'])
            ->get();
        return view('requisitions.quotation-planner.show', compact(
            'requisition',
            'unassignedItems',
            'groups'
        ));
    }

    /**
     * Guarda la estrategia de cotización (grupos creados).
     * 
     * @param Request $request
     * @param Requisition $requisition
     * @return JsonResponse
     */
    public function saveStrategy(Request $request, Requisition $requisition)
    {
        if ($response = $this->readOnlyAfterSendResponse($requisition)) {
            return $response;
        }

        $validated = $request->validate([
            'groups' => 'nullable|array',
            'groups.*.id' => 'nullable|exists:quotation_groups,id',
            'groups.*.name' => 'required|string|max:255',
            'groups.*.notes' => 'nullable|string',
            'groups.*.item_ids' => 'nullable|array',
            'groups.*.item_ids.*' => 'exists:requisition_items,id',
        ]);

        DB::beginTransaction();
        try {
            $savedGroups = [];
            $processedGroupIds = []; // ✅ NUEVO: IDs de grupos procesados
            $groups = $validated['groups'] ?? [];

            foreach ($groups as $groupData) {
                $groupData['notes'] = $groupData['notes'] ?? '';

                if (isset($groupData['id']) && $groupData['id'] > 0) {
                    // Actualizar grupo existente
                    $group = QuotationGroup::findOrFail($groupData['id']);
                    $group->update([
                        'name' => $groupData['name'],
                        'notes' => $groupData['notes'],
                        'updated_by' => Auth::id(),
                    ]);
                } else {
                    // Crear nuevo grupo
                    $group = QuotationGroup::create([
                        'requisition_id' => $requisition->id,
                        'name' => $groupData['name'],
                        'notes' => $groupData['notes'],
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                    ]);
                }

                // ✅ GUARDAR el ID del grupo procesado (nuevo o existente)
                $processedGroupIds[] = $group->id;

                // Sincronizar items
                $itemIds = $groupData['item_ids'] ?? [];
                if (!empty($itemIds)) {
                    $syncData = [];
                    foreach ($itemIds as $index => $itemId) {
                        $syncData[$itemId] = ['sort_order' => $index];
                    }
                    $group->items()->sync($syncData);
                } else {
                    $group->items()->sync([]);
                }

                $savedGroups[] = [
                    'id' => $group->id,
                    'name' => $group->name,
                    'notes' => $group->notes,
                    'item_count' => count($itemIds),
                ];
            }

            $groupsToClose = QuotationGroup::where('requisition_id', $requisition->id)
                ->active()
                ->when(!empty($processedGroupIds), fn ($query) => $query->whereNotIn('id', $processedGroupIds))
                ->get();

            foreach ($groupsToClose as $groupToClose) {
                $groupToClose->cancel('Grupo retirado de la estrategia de cotizacion.', Auth::id());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($savedGroups) > 0
                    ? 'Estrategia guardada correctamente'
                    : 'Todos los grupos fueron eliminados',
                'groups' => $savedGroups,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crea un nuevo grupo de cotización.
     * 
     * @param Request $request
     * @param Requisition $requisition
     * @return JsonResponse
     */
    public function createGroup(Request $request, Requisition $requisition): JsonResponse
    {
        if ($response = $this->readOnlyAfterSendResponse($requisition)) {
            return $response;
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'notes' => 'nullable|string',
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'integer|exists:requisition_items,id',
        ]);

        try {
            $group = $this->groupService->create(
                $requisition,
                $validated['name'],
                $validated['notes'] ?? null,
                $validated['item_ids'] ?? [],
                Auth::id()
            );

            // Cargar relaciones con eager loading para evitar N+1
            $group->loadMissing(['items.productService', 'items.expenseCategory']);

            return response()->json([
                'success' => true,
                'message' => 'Grupo creado exitosamente.',
                'data' => [
                    'group' => $group,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al crear grupo', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear el grupo.'
            ], 500);
        }
    }

    /**
     * Elimina un grupo de cotización.
     * 
     * @param Requisition $requisition
     * @param QuotationGroup $group
     * @return JsonResponse
     */
    public function deleteGroup(Requisition $requisition, QuotationGroup $group): JsonResponse
    {
        if ($response = $this->readOnlyAfterSendResponse($requisition)) {
            return $response;
        }

        try {
            $this->groupService->cancel($requisition, $group, 'Grupo cancelado desde el planificador.', Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'Grupo cancelado exitosamente.'
            ]);
        } catch (GroupDoesNotBelongToRequisitionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 403);
        } catch (\Exception $e) {
            Log::error('Error al eliminar grupo', [
                'group_id' => $group->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el grupo.'
            ], 500);
        }
    }

    /**
     * Agrega partidas a un grupo.
     * 
     * @param Request $request
     * @param Requisition $requisition
     * @param QuotationGroup $group
     * @return JsonResponse
     */
    public function addItemsToGroup(Request $request, Requisition $requisition, QuotationGroup $group): JsonResponse
    {
        if ($response = $this->readOnlyAfterSendResponse($requisition)) {
            return $response;
        }

        $validated = $request->validate([
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'integer|exists:requisition_items,id',
        ]);

        try {
            $this->groupService->addItems($requisition, $group, $validated['item_ids']);

            // Cargar relaciones con eager loading para evitar N+1
            $group->loadMissing('items.productService');

            return response()->json([
                'success' => true,
                'message' => 'Partidas agregadas al grupo.',
                'data' => [
                    'group' => $group,
                ]
            ]);
        } catch (GroupDoesNotBelongToRequisitionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 403);
        } catch (ItemsNotInRequisitionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Log::error('Error al agregar partidas al grupo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al agregar partidas al grupo.'
            ], 500);
        }
    }

    /**
     * Remueve partidas de un grupo.
     * 
     * @param Request $request
     * @param Requisition $requisition
     * @param QuotationGroup $group
     * @return JsonResponse
     */
    public function removeItemsFromGroup(Request $request, Requisition $requisition, QuotationGroup $group): JsonResponse
    {
        if ($response = $this->readOnlyAfterSendResponse($requisition)) {
            return $response;
        }

        $validated = $request->validate([
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'integer|exists:requisition_items,id',
        ]);

        try {
            $this->groupService->removeItems($requisition, $group, $validated['item_ids'], Auth::id());

            // Cargar relaciones con eager loading para evitar N+1
            $group->loadMissing(['items.productService', 'items.expenseCategory']);

            return response()->json([
                'success' => true,
                'message' => $group->isCancelled()
                    ? 'Se retiró la última partida y el grupo fue eliminado.'
                    : 'Partidas removidas del grupo.',
                'data' => [
                    'group' => $group,
                ]
            ]);
        } catch (GroupDoesNotBelongToRequisitionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 403);
        } catch (\Exception $e) {
            Log::error('Error al remover partidas del grupo', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al remover partidas del grupo.'
            ], 500);
        }
    }

    private function readOnlyAfterSendResponse(Requisition $requisition): ?JsonResponse
    {
        $hasSentRfq = $requisition->rfqs()
            ->whereIn('status', ['SENT', 'RESPONSES_RECEIVED', 'RECEIVED', 'EVALUATED'])
            ->exists();

        if (! $hasSentRfq) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Ya existen solicitudes enviadas para esta requisición. La configuración de paquetes está disponible solo para consulta.',
        ], 409);
    }

    /**
     * Obtiene sugerencias de proveedores para un grupo.
     * 
     * @param Requisition $requisition
     * @param QuotationGroup $group
     * @return JsonResponse
     */
    public function getSupplierSuggestions(Requisition $requisition, QuotationGroup $group): JsonResponse
    {
        if ((int)$group->requisition_id !== (int)$requisition->id) {
            return response()->json([
                'success' => false,
                'message' => 'Grupo inválido.'
            ], 403);
        }

        try {
            // Obtener categorías del grupo
            $categories = $group->items()
                ->with(['productService', 'expenseCategory'])
                ->get()
                ->pluck('expenseCategory.id')
                ->unique();

            // Buscar proveedores que manejen estas categorías
            // TODO: Implementar lógica de sugerencias basada en:
            // - Categorías de productos
            // - Historial de compras
            // - Calificación de proveedores
            // - Disponibilidad

            $suggestions = [
                // Placeholder - aquí irá la lógica real
                'message' => 'Funcionalidad de sugerencias pendiente de implementación',
                'categories' => $categories,
            ];

            return response()->json([
                'success' => true,
                'data' => $suggestions
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener sugerencias de proveedores', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener sugerencias.'
            ], 500);
        }
    }
}
