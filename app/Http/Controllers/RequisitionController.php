<?php

namespace App\Http\Controllers;

use App\Enum\PurchaseType;
use App\Enum\RequisitionStatus;
use App\Events\RequisitionUpdated;
use App\Http\Requests\SaveRequisitionRequest;
use App\Models\BudgetCedula;
use App\Models\CostCenter;
use App\Models\Requisition;
use App\Models\Company;
use App\Models\Department;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use App\Models\ReceivingLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Handles requisition management including creation, viewing, updating, and deletion.
 * 
 * IMPORTANTE: Las requisiciones NO manejan precios (RN-002).
 * Los precios se asignan en el módulo de COTIZACIONES.
 */
class RequisitionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        return view('requisitions.index');
    }

    /**
     * Get requisitions for DataTables.
     */
    public function datatable(Request $request): JsonResponse
    {
        // Usamos withCount para que el conteo venga en la consulta principal
        $query = Requisition::query()
            ->with(['costCenter', 'requester', 'department'])
            ->withCount('items');

        return DataTables::of($query)
            ->addColumn('cost_center', fn($r) => e($r->costCenter?->name ?? '—'))
            ->addColumn('department', fn($r) => e($r->department?->name ?? '—'))
            ->addColumn('requester', fn($r) => e($r->requester?->name ?? '—'))

            // 'items_count' ya vendrá como atributo gracias a withCount
            ->editColumn('items_count', function ($r) {
                return $r->items_count;
            })

            // ✅ Status con data-status para filtrado
            ->editColumn('status', function ($requisition) {
                return '<span data-status="' . $requisition->status->value . '" class="badge bg-' .
                    $requisition->status->badgeClass() .
                    '">' .
                    $requisition->status->label() .
                    '</span>';
            })

            // ✅ Fecha requerida - Manejo seguro
            ->editColumn('required_date', function ($r) {
                if (!$r->required_date) return '—';

                try {
                    // Si es un objeto Carbon o DateTime
                    if ($r->required_date instanceof \Carbon\Carbon || $r->required_date instanceof \DateTime) {
                        return $r->required_date->format('d/m/Y');
                    }

                    // Si es un string, convertirlo a Carbon
                    return \Carbon\Carbon::parse($r->required_date)->format('d/m/Y');
                } catch (\Exception $e) {
                    return $r->required_date; // Retornar el valor original si falla
                }
            })

            // ✅ Fecha de creación - Manejo seguro
            ->editColumn('created_at', function ($r) {
                if (!$r->created_at) return '—';

                try {
                    // Si es un objeto Carbon o DateTime
                    if ($r->created_at instanceof \Carbon\Carbon || $r->created_at instanceof \DateTime) {
                        return $r->created_at->format('d/m/Y');
                    }

                    // Si es un string, convertirlo a Carbon
                    return \Carbon\Carbon::parse($r->created_at)->format('d/m/Y');
                } catch (\Exception $e) {
                    return $r->created_at; // Retornar el valor original si falla
                }
            })

            // ✅ Filtros personalizados para fechas (buscar por formato ISO YYYY-MM-DD)
            ->filterColumn('required_date', function ($query, $keyword) {
                // Convertir la búsqueda DD/MM/YYYY o YYYY-MM-DD a formato que SQL entienda
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $keyword)) {
                    // Si viene en formato ISO (YYYY-MM-DD) del input type="date"
                    $query->where('required_date', '>=', \Carbon\Carbon::parse($keyword)->startOfDay())
                          ->where('required_date', '<=', \Carbon\Carbon::parse($keyword)->endOfDay());
                } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $keyword, $matches)) {
                    // Si viene en formato DD/MM/YYYY
                    $date = "{$matches[3]}-{$matches[2]}-{$matches[1]}";
                    $query->where('required_date', '>=', \Carbon\Carbon::parse($date)->startOfDay())
                          ->where('required_date', '<=', \Carbon\Carbon::parse($date)->endOfDay());
                }
            })

            ->filterColumn('created_at', function ($query, $keyword) {
                // Convertir la búsqueda DD/MM/YYYY o YYYY-MM-DD a formato que SQL entienda
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $keyword)) {
                    // Si viene en formato ISO (YYYY-MM-DD) del input type="date"
                    $query->where('created_at', '>=', \Carbon\Carbon::parse($keyword)->startOfDay())
                          ->where('created_at', '<=', \Carbon\Carbon::parse($keyword)->endOfDay());
                } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $keyword, $matches)) {
                    // Si viene en formato DD/MM/YYYY
                    $date = "{$matches[3]}-{$matches[2]}-{$matches[1]}";
                    $query->where('created_at', '>=', \Carbon\Carbon::parse($date)->startOfDay())
                          ->where('created_at', '<=', \Carbon\Carbon::parse($date)->endOfDay());
                }
            })

            // ✅ Filtro personalizado para status (buscar por el valor del enum)
            ->filterColumn('status', function ($query, $keyword) {
                if (!empty($keyword)) {
                    $query->where('status', $keyword);
                }
            })

            ->addColumn('actions', function ($r) {
                $showUrl = route('requisitions.show', $r->id);
                $editUrl = route('requisitions.edit-livewire', $r->id);
                $deleteUrl = route('requisitions.destroy', $r->id);
                $cancelUrl = route('requisitions.cancel', $r->id);

                // Permisos
                $canEdit = $r->canBeEdited();     // DRAFT o PAUSED
                $canDelete = $r->canBeDeleted();   // Solo DRAFT
                $canCancel = $r->canBeCancelled(); // PENDING, PAUSED, IN_QUOTATION

                $csrfToken = csrf_token();

                $html = '<div class="d-flex justify-content-end gap-1">'
                    . '<a class="btn btn-sm btn-outline-secondary" href="' . $showUrl . '" title="Ver Detalles"><i class="ti ti-eye"></i></a>';

                if ($canEdit) {
                    $html .= '<a class="btn btn-sm btn-outline-primary" href="' . $editUrl . '" title="Editar"><i class="ti ti-pencil"></i></a>';
                }
                // Cancelar (PENDING, PAUSED, IN_QUOTATION)
                if ($canCancel) {
                    $html .= '<button type="button" class="btn btn-sm btn-outline-warning js-cancel-btn" data-folio="' . $r->folio . '" data-url="' . $cancelUrl . '" title="Cancelar"><i class="ti ti-ban"></i></button>';
                }

                $html .= '</div>';
                return $html;
            })
            ->rawColumns(['status', 'actions'])
            ->make(true);
    }

    /**
     * Show the form for creating a new requisition.
     */
    public function create(): View
    {
        return $this->loadFormData(new Requisition());
    }

    /**
     * Store a newly created requisition in storage.
     */
    public function store(SaveRequisitionRequest $request): RedirectResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                $data = $request->validated();
                $action = $request->input('submit_action', 'draft');
                $action = $action === 'submit' ? 'submit' : 'draft';
                $data['fiscal_year'] = now()->year;

                $requisition = $this->createRequisition($data, 'draft');
                $this->processRequisitionItems($requisition, $data['items'] ?? []);

                if ($action === 'submit') {
                    $requisition->submitToCompras();
                }

                return redirect()
                    ->route('requisitions.show', $requisition)
                    ->with('success', $action === 'submit'
                        ? 'Requisición creada y enviada a Compras.'
                        : 'Requisición creada correctamente.');
            });
        } catch (Throwable $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->with('error', 'Error al guardar la requisición: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for creating a new requisition with Livewire.
     */
    public function createLivewire(): View
    {
        return $this->loadFormData(new Requisition());
    }

    /**
     * Muestra el formulario de edición usando Livewire.
     */
    public function editLivewire(Requisition $requisition)
    {
        // REGLA DE ORO: Solo se editan borradores (Drafts)
        // Si intentan editar algo que ya está en 'pending', los mandamos de regreso a la base.
        if ($requisition->status !== RequisitionStatus::DRAFT && $requisition->status !== RequisitionStatus::REJECTED) {
            return redirect()->route('requisitions.show', $requisition)
                ->with('error', 'Atención. Solo puedes editar requisiciones en estado Borrador o Rechazada.');
        }

        // Retornamos una vista que solo contiene el componente Livewire
        return view('requisitions.edit-livewire', compact('requisition'));
    }

    /**
     * Show the form for editing the specified requisition.
     */
    public function edit(Requisition $requisition): View
    {
        if (!$requisition->canBeEdited()) {
            abort(403, 'No se puede editar una requisición en estado "' . $requisition->statusLabel() . '".');
        }

        return view('requisitions.edit-livewire', compact('requisition'));
    }

    /**
     * Update the specified requisition in storage.
     */
    public function update(SaveRequisitionRequest $request, Requisition $requisition): RedirectResponse
    {
        try {
            return DB::transaction(function () use ($request, $requisition) {
                $data = $request->validated();

                // Determinar la acción
                $action = $request->input('action', 'save');
                $action = in_array($action, ['save', 'submit'], true) ? $action : 'save';

                // Actualizar la requisición
                $this->updateRequisition($requisition, $data);

                // Procesar partidas
                $this->processRequisitionItems($requisition, $data['items'] ?? []);

                // Si se envía a Compras
                if ($action === 'submit') {
                    $requisition->submitToCompras();
                    event(new RequisitionUpdated($requisition));

                    return redirect()
                        ->route('requisitions.index')
                        ->with('success', 'Requisición actualizada y enviada a Compras (RN-005).');
                }

                return redirect()
                    ->route('requisitions.show', $requisition)
                    ->with('success', 'Requisición actualizada correctamente.');
            });
        } catch (Throwable $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'Error al actualizar la requisición: ' . $e->getMessage());
        }
    }

    /**
     * Soft delete de la requisición (solo BORRADOR).
     * Elimina físicamente la requisición y sus partidas.
     */
    /**
     * Las requisiciones ya no se eliminan para conservar expediente.
     */
    public function destroy(Requisition $requisition): RedirectResponse
    {
        return back()->with('error', 'Las requisiciones ya no se eliminan. Usa la cancelacion para conservar el expediente.');
    }

    /**
     * Cancelar la requisición (solo PENDING o estados activos).
     * Cambia el estado a CANCELLED sin eliminar el registro.
     */
    public function cancel(Request $request, Requisition $requisition): RedirectResponse
    {
        // Solo se pueden cancelar requisiciones en estados activos
        if (!$requisition->canBeCancelled()) {
            return back()->with(
                'error',
                'No se puede cancelar una requisición en estado "' . $requisition->status->label() . '".'
            );
        }

        // Validar motivo de cancelación
        $request->validate([
            'cancellation_reason' => 'required|string|max:1000'
        ], [
            'cancellation_reason.required' => 'Debe proporcionar un motivo de cancelación.',
            'cancellation_reason.max' => 'El motivo no puede exceder 1000 caracteres.'
        ]);

        try {
            DB::beginTransaction();

            // Cancelar la requisición
            $requisition->cancel(
                $request->input('cancellation_reason'),
                Auth::id()
            );

            DB::commit();

            return redirect()
                ->route('requisitions.index')
                ->with('success', "Requisición {$requisition->folio} cancelada correctamente.");
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Error al cancelar la requisición: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified requisition.
     */
    public function show(Requisition $requisition): View
    {
        $requisition->load([
            'company',
            'costCenter',
            'receivingLocation',
            'department',
            'items.productService',
            'items.expenseCategory',
            'items.budgetCedula',
            'items.suggestedVendor',
            'requester',
            'creator',
            'pauser',
            'reactivator',
            'canceller',
        ]);

        return view('requisitions.show', compact('requisition'));
    }

    /**
     * Load form data for create and edit views.
     */
    protected function loadFormData(Requisition $requisition): View
    {
        $userCompanies = Auth::user()
            ->companies()
            ->orderBy('companies.name')
            ->get(['companies.id', 'companies.name']);

        $selectedCompanyId = old(
            'company_id',
            $requisition->company_id
                ?? ($userCompanies->count() === 1 ? $userCompanies->first()->id : null)
        );

        $view = $requisition->exists ? 'requisitions.edit' : 'requisitions.create';

        return view($view, [
            'requisition' => $requisition,
            'companies' => $userCompanies,
            'costCenters' => $this->getCostCenters(
                $selectedCompanyId,
                old('purchase_type', $requisition->costCenter?->purchase_type?->value ?? $requisition->costCenter?->purchase_type)
            ),
            'departments' => Department::active()->orderBy('name')->get(['id', 'name']),
            'statusOptions' => RequisitionStatus::options(),
            'expenseCategories' => ExpenseCategory::active()->orderBy('name')->get(['id', 'name']),
            'receivingLocations' => ReceivingLocation::active()->where('portal_blocked', false)->orderBy('name')->get(['id', 'code', 'name', 'city']),
            'selectedCompanyId' => $selectedCompanyId,
            'purchaseTypes' => PurchaseType::values(),
            'selectedPurchaseType' => old('purchase_type', $requisition->costCenter?->purchase_type?->value ?? $requisition->costCenter?->purchase_type),
            'initialItems' => $this->hydrateFormItems(old('items', [])),
            'currentMonth' => (int) date('n'),
            'months' => $this->getMonthsOptions(),
        ]);
    }

    protected function hydrateFormItems(array $items): array
    {
        if (empty($items)) {
            return [];
        }

        $products = ProductService::whereIn('id', collect($items)->pluck('product_service_id')->filter()->all())
            ->get()
            ->keyBy('id');
        $categories = ExpenseCategory::whereIn('id', collect($items)->pluck('expense_category_id')->filter()->all())
            ->get(['id', 'name'])
            ->keyBy('id');
        $cedulas = BudgetCedula::whereIn('id', collect($items)->pluck('budget_cedula_id')->filter()->all())
            ->get(['id', 'name'])
            ->keyBy('id');

        return collect($items)
            ->map(function (array $item) use ($products, $categories, $cedulas) {
                $product = $products->get((int) ($item['product_service_id'] ?? 0));
                $category = $categories->get((int) ($item['expense_category_id'] ?? 0));
                $cedula = $cedulas->get((int) ($item['budget_cedula_id'] ?? 0));

                return [
                    'id' => $item['id'] ?? null,
                    'product_id' => $item['product_service_id'] ?? null,
                    'product_name' => $product
                        ? '['.$product->code.'] '.($product->short_name ?: str($product->description)->limit(60))
                        : 'Producto seleccionado',
                    'description' => $item['description'] ?? $product?->technical_description ?? $product?->description ?? '',
                    'quantity' => $item['quantity'] ?? 1,
                    'unit' => $item['unit'] ?? $product?->unit_of_measure ?? 'PIEZA',
                    'expense_category_id' => $item['expense_category_id'] ?? null,
                    'expense_category_name' => $category?->name ?? 'Categoría seleccionada',
                    'budget_cedula_id' => $item['budget_cedula_id'] ?? null,
                    'budget_cedula_name' => $cedula?->name ?? 'Subcategoría seleccionada',
                    'notes' => $item['notes'] ?? '',
                ];
            })
            ->filter(fn (array $item) => ! empty($item['product_id']))
            ->values()
            ->all();
    }

    /**
     * Create a new requisition from the given data.
     */
    protected function createRequisition(array $data, string $action): Requisition
    {
        return Requisition::create([
            'company_id' => $data['company_id'],
            'cost_center_id' => $data['cost_center_id'],
            'receiving_location_id' => $data['receiving_location_id'],
            'department_id' => $data['department_id'] ?? null,
            'fiscal_year' => $data['fiscal_year'],
            'folio' => Requisition::nextFolio($data['fiscal_year']),
            'requested_by' => Auth::id(),
            'required_date' => $data['required_date'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $action === 'draft' ? RequisitionStatus::DRAFT->value : RequisitionStatus::PENDING->value,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Update an existing requisition with the given data.
     */
    protected function updateRequisition(Requisition $requisition, array $data): void
    {
        $updateData = [
            'required_date' => $data['required_date'] ?? null,
            'description' => $data['description'] ?? null,
            'updated_by' => Auth::id(),
        ];

        // Solo permitir cambiar cost_center, department y receiving_location si está en draft
        if ($requisition->isDraft()) {
            $updateData['cost_center_id'] = $data['cost_center_id'];
            $updateData['department_id'] = $data['department_id'] ?? null;
            $updateData['receiving_location_id'] = $data['receiving_location_id'];
        }

        $requisition->update($updateData);
    }

    /**
     * Process requisition items (create, update, delete).
     */
    protected function processRequisitionItems(Requisition $requisition, array $items): void
    {
        $currentItems = $requisition->items()->get(['id']);
        $currentIds = $currentItems->pluck('id')->all();
        $incomingIds = collect($items)
            ->pluck('id')
            ->filter()
            ->map(fn($v) => (int) $v)
            ->all();

        // Eliminar partidas que ya no están en el request
        $toDelete = array_diff($currentIds, $incomingIds);
        if (!empty($toDelete)) {
            $requisition->items()->whereIn('id', $toDelete)->delete();
        }

        // Procesar cada partida
        foreach ($items as $index => $item) {
            $this->processRequisitionItem($requisition, $item, $index + 1);
        }
    }

    /**
     * Process a single requisition item.
     * 
     * IMPORTANTE: NO se manejan precios aquí (RN-002).
     * Los precios se asignan en el módulo de COTIZACIONES.
     * 
     * Esta función procesa una partida individual de la requisición:
     * - Valida que el producto exista en el catálogo (RN-001)
     * - Hereda información del catálogo (código, descripción, unidad)
     * - Asigna la categoría de gasto seleccionada por el requisitor (RN-010A, RN-010B)
     * - NO asigna mes de aplicación (este campo fue eliminado del sistema)
     * - NO maneja precios (estos se asignan en cotizaciones - RN-002)
     * 
     * @param Requisition $requisition La requisición padre
     * @param array $itemData Datos de la partida desde el formulario
     * @param int $lineNumber Número de línea secuencial
     * @throws \RuntimeException Si el producto no existe en el catálogo
     * @return void
     */
    protected function processRequisitionItem(Requisition $requisition, array $itemData, int $lineNumber): void
    {
        // Obtener ID si es una actualización (edición de partida existente)
        $itemId = $itemData['id'] ?? null;

        // RN-001: Validar que el producto exista en el catálogo
        $product = ProductService::with('category')->find($itemData['product_service_id']);

        if (!$product) {
            throw new \RuntimeException('El producto seleccionado no existe en el catálogo (RN-001).');
        }

        // Preparar datos de la partida heredando información del catálogo
        $data = [
            // === Datos del producto (heredados del catálogo) ===
            'product_service_id' => $product->id,
            'line_number' => $lineNumber,
            'item_category' => optional($product->category)->name,
            'product_code' => $product->code,

            // Usar technical_description del catálogo si no se proporciona una descripción personalizada
            'description' => $itemData['description'] ?? $product->technical_description,

            // Unidad de medida del catálogo
            'unit' => $product->unit_of_measure ?? 'PIEZA',

            // Proveedor sugerido del catálogo (no vinculante)
            'suggested_vendor_id' => $product->default_vendor_id,

            // === Datos definidos por el requisitor ===
            // RN-010A, RN-010B: Categoría de gasto OBLIGATORIA, definida por REQUISITOR
            'expense_category_id' => $itemData['expense_category_id'],
            'budget_cedula_id' => $itemData['budget_cedula_id'],

            // Cantidad solicitada (mínimo 0.001)
            'quantity' => max(0.001, (float) ($itemData['quantity'] ?? 1)),

            // Observaciones del requisitor
            'notes' => $itemData['notes'] ?? null,
        ];

        // Actualizar partida existente o crear nueva
        if ($itemId) {
            // Modo EDICIÓN: actualizar partida existente
            $item = $requisition->items()->find($itemId);
            if ($item) {
                $item->update($data);
            }
        } else {
            // Modo CREACIÓN: crear nueva partida
            $requisition->items()->create($data);
        }
    }

    /**
     * Get cost centers for the specified company.
     */
    protected function getCostCenters(?int $companyId, ?string $purchaseType = null)
    {
        $user = Auth::user();

        if (! $user || ! $companyId) {
            return collect();
        }

        return $user->costCenters()
            ->where('cost_centers.company_id', $companyId)
            ->where('cost_centers.status', 'ACTIVO')
            ->whereNull('cost_centers.deleted_at')
            ->wherePivot('is_active', true)
            ->when($purchaseType, fn ($query) => $query->where('cost_centers.purchase_type', $purchaseType))
            ->orderBy('cost_centers.name')
            ->get(['cost_centers.id', 'cost_centers.name', 'cost_centers.code', 'cost_centers.company_id', 'cost_centers.purchase_type']);
    }

    /**
     * Get months options for dropdown.
     */
    protected function getMonthsOptions(): array
    {
        $currentMonth = (int) date('n');
        $months = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre'
        ];

        // Filtrar solo meses válidos (del mes actual a diciembre - RN-010E)
        return array_filter($months, fn($month) => $month >= $currentMonth, ARRAY_FILTER_USE_KEY);
    }

    /**
     * DataTables para la Bandeja de Aprobación.
     * Filtra estrictamente por estatus PENDIENTE.
     */
    public function approvalDatatable(Request $request): JsonResponse
    {
        // 1. Query Base: Eager Loading para evitar N+1
        $query = Requisition::with(['costCenter', 'requester', 'department'])
            ->withCount('items')
            ->select('requisitions.*')
            ->where('status', RequisitionStatus::PENDING->value)
            ->orWhere('status', RequisitionStatus::IN_QUOTATION->value);

        return DataTables::of($query)
            ->addColumn('cost_center_name', function ($row) {
                return $row->costCenter ? ($row->costCenter->code . ' - ' . $row->costCenter->name) : 'N/A';
            })
            ->addColumn('requester_name', function ($row) {
                return $row->requester ? $row->requester->name : 'N/A';
            })
            ->addColumn('department_name', function ($row) {
                return $row->department ? $row->department->name : 'N/A';
            })
            ->addColumn('items_count', function ($row) {
                return $row->items_count;
            })
            ->editColumn('status', function ($row) {
                return '<span class="badge bg-' . $row->status->badgeClass() . '">' .
                    $row->status->label() . '</span>';
            })
            // Columna Acciones con lógica condicional
            ->addColumn('action', function ($row) {
                $actions = [];

                // Validar Requisición solo para PENDING
                if ($row->status === RequisitionStatus::PENDING) {
                    $actions[] = [
                        'url' => route('requisitions.validate.show', $row->id),
                        'icon' => 'ti ti-check',
                        'label' => 'Validar Requisición'
                    ];
                }

                // Planificar Cotización solo para IN_QUOTATION
                if ($row->status === RequisitionStatus::IN_QUOTATION) {
                    $actions[] = [
                        'url' => route('requisitions.quotation-planner.show', $row->id),
                        'icon' => 'ti ti-layout-grid',
                        'label' => 'Planificar Cotización'
                    ];
                }

                if (empty($actions)) {
                    return '<span class="text-muted">Sin acciones</span>';
                }

                $html = '<div class="d-flex justify-content-end gap-1">';
                foreach ($actions as $action) {
                    $html .= '<a class="btn btn-sm btn-outline-secondary" href="' . $action['url'] . '" title="' . $action['label'] . '"><i class="' . $action['icon'] . '"></i></a>';
                }
                $html .= '</div>';

                return $html;
            })
            ->editColumn('created_at', fn($row) => $row->created_at ? $row->created_at->format('d/m/Y H:i') : 'N/A')
            ->editColumn('required_date', fn($row) => $row->required_date ? $row->required_date->format('d/m/Y') : 'N/A')
            ->rawColumns(['status', 'action'])
            ->make(true);
    }

    /**
     * Retorna el JSON para llenar el Modal de Revisión Técnica.
     * Ruta: GET /requisitions/{id}/review-data
     */
    public function reviewData(Requisition $requisition): JsonResponse
    {
        // Cargamos relaciones (usando los nombres CORRECTOS en inglés)
        $requisition->load(['requester', 'costCenter', 'items.productService', 'items.expenseCategory']);

        return response()->json([
            'folio' => $requisition->folio,

            // EL FIX: Operador Null Safe (?->) y Coalescing Operator (??)
            // Si requester es null, devuelve null. Si name es null, devuelve null. Al final, imprime 'N/A'.
            'solicitante' => $requisition->requester?->name ?? 'N/A',

            // EL ERROR ESTABA AQUÍ:
            // Si required_date es null, ?->format devuelve null, y el ?? pone 'N/A'.
            // Ya no explotará.
            'fecha_requerida' => $requisition->required_date?->format('Y-m-d') ?? 'N/A',

            'observaciones' => $requisition->description ?? 'Sin observaciones',

            'cost_center' => $requisition->costCenter
                ? ($requisition->costCenter->code . ' - ' . $requisition->costCenter->name)
                : 'N/A',

            'partidas' => $requisition->items->map(fn($item) => [
                // Usamos Null Safe aquí también por si acaso
                'producto' => $item->description ?? $item->productService?->description ?? 'N/A',
                'cantidad' => (float) $item->quantity,
                'unidad' => $item->unit ?? 'PZA',
                'categoria_sugerida' => $item->expenseCategory?->name ?? 'N/A'
            ]),

            'action_url' => route('requisitions.validate-technical', $requisition->id)
        ]);
    }

    /**
     * Procesa la validación técnica del comprador.
     * Ruta: POST /requisitions/{id}/validate-technical
     */
    public function validateTechnical(Request $request, Requisition $requisition): JsonResponse
    {
        // Validamos que el Comprador haya marcado los checkboxes obligatorios [cite: 110-112]
        $request->validate([
            'specs_clear' => 'required|accepted', // "Las especificaciones son claras"
            'time_feasible' => 'required|accepted', // "El tiempo es realista"
            'category_id' => 'required|exists:categorias_gastos,id' // Compras puede ajustar la categoría
        ]);

        try {
            DB::transaction(function () use ($requisition, $request) {
                // Actualizamos estatus a "En Cotización" [cite: 634]
                $requisition->update([
                    'status' => 'En cotización',
                    'validated_at' => now(),
                    'validated_by' => Auth::id(),
                    // Si compras cambió la categoría, aquí se guardaría la lógica para actualizar items
                ]);

                // Aquí dispararíamos la notificación al usuario (opcional pero recomendado)
            });

            return response()->json([
                'success' => true,
                'message' => 'Requisición validada. Lista para cotizar (RFQ).'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al validar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Rechaza la requisición y la devuelve al usuario.
     * Ruta: POST /requisitions/{id}/reject
     */
    public function reject(Request $request, Requisition $requisition): JsonResponse
    {
        // La justificación es obligatoria y mínima de 50 caracteres [cite: 334-335]
        $request->validate([
            'reason' => 'required|string|min:50'
        ]);

        $requisition->update([
            'status' => 'Rechazada', // [cite: 638]
            'rejection_reason' => $request->reason,
            'rejected_at' => now(),
            'rejected_by' => Auth::id()
        ]);

        // IMPORTANTE: El presupuesto no se afecta en rechazo, así que no hacemos rollback de fondos aquí.

        return response()->json(['success' => true, 'message' => 'Requisición devuelta al usuario.']);
    }

    // Método temporal en tu controlador
    public function duplicateForTesting($id)
    {
        if (!app()->environment('local')) {
            abort(403, 'Esta función solo está disponible en desarrollo');
        }

        $original = Requisition::with('items')->findOrFail($id);

        // Duplicar la requisición
        $nueva = $original->replicate();

        // Generar nuevo folio
        $nueva->folio = Requisition::nextFolio();

        // Resetear campos que no deben duplicarse
        $nueva->status = RequisitionStatus::PENDING;
        $nueva->pause_reason = null;
        $nueva->paused_by = null;
        $nueva->paused_at = null;
        $nueva->reactivated_by = null;
        $nueva->reactivated_at = null;
        $nueva->cancellation_reason = null;
        $nueva->cancelled_by = null;
        $nueva->cancelled_at = null;
        $nueva->rejection_reason = null;
        $nueva->rejected_by = null;
        $nueva->rejected_at = null;
        $nueva->created_by = Auth::id();
        $nueva->updated_by = Auth::id();

        // ❌ NO HAGAS ESTO:
        // $nueva->created_at = null;
        // $nueva->updated_at = null;

        // ✅ MEJOR: Déjalos que Laravel los maneje automáticamente

        $nueva->save();

        // Duplicar los items (partidas)
        foreach ($original->items as $item) {
            $nuevoItem = $item->replicate();
            $nuevoItem->requisition_id = $nueva->id;
            $nuevoItem->save();
        }

        return redirect()->route('requisitions.edit', $nueva->id)
            ->with('success', 'Requisición duplicada exitosamente');
    }
}


