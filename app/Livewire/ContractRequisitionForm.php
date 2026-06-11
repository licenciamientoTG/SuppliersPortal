<?php

namespace App\Livewire;

use App\Enum\RequisitionStatus;
use App\Models\BudgetCedula;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractProduct;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\ProductService;
use App\Models\ReceivingLocation;
use App\Models\Requisition;
use App\Services\BudgetAllocationService;
use App\Services\BudgetCedulaCatalogService;
use App\Services\ContractPurchaseOrderService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ContractRequisitionForm extends Component
{
    public $company_id;
    public $required_date;
    public $receiving_location_id;
    public $notes = '';

    public array $items = [];

    public $companies = [];
    public $locations = [];
    public $eligibleContracts = [];
    public $availableCostCenters = [];
    public $expenseCategories = [];
    public $availableBudgetCedulas = [];
    public int $fiscalYear;

    public $newItem = [
        'contract_id' => '',
        'contract_product_id' => '',
        'quantity' => 1,
        'cost_center_id' => '',
        'budget_cedula_id' => '',
        'expense_category_id' => '',
        'notes' => '',
    ];

    public $newItemContractProducts = [];
    public $newItemSnapshotPrice = null;
    public $newItemCurrency = 'MXN';

    public function mount(): void
    {
        $this->fiscalYear = (int) now()->year;
        $this->required_date = now()->addDay()->toDateString();
        $this->companies = Company::where('is_active', true)->orderBy('name')->get();
        $this->locations = ReceivingLocation::active()->orderBy('name')->get();
        $this->expenseCategories = ExpenseCategory::orderBy('name')->get();
    }

    public function updatedCompanyId(): void
    {
        $user = Auth::user();

        $this->eligibleContracts = Contract::eligible()
            ->byCompany((int) $this->company_id)
            ->with('supplier')
            ->get();

        $this->availableCostCenters = $user
            ? $user->costCenters()
                ->where('cost_centers.company_id', (int) $this->company_id)
                ->where('cost_centers.status', 'ACTIVO')
                ->whereNull('cost_centers.deleted_at')
                ->wherePivot('is_active', true)
                ->orderBy('cost_centers.name')
                ->get([
                    'cost_centers.id',
                    'cost_centers.code',
                    'cost_centers.name',
                    'cost_centers.company_id',
                    'cost_centers.budget_type',
                ])
            : collect();

        $this->resetNewItemState();
    }

    public function updatedNewItemContractId($value): void
    {
        if (! $value) {
            $this->newItemContractProducts = [];
            $this->newItemSnapshotPrice = null;

            return;
        }

        $this->newItemContractProducts = ContractProduct::where('contract_id', $value)
            ->with('product')
            ->get();

        $this->newItem['contract_product_id'] = '';
        $this->newItemSnapshotPrice = null;
    }

    public function updatedNewItemContractProductId($value): void
    {
        if (! $value) {
            $this->newItemSnapshotPrice = null;

            return;
        }

        $cp = ContractProduct::find($value);
        $this->newItemSnapshotPrice = $cp?->unit_price;
        $this->newItemCurrency = $cp?->currency_code ?? 'MXN';
    }

    public function updatedNewItemCostCenterId(): void
    {
        $this->refreshBudgetCedulas();
    }

    public function updatedNewItemExpenseCategoryId(): void
    {
        $this->refreshBudgetCedulas();
    }

    public function addItem(): void
    {
        $this->validate([
            'newItem.contract_id' => ['required', 'integer'],
            'newItem.contract_product_id' => ['required', 'integer'],
            'newItem.quantity' => ['required', 'numeric', 'min:0.001'],
            'newItem.cost_center_id' => ['required', 'integer'],
            'newItem.expense_category_id' => ['required', 'integer'],
            'newItem.budget_cedula_id' => ['required', 'integer'],
        ], [
            'newItem.contract_id.required' => 'Selecciona un contrato.',
            'newItem.contract_product_id.required' => 'Selecciona un producto.',
            'newItem.quantity.required' => 'Ingresa la cantidad.',
            'newItem.cost_center_id.required' => 'Selecciona un centro de costo.',
            'newItem.expense_category_id.required' => 'Selecciona una categoria de gasto.',
            'newItem.budget_cedula_id.required' => 'Selecciona una cedula presupuestal.',
        ]);

        $contract = Contract::find($this->newItem['contract_id']);
        if (! $contract || ! $contract->isEligible()) {
            $this->addError('newItem.contract_id', 'El contrato seleccionado ya no esta activo o el proveedor fue inactivado.');

            return;
        }

        $cp = ContractProduct::where('id', $this->newItem['contract_product_id'])
            ->where('contract_id', $this->newItem['contract_id'])
            ->with('product')
            ->first();

        if (! $cp) {
            $this->addError('newItem.contract_product_id', 'El producto no pertenece al contrato seleccionado.');

            return;
        }

        $costCenter = CostCenter::find($this->newItem['cost_center_id']);
        $budgetCedula = BudgetCedula::find($this->newItem['budget_cedula_id']);

        if (! $costCenter) {
            $this->addError('newItem.cost_center_id', 'El centro de costo seleccionado no existe.');

            return;
        }

        if (! $budgetCedula || (int) $budgetCedula->expense_category_id !== (int) $this->newItem['expense_category_id']) {
            $this->addError('newItem.budget_cedula_id', 'La cedula presupuestal no corresponde a la categoria de gasto seleccionada.');

            return;
        }

        $daysLeft = Carbon::today()->diffInDays($contract->end_date, false);
        $product = $cp->product;

        $this->items[] = [
            'contract_id' => $contract->id,
            'contract_folio' => $contract->folio,
            'supplier_id' => $contract->supplier_id,
            'supplier_name' => $contract->supplier->company_name,
            'contract_product_id' => $cp->id,
            'product_name' => $this->resolveProductDisplayName($product, $cp),
            'product_service_id' => $cp->product_service_id,
            'description' => $this->resolveProductDescription($product, $cp),
            'product_code' => $product?->code,
            'item_category' => $product?->product_type,
            'unit_price' => (float) $cp->unit_price,
            'currency_code' => $cp->currency_code,
            'unit_of_measure' => $cp->unit_of_measure,
            'quantity' => (float) $this->newItem['quantity'],
            'cost_center_id' => (int) $this->newItem['cost_center_id'],
            'cost_center_name' => trim(($costCenter->code ? "{$costCenter->code} - " : '').$costCenter->name),
            'budget_cedula_id' => (int) $this->newItem['budget_cedula_id'],
            'budget_cedula_name' => $budgetCedula->name,
            'expense_category_id' => (int) $this->newItem['expense_category_id'],
            'expense_category_name' => ExpenseCategory::find($this->newItem['expense_category_id'])?->name ?? '-',
            'notes' => $this->newItem['notes'],
            'expiry_warning' => $daysLeft <= 30 ? $contract->end_date->format('d/m/Y') : null,
        ];

        $this->resetNewItemState();
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function submit(): void
    {
        $this->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'required_date' => ['required', 'date', 'after_or_equal:today'],
            'receiving_location_id' => ['required', 'integer', 'exists:receiving_locations,id'],
            'items' => ['required', 'array', 'min:1'],
        ]);

        try {
            DB::transaction(function () {
                $validatedItems = $this->validateSubmissionItems();

                $requisition = Requisition::create([
                    'folio' => Requisition::nextFolio(),
                    'company_id' => $this->company_id,
                    'required_date' => $this->required_date,
                    'receiving_location_id' => $this->receiving_location_id,
                    'description' => $this->notes,
                    'source_type' => 'contract',
                    'status' => RequisitionStatus::COMPLETED->value,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                    'requested_by' => Auth::id(),
                ]);

                foreach ($validatedItems as $itemData) {
                    $requisition->items()->create([
                        'product_service_id' => $itemData['product_service_id'],
                        'description' => $itemData['description'],
                        'item_category' => $itemData['item_category'],
                        'product_code' => $itemData['product_code'],
                        'quantity' => $itemData['quantity'],
                        'unit' => $itemData['unit_of_measure'],
                        'suggested_vendor_id' => $itemData['suggested_vendor_id'],
                        'contract_id' => $itemData['contract_id'],
                        'contract_product_id' => $itemData['contract_product_id'],
                        'unit_price' => $itemData['unit_price'],
                        'currency_code' => $itemData['currency_code'],
                        'cost_center_id' => $itemData['cost_center_id'],
                        'budget_cedula_id' => $itemData['budget_cedula_id'],
                        'expense_category_id' => $itemData['expense_category_id'],
                        'notes' => $itemData['notes'] ?? null,
                    ]);
                }

                $purchaseOrders = app(ContractPurchaseOrderService::class)->generateFromRequisition($requisition);

                $successMessage = "Requisicion {$requisition->folio} generada correctamente.";

                if ($purchaseOrders->isNotEmpty()) {
                    $successMessage .= ' ';
                    $successMessage .= $purchaseOrders->count() === 1
                        ? "Se emitio la OC {$purchaseOrders->first()->folio}."
                        : "Se emitieron {$purchaseOrders->count()} ordenes de compra.";
                }

                session()->flash('status', $successMessage);
                $this->redirectRoute('contracts.requisition.create');
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            Log::error('Contract requisition runtime failure.', [
                'company_id' => $this->company_id,
                'receiving_location_id' => $this->receiving_location_id,
                'items_count' => count($this->items),
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            throw ValidationException::withMessages([
                'items' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            Log::error('Contract requisition submission failed.', [
                'company_id' => $this->company_id,
                'receiving_location_id' => $this->receiving_location_id,
                'items_count' => count($this->items),
                'exception' => $exception,
            ]);

            throw ValidationException::withMessages([
                'items' => 'No se pudo generar la requisicion y la OC. Verifica las migraciones recientes de compras por contrato y vuelve a intentar.',
            ]);
        }
    }

    protected function refreshBudgetCedulas(): void
    {
        $this->availableBudgetCedulas = [];
        $this->newItem['budget_cedula_id'] = '';

        $costCenterId = (int) ($this->newItem['cost_center_id'] ?? 0);
        $expenseCategoryId = (int) ($this->newItem['expense_category_id'] ?? 0);

        if (! $costCenterId || ! $expenseCategoryId) {
            return;
        }

        $this->availableBudgetCedulas = app(BudgetCedulaCatalogService::class)
            ->getValidCedulas($costCenterId, $expenseCategoryId, $this->fiscalYear);
    }

    protected function validateSubmissionItems(): array
    {
        $user = Auth::user();
        $budgetAllocationService = app(BudgetAllocationService::class);
        $budgetCedulaCatalogService = app(BudgetCedulaCatalogService::class);

        if (! $user) {
            throw ValidationException::withMessages([
                'items' => 'Debes iniciar sesion para crear una requisicion por contrato.',
            ]);
        }

        $this->validateCurrencyConsistency();

        $validatedItems = [];
        $month = (int) now()->month;

        foreach ($this->items as $index => $item) {
            $contract = Contract::with('supplier')->find($item['contract_id']);
            $contractProduct = ContractProduct::with('product')->find($item['contract_product_id']);
            $costCenter = CostCenter::find($item['cost_center_id']);
            $budgetCedula = BudgetCedula::find($item['budget_cedula_id']);
            $product = ProductService::find($item['product_service_id']);

            if (! $contract || ! $contract->isEligible()) {
                throw ValidationException::withMessages([
                    'items' => "El contrato {$item['contract_folio']} ya no esta activo o el proveedor fue inactivado.",
                ]);
            }

            if (! $contractProduct || (int) $contractProduct->contract_id !== (int) $contract->id) {
                throw ValidationException::withMessages([
                    'items' => 'La partida '.($index + 1).' ya no tiene un producto de contrato valido.',
                ]);
            }

            if (! $product) {
                throw ValidationException::withMessages([
                    'items' => 'La partida '.($index + 1).' no tiene un producto valido en el catalogo.',
                ]);
            }

            if (! $costCenter || (int) $costCenter->company_id !== (int) $this->company_id) {
                throw ValidationException::withMessages([
                    'items' => 'La partida '.($index + 1).' tiene un centro de costo invalido para la empresa seleccionada.',
                ]);
            }

            $assignedToUser = $user->costCenters()
                ->where('cost_centers.id', $costCenter->id)
                ->where('cost_centers.status', 'ACTIVO')
                ->whereNull('cost_centers.deleted_at')
                ->wherePivot('is_active', true)
                ->exists();

            if (! $assignedToUser) {
                throw ValidationException::withMessages([
                    'items' => 'La partida '.($index + 1).' usa un centro de costo no asignado a tu usuario.',
                ]);
            }

            if (! $costCenter->hasAnnualBudget($this->fiscalYear)) {
                throw ValidationException::withMessages([
                    'items' => 'La partida '.($index + 1)." usa un centro de costo sin presupuesto configurado para {$this->fiscalYear}.",
                ]);
            }

            if (! $budgetCedula || (int) $budgetCedula->expense_category_id !== (int) $item['expense_category_id']) {
                throw ValidationException::withMessages([
                    'items' => 'La partida '.($index + 1).' tiene una cedula presupuestal que no corresponde a la categoria seleccionada.',
                ]);
            }

            if (! $budgetCedulaCatalogService->isValidCedulaForContext(
                (int) $costCenter->id,
                (int) $item['expense_category_id'],
                (int) $budgetCedula->id,
                $this->fiscalYear
            )) {
                throw ValidationException::withMessages([
                    'items' => 'La partida '.($index + 1).' usa una cedula presupuestal no permitida para ese centro de costo.',
                ]);
            }

            $lineSubtotal = round((float) $contractProduct->unit_price * (float) $item['quantity'], 2);
            $lineTotal = round($lineSubtotal * 1.16, 2);

            $budgetCheck = $budgetAllocationService->checkAvailability(
                (int) $costCenter->id,
                $this->fiscalYear,
                $month,
                (int) $item['expense_category_id'],
                $lineTotal,
                (int) $budgetCedula->id
            );

            if (! ($budgetCheck['available'] ?? false)) {
                throw ValidationException::withMessages([
                    'items' => 'La partida '.($index + 1).' no tiene presupuesto suficiente. '.($budgetCheck['message'] ?? ''),
                ]);
            }

            $validatedItems[] = array_merge($item, [
                'supplier_id' => $contract->supplier_id,
                'unit_price' => (float) $contractProduct->unit_price,
                'currency_code' => $contractProduct->currency_code,
                'unit_of_measure' => $contractProduct->unit_of_measure,
                'product_name' => $this->resolveProductDisplayName($product, $contractProduct),
                'description' => $this->resolveProductDescription($product, $contractProduct),
                'product_code' => $product->code,
                'item_category' => $product->product_type,
                'budget_cedula_id' => (int) $budgetCedula->id,
                'suggested_vendor_id' => $product->default_vendor_id,
            ]);
        }

        return $validatedItems;
    }

    protected function validateCurrencyConsistency(): void
    {
        $currenciesBySupplier = collect($this->items)
            ->groupBy('supplier_id')
            ->map(fn ($items) => collect($items)->pluck('currency_code')->filter()->unique()->values());

        $hasMixedCurrency = $currenciesBySupplier->contains(fn ($currencies) => $currencies->count() > 1);

        if ($hasMixedCurrency) {
            throw ValidationException::withMessages([
                'items' => 'No se permite mezclar monedas distintas para el mismo proveedor dentro de una requisicion por contrato.',
            ]);
        }
    }

    protected function resetNewItemState(): void
    {
        $this->reset(['newItem', 'newItemContractProducts', 'newItemSnapshotPrice', 'newItemCurrency', 'availableBudgetCedulas']);
        $this->newItem = [
            'contract_id' => '',
            'contract_product_id' => '',
            'quantity' => 1,
            'cost_center_id' => '',
            'budget_cedula_id' => '',
            'expense_category_id' => '',
            'notes' => '',
        ];
        $this->newItemCurrency = 'MXN';
    }

    protected function resolveProductDisplayName(?ProductService $product, ContractProduct $contractProduct): string
    {
        $displayName = $product?->getDisplayName();

        return is_string($displayName) && trim($displayName) !== ''
            ? trim($displayName)
            : "Producto #{$contractProduct->product_service_id}";
    }

    protected function resolveProductDescription(?ProductService $product, ContractProduct $contractProduct): string
    {
        $description = $product?->getRequisitionDescription();

        return is_string($description) && trim($description) !== ''
            ? trim($description)
            : $this->resolveProductDisplayName($product, $contractProduct);
    }

    public function render()
    {
        return view('livewire.contract-requisition-form');
    }
}
