<?php

namespace App\Livewire\Rfq\Board;

use App\Exceptions\Rfq\AwardNotAllowedException;
use App\Exceptions\Rfq\InvalidRfqStateException;
use App\Exceptions\Rfq\RfqAlreadyRejectedException;
use App\Models\Requisition;
use App\Models\Rfq;
use App\Models\Supplier;
use App\Services\Rfq\PriceMemoryService;
use App\Services\Rfq\RfqAwardService;
use App\Services\Rfq\RfqDraftService;
use App\Services\Rfq\RfqSendService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Tarjeta de un grupo de cotización en el tablero. Cada tarjeta avanza por
 * su propio ciclo: Armado → Solicitada/Con precios → Adjudicada.
 */
class GroupCard extends Component
{
    public Requisition $requisition;

    public int $groupId;

    // Formulario "Solicitar cotización"
    public bool $showRequestForm = false;

    public array $supplierIds = [];

    public string $responseDeadline = '';

    public string $notes = '';

    // Formulario "Adjudicar directo"
    public bool $showAwardForm = false;

    public $awardSupplierId = null;

    public string $awardJustification = '';

    public function mount(Requisition $requisition, int $groupId)
    {
        $this->requisition = $requisition;
        $this->groupId = $groupId;
        $this->responseDeadline = now()->addDays(7)->format('Y-m-d');

        $this->prefillFromActiveRfq();
    }

    #[On('board-refresh')]
    public function refreshCard()
    {
        $this->requisition->refresh();
        unset($this->group, $this->activeRfq, $this->state, $this->priceHint, $this->awardableSuppliers, $this->responseProgress, $this->isValidated);
        $this->prefillFromActiveRfq();
    }

    private function prefillFromActiveRfq(): void
    {
        $rfq = $this->activeRfq;

        if ($rfq) {
            $this->supplierIds = $rfq->suppliers->pluck('id')->map(fn ($id) => (string) $id)->all();
            $this->responseDeadline = $rfq->response_deadline?->format('Y-m-d') ?? now()->addDays(7)->format('Y-m-d');
            $this->notes = $rfq->message ?? '';
        }
    }

    // =========================================================
    //  Estado derivado
    // =========================================================

    #[Computed]
    public function group()
    {
        return $this->requisition->quotationGroups()
            ->with('items.productService')
            ->findOrFail($this->groupId);
    }

    #[Computed]
    public function activeRfq(): ?Rfq
    {
        return Rfq::where('requisition_id', $this->requisition->id)
            ->where('quotation_group_id', $this->groupId)
            ->active()
            ->with(['suppliers', 'rfqResponses'])
            ->latest('id')
            ->first();
    }

    /**
     * Ciclo de vida de la tarjeta: preparing | sent | received | awarded | completed
     */
    #[Computed]
    public function state(): string
    {
        $rfq = $this->activeRfq;

        if (! $rfq) {
            $completed = Rfq::where('requisition_id', $this->requisition->id)
                ->where('quotation_group_id', $this->groupId)
                ->where('status', 'COMPLETED')
                ->exists();

            return $completed ? 'completed' : 'preparing';
        }

        return match ($rfq->status) {
            'SENT' => 'sent',
            'RECEIVED' => 'received',
            'EVALUATED' => 'awarded',
            default => 'preparing',
        };
    }

    #[Computed]
    public function isValidated(): bool
    {
        return $this->requisition->validated_at !== null;
    }

    #[Computed]
    public function priceHint(): array
    {
        return app(PriceMemoryService::class)->groupHint($this->group);
    }

    #[Computed]
    public function selectableSuppliers()
    {
        return Supplier::approved()->orderBy('company_name')->get(['id', 'company_name']);
    }

    /**
     * Proveedores cuya respuesta cubre todas las partidas del grupo
     * (candidatos a adjudicación directa desde la tarjeta).
     */
    #[Computed]
    public function awardableSuppliers()
    {
        $rfq = $this->activeRfq;

        if (! $rfq || ! in_array($this->state, ['received', 'sent'], true)) {
            return collect();
        }

        $itemIds = $this->group->items->pluck('id');

        $coveredBySupplier = $rfq->rfqResponses
            ->where('status', 'SUBMITTED')
            ->where('not_available', false)
            ->groupBy('supplier_id')
            ->map(fn ($responses) => $responses->pluck('requisition_item_id')->unique());

        $supplierIds = $coveredBySupplier
            ->filter(fn ($covered) => $itemIds->diff($covered)->isEmpty())
            ->keys();

        return Supplier::whereIn('id', $supplierIds)->get(['id', 'company_name']);
    }

    /**
     * Progreso de respuestas para modo Seguimiento.
     */
    #[Computed]
    public function responseProgress(): array
    {
        $rfq = $this->activeRfq;

        if (! $rfq) {
            return ['responded' => 0, 'invited' => 0];
        }

        return [
            'responded' => $rfq->suppliers->filter(fn ($s) => $s->pivot->responded_at !== null)->count(),
            'invited' => $rfq->suppliers->count(),
        ];
    }

    // =========================================================
    //  Acciones
    // =========================================================

    public function toggleRequestForm()
    {
        $this->showRequestForm = ! $this->showRequestForm;
    }

    /**
     * Guarda la configuración del grupo como RFQ en borrador (sin enviar).
     */
    public function saveDraft()
    {
        $this->validateRequestForm();

        try {
            DB::transaction(fn () => app(RfqDraftService::class)->syncGroupRfq(
                $this->requisition,
                $this->groupId,
                array_map('intval', $this->supplierIds),
                $this->responseDeadline,
                $this->notes !== '' ? $this->notes : null,
                Auth::id()
            ));

            $this->showRequestForm = false;
            $this->dispatch('board-refresh');
            session()->flash('success', 'Borrador de RFQ guardado para el grupo.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Crea/actualiza el borrador y lo envía a proveedores en una sola acción.
     */
    public function sendNow()
    {
        if (! $this->isValidated) {
            session()->flash('error', 'Firma la validación técnica antes de enviar cotizaciones.');

            return;
        }

        $this->validateRequestForm();

        try {
            $rfq = DB::transaction(function () {
                $drafts = app(RfqDraftService::class);

                $rfq = $drafts->syncGroupRfq(
                    $this->requisition,
                    $this->groupId,
                    array_map('intval', $this->supplierIds),
                    $this->responseDeadline,
                    $this->notes !== '' ? $this->notes : null,
                    Auth::id()
                ) ?? $this->activeRfq;

                return app(RfqSendService::class)->send($rfq);
            });

            $this->showRequestForm = false;
            $this->dispatch('board-refresh');
            session()->flash('success', "✅ RFQ {$rfq->folio} enviada a {$rfq->suppliers->count()} proveedor(es).");
        } catch (InvalidRfqStateException $e) {
            session()->flash('error', $e->getMessage());
        } catch (\Exception $e) {
            session()->flash('error', 'No fue posible enviar la RFQ: '.$e->getMessage());
        }
    }

    public function openManualQuote()
    {
        $this->dispatch('open-manual-quote', groupId: $this->groupId);
    }

    public function toggleAwardForm()
    {
        $this->showAwardForm = ! $this->showAwardForm;

        if ($this->showAwardForm && $this->awardableSuppliers->count() === 1) {
            $this->awardSupplierId = $this->awardableSuppliers->first()->id;
        }
    }

    /**
     * Adjudicación directa desde la tarjeta (misma lógica que el comparativo).
     */
    public function awardDirect()
    {
        if (! $this->isValidated) {
            session()->flash('error', 'Firma la validación técnica antes de adjudicar.');

            return;
        }

        $this->validate([
            'awardSupplierId' => 'required|integer|exists:suppliers,id',
            'awardJustification' => 'required|string|min:15',
        ], [], [
            'awardSupplierId' => 'proveedor',
            'awardJustification' => 'justificación',
        ]);

        try {
            $summary = app(RfqAwardService::class)->award(
                $this->activeRfq,
                (int) $this->awardSupplierId,
                $this->awardJustification,
                null,
                Auth::id()
            );

            $this->showAwardForm = false;
            $this->reset('awardJustification', 'awardSupplierId');
            $this->dispatch('board-refresh');
            session()->flash('success', 'Adjudicación registrada y enviada a aprobación de '.($summary->currentApprover?->name ?? 'aprobador asignado').'.');
        } catch (RfqAlreadyRejectedException|AwardNotAllowedException $e) {
            session()->flash('error', $e->getMessage());
        } catch (\Exception $e) {
            session()->flash('error', 'No fue posible registrar la adjudicación: '.$e->getMessage());
        }
    }

    private function validateRequestForm(): void
    {
        $this->validate([
            'supplierIds' => 'required|array|min:1',
            'supplierIds.*' => 'integer|exists:suppliers,id',
            'responseDeadline' => 'required|date|after:today',
            'notes' => 'nullable|string',
        ], [
            'supplierIds.required' => 'Selecciona al menos un proveedor.',
            'supplierIds.min' => 'Selecciona al menos un proveedor.',
            'responseDeadline.after' => 'La fecha límite debe ser posterior a hoy.',
        ]);
    }

    public function render()
    {
        return view('livewire.rfq.board.group-card');
    }
}
