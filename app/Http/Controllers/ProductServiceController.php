<?php

namespace App\Http\Controllers;

use App\Enum\ProductServiceStatus;
use App\Events\ProductServiceApproved;
use App\Http\Requests\SaveProductServiceRequest;
use App\Models\Account;
use App\Models\BudgetCedula;
use App\Models\ContractProduct;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use App\Models\RequisitionItem;
use App\Models\Subaccount;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\NewProductRequestedNotification;
use App\Services\BudgetAccessService;
use App\Services\ProductBudgetClassificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/**
 * ProductServiceController
 * Gestiona el Catálogo de Productos y Servicios
 * según ESPECIFICACIONES_TECNICAS_SISTEMA_CONTROL_PRESUPUESTAL.md
 */
class ProductServiceController extends Controller
{
    /**
     * Muestra la vista principal del catálogo
     */
    public function index(): View
    {
        return view('products_services.index');
    }

    /**
     * DataTable server-side para el listado de productos/servicios
     */
    public function datatable(Request $request)
    {
        $query = ProductService::query()
            ->with(['creator', 'subaccounts.account']);

        $allowedSubaccounts = app(BudgetAccessService::class)->subaccountIdsFor($request->user());
        if (! $request->user()?->can('productos.administrar')) {
            $query->withAllowedSubaccounts($allowedSubaccounts);
        }

        return DataTables::of($query)
            ->addColumn('creator_name', fn ($p) => e($p->creator?->name ?? '—'))
            ->addColumn('product_type_badge', function ($p) {
                $color = $p->product_type === 'SERVICIO' ? 'info' : 'primary';

                return '<span class="badge bg-'.$color.'">'.$p->product_type.'</span>';
            })
            ->editColumn('estimated_price', fn ($p) => number_format((float) $p->estimated_price, 2))
            ->editColumn('status', function ($p) {
                $status = ProductServiceStatus::from($p->status);
                $cls = ProductServiceStatus::badgeClass($status);
                $label = ProductServiceStatus::options()[$status->value] ?? $status->value;
                $activeIcon = $p->is_active ? '<i class="ti ti-check-circle ms-1"></i>' : '';

                return '<span class="badge bg-'.$cls.'">'.$label.$activeIcon.'</span>';
            })
            ->editColumn('technical_description', function ($p) {
                $display = $p->short_name ?? Str::limit($p->technical_description, 60);

                return e($display);
            })
            ->addColumn('actions', function ($p) {
                $showUrl = route('products-services.show', $p->id);
                $editUrl = route('products-services.edit', $p->id);
                $deleteUrl = route('products-services.destroy', $p->id);

                // Opciones según el estado
                $canActivate = $p->status === ProductServiceStatus::PENDING->value;
                $canDeactivate = $p->status === ProductServiceStatus::ACTIVE->value;
                $canReject = $p->status === ProductServiceStatus::PENDING->value;
                $canReactivate = $p->status === ProductServiceStatus::INACTIVE->value;

                $html = '<div class="d-flex justify-content-end gap-1">'
                    .'<a class="btn btn-sm btn-outline-secondary" href="'.$showUrl.'" title="Ver"><i class="ti ti-eye"></i></a>'
                    .'<a class="btn btn-sm btn-outline-primary" href="'.$editUrl.'" title="Editar"><i class="ti ti-pencil"></i></a>';

                // Acciones de estado (solo para Administrador del Catálogo)
                if (auth()->check() && auth()->user()->hasRole(['catalog_admin', 'superadmin'])) {
                    if ($canActivate) {
                        $approveUrl = route('products-services.approve', $p->id);
                        $html .= '<form action="'.$approveUrl.'" method="POST" class="js-approve-form d-inline">'
                            .csrf_field()
                            .'<button type="submit" class="btn btn-sm btn-outline-success" title="Aprobar"><i class="ti ti-check"></i></button>'
                            .'</form>';
                    }

                    if ($canReject) {
                        $rejectUrl = route('products-services.reject', $p->id);
                        $html .= '<a class="btn btn-sm btn-outline-warning js-reject-btn" href="#" data-url="'.$rejectUrl.'" data-entity="'.$p->code.'" title="Rechazar"><i class="ti ti-x"></i></a>';
                    }

                    if ($canDeactivate) {
                        $deactivateUrl = route('products-services.deactivate', $p->id);
                        $html .= '<form action="'.$deactivateUrl.'" method="POST" class="js-status-action-form d-inline">'
                            .csrf_field()
                            .'<button type="submit" class="btn btn-sm btn-outline-secondary" title="Desactivar"><i class="ti ti-circle-off"></i></button>'
                            .'</form>';
                    }

                    if ($canReactivate) {
                        $reactivateUrl = route('products-services.reactivate', $p->id);
                        $html .= '<form action="'.$reactivateUrl.'" method="POST" class="js-status-action-form d-inline">'
                            .csrf_field()
                            .'<button type="submit" class="btn btn-sm btn-outline-success" title="Reactivar"><i class="ti ti-circle-check"></i></button>'
                            .'</form>';
                    }
                }

                $html .= '<form action="'.$deleteUrl.'" method="POST" class="js-delete-form d-inline">'
                    .csrf_field().method_field('DELETE')
                    .'<button type="button" class="btn btn-sm btn-outline-danger js-delete-btn" data-entity="'.$p->code.'" title="Eliminar"><i class="ti ti-trash"></i></button>'
                    .'</form>'
                    .'</div>';

                return $html;
            })
            ->rawColumns(['product_type_badge', 'status', 'actions'])
            ->make(true);
    }

    /**
     * Muestra el formulario de creación
     */
    public function create(): View
    {
        $productService = new ProductService([
            'status' => ProductServiceStatus::PENDING->value,
            'currency_code' => 'MXN',
            'product_type' => 'PRODUCTO',
            'unit_of_measure' => 'PIEZA',
        ]);

        $suppliers = Supplier::active()->orderBy('company_name')->get(['id', 'company_name']);
        $statusOpts = ProductServiceStatus::options();

        // Unidades de medida comunes
        $unitsOfMeasure = [
            'PIEZA' => 'Pieza',
            'SERVICIO' => 'Servicio',
            'KG' => 'Kilogramo',
            'LITRO' => 'Litro',
            'METRO' => 'Metro',
            'CAJA' => 'Caja',
            'PAQUETE' => 'Paquete',
            'HORA' => 'Hora',
            'MES' => 'Mes',
        ];

        $budgetCedulas = \App\Models\BudgetCedula::active()->notDeleted()
            ->whereHas('expenseCategory', fn ($q) => $q->active())
            ->with(['expenseCategory:id,code,name', 'subaccount:id,legacy_budget_cedula_id,code'])
            ->get()
            ->sortBy([['expenseCategory.code', 'asc'], ['name', 'asc']])
            ->values();
        $departments = Department::active()->orderBy('name')->get(['id', 'name', 'abbreviated']);
        $departmentAssignments = [];

        return view('products_services.create', compact(
            'productService',
            'suppliers',
            'statusOpts',
            'unitsOfMeasure',
            'budgetCedulas',
            'departments',
            'departmentAssignments'
        ));
    }

    /**
     * Almacena un nuevo producto/servicio
     */
    public function store(SaveProductServiceRequest $request): RedirectResponse
    {
        return DB::transaction(function () use ($request) {
            $data = $request->validated();

            $productService = new ProductService;
            $productService->fill([
                // Identificación
                'code' => ProductService::nextCode(),
                'technical_description' => $data['technical_description'] ?? null,
                'short_name' => $data['short_name'] ?? null,
                'product_type' => $data['product_type'],

                // Clasificación presupuestal
                'is_inventoriable' => $request->boolean('is_inventoriable'),

                // Especificaciones técnicas
                'brand' => $data['brand'] ?? null,
                'model' => $data['model'] ?? null,
                'unit_of_measure' => $data['unit_of_measure'],
                'specifications' => $data['specifications'] ?? null,

                // Información comercial
                'estimated_price' => $data['estimated_price'],
                'currency_code' => $data['currency_code'] ?? 'MXN',
                'default_vendor_id' => $data['default_vendor_id'] ?? null,
                'minimum_quantity' => $data['minimum_quantity'] ?? null,
                'maximum_quantity' => $data['maximum_quantity'] ?? null,
                'lead_time_days' => $data['lead_time_days'] ?? null,

                // Estado
                'status' => ProductServiceStatus::PENDING->value,
                'is_active' => false, // Se activa al aprobar

                // Observaciones
                'observations' => $data['observations'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,

                // Auditoría
                'created_by' => Auth::id(),
            ]);
            $productService->save();

            $this->syncBudgetRelations($productService, $data);

            app(ProductBudgetClassificationService::class)->ensureProductHasBudgetClassification($productService);

            return redirect()
                ->route('products-services.show', $productService)
                ->with('success', 'Producto/Servicio registrado correctamente. Estado: Pendiente de Validación.');
        });
    }

    /**
     * Muestra los detalles de un producto/servicio
     */
    public function show(ProductService $productService): View
    {
        $productService->load([
            'defaultVendor',
            'creator',
            'approver',
            'expenseCategories',
            'budgetCedulas',
        ]);

        return view('products_services.show', compact('productService'));
    }

    /**
     * Muestra el formulario de edición
     */
    public function edit(ProductService $productService): View
    {
        $suppliers = Supplier::active()->orderBy('company_name')->get(['id', 'company_name']);
        $statusOpts = ProductServiceStatus::options();

        $unitsOfMeasure = [
            'PIEZA' => 'Pieza',
            'SERVICIO' => 'Servicio',
            'KG' => 'Kilogramo',
            'LITRO' => 'Litro',
            'METRO' => 'Metro',
            'CAJA' => 'Caja',
            'PAQUETE' => 'Paquete',
            'HORA' => 'Hora',
            'MES' => 'Mes',
        ];

        $budgetCedulas = \App\Models\BudgetCedula::active()->notDeleted()
            ->whereHas('expenseCategory', fn ($q) => $q->active())
            ->with(['expenseCategory:id,code,name', 'subaccount:id,legacy_budget_cedula_id,code'])
            ->get()
            ->sortBy([['expenseCategory.code', 'asc'], ['name', 'asc']])
            ->values();

        $selectedBudgetCedulaIds = $productService->budgetCedulas->pluck('id')->all();
        $departments = Department::active()->orderBy('name')->get(['id', 'name', 'abbreviated']);
        $departmentAssignments = $productService->departmentSubaccountMappings()
            ->with('subaccount:id,legacy_budget_cedula_id')
            ->get()
            ->groupBy(fn ($mapping) => (int) $mapping->subaccount->legacy_budget_cedula_id)
            ->map(fn ($mappings) => $mappings->pluck('department_id')->map(fn ($id) => (int) $id)->all())
            ->all();

        return view('products_services.edit', compact(
            'productService',
            'suppliers',
            'statusOpts',
            'unitsOfMeasure',
            'budgetCedulas',
            'selectedBudgetCedulaIds',
            'departments',
            'departmentAssignments'
        ));
    }

    /**
     * Actualiza un producto/servicio existente
     */
    public function update(SaveProductServiceRequest $request, ProductService $productService): RedirectResponse
    {

        return DB::transaction(function () use ($request, $productService) {
            $data = $request->validated();

            $productService->fill([
                // Identificación
                'technical_description' => $data['technical_description'] ?? null,
                'short_name' => $data['short_name'] ?? null,
                'product_type' => $data['product_type'],

                // Clasificación presupuestal
                'is_inventoriable' => $request->boolean('is_inventoriable'),

                // Especificaciones técnicas
                'brand' => $data['brand'] ?? null,
                'model' => $data['model'] ?? null,
                'unit_of_measure' => $data['unit_of_measure'],
                'specifications' => $data['specifications'] ?? null,

                // Información comercial
                'estimated_price' => $data['estimated_price'],
                'currency_code' => $data['currency_code'] ?? 'MXN',
                'default_vendor_id' => $data['default_vendor_id'] ?? null,
                'minimum_quantity' => $data['minimum_quantity'] ?? null,
                'maximum_quantity' => $data['maximum_quantity'] ?? null,
                'lead_time_days' => $data['lead_time_days'] ?? null,

                // Observaciones
                'observations' => $data['observations'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,

                // Auditoría
                'updated_by' => Auth::id(),
            ]);

            // Si estaba REJECTED, volver a PENDING
            if ($productService->status === ProductServiceStatus::REJECTED->value) {
                $productService->status = ProductServiceStatus::PENDING->value;
                $productService->rejection_reason = null;
            }

            $productService->save();

            $this->syncBudgetRelations($productService, $data);

            app(ProductBudgetClassificationService::class)->ensureProductHasBudgetClassification($productService);

            return redirect()
                ->route('products-services.show', $productService)
                ->with('success', 'Producto/Servicio actualizado correctamente.');
        });
    }

    private function syncBudgetRelations(ProductService $productService, array $data): void
    {
        $budgetCedulaIds = collect($data['budget_cedula_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $productService->budgetCedulas()->sync($budgetCedulaIds);

        $expenseCategoryIds = \App\Models\BudgetCedula::whereIn('id', $budgetCedulaIds)
            ->pluck('expense_category_id')
            ->unique();
        $productService->expenseCategories()->sync($expenseCategoryIds);

        $subaccounts = Subaccount::query()
            ->whereIn('legacy_budget_cedula_id', $budgetCedulaIds)
            ->get(['id', 'legacy_budget_cedula_id']);
        $productService->subaccounts()->sync($subaccounts->pluck('id'));

        $accountIds = Account::query()
            ->whereIn('legacy_expense_category_id', $expenseCategoryIds)
            ->pluck('id');
        $productService->accounts()->sync($accountIds);

        $productService->departmentSubaccountMappings()->delete();
        if ($budgetCedulaIds->count() <= 1) {
            return;
        }

        $subaccountsByCedula = $subaccounts->keyBy('legacy_budget_cedula_id');
        $assignments = $data['department_subaccount_assignments'] ?? [];
        foreach ($budgetCedulaIds as $budgetCedulaId) {
            $subaccount = $subaccountsByCedula->get((int) $budgetCedulaId);
            foreach ($assignments[$budgetCedulaId] ?? [] as $departmentId) {
                $productService->departmentSubaccountMappings()->create([
                    'department_id' => (int) $departmentId,
                    'subaccount_id' => $subaccount->id,
                ]);
            }
        }
    }

    /**
     * Elimina (soft delete) un producto/servicio
     */
    public function destroy(ProductService $productService): RedirectResponse
    {
        $usageMessage = $this->usageBlockingMessage($productService);

        if ($usageMessage) {
            return redirect()
                ->route('products-services.index')
                ->with('error', $usageMessage);
        }

        $productService->deleted_by = Auth::id();
        $productService->save();
        $productService->delete();

        return redirect()
            ->route('products-services.index')
            ->with('success', 'Producto/Servicio eliminado correctamente.');
    }

    /**
     * Si el producto/servicio está en uso, regresa el mensaje explicando dónde.
     * Si no está en uso, regresa null.
     */
    protected function usageBlockingMessage(ProductService $productService): ?string
    {
        $requisitionItem = RequisitionItem::with('requisition')
            ->where('product_service_id', $productService->id)
            ->first();

        if ($requisitionItem) {
            $folio = $requisitionItem->requisition?->folio ?? "#{$requisitionItem->requisition_id}";

            return "No se puede eliminar el producto/servicio \"{$productService->code}\": está registrado como partida en la requisición {$folio}.";
        }

        $contractProduct = ContractProduct::with('contract')
            ->where('product_service_id', $productService->id)
            ->first();

        if ($contractProduct) {
            $folio = $contractProduct->contract?->folio ?? "#{$contractProduct->contract_id}";

            return "No se puede eliminar el producto/servicio \"{$productService->code}\": está registrado como parte del contrato {$folio}.";
        }

        return null;
    }

    /**
     * Aprueba un producto/servicio (Administrador del Catálogo)
     */
    public function approve(ProductService $productService): RedirectResponse
    {
        if ($productService->status !== ProductServiceStatus::PENDING->value) {
            return redirect()
                ->route('products-services.show', $productService)
                ->with('error', 'Solo se pueden aprobar productos en estado Pendiente.');
        }

        try {
            app(ProductBudgetClassificationService::class)->ensureProductHasBudgetClassification($productService);
            $productService->refresh();
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('products-services.show', $productService)
                ->with('error', $exception->getMessage());
        }

        if (! $productService->budgetCedulas()->exists()) {
            return redirect()
                ->route('products-services.show', $productService)
                ->with('error', 'El producto debe tener al menos una subcuenta asignada para ser aprobado.');
        }

        return DB::transaction(function () use ($productService) {
            $productService->status = ProductServiceStatus::ACTIVE->value;
            $productService->is_active = true; // Activar
            $productService->approved_by = Auth::id();
            $productService->approved_at = now();
            $productService->rejection_reason = null;
            $productService->save();

            event(new ProductServiceApproved($productService));

            return redirect()
                ->route('products-services.show', $productService)
                ->with('success', 'Producto/Servicio aprobado correctamente. Ahora está disponible para requisiciones.');
        });
    }

    /**
     * Rechaza un producto/servicio (Administrador del Catálogo)
     */
    public function reject(Request $request, ProductService $productService): RedirectResponse
    {
        $request->validate([
            'rejection_reason' => 'required|string|min:10|max:500',
        ]);

        if ($productService->status !== ProductServiceStatus::PENDING->value) {
            return redirect()
                ->route('products-services.show', $productService)
                ->with('error', 'Solo se pueden rechazar productos en estado Pendiente.');
        }

        $productService->status = ProductServiceStatus::REJECTED->value;
        $productService->rejection_reason = $request->rejection_reason;
        $productService->updated_by = Auth::id();
        $productService->save();

        return redirect()
            ->route('products-services.show', $productService)
            ->with('success', 'Producto/Servicio rechazado.');
    }

    /**
     * Desactiva un producto/servicio (Administrador del Catálogo)
     */
    public function deactivate(Request $request, ProductService $productService): RedirectResponse|JsonResponse
    {
        if ($productService->status !== ProductServiceStatus::ACTIVE->value) {
            return $this->statusActionResponse($request, $productService, false,
                'Solo se pueden desactivar productos en estado Activo.');
        }

        $productService->status = ProductServiceStatus::INACTIVE->value;
        $productService->is_active = false;
        $productService->updated_by = Auth::id();
        $productService->save();

        return $this->statusActionResponse($request, $productService, true,
            'Producto/Servicio desactivado. Ya no aparecerá en nuevas requisiciones.');
    }

    /**
     * Reactiva un producto/servicio (Administrador del Catálogo)
     */
    public function reactivate(Request $request, ProductService $productService): RedirectResponse|JsonResponse
    {
        if ($productService->status !== ProductServiceStatus::INACTIVE->value) {
            return $this->statusActionResponse($request, $productService, false,
                'Solo se pueden reactivar productos en estado Inactivo.');
        }

        try {
            app(ProductBudgetClassificationService::class)->ensureProductHasBudgetClassification($productService);
            $productService->refresh();
        } catch (\RuntimeException $exception) {
            return $this->statusActionResponse($request, $productService, false,
                $exception->getMessage());
        }

        if (! $productService->budgetCedulas()->exists()) {
            return $this->statusActionResponse($request, $productService, false,
                'El producto debe tener al menos una subcuenta asignada para reactivarse.');
        }

        $productService->status = ProductServiceStatus::ACTIVE->value;
        $productService->is_active = true;
        $productService->updated_by = Auth::id();
        $productService->save();

        return $this->statusActionResponse($request, $productService, true,
            'Producto/Servicio reactivado. Ya está disponible para requisiciones nuevamente.');
    }

    /**
     * Responde en JSON si la petición es AJAX (catálogo en tabla), o redirige
     * al detalle del producto/servicio si es un envío de formulario normal.
     */
    protected function statusActionResponse(Request $request, ProductService $productService, bool $success, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['success' => $success, 'message' => $message], $success ? 200 : 422);
        }

        return redirect()
            ->route('products-services.show', $productService)
            ->with($success ? 'success' : 'error', $message);
    }

    /**
     * API: Productos activos para requisiciones
     */
    public function apiActiveForRequisitions(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = (int) $request->input('company_id');
        $costCenterId = (int) $request->input('cost_center_id');

        if (! $companyId || ! $costCenterId) {
            return response()->json([
                'success' => false,
                'message' => 'La compania y el centro de costo son obligatorios.',
                'products' => [],
            ], 400);
        }

        $assignedCompany = $user?->companies()
            ->where('companies.id', $companyId)
            ->exists();

        if (! $assignedCompany) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para consultar esta compania.',
                'products' => [],
            ], 403);
        }

        $assignedCostCenter = $user?->costCenters()
            ->where('cost_centers.id', $costCenterId)
            ->where('cost_centers.company_id', $companyId)
            ->where('cost_centers.status', 'ACTIVO')
            ->whereNull('cost_centers.deleted_at')
            ->where('cost_center_user.is_active', true)
            ->exists();

        if (! $assignedCostCenter) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para consultar este centro de costo.',
                'products' => [],
            ], 403);
        }

        $departmentId = (int) $user->department_id;

        $query = ProductService::active()
            ->select([
                'id', 'code', 'technical_description', 'short_name', 'product_type',
                'brand', 'model', 'unit_of_measure', 'estimated_price', 'currency_code',
                'default_vendor_id', 'minimum_quantity', 'maximum_quantity', 'lead_time_days',
            ])
            ->with([
                'defaultVendor:id,first_name,last_name,company_name',
                'subaccounts:id,account_id,legacy_budget_cedula_id,code,name,is_fixed_asset',
                'subaccounts.account:id,legacy_expense_category_id,code,name,is_fixed_asset',
                'departmentSubaccountMappings' => fn ($query) => $query
                    ->where('department_id', $departmentId)
                    ->with('subaccount:id,account_id,legacy_budget_cedula_id,code,name,is_fixed_asset'),
                'departmentSubaccountMappings.subaccount.account:id,legacy_expense_category_id,code,name,is_fixed_asset',
            ]);

        $allowedSubaccounts = app(BudgetAccessService::class)->subaccountIdsFor($user);
        $query->withAllowedSubaccounts($allowedSubaccounts);

        // Búsqueda por término (para Select2)
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('short_name', 'like', "%{$search}%")
                    ->orWhere('technical_description', 'like', "%{$search}%");
            });
        }

        $query->orderBy('code');

        // Livewire recibe 15 resultados por página; `all` se conserva para formularios legacy.
        $perPage = 15;
        $page = max(1, (int) $request->input('page', 1));
        $isLegacyFullLoad = $request->boolean('all', false);
        $products = $isLegacyFullLoad
            ? $query->get()
            : $query->offset(($page - 1) * $perPage)->limit($perPage + 1)->get();
        $hasMore = ! $isLegacyFullLoad && $products->count() > $perPage;

        if ($hasMore) {
            $products->pop();
        }

        $subaccounts = $products->flatMap->subaccounts;
        $budgetCedulas = BudgetCedula::query()
            ->whereKey($subaccounts->pluck('legacy_budget_cedula_id')->filter()->unique())
            ->get(['id', 'expense_category_id', 'name'])
            ->keyBy('id');
        $expenseCategories = ExpenseCategory::query()
            ->whereKey($subaccounts->pluck('account.legacy_expense_category_id')->filter()->unique())
            ->get(['id', 'name'])
            ->keyBy('id');

        return response()->json([
            'products' => $products->map(function ($p) use ($allowedSubaccounts, $budgetCedulas, $expenseCategories) {
                $budgetClassification = $this->requisitionBudgetClassification(
                    $p,
                    $budgetCedulas,
                    $expenseCategories,
                );

                if (! $budgetClassification
                    || ! $allowedSubaccounts->contains($budgetClassification['subaccount_id'])) {
                    return null;
                }

                return [
                    'id' => $p->id,
                    'code' => $p->code,
                    'description' => $p->technical_description,
                    'short_name' => $p->short_name,
                    'product_type' => $p->product_type,
                    'brand' => $p->brand,
                    'model' => $p->model,
                    'unit_of_measure' => $p->unit_of_measure,
                    'estimated_price' => (float) $p->estimated_price,
                    'currency_code' => $p->currency_code,
                    'default_vendor_id' => $p->default_vendor_id,
                    'default_vendor_name' => $p->defaultVendor?->name,
                    'minimum_quantity' => $p->minimum_quantity ? (float) $p->minimum_quantity : null,
                    'maximum_quantity' => $p->maximum_quantity ? (float) $p->maximum_quantity : null,
                    'lead_time_days' => $p->lead_time_days,
                    'budget_classification' => $budgetClassification,
                ];
            })->filter()->values(),
            'pagination' => [
                'more' => $hasMore,
            ],
        ]);
    }

    /**
     * Resuelve la clasificación con relaciones ya precargadas para evitar consultas por producto.
     */
    private function requisitionBudgetClassification(
        ProductService $product,
        \Illuminate\Support\Collection $budgetCedulas,
        \Illuminate\Support\Collection $expenseCategories,
    ): ?array {
        $subaccounts = $product->subaccounts
            ->filter(fn (Subaccount $subaccount) => $subaccount->legacy_budget_cedula_id)
            ->sortBy('id')
            ->values();

        if ($subaccounts->isEmpty()) {
            return null;
        }

        $subaccount = $subaccounts->count() === 1
            ? $subaccounts->first()
            : $product->departmentSubaccountMappings
                ->pluck('subaccount')
                ->first(fn (?Subaccount $mappingSubaccount) => $mappingSubaccount
                    && $subaccounts->contains('id', $mappingSubaccount->id));

        $account = $subaccount?->account;
        $budgetCedula = $budgetCedulas->get($subaccount?->legacy_budget_cedula_id);
        $expenseCategory = $expenseCategories->get($account?->legacy_expense_category_id);

        if (! $subaccount || ! $account || ! $budgetCedula || ! $expenseCategory
            || (int) $budgetCedula->expense_category_id !== (int) $expenseCategory->id) {
            return null;
        }

        return [
            'account_id' => (int) $account->id,
            'account_code' => $account->code,
            'account_name' => $account->name,
            'subaccount_id' => (int) $subaccount->id,
            'subaccount_code' => $subaccount->code,
            'subaccount_name' => $subaccount->name,
            'expense_category_id' => (int) $expenseCategory->id,
            'expense_category_name' => $expenseCategory->name,
            'budget_cedula_id' => (int) $budgetCedula->id,
            'budget_cedula_name' => $budgetCedula->name,
            'is_fixed_asset' => (bool) ($subaccount->is_fixed_asset || $account->is_fixed_asset),
        ];
    }

    /**
     * Crea un producto desde una requisici�n.
     */
    public function storeFromRequisition(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'cost_center_id' => 'required|exists:cost_centers,id',
            'technical_description' => 'required|string|min:20|max:5000',
            'short_name' => 'nullable|string|max:100',
            'product_type' => 'nullable|in:PRODUCTO,SERVICIO',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'unit_of_measure' => 'nullable|string|max:30',
            'estimated_price' => 'required|numeric|min:0',
            'currency_code' => 'nullable|string|size:3|in:MXN,USD,EUR',
            'default_vendor_id' => 'nullable|exists:suppliers,id',
        ]);

        return DB::transaction(function () use ($validated) {
            $this->ensureCostCenterBelongsToCompany((int) $validated['cost_center_id'], (int) $validated['company_id']);

            $productService = new ProductService;
            $productService->fill([
                'code' => ProductService::nextCode(),
                'technical_description' => $validated['technical_description'],
                'short_name' => $validated['short_name'] ?? null,
                'product_type' => $validated['product_type'] ?? 'PRODUCTO',
                'brand' => $validated['brand'] ?? null,
                'model' => $validated['model'] ?? null,
                'unit_of_measure' => $validated['unit_of_measure'] ?? 'PIEZA',
                'estimated_price' => $validated['estimated_price'],
                'currency_code' => $validated['currency_code'] ?? 'MXN',
                'default_vendor_id' => $validated['default_vendor_id'] ?? null,

                'status' => ProductServiceStatus::PENDING->value,
                'is_active' => false,
                'created_by' => Auth::id(),
            ]);
            $productService->save();

            // Notificar a admins del catálogo
            $catalogAdmins = User::role(['catalog_admin', 'general_director', 'superadmin'])->get();
            foreach ($catalogAdmins as $admin) {
                $admin->notify(new NewProductRequestedNotification($productService, Auth::user()));
            }

            return response()->json([
                'success' => true,
                'message' => 'Producto solicitado correctamente. El Administrador del Catálogo lo revisará.',
                'product' => [
                    'id' => $productService->id,
                    'code' => $productService->code,
                    'description' => $productService->technical_description,
                    'unit_of_measure' => $productService->unit_of_measure,
                    'status' => $productService->status,
                ],
            ], 201);
        });
    }

    protected function ensureCostCenterBelongsToCompany(int $costCenterId, int $companyId): CostCenter
    {
        $costCenter = CostCenter::query()
            ->findOrFail($costCenterId);

        if ((int) $costCenter->company_id !== $companyId) {
            throw ValidationException::withMessages([
                'cost_center_id' => 'El centro de costo no pertenece a la compañía seleccionada.',
            ]);
        }

        return $costCenter;
    }
}
