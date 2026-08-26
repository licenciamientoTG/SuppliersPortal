<?php

namespace App\Jobs;

use App\Jobs\Concerns\ReportsMailFailure;
use App\Mail\DeliveryAlertDay3Mail;
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
 * Job Día 3 — Alerta Crítica (venció el plazo de 3 días hábiles).
 *
 * Se despacha con delay calculado a 3 días hábiles desde la entrega.
 * Notifica al Director de Administración y Finanzas + Depto. de Compras.
 */
class SendDeliveryAlertDay3Job implements ShouldQueue
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
            // La estación capturó la recepción antes del Día 3
            return;
        }

        $order->loadMissing(['supplier', 'receivingLocation', 'receivingLocation.users']);

        $supplierName = $order->supplier->company_name ?? 'Proveedor';
        $deliveryDate = $order->supplier_delivered_at->format('d/m/Y');
        $locationName = $order->receivingLocation->name ?? 'Sin ubicación';

        $mail = new DeliveryAlertDay3Mail($order, $supplierName, $deliveryDate, $locationName);

        // Destinatarios: superadmin (Director de Administración y Finanzas) + buyers (Compras) - CACHEADO
        $recipients = $order->receivingLocation->users->pluck('email')->filter()->toArray();
        $superadmins = AlertRecipientService::getSuperadmins();
        $buyers = AlertRecipientService::getBuyers();
        $recipients = array_unique(array_merge($recipients, $superadmins, $buyers));

        if (empty($recipients)) {
            Log::warning("SendDeliveryAlertDay3Job: Sin destinatarios para OC {$order->folio}");

            return;
        }

        Mail::to($recipients)->send($mail);

        Log::info("SendDeliveryAlertDay3Job: ALERTA CRÍTICA enviada para OC {$order->folio} a ".count($recipients).' destinatarios');
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
