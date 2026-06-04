<?php

namespace App\Notifications;

use App\Models\Supplier;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewSupplierRegistrationForBuyerNotification extends Notification
{
    use Queueable;

    public function __construct(public Supplier $supplier) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('admin.review.suppliers.show', $this->supplier->id);

        return (new MailMessage)
            ->subject('Nuevo proveedor registrado para revision - '.$this->supplier->company_name)
            ->view('emails.suppliers.new-registration-buyer', [
                'name'         => $notifiable->name ?? null,
                'companyName'  => $this->supplier->company_name,
                'rfc'          => $this->supplier->rfc,
                'contact'      => $this->supplier->contact_person,
                'email'        => $this->supplier->email,
                'supplierType' => $this->formatSupplierType($this->supplier->supplier_type),
                'url'          => $url,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_supplier_registration',
            'supplier_id' => $this->supplier->id,
            'supplier_name' => $this->supplier->company_name,
            'supplier_rfc' => $this->supplier->rfc,
            'url' => route('admin.review.suppliers.show', $this->supplier->id),
            'message' => 'Se registró el proveedor '.$this->supplier->company_name.' y está pendiente de revisión.',
        ];
    }

    private function formatSupplierType(?string $supplierType): string
    {
        return match ($supplierType) {
            'product' => 'Productos',
            'service' => 'Servicios',
            'product_service' => 'Productos y Servicios',
            default => $supplierType ?: '-',
        };
    }
}
