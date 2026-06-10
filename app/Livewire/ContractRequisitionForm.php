<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractProduct;
use App\Models\ExpenseCategory;
use App\Models\ReceivingLocation;
use App\Models\Requisition;
use App\Models\RequisitionItem;
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

    // Catálogos
    public $companies         = [];
    public $locations         = [];
    public $eligibleContracts = [];
    public $expenseCategories = [];

    // Estado de partida nueva
    public $newItem = [
        'contract_id'            => '',
        'contract_product_id'    => '',
        'quantity'               => 1,
        'cost_center_id'         => '',
        'budget_category_id'     => '',
        'expense_category_id'    => '',
        'notes'                  => '',
    ];
    public $newItemContractProducts = [];
    public $newItemSnapshotPrice    = null;
    public $newItemCurrency         = 'MXN';

    public function mount(): void
    {
        $this->companies         = Company::where('is_active', true)->orderBy('name')->get();
        $this->locations         = ReceivingLocation::orderBy('name')->get();
        $this->expenseCategories = ExpenseCategory::orderBy('name')->get();
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

    public function addItem(): void
    {
        $this->validate([
            'newItem.contract_id'         => ['required', 'integer'],
            'newItem.contract_product_id' => ['required', 'integer'],
            'newItem.quantity'            => ['required', 'numeric', 'min:0.001'],
            'newItem.expense_category_id' => ['required', 'integer'],
        ], [
            'newItem.contract_id.required'         => 'Selecciona un contrato.',
            'newItem.contract_product_id.required' => 'Selecciona un producto.',
            'newItem.quantity.required'            => 'Ingresa la cantidad.',
            'newItem.expense_category_id.required' => 'Selecciona una categoría de gasto.',
        ]);

        $contract = Contract::find($this->newItem['contract_id']);
        if (! $contract || ! $contract->isEligible()) {
            $this->addError('newItem.contract_id', 'El contrato seleccionado ya no está activo o el proveedor fue inactivado.');
            return;
        }

        $cp = ContractProduct::where('id', $this->newItem['contract_product_id'])
            ->where('contract_id', $this->newItem['contract_id'])
            ->first();

        if (! $cp) {
            $this->addError('newItem.contract_product_id', 'El producto no pertenece al contrato seleccionado.');
            return;
        }

        // Aviso si el contrato vence en ≤ 30 días
        $daysLeft = Carbon::today()->diffInDays($contract->end_date, false);

        $this->items[] = [
            'contract_id'            => $contract->id,
            'contract_folio'         => $contract->folio,
            'supplier_name'          => $contract->supplier->company_name,
            'contract_product_id'    => $cp->id,
            'product_name'           => $cp->product->name ?? "Producto #{$cp->product_service_id}",
            'product_service_id'     => $cp->product_service_id,
            'unit_price'             => $cp->unit_price,
            'currency_code'          => $cp->currency_code,
            'unit_of_measure'        => $cp->unit_of_measure,
            'quantity'               => $this->newItem['quantity'],
            'cost_center_id'         => $this->newItem['cost_center_id'],
            'budget_category_id'     => $this->newItem['budget_category_id'],
            'expense_category_id'    => $this->newItem['expense_category_id'],
            'expense_category_name'  => ExpenseCategory::find($this->newItem['expense_category_id'])?->name ?? '—',
            'notes'                  => $this->newItem['notes'],
            'expiry_warning'         => $daysLeft <= 30 ? $contract->end_date->format('d/m/Y') : null,
        ];

        $this->reset(['newItem', 'newItemContractProducts', 'newItemSnapshotPrice', 'newItemCurrency']);
        $this->newItem = ['contract_id' => '', 'contract_product_id' => '', 'quantity' => 1,
                          'cost_center_id' => '', 'budget_category_id' => '', 'expense_category_id' => '', 'notes' => ''];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function submit(): void
    {
        $this->validate([
            'company_id'            => ['required', 'integer', 'exists:companies,id'],
            'required_date'         => ['required', 'date'],
            'receiving_location_id' => ['required', 'integer', 'exists:receiving_locations,id'],
            'items'                 => ['required', 'array', 'min:1'],
        ]);

        // Re-validar elegibilidad de todos los contratos en el submit
        foreach ($this->items as $item) {
            $contract = Contract::find($item['contract_id']);
            if (! $contract || ! $contract->isEligible()) {
                session()->flash('error', "El contrato {$item['contract_folio']} ya no está activo. Revisa las partidas.");
                return;
            }
        }

        DB::transaction(function () {
            $requisition = Requisition::create([
                'folio'                 => Requisition::nextFolio(),
                'company_id'            => $this->company_id,
                'required_date'         => $this->required_date,
                'receiving_location_id' => $this->receiving_location_id,
                'description'           => $this->notes,
                'source_type'           => 'contract',
                'status'                => 'SUBMITTED',
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
                    'unit_price'          => $cp->unit_price,   // snapshot
                    'currency_code'       => $cp->currency_code,
                    'cost_center_id'      => $itemData['cost_center_id'] ?: null,
                    'budget_cedula_id'    => $itemData['budget_category_id'] ?: null,
                    'expense_category_id' => $itemData['expense_category_id'] ?: null,
                    'notes'               => $itemData['notes'] ?? null,
                    'created_by'          => Auth::id(),
                    'updated_by'          => Auth::id(),
                ]);
            }

            // TODO: Generar OCs agrupadas por proveedor
            // (Task 14) app(\App\Services\ContractPurchaseOrderService::class)
            //     ->generateFromRequisition($requisition);

            $this->redirect(route('requisitions.show', $requisition), navigate: true);
        });
    }

    public function render()
    {
        return view('livewire.contract-requisition-form');
    }
}
