<?php

namespace App\Services\Rfq;

use App\Exceptions\Rfq\InvalidRfqStateException;
use App\Models\Rfq;
use App\Notifications\BuyerWorkflowNotification;
use App\Notifications\NewRfqForSupplierNotification;
use App\Notifications\RfqSentToSuppliersNotification;
use App\Services\BuyerNotificationService;
use App\Services\SafeNotificationService;
use Illuminate\Support\Facades\Log;

/**
 * Envío de una RFQ en borrador a sus proveedores, con las tres
 * notificaciones del flujo (requisitor, proveedores y compradores).
 */
class RfqSendService
{
    public function __construct(
        private BuyerNotificationService $buyerNotificationService,
        private SafeNotificationService $safeNotifications,
    ) {}

    /**
     * @throws InvalidRfqStateException si la RFQ no está en borrador o no tiene proveedores
     */
    public function send(Rfq $rfq): Rfq
    {
        if ($rfq->status !== 'DRAFT') {
            throw new InvalidRfqStateException('Negativo, soldado. Solo se pueden enviar RFQs en estado borrador.');
        }

        if ($rfq->suppliers->isEmpty()) {
            throw new InvalidRfqStateException('Operación abortada. La RFQ no tiene proveedores asignados.');
        }

        $rfq->update([
            'status' => 'SENT',
            'sent_at' => now(),
        ]);

        if ($rfq->requisition && $rfq->requisition->requester) {
            $this->safeNotifications->notify(
                new RfqSentToSuppliersNotification($rfq),
                [$rfq->requisition->requester],
                'de RFQ enviada al requisitor',
                $rfq->folio,
                route('rfq.show', $rfq),
            );
        }

        foreach ($rfq->suppliers as $supplier) {
            $rfq->suppliers()->updateExistingPivot($supplier->id, [
                'invited_at' => now(),
            ]);

            $this->safeNotifications->notify(
                new NewRfqForSupplierNotification($rfq),
                [$supplier],
                'de RFQ enviada al proveedor',
                $rfq->folio,
                route('rfq.show', $rfq),
            );

            Log::info('RFQ notification sent to supplier account', [
                'rfq_id' => $rfq->id,
                'supplier_id' => $supplier->id,
                'supplier_email' => strtolower(trim((string) $supplier->email)),
            ]);
        }

        $fresh = $rfq->fresh(['requisition.requester', 'suppliers']);

        $this->notifyBuyersRfqWasSent($fresh);

        return $fresh;
    }

    private function notifyBuyersRfqWasSent(Rfq $rfq): void
    {
        $this->buyerNotificationService->notify(
            new BuyerWorkflowNotification(
                type: 'buyer_rfq_sent',
                subject: 'RFQ enviada a proveedores - '.$rfq->folio,
                heading: 'RFQ enviada',
                intro: 'la solicitud de cotización ya fue enviada a los proveedores seleccionados.',
                details: [
                    'RFQ' => $rfq->folio,
                    'Requisición' => $rfq->requisition?->folio ?? 'N/A',
                    'Solicitante' => $rfq->requisition?->requester?->name ?? 'N/A',
                    'Proveedores notificados' => (string) $rfq->suppliers->count(),
                    'Fecha límite' => $rfq->response_deadline?->format('d/m/Y') ?? 'No especificada',
                ],
                url: route('rfq.show', $rfq),
                buttonLabel: 'Ver RFQ',
                message: 'La RFQ '.$rfq->folio.' fue enviada a '.$rfq->suppliers->count().' proveedor(es).',
                context: [
                    'rfq_id' => $rfq->id,
                    'rfq_folio' => $rfq->folio,
                    'requisition_id' => $rfq->requisition_id,
                    'requisition_folio' => $rfq->requisition?->folio,
                ],
            ),
        );
    }
}
