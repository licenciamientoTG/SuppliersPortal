<?php

namespace App\Livewire\Rfq\Board;

use App\Exceptions\Rfq\DuplicateSupplierRfcException;
use App\Models\QuotationGroup;
use App\Models\Requisition;
use App\Models\Supplier;
use App\Services\Rfq\ManualQuoteService;
use App\Services\Rfq\PriceMemoryService;
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

    #[On('open-manual-quote')]
    public function open(int $groupId): void
    {
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
        }

        $this->quotationDate = now()->format('Y-m-d');
        $this->validityDays = 30;
        $this->attachment = null;
        $this->resetErrorBag();
        $this->show = true;
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
            $service->save(
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
        session()->flash('success', "✅ Cotización de {$supplier->company_name} capturada para el grupo {$group->name}.");
    }

    public function render()
    {
        return view('livewire.rfq.board.manual-quote-modal');
    }
}
