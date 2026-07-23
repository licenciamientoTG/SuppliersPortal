<?php

namespace App\Notifications;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractPurchaseOrderPendingApprovalNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly PurchaseOrder $purchaseOrder) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $po = $this->purchaseOrder;

        return (new MailMessage)
            ->subject("OC de contrato pendiente de autorización: {$po->folio}")
            ->greeting('Hola, ' . $notifiable->name)
            ->line("La orden de compra {$po->folio}, generada desde un contrato por convenio de precios, requiere tu autorización.")
            ->line('Proveedor: ' . ($po->supplier->company_name ?? '—')
                . ' · Total: $' . number_format((float) $po->total, 2) . ' ' . $po->currency . '.')
            ->action('Revisar orden de compra', route('purchase-orders.show', $po))
            ->line('La OC no se emitirá al proveedor hasta que sea autorizada.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'              => 'contract_po_pending_approval',
            'purchase_order_id' => $this->purchaseOrder->id,
            'folio'             => $this->purchaseOrder->folio,
            'url'               => route('purchase-orders.show', $this->purchaseOrder),
            'message'           => "La OC {$this->purchaseOrder->folio} (contrato por convenio) requiere tu autorización.",
        ];
    }
}
