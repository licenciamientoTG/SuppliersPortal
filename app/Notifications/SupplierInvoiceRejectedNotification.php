<?php

namespace App\Notifications;

use App\Models\SupplierInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupplierInvoiceRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(public SupplierInvoice $invoice) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Factura rechazada: no coincide con la orden de compra')
            ->greeting('Hola, '.$notifiable->company_name)
            ->line("Tu factura {$this->invoice->uuid} fue rechazada porque su importe no coincide con la orden de compra dentro de la tolerancia permitida.")
            ->line($this->invoice->rejection_reason ?? '')
            ->line('Por favor emite una factura que coincida exactamente con la orden de compra y cárgala de nuevo en el portal.')
            ->action('Ir a mis facturas', route('supplier.invoices.index'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'supplier_invoice_rejected',
            'supplier_invoice_id' => $this->invoice->id,
            'url' => route('supplier.invoices.index'),
            'message' => "Tu factura {$this->invoice->uuid} fue rechazada: el importe no coincide con la orden de compra.",
        ];
    }
}
