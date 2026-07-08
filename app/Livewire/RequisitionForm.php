<?php

namespace App\Livewire;

use App\Enum\PurchaseType;
use App\Enum\RequisitionStatus;
use App\Models\BudgetCedula;
use App\Models\Company;
use App\Models\ReceivingLocation;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Services\BudgetCedulaCatalogService;
use App\Services\ProductBudgetClassificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Renderless;
use Livewire\Component;

class RequisitionForm extends Component
{
    // ===== PROPIEDADES DE MODO =====
    public $isEditMode = false;
    public $requisitionId;
    public $folio;

    // ===== PROPIEDADES DEL FORMULARIO =====
    public $company_id;
    public $required_date;
    public $description = '';

    // ===== COLECCIONES =====
    public $companies = [];
    public $receivingLocations = [];
    public $costCenterCatalog = [];

    // ===== UBICACIÓN DE RECEPCIÓN =====
    public $receiving_location_id;

    // ===== CONTADOR DE CARACTERES =====
    public $descriptionMaxLength = 500;
    public $descriptionRemainingChars = 500;

    // ===== PARTIDAS =====
    public $items = [];
    public $editingItemIndex = null;

    /**
     * Inicialización del componente.
     * Soporta modo CREACIÓN y EDICIÓN.
     */
    public function mount($requisition = null)
    {
        // Cargar solo las compañías del usuario autenticado
        $this->companies = Auth::user()->companies()->orderBy('name')->get();

        // Cargar ubicaciones de recepción activas
        $this->costCenterCatalog = Auth::user()->costCenters()
            ->select([
                'cost_centers.id',
                'cost_centers.company_id',
                'cost_centers.purchase_type',
                'cost_centers.code',
                'cost_centers.name',
            ])
            ->where('cost_centers.status', 'ACTIVO')
            ->whereNull('cost_centers.deleted_at')
            ->wherePivot('is_active', true)
            ->orderBy('cost_centers.code')
            ->get()
            ->map(function ($costCenter) {
                return [
                    'id' => (string) $costCenter->id,
                    'company_id' => (string) $costCenter->company_id,
                    'purchase_type' => $costCenter->purchase_type instanceof PurchaseType
                        ? $costCenter->purchase_type->value
                        : (string) $costCenter->purchase_type,
                    'code' => $costCenter->code,
                    'name' => $costCenter->name,
                    'is_default' => (bool) ($costCenter->pivot->is_default ?? false),
                ];
            })
            ->values()
            ->toArray();

        if ($requisition && $requisition->exists) {
            // ===== MODO EDICIÓN =====
            $this->isEditMode = true;
            $this->requisitionId = $requisition->id;
            $this->folio = $requisition->folio;

            // Validar que sea editable (solo DRAFT)
            if ($requisition->status->value !== RequisitionStatus::DRAFT->value && $requisition->status->value !== RequisitionStatus::REJECTED->value) {
                session()->flash('error', 'Solo se pueden editar requisiciones en estado Borrador o Rechazada.');
                return redirect()->route('requisitions.index');
            }

            // Cargar datos del formulario
            $this->company_id = $requisition->company_id;
            $this->description = $requisition->description;
            $this->required_date = now()->toDateString();

            // Cargar ubicación de recepción de la cabecera
            $this->receiving_location_id = $requisition->receiving_location_id;

            // Cargar partidas existentes
            $this->items = $requisition->items->map(function ($item) {
                return [
                    'product_id'            => $item->product_service_id,
                    'product_name'          => "[{$item->product_code}] " . ($item->productService->short_name ?? $item->description),
                    'description'           => $item->description,
                    'quantity'              => $item->quantity,
                    'unit'                  => $item->unit,
                    'expense_category_id'   => $item->expense_category_id,
                    'expense_category_name' => $item->expenseCategory->name ?? 'N/A',
                    'budget_cedula_id'      => $item->budget_cedula_id,
                    'budget_cedula_name'    => $item->budgetCedula->name ?? 'N/A',
                    'cost_center_id'        => $item->cost_center_id,
                    'cost_center_name'      => $item->costCenter?->name ?? 'N/A',
                    'purchase_type'         => $item->costCenter?->purchase_type instanceof \App\Enum\PurchaseType
                        ? $item->costCenter->purchase_type->value
                        : (string) ($item->costCenter?->purchase_type ?? ''),
                    'notes'                 => $item->notes ?? '',
                ];
            })->toArray();

            // Actualizar contador de caracteres
            $this->updatedDescription($this->description);
        } else {
            // ===== MODO CREACIÓN =====
            $this->isEditMode = false;
            $this->required_date = now()->toDateString();
            $this->items = [];

            if ($this->companies->count() === 1) {
                $this->company_id = $this->companies->first()->id;
            }
        }

        $this->refreshReceivingLocations();
    }

    // =====================================================
    // MÉTODOS DE GUARDADO
    // =====================================================

    /**
     * Guardar como borrador.
     */
    public function saveDraft()
    {
        $this->save('draft');
    }

    /**
     * Enviar a Compras.
     */
    public function submit()
    {
        $this->save('pending');
    }

    /**
     * Método privado para guardar/actualizar la requisición.
     */
    private function save($status = 'draft')
    {
        // Validar campos obligatorios
        $this->validate([
            'company_id'            => 'required|exists:companies,id',
            'receiving_location_id' => 'required|exists:receiving_locations,id',
            'description'           => 'nullable|string|max:500',
        ], [
            'company_id.required'            => 'La compañía es obligatoria.',
            'receiving_location_id.required' => 'La ubicación de recepción es obligatoria.',
        ]);

        // Validar que tenga al menos una partida (RN-003)
        if (empty($this->items)) {
            $this->dispatch('validation-error', message: 'Debe agregar al menos una partida a la requisición (RN-003).');
            return;
        }

        foreach ($this->items as $item) {
            if (! $this->validateItemPayload($item)) {
                $this->dispatch(
                    'validation-error',
                    message: 'Revisa las partidas: cada una debe tener un centro de costo y datos presupuestales válidos para la compañía seleccionada.'
                );
                return;
            }
        }

        if (! $this->receivingLocationBelongsToCompany()) {
            $this->addError('receiving_location_id', 'La ubicación de recepción no pertenece a la compañía seleccionada.');
            $this->dispatch('validation-error', message: 'La ubicación de recepción no pertenece a la compañía seleccionada.');

            return;
        }

        try {
            DB::beginTransaction();

            if ($this->isEditMode) {
                // ===== MODO EDICIÓN =====
                $requisition = Requisition::findOrFail($this->requisitionId);

                // Actualizar datos principales
                $requisition->update([
                    'company_id'            => $this->company_id,
                    'receiving_location_id' => $this->receiving_location_id,
                    'required_date' => now()->toDateString(),
                    'description' => $this->description,
                    'status' => $status === 'pending' ? 'draft' : $status, // Se cambiará después si es pending
                    'updated_by' => Auth::id(),
                ]);

                // Eliminar partidas existentes
                $requisition->items()->delete();

                // Recrear partidas
                foreach ($this->items as $index => $item) {
                    $product = \App\Models\ProductService::find($item['product_id']);
                    RequisitionItem::create([
                        'requisition_id'      => $requisition->id,
                        'product_service_id'  => $item['product_id'],
                        'line_number'         => $index + 1,
                        'product_code'        => $product->code,
                        'description'         => $item['description'],
                        'expense_category_id' => $item['expense_category_id'],
                        'budget_cedula_id'    => $item['budget_cedula_id'],
                        'cost_center_id'      => $item['cost_center_id'],
                        'item_category'       => $product->product_type,
                        'quantity'            => $item['quantity'],
                        'unit'                => $item['unit'],
                        'suggested_vendor_id' => $product->default_vendor_id ?? null,
                        'notes'               => $item['notes'] ?? null,
                    ]);
                }

                $message = "Requisición {$requisition->folio} actualizada correctamente";
            } else {
                // ===== MODO CREACIÓN =====
                $requisition = Requisition::create([
                    'company_id'            => $this->company_id,
                    'receiving_location_id' => $this->receiving_location_id,
                    'folio'                => Requisition::nextFolio(),
                    'requested_by'         => Auth::id(),
                    'required_date'        => now()->toDateString(),
                    'description'          => $this->description,
                    'status'               => 'draft',
                    'created_by'           => Auth::id(),
                ]);

                // Crear partidas
                foreach ($this->items as $index => $item) {
                    $product = \App\Models\ProductService::find($item['product_id']);
                    RequisitionItem::create([
                        'requisition_id'      => $requisition->id,
                        'product_service_id'  => $item['product_id'],
                        'line_number'         => $index + 1,
                        'product_code'        => $product->code,
                        'description'         => $item['description'],
                        'expense_category_id' => $item['expense_category_id'],
                        'budget_cedula_id'    => $item['budget_cedula_id'],
                        'cost_center_id'      => $item['cost_center_id'],
                        'item_category'       => $product->product_type,
                        'quantity'            => $item['quantity'],
                        'unit'                => $item['unit'],
                        'suggested_vendor_id' => $product->default_vendor_id ?? null,
                        'notes'               => $item['notes'] ?? null,
                    ]);
                }

                $message = "Requisición creada con folio: {$requisition->folio}";
            }

            // Si se va a enviar a Compras
            if ($status === 'pending') {
                Log::info('📤 Enviando requisición a Compras desde Livewire', [
                    'requisition_id' => $requisition->id,
                    'folio' => $requisition->folio,
                ]);

                $requisition->refresh();
                $requisition->load('requester', 'items');

                $sent = $requisition->submitToCompras();

                if (!$sent) {
                    throw new \Exception('No se pudo enviar la requisición a Compras.');
                }

                $message = "Requisición {$requisition->folio} enviada a Compras correctamente";
            }

            DB::commit();

            session()->flash('success', $message);
            return redirect()->route('requisitions.index');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('❌ Error al guardar requisición en Livewire', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->dispatch('save-error', message: 'Error al guardar la requisición: ' . $e->getMessage());
        }
    }

    // =====================================================
    // LISTENERS
    // =====================================================

    public function updatedCompanyId($value)
    {
        $userCompanyIds = Auth::user()->companies()->pluck('companies.id')->toArray();

        if ($value && ! in_array($value, $userCompanyIds)) {
            $this->addError('company_id', 'No tienes permiso para usar esta compañía.');
            $this->company_id = null;
        }

        $this->refreshReceivingLocations();
    }

    public function updatedDescription($value)
    {
        $this->descriptionRemainingChars = $this->descriptionMaxLength - strlen($value);
    }

    // =====================================================
    // GESTIÓN DE PARTIDAS
    // =====================================================

    #[Renderless]
    public function addItem($itemData)
    {
        $itemData = $this->enrichItemWithBudgetClassification($itemData);

        if (! $this->validateItemPayload($itemData)) {
            $this->dispatch('item-error', message: 'Faltan campos obligatorios');
            return;
        }

        $this->items[] = [
            'product_id'            => $itemData['product_id'],
            'product_name'          => $itemData['product_name'],
            'description'           => $itemData['description'],
            'quantity'              => $itemData['quantity'],
            'unit'                  => $itemData['unit'],
            'expense_category_id'   => $itemData['expense_category_id'],
            'expense_category_name' => $itemData['expense_category_name'],
            'budget_cedula_id'      => $itemData['budget_cedula_id'],
            'budget_cedula_name'    => $itemData['budget_cedula_name'],
            'cost_center_id'        => $itemData['cost_center_id'],
            'cost_center_name'      => $itemData['cost_center_name'],
            'purchase_type'         => $itemData['purchase_type'],
            'notes'                 => $itemData['notes'] ?? '',
        ];

        $this->dispatch('item-added', message: 'Partida agregada correctamente');
    }

    #[Renderless]
    public function updateItem($index, $itemData)
    {
        if (!isset($this->items[$index])) {
            $this->dispatch('item-error', message: 'Partida no encontrada');
            return;
        }

        $itemData = $this->enrichItemWithBudgetClassification($itemData);

        if (! $this->validateItemPayload($itemData)) {
            $this->dispatch('item-error', message: 'Faltan campos obligatorios');
            return;
        }

        $this->items[$index] = [
            'product_id'            => $itemData['product_id'],
            'product_name'          => $itemData['product_name'],
            'description'           => $itemData['description'],
            'quantity'              => $itemData['quantity'],
            'unit'                  => $itemData['unit'],
            'expense_category_id'   => $itemData['expense_category_id'],
            'expense_category_name' => $itemData['expense_category_name'],
            'budget_cedula_id'      => $itemData['budget_cedula_id'],
            'budget_cedula_name'    => $itemData['budget_cedula_name'],
            'cost_center_id'        => $itemData['cost_center_id'],
            'cost_center_name'      => $itemData['cost_center_name'],
            'purchase_type'         => $itemData['purchase_type'],
            'notes'                 => $itemData['notes'] ?? '',
        ];

        $this->dispatch('item-updated', message: 'Partida actualizada correctamente');
    }

    #[Renderless]
    public function removeItem($index)
    {
        if (isset($this->items[$index])) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
            $this->dispatch('item-removed', message: 'Partida eliminada correctamente');
        }
    }

    public function getItemForEdit($index)
    {
        if (isset($this->items[$index])) {
            $this->editingItemIndex = $index;
            return $this->items[$index];
        }
        return null;
    }

    public function render()
    {
        return view('livewire.requisition-form');
    }

    private function refreshReceivingLocations(): void
    {
        $this->receivingLocations = $this->company_id
            ? ReceivingLocation::active()
                ->portalUnblocked()
                ->forCompany($this->company_id)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'city'])
            : collect();

        if ($this->receiving_location_id && ! $this->receivingLocationBelongsToCompany()) {
            $this->receiving_location_id = null;
        }
    }

    private function receivingLocationBelongsToCompany(): bool
    {
        if (! $this->company_id || ! $this->receiving_location_id) {
            return false;
        }

        return ReceivingLocation::query()
            ->whereKey((int) $this->receiving_location_id)
            ->where('company_id', (int) $this->company_id)
            ->exists();
    }

    private function validateItemPayload(array $itemData): bool
    {
        $itemData = $this->enrichItemWithBudgetClassification($itemData);

        if (empty($itemData['product_id'])
            || empty($itemData['expense_category_id'])
            || empty($itemData['budget_cedula_id'])
            || empty($itemData['cost_center_id'])) {
            return false;
        }

        $validCostCenter = Auth::user()->costCenters()
            ->where('cost_centers.id', (int) $itemData['cost_center_id'])
            ->where('cost_centers.company_id', (int) $this->company_id)
            ->where('cost_centers.status', 'ACTIVO')
            ->whereNull('cost_centers.deleted_at')
            ->wherePivot('is_active', true)
            ->exists();

        if (! $validCostCenter) {
            return false;
        }

        $cedula = BudgetCedula::query()
            ->whereKey((int) $itemData['budget_cedula_id'])
            ->where('expense_category_id', (int) $itemData['expense_category_id'])
            ->first();

        if (! $cedula) {
            return false;
        }

        return app(BudgetCedulaCatalogService::class)->isValidCedulaForContext(
            (int) $itemData['cost_center_id'],
            (int) $itemData['expense_category_id'],
            (int) $itemData['budget_cedula_id'],
            now()->year
        );
    }

    private function enrichItemWithBudgetClassification(array $itemData): array
    {
        if (empty($itemData['product_id'])) {
            return $itemData;
        }

        try {
            $classification = app(ProductBudgetClassificationService::class)
                ->resolveForProduct((int) $itemData['product_id']);
        } catch (\RuntimeException $exception) {
            return $itemData;
        }

        return array_merge($itemData, [
            'expense_category_id' => $classification['expense_category_id'],
            'expense_category_name' => $classification['expense_category_name'],
            'budget_cedula_id' => $classification['budget_cedula_id'],
            'budget_cedula_name' => $classification['budget_cedula_name'],
            'account_name' => $classification['account_name'],
            'subaccount_name' => $classification['subaccount_name'],
        ]);
    }
}
