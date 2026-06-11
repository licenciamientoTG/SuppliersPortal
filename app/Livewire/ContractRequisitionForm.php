<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractProduct;
use App\Models\ReceivingLocation;
use App\Models\Requisition;
use App\Services\ContractPurchaseOrderService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ContractRequisitionForm extends Component
{
    public $company_id;
    public $required_date;
    public $receiving_location_id;
    public $notes = '';

    public array $items = [];

    // Catálogos de cabecera
    public $companies  = [];
    public $locations  = [];

    // Catálogo de CC (pasado a JS; no se usa en server para las partidas)
    public $costCenterCatalog = [];

    // Contratos y productos del contrato seleccionado (reactivos)
    public $eligibleContracts          = [];
    public $newItemContractProducts    = [];
    public $newItemSnapshotPrice       = null;
    public $newItemCurrency            = 'MXN';

    // Campos del item nuevo que sí necesitan reactividad Livewire
    public $newItem = [
        'contract_id'         => '',
        'contract_product_id' => '',
        'quantity'            => 1,
        'notes'               => '',
    ];

    public function mount(): void
    {
        $this->companies  = Company::where('is_active', true)->orderBy('name')->get();
        $this->locations  = ReceivingLocation::active()->orderBy('name')->get();
        $this->required_date = now()->addDays(7)->format('Y-m-d');

        $this->costCenterCatalog = Auth::user()->costCenters()
            ->select(
                'cost_centers.id',
                'cost_centers.company_id',
                'cost_centers.purchase_type',
                'cost_centers.code',
                'cost_centers.name'
            )
            ->where('cost_centers.status', 'ACTIVO')
            ->whereNull('cost_centers.deleted_at')
            ->wherePivot('is_active', true)
            ->orderBy('cost_centers.code')
            ->get()
            ->map(fn ($cc) => [
                'id'            => (string) $cc->id,
                'company_id'    => (string) $cc->company_id,
                'purchase_type' => $cc->purchase_type instanceof \App\Enum\PurchaseType
                    ? $cc->purchase_type->value
                    : (string) $cc->purchase_type,
                'code'          => $cc->code,
                'name'          => $cc->name,
            ])
            ->values()
            ->toArray();
    }

    public function updatedCompanyId(): void
    {
        $this->eligibleContracts = Contract::eligible()
            ->byCompany((int) $this->company_id)
            ->with('supplier')
            ->get();

        $this->newItem['contract_id']         = '';
        $this->newItem['contract_product_id'] = '';
        $this->newItemContractProducts        = [];
        $this->newItemSnapshotPrice           = null;
    }

    public function updatedNewItemContractId($value): void
    {
        if (! $value) {
            $this->newItemContractProducts = [];
            $this->newItemSnapshotPrice    = null;
            return;
        }

        $this->newItemContractProducts = ContractProduct::where('contract_id', $value)
            ->with('product')
            ->get();
        $this->newItem['contract_product_id'] = '';
        $this->newItemSnapshotPrice           = null;
    }

    public function updatedNewItemContractProductId($value): void
    {
        if (! $value) {
            $this->newItemSnapshotPrice = null;
            return;
        }

        $cp = ContractProduct::find($value);
        $this->newItemSnapshotPrice = $cp?->unit_price;
        $this->newItemCurrency      = $cp?->currency_code ?? 'MXN';
    }

    /**
     * Llamado desde JS con los campos que no tienen wire:model (CC, categ., cédula).
     */
    public function addItem(array $extra): void
    {
        $this->validate([
            'newItem.contract_id'         => ['required', 'integer'],
            'newItem.contract_product_id' => ['required', 'integer'],
            'newItem.quantity'            => ['required', 'numeric', 'min:0.001'],
        ], [
            'newItem.contract_id.required'         => 'Selecciona un contrato.',
            'newItem.contract_product_id.required' => 'Selecciona un producto.',
            'newItem.quantity.required'            => 'Ingresa la cantidad.',
        ]);

        if (empty($extra['cost_center_id'])) {
            $this->dispatch('toast', type: 'error', message: 'Selecciona un Centro de Costo.');
            return;
        }
        if (empty($extra['expense_category_id'])) {
            $this->dispatch('toast', type: 'error', message: 'Selecciona una Categoría de Gasto.');
            return;
        }
        if (empty($extra['budget_cedula_id'])) {
            $this->dispatch('toast', type: 'error', message: 'Selecciona una Subcategoría Presupuestal.');
            return;
        }

        $contract = Contract::find($this->newItem['contract_id']);
        if (! $contract || ! $contract->isEligible()) {
            $this->dispatch('toast', type: 'error', message: 'El contrato seleccionado ya no está activo.');
            return;
        }

        $cp = ContractProduct::where('id', $this->newItem['contract_product_id'])
            ->where('contract_id', $this->newItem['contract_id'])
            ->first();

        if (! $cp) {
            $this->dispatch('toast', type: 'error', message: 'El producto no pertenece al contrato seleccionado.');
            return;
        }

        $daysLeft = Carbon::today()->diffInDays($contract->end_date, false);

        $this->items[] = [
            'contract_id'           => $contract->id,
            'contract_folio'        => $contract->folio,
            'supplier_name'         => $contract->supplier->company_name,
            'contract_product_id'   => $cp->id,
            'product_name'          => $cp->product->name ?? "Producto #{$cp->product_service_id}",
            'product_service_id'    => $cp->product_service_id,
            'unit_price'            => $cp->unit_price,
            'currency_code'         => $cp->currency_code,
            'unit_of_measure'       => $cp->unit_of_measure,
            'quantity'              => $this->newItem['quantity'],
            'notes'                 => $this->newItem['notes'] ?? '',
            'purchase_type'         => $extra['purchase_type'] ?? '',
            'cost_center_id'        => $extra['cost_center_id'],
            'cost_center_name'      => $extra['cost_center_name'] ?? '',
            'expense_category_id'   => $extra['expense_category_id'],
            'expense_category_name' => $extra['expense_category_name'] ?? '',
            'budget_cedula_id'      => $extra['budget_cedula_id'],
            'budget_cedula_name'    => $extra['budget_cedula_name'] ?? '',
            'expiry_warning'        => $daysLeft <= 30 ? $contract->end_date->format('d/m/Y') : null,
        ];

        $this->newItem = [
            'contract_id'         => '',
            'contract_product_id' => '',
            'quantity'            => 1,
            'notes'               => '',
        ];
        $this->newItemContractProducts = [];
        $this->newItemSnapshotPrice    = null;
        $this->newItemCurrency         = 'MXN';

        if ($daysLeft <= 30) {
            $this->dispatch('toast', type: 'warning',
                message: "Partida agregada. El contrato {$contract->folio} vence el {$contract->end_date->format('d/m/Y')}.");
        } else {
            $this->dispatch('toast', type: 'success', message: 'Partida agregada correctamente.');
        }
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function saveDraft(): void
    {
        $this->validate([
            'company_id'            => ['required', 'integer', 'exists:companies,id'],
            'required_date'         => ['required', 'date'],
            'receiving_location_id' => ['required', 'integer', 'exists:receiving_locations,id'],
            'items'                 => ['required', 'array', 'min:1'],
        ]);

        $requisition = DB::transaction(fn () => $this->persistRequisition('DRAFT'));

        session()->flash('success', "Borrador guardado. Folio: {$requisition->folio}");
        $this->redirect(route('requisitions.show', $requisition), navigate: true);
    }

    public function submitAndCreateOCDs(): void
    {
        $this->validate([
            'company_id'            => ['required', 'integer', 'exists:companies,id'],
            'required_date'         => ['required', 'date'],
            'receiving_location_id' => ['required', 'integer', 'exists:receiving_locations,id'],
            'items'                 => ['required', 'array', 'min:1'],
        ]);

        foreach ($this->items as $idx => $item) {
            if (empty($item['cost_center_id'])) {
                $num = $idx + 1;
                $this->dispatch('toast', type: 'error',
                    message: "La partida #{$num} ({$item['product_name']}) no tiene Centro de Costo.");
                return;
            }
        }

        foreach ($this->items as $item) {
            $contract = Contract::find($item['contract_id']);
            if (! $contract || ! $contract->isEligible()) {
                $this->dispatch('toast', type: 'error',
                    message: "El contrato {$item['contract_folio']} ya no está activo.");
                return;
            }
        }

        try {
            $result = DB::transaction(function () {
                $req  = $this->persistRequisition('SUBMITTED');
                $ocds = app(ContractPurchaseOrderService::class)->generateFromRequisition($req);
                return ['requisition' => $req, 'ocds' => $ocds];
            });

            $ocdCount = $result['ocds']->count();
            session()->flash('success',
                "Requisición {$result['requisition']->folio} enviada. Se generaron {$ocdCount} OCD(s) en aprobación.");

            $this->redirect(route('requisitions.show', $result['requisition']), navigate: true);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    // ── Helpers privados ──────────────────────────────────────────────────

    private function persistRequisition(string $status): Requisition
    {
        $requisition = Requisition::create([
            'folio'                 => Requisition::nextFolio(),
            'company_id'            => $this->company_id,
            'required_date'         => $this->required_date,
            'receiving_location_id' => $this->receiving_location_id,
            'description'           => $this->notes,
            'source_type'           => 'contract',
            'status'                => $status,
            'created_by'            => Auth::id(),
            'updated_by'            => Auth::id(),
            'requested_by'          => Auth::id(),
        ]);

        foreach ($this->items as $itemData) {
            $cp = ContractProduct::find($itemData['contract_product_id']);

            $requisition->items()->create([
                'product_service_id'  => $itemData['product_service_id'],
                'description'         => $itemData['product_name'],
                'quantity'            => $itemData['quantity'],
                'unit'                => $itemData['unit_of_measure'],
                'contract_id'         => $itemData['contract_id'],
                'contract_product_id' => $itemData['contract_product_id'],
                'unit_price'          => $cp->unit_price,
                'currency_code'       => $cp->currency_code,
                'cost_center_id'      => $itemData['cost_center_id'] ?: null,
                'expense_category_id' => $itemData['expense_category_id'] ?: null,
                'budget_cedula_id'    => $itemData['budget_cedula_id'] ?: null,
                'notes'               => $itemData['notes'] ?? null,
                'created_by'          => Auth::id(),
                'updated_by'          => Auth::id(),
            ]);
        }

        return $requisition;
    }

    public function render()
    {
        return view('livewire.contract-requisition-form');
    }
}
