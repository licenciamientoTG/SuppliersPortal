<?php

namespace App\Notifications;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractPurchaseOrderRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly PurchaseOrder $purchaseOrder,
        private readonly string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $po = $this->purchaseOrder;

        return (new MailMessage)
            ->subject("OC de contrato rechazada: {$po->folio}")
            ->greeting('Hola, ' . $notifiable->name)
            ->line("La orden de compra {$po->folio}, generada desde un contrato por convenio de precios, fue rechazada por el autorizador.")
            ->line("Motivo: {$this->reason}")
            ->action('Ver orden de compra', route('purchase-orders.show', $po))
            ->line('La OC no fue emitida al proveedor.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'              => 'contract_po_rejected',
            'purchase_order_id' => $this->purchaseOrder->id,
            'folio'             => $this->purchaseOrder->folio,
            'reason'            => $this->reason,
            'url'               => route('purchase-orders.show', $this->purchaseOrder),
            'message'           => "La OC {$this->purchaseOrder->folio} fue rechazada: {$this->reason}",
        ];
    }
}
