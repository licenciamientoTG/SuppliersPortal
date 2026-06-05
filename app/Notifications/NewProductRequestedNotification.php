<?php

namespace App\Notifications;

use App\Models\ProductService;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewProductRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ProductService $productService,
        public readonly User $requestedBy
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('products-services.show', $this->productService->id);

        return (new MailMessage)
            ->subject('Nuevo producto o servicio solicitado - '.$this->productService->code)
            ->view('emails.notifications.new-product-requested', [
                'name'        => $notifiable->first_name ?? $notifiable->name,
                'code'        => $this->productService->code,
                'description' => $this->productService->getDisplayName(),
                'type'        => $this->productService->product_type,
                'requestedBy' => $this->requestedBy->name,
                'costCenter'  => $this->productService->costCenter?->name ?? 'N/A',
                'company'     => $this->productService->company?->name ?? 'N/A',
                'url'         => $url,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_product_requested',
            'product_service_id' => $this->productService->id,
            'product_service_code' => $this->productService->code,
            'requested_by_id' => $this->requestedBy->id,
            'requested_by_name' => $this->requestedBy->name,
            'url' => route('products-services.show', $this->productService->id),
            'message' => 'Se solicitó el alta del producto o servicio '.$this->productService->getDisplayName().'.',
        ];
    }
}
