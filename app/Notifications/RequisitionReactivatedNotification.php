<?php

namespace App\Notifications;

use App\Models\Requisition;
use App\Models\ProductService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificación que se envía al solicitante cuando su requisición
 * es reactivada automáticamente después de aprobar un producto
 *
 * PASO 3G - Crear en: app/Notifications/RequisitionReactivatedNotification.php
 */
class RequisitionReactivatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Requisition $requisition;
    public ProductService $productService;

    /**
     * Constructor
     */
    public function __construct(Requisition $requisition, ProductService $productService)
    {
        $this->requisition = $requisition;
        $this->productService = $productService;
    }

    /**
     * Canales de notificación
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Construye el mensaje de email
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = route('requisitions.show', $this->requisition->id);

        return (new MailMessage)
            ->subject('Tu Requisición ha sido Reactivada - ' . $this->requisition->folio)
            ->view('emails.notifications.requisition-reactivated', [
                'name'               => $notifiable->first_name ?? $notifiable->name,
                'folio'              => $this->requisition->folio,
                'productCode'        => $this->productService->code,
                'productDescription' => \Str::limit($this->productService->technical_description, 80),
                'url'                => $url,
            ]);
    }

    /**
     * Datos para la notificación en base de datos
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'requisition_reactivated',
            'requisition_id' => $this->requisition->id,
            'requisition_folio' => $this->requisition->folio,
            'product_service_code' => $this->productService->code,
            'product_service_description' => $this->productService->technical_description,
            'url' => route('requisitions.show', $this->requisition->id),
            'message' => 'Tu requisición ' . $this->requisition->folio . ' ha sido reactivada.',
        ];
    }
}
