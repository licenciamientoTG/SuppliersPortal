<?php

namespace App\Livewire\Rfq;

use App\Exceptions\Rfq\IncompleteValidationException;
use App\Models\Requisition;
use App\Models\Rfq;
use App\Services\Rfq\PriceMemoryService;
use App\Services\Rfq\QuotationGroupService;
use App\Services\Rfq\RequisitionValidationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Tablero de cotización de dos fases (Preparación / Seguimiento).
 *
 * Alternativa en beta al wizard de 5 pasos: cada grupo de cotización avanza
 * por su propio ciclo de vida en tarjetas independientes (GroupCard).
 */
class QuotationBoard extends Component
{
    public Requisition $requisition;

    // Banner de validación técnica
    public $validationData = [];

    public bool $showValidationDetail = false;

    // Selección de partidas para agrupar
    public array $selectedItemIds = [];

    public string $newGroupName = '';

    public function mount(Requisition $requisition)
    {
        $this->requisition = $requisition->load([
            'requester',
            'company',
            'department',
            'items.productService',
            'items.expenseCategory',
        ]);

        $this->validationData = [
            'specs_clear' => (bool) $requisition->validation_specs_clear,
            'time_feasible' => (bool) $requisition->validation_time_feasible,
            'alternatives_evaluated' => (bool) $requisition->validation_alternatives_evaluated,
            'purchasing_notes' => $requisition->purchasing_validation_notes,
        ];

        $this->showValidationDetail = ! $this->isValidated();
    }

    public function isValidated(): bool
    {
        return $this->requisition->validated_at !== null;
    }

    /**
     * Firma la validación técnica (mismo efecto que el paso 1 del wizard).
     */
    public function signValidation()
    {
        try {
            app(RequisitionValidationService::class)->sign(
                $this->requisition,
                [
                    'specs_clear' => (bool) ($this->validationData['specs_clear'] ?? false),
                    'time_feasible' => (bool) ($this->validationData['time_feasible'] ?? false),
                    'alternatives_evaluated' => (bool) ($this->validationData['alternatives_evaluated'] ?? false),
                ],
                $this->validationData['purchasing_notes'] ?? null,
                Auth::id()
            );

            $this->requisition->refresh();
            $this->showValidationDetail = false;

            session()->flash('success', '✅ Requisición validada. Ya puedes enviar cotizaciones y adjudicar.');
        } catch (IncompleteValidationException $e) {
            session()->flash('error', $e->getMessage());
        } catch (\Exception $e) {
            session()->flash('error', 'Error al validar la requisición: '.$e->getMessage());
        }
    }

    // =========================================================
    //  Agrupación de partidas (checkboxes y drag & drop)
    // =========================================================

    #[Computed]
    public function unassignedItems()
    {
        return $this->requisition->items()
            ->whereDoesntHave('quotationGroups', fn ($q) => $q->where('status', 'ACTIVE'))
            ->with('productService', 'expenseCategory')
            ->get();
    }

    #[Computed]
    public function groups()
    {
        return $this->requisition->quotationGroups()
            ->active()
            ->with('items.productService')
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function groupHints(): array
    {
        $memory = app(PriceMemoryService::class);

        return $this->groups
            ->mapWithKeys(fn ($group) => [$group->id => $memory->groupHint($group)])
            ->all();
    }

    /**
     * IDs de grupos que ya tienen RFQ en circulación (enviada o posterior):
     * pasan de la fase Preparación a Seguimiento.
     */
    #[Computed]
    public function followUpGroupIds(): array
    {
        return Rfq::where('requisition_id', $this->requisition->id)
            ->whereNotNull('quotation_group_id')
            ->whereNotIn('status', ['CANCELLED', 'REJECTED', 'DRAFT'])
            ->pluck('quotation_group_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function createGroupWithSelection()
    {
        $itemIds = $this->normalizedSelection();

        if (empty($itemIds)) {
            session()->flash('error', 'Selecciona al menos una partida para crear un grupo.');

            return;
        }

        $this->createGroup($itemIds);
    }

    /**
     * Crea un grupo nuevo con las partidas dadas (drop en la zona "nuevo grupo"
     * o botón con selección de checkboxes).
     */
    public function createGroup(array $itemIds)
    {
        $itemIds = array_map('intval', $itemIds);
        $name = trim($this->newGroupName) !== ''
            ? trim($this->newGroupName)
            : 'Grupo '.($this->groups->count() + 1);

        try {
            app(QuotationGroupService::class)->create($this->requisition, $name, null, $itemIds, Auth::id());

            $this->reset('selectedItemIds', 'newGroupName');
            $this->dispatch('board-refresh');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function addSelectionToGroup(int $groupId)
    {
        $itemIds = $this->normalizedSelection();

        if (empty($itemIds)) {
            session()->flash('error', 'Selecciona al menos una partida para agregar al grupo.');

            return;
        }

        $this->addItemsToGroup($groupId, $itemIds);
    }

    /**
     * Agrega partidas a un grupo existente (drop sobre la tarjeta o selección).
     */
    public function addItemsToGroup(int $groupId, array $itemIds)
    {
        $group = $this->requisition->quotationGroups()->active()->findOrFail($groupId);

        try {
            app(QuotationGroupService::class)->addItems($this->requisition, $group, array_map('intval', $itemIds));

            $this->reset('selectedItemIds');
            $this->dispatch('board-refresh');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function removeItemFromGroup(int $groupId, int $itemId)
    {
        $group = $this->requisition->quotationGroups()->active()->findOrFail($groupId);

        try {
            app(QuotationGroupService::class)->removeItems($this->requisition, $group, [$itemId]);

            $this->dispatch('board-refresh');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function cancelGroup(int $groupId)
    {
        $group = $this->requisition->quotationGroups()->active()->findOrFail($groupId);

        try {
            app(QuotationGroupService::class)->cancel($this->requisition, $group, 'Grupo cancelado desde el tablero.', Auth::id());

            $this->dispatch('board-refresh');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    #[On('board-refresh')]
    public function refreshBoard()
    {
        $this->requisition->refresh();
        $this->requisition->load(['items.productService', 'items.expenseCategory']);
        unset($this->unassignedItems, $this->groups, $this->groupHints, $this->followUpGroupIds);
    }

    private function normalizedSelection(): array
    {
        return collect($this->selectedItemIds)
            ->filter()
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function render()
    {
        return view('livewire.rfq.quotation-board');
    }
}
