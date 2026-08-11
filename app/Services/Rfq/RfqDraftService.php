<?php

namespace App\Services\Rfq;

use App\Models\QuotationGroup;
use App\Models\Requisition;
use App\Models\Rfq;
use Illuminate\Support\Facades\Log;

/**
 * Creación y sincronización de RFQs en borrador por grupo de cotización.
 *
 * No abre transacciones propias: el llamador decide el alcance transaccional
 * (el wizard agrupa todos los grupos en una sola transacción; el tablero
 * opera grupo por grupo).
 */
class RfqDraftService
{
    public function __construct(private RfqFolioService $folios) {}

    /**
     * Sincroniza la RFQ activa de un grupo contra la configuración deseada.
     *
     * - Sin RFQ activa: crea un borrador nuevo.
     * - Sin cambios: no toca nada y regresa null.
     * - DRAFT con cambios: actualiza deadline/notas y sincroniza proveedores.
     * - Enviada con cambios: la cancela y crea un borrador nuevo.
     */
    public function syncGroupRfq(
        Requisition $requisition,
        int $groupId,
        array $supplierIds,
        string $responseDeadline,
        ?string $notes,
        int $userId
    ): ?Rfq {
        $existingRfq = Rfq::where('requisition_id', $requisition->id)
            ->where('quotation_group_id', $groupId)
            ->active()
            ->first();

        if (! $existingRfq) {
            return $this->createDraft($requisition, $groupId, $supplierIds, $responseDeadline, $notes, $userId);
        }

        $currentSuppliers = $existingRfq->suppliers->pluck('id')->sort()->values()->toArray();
        $newSuppliers = collect($supplierIds)->map(fn ($id) => (int) $id)->sort()->values()->toArray();

        $hasChanges = (
            $currentSuppliers !== $newSuppliers ||
            $existingRfq->response_deadline->format('Y-m-d') !== $responseDeadline ||
            $existingRfq->message !== $notes
        );

        if (! $hasChanges) {
            Log::info("⏭️ Sin cambios en RFQ {$existingRfq->folio}, ignorando.");

            return null;
        }

        if ($existingRfq->status === 'DRAFT') {
            $existingRfq->update([
                'response_deadline' => $responseDeadline,
                'message' => $notes,
            ]);
            $existingRfq->suppliers()->sync($this->supplierPivotData($supplierIds));

            return $existingRfq;
        }

        $existingRfq->update([
            'status' => 'CANCELLED',
            'cancelled_at' => now(),
            'cancelled_by' => $userId,
            'cancellation_reason' => 'Actualización manual de proveedores tras envío.',
        ]);

        return $this->createDraft($requisition, $groupId, $supplierIds, $responseDeadline, $notes, $userId);
    }

    /**
     * Garantiza que el grupo tenga una RFQ activa (la crea en borrador si no existe).
     * Usado por la captura manual de cotizaciones.
     */
    public function ensureActiveDraftForGroup(Requisition $requisition, QuotationGroup $group, int $userId): Rfq
    {
        $rfq = Rfq::where('requisition_id', $requisition->id)
            ->where('quotation_group_id', $group->id)
            ->active()
            ->first();

        if ($rfq) {
            return $rfq;
        }

        return Rfq::create([
            'folio' => $this->folios->next(),
            'requisition_id' => $requisition->id,
            'quotation_group_id' => $group->id,
            'status' => 'DRAFT',
            'response_deadline' => now()->addDays(7),
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    /**
     * Prepara la RFQ exclusiva para precio conocido / compra directa.
     * Un borrador de portal puede sustituirse antes de que se envíe; una RFQ
     * en circulación nunca se mezcla con la ruta manual.
     */
    public function ensureExternalDraftForGroup(Requisition $requisition, QuotationGroup $group, int $userId): Rfq
    {
        $rfq = Rfq::where('requisition_id', $requisition->id)
            ->where('quotation_group_id', $group->id)
            ->active()
            ->latest('id')
            ->first();

        if ($rfq?->source === 'external') {
            if (in_array($rfq->status, ['DRAFT', 'RECEIVED'], true)) {
                return $rfq;
            }

            throw new \DomainException('Este grupo ya tiene una compra directa en validación o autorizada.');
        }

        if ($rfq) {
            if ($rfq->status !== 'DRAFT') {
                throw new \DomainException('No se puede cambiar a compra directa porque la RFQ de este grupo ya fue enviada.');
            }

            $rfq->cancel('Borrador sustituido por compra directa con precio conocido.', $userId);
        }

        return Rfq::create([
            'folio' => $this->folios->next(),
            'requisition_id' => $requisition->id,
            'quotation_group_id' => $group->id,
            'source' => 'external',
            'status' => 'DRAFT',
            'response_deadline' => now(),
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    private function createDraft(
        Requisition $requisition,
        int $groupId,
        array $supplierIds,
        string $responseDeadline,
        ?string $notes,
        int $userId
    ): Rfq {
        $rfq = Rfq::create([
            'folio' => $this->folios->next(),
            'requisition_id' => $requisition->id,
            'quotation_group_id' => $groupId,
            'status' => 'DRAFT',
            'response_deadline' => $responseDeadline,
            'message' => $notes,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $rfq->suppliers()->attach($this->supplierPivotData($supplierIds));

        return $rfq;
    }

    private function supplierPivotData(array $supplierIds): array
    {
        $pivotData = [];
        foreach ($supplierIds as $id) {
            $pivotData[$id] = [
                'invited_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return $pivotData;
    }
}
