<?php

namespace App\Notifications;

use App\Models\SupplierInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceRejectedByToleranceNotification extends Notification
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
            ->subject('Factura rechazada automáticamente por tolerancia de importe')
            ->line("La factura {$this->invoice->uuid} del proveedor {$this->invoice->supplier?->company_name} se rechazó automáticamente.")
            ->line($this->invoice->rejection_reason ?? '')
            ->line('Se notificó al proveedor para que emita una factura que coincida con la orden de compra.')
            ->action('Ver factura', route('invoices.show', $this->invoice));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'invoice_rejected_by_tolerance',
            'supplier_invoice_id' => $this->invoice->id,
            'url' => route('invoices.show', $this->invoice),
            'message' => "La factura {$this->invoice->uuid} se rechazó automáticamente por exceder la tolerancia de importe.",
        ];
    }
}
