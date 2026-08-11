<?php

namespace App\Livewire\Rfq\Board;

use App\Exceptions\Rfq\DuplicateSupplierRfcException;
use App\Models\QuotationGroup;
use App\Models\Requisition;
use App\Models\Rfq;
use App\Models\Supplier;
use App\Services\Rfq\ManualQuoteService;
use App\Services\Rfq\PriceMemoryService;
use App\Services\Rfq\ProductPurchaseHistoryService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Modal de captura de precio conocido del tablero: la cotización manual del
 * wizard promovida a acción principal, con pre-llenado de memoria de precios.
 */
class ManualQuoteModal extends Component
{
    use WithFileUploads;

    public Requisition $requisition;

    public bool $show = false;

    public ?int $groupId = null;

    public $supplierId = null;

    public array $newSupplier = [
        'company_name' => '',
        'rfc' => '',
        'postal_code' => '',
        'contact_person' => '',
        'email' => '',
        'phone_number' => '',
    ];

    public array $items = [];

    /** Referencias de memoria de precios por partida (solo lectura, para la vista) */
    public array $priceReferences = [];

    public $quotationDate = null;

    public int $validityDays = 30;

    public $attachment = null;

    public ?int $historyItemId = null;

    public array $purchaseHistory = [];

    #[On('open-manual-quote')]
    public function open(int $groupId): void
    {
        if (! $this->requisition->validated_at) {
            session()->flash('error', 'Firma la validación técnica antes de capturar un precio conocido.');

            return;
        }

        $existing = Rfq::where('requisition_id', $this->requisition->id)
            ->where('quotation_group_id', $groupId)
            ->active()
            ->latest('id')
            ->first();

        if ($existing && $existing->source !== 'external' && $existing->status !== 'DRAFT') {
            session()->flash('error', 'Este grupo ya tiene una RFQ enviada y no puede cambiar a compra directa.');

            return;
        }

        $group = QuotationGroup::with('items')->findOrFail($groupId);

        $this->groupId = $group->id;
        $this->supplierId = null;
        $this->newSupplier = [
            'company_name' => '',
            'rfc' => '',
            'postal_code' => '',
            'contact_person' => '',
            'email' => '',
            'phone_number' => '',
        ];

        $this->priceReferences = app(PriceMemoryService::class)->latestForItems($group->items);

        $this->items = [];
        foreach ($group->items as $item) {
            $reference = $this->priceReferences[$item->id] ?? null;

            $this->items[$item->id] = [
                'not_available' => false,
                'unit_price' => $reference['unit_price'] ?? null,
                'iva_rate' => $reference['iva_rate'] ?? 16,
                'currency' => $reference['currency'] ?? 'MXN',
                'delivery_days' => $reference['delivery_days'] ?? null,
                'payment_terms' => null,
                'warranty_terms' => null,
                'brand' => null,
                'model' => null,
                'specifications' => null,
            ];
        }

        // Si toda la memoria apunta al mismo proveedor, pre-seleccionarlo
        $supplierIds = collect($this->priceReferences)->pluck('supplier_id')->unique();
        if ($supplierIds->count() === 1 && count($this->priceReferences) === count($this->items)) {
            $this->supplierId = $supplierIds->first();
            $this->applySupplierDefaults((int) $this->supplierId);
        }

        $this->quotationDate = now()->format('Y-m-d');
        $this->validityDays = 30;
        $this->attachment = null;
        $this->resetErrorBag();
        $this->show = true;
    }

    public function updatedSupplierId($supplierId): void
    {
        if ($supplierId) {
            $this->applySupplierDefaults((int) $supplierId, true);
        }
    }

    public function openPurchaseHistory(int $itemId): void
    {
        $item = $this->group?->items->firstWhere('id', $itemId);
        if (! $item?->product_service_id) {
            $this->purchaseHistory = [];
            $this->historyItemId = $itemId;

            return;
        }

        $this->historyItemId = $itemId;
        $this->purchaseHistory = app(ProductPurchaseHistoryService::class)
            ->latestForProduct((int) $item->product_service_id)
            ->values()
            ->all();
    }

    public function closePurchaseHistory(): void
    {
        $this->historyItemId = null;
        $this->purchaseHistory = [];
    }

    public function applyPurchaseHistory(int $historyId): void
    {
        $reference = collect($this->purchaseHistory)->firstWhere('id', $historyId);
        if (! $reference || ! $this->historyItemId) {
            return;
        }

        $itemId = $this->historyItemId;
        $this->items[$itemId] = array_merge($this->items[$itemId] ?? [], [
            'unit_price' => $reference['unit_price'],
            'iva_rate' => $reference['iva_rate'],
            'currency' => $reference['currency'],
            'delivery_days' => $reference['delivery_days'],
            'payment_terms' => $reference['payment_terms'],
            'not_available' => false,
        ]);
        $this->supplierId = $reference['supplier_id'];
        $this->applySupplierDefaults((int) $reference['supplier_id'], false);
        $this->closePurchaseHistory();
    }

    private function applySupplierDefaults(int $supplierId, bool $overwrite = false): void
    {
        $supplier = Supplier::find($supplierId);
        if (! $supplier) {
            return;
        }

        foreach ($this->items as $itemId => $item) {
            $this->items[$itemId]['currency'] = $overwrite || empty($item['currency'])
                ? ($supplier->currency ?: 'MXN')
                : $item['currency'];
            $this->items[$itemId]['payment_terms'] = $overwrite || empty($item['payment_terms'])
                ? $supplier->default_payment_terms
                : $item['payment_terms'];
        }
    }

    public function close(): void
    {
        $this->show = false;
    }

    #[Computed]
    public function group(): ?QuotationGroup
    {
        return $this->groupId
            ? QuotationGroup::with('items.productService')->find($this->groupId)
            : null;
    }

    #[Computed]
    public function selectableSuppliers()
    {
        return Supplier::query()
            ->where(function ($q) {
                $q->where('approval_status', 'approved')->where('is_active', true);
            })
            ->orWhere('is_external', true)
            ->orderBy('company_name')
            ->get();
    }

    public function save(): void
    {
        if (! $this->requisition->validated_at) {
            session()->flash('error', 'Firma la validación técnica antes de capturar un precio conocido.');

            return;
        }

        $rules = array_merge([
            'quotationDate' => 'required|date',
            'validityDays' => 'required|integer|min:1|max:365',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], ManualQuoteService::itemRules('items'));

        if (! $this->supplierId) {
            $rules = array_merge($rules, ManualQuoteService::newSupplierRules('newSupplier'));
        }

        $messages = [
            'newSupplier.email.unique' => 'Ya existe un proveedor registrado con este correo electrónico.',
        ];

        $this->validate($rules, $messages);

        $service = app(ManualQuoteService::class);

        try {
            $supplier = $service->resolveSupplier(
                $this->supplierId ? (int) $this->supplierId : null,
                $this->newSupplier
            );
        } catch (DuplicateSupplierRfcException $e) {
            $this->addError('newSupplier.rfc', $e->getMessage());

            return;
        }

        $group = QuotationGroup::with('items')->findOrFail($this->groupId);

        try {
            $result = $service->save(
                $this->requisition,
                $group,
                $supplier,
                $this->items,
                $this->quotationDate,
                (int) $this->validityDays,
                $this->attachment,
                Auth::id()
            );
        } catch (\Exception $e) {
            session()->flash('error', 'No se pudo guardar la cotización manual: '.$e->getMessage());

            return;
        }

        $this->show = false;
        $this->dispatch('board-refresh');
        if (! $result['summary']) {
            session()->flash('error', 'Precio conocido capturado, pero no se pudo enviar a autorización: '.$result['award_error']);

            return;
        }
        session()->flash('success', "Compra directa de {$supplier->company_name} enviada a validación presupuestal y autorización.");
    }

    public function render()
    {
        return view('livewire.rfq.board.manual-quote-modal');
    }
}
