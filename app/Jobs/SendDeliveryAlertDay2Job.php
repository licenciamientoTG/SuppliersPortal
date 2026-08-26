<?php

namespace App\Jobs;

use App\Jobs\Concerns\ReportsMailFailure;
use App\Mail\DeliveryAlertDay2Mail;
use App\Models\DirectPurchaseOrder;
use App\Models\PurchaseOrder;
use App\Services\AlertRecipientService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Job Día 2 — Alerta de seguimiento (1 día hábil antes del vencimiento).
 *
 * Se despacha con delay calculado a 2 días hábiles desde la entrega.
 * Notifica al receptor de estación y a Finanzas.
 */
class SendDeliveryAlertDay2Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReportsMailFailure, SerializesModels;

    public function __construct(
        public string $orderType,
        public int $orderId,
    ) {}

    public function handle(): void
    {
        $order = $this->resolveOrder();

        if (! $order || $order->status !== 'DELIVERED_PENDING_RECEPTION') {
            // La estación ya capturó la recepción antes del Día 2
            return;
        }

        $order->loadMissing(['supplier', 'receivingLocation', 'receivingLocation.users']);

        $supplierName = $order->supplier->company_name ?? 'Proveedor';
        $deliveryDate = $order->supplier_delivered_at->format('d/m/Y');
        $locationName = $order->receivingLocation->name ?? 'Sin ubicación';

        $mail = new DeliveryAlertDay2Mail($order, $supplierName, $deliveryDate, $locationName);

        // Destinatarios: receptores de la estación
        $recipients = $order->receivingLocation->users->pluck('email')->filter()->toArray();

        // Destinatarios: usuarios con rol superadmin (Finanzas / Dirección) - CACHEADO
        $buyers = AlertRecipientService::getBuyers();
        $finanzas = AlertRecipientService::getSuperadmins();
        $recipients = array_unique(array_merge($recipients, $buyers, $finanzas));

        if (empty($recipients)) {
            Log::warning("SendDeliveryAlertDay2Job: Sin destinatarios para OC {$order->folio}");

            return;
        }

        Mail::to($recipients)->send($mail);

        Log::info("SendDeliveryAlertDay2Job: Alerta enviada para OC {$order->folio} a ".count($recipients).' destinatarios');
    }

    private function resolveOrder()
    {
        return $this->orderType === 'direct'
            ? DirectPurchaseOrder::find($this->orderId)
            : PurchaseOrder::find($this->orderId);
    }

    protected function mailFailureReference(): ?string
    {
        return "{$this->orderType}:{$this->orderId}";
    }
}
