<?php

namespace App\Jobs;

use App\Jobs\Concerns\ReportsMailFailure;
use App\Mail\DeliveryAlertDay0Mail;
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
 * Job Día 0 — Alerta Inmediata al registrar entrega del proveedor.
 *
 * Se despacha inmediatamente cuando el proveedor sube su remisión.
 * Notifica al receptor asignado a la estación y al Depto. de Compras.
 */
class SendDeliveryAlertDay0Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReportsMailFailure, SerializesModels;

    public function __construct(
        public string $orderType,
        public int $orderId,
        public string $evidenceUrl,
    ) {}

    public function handle(): void
    {
        $order = $this->resolveOrder();

        if (! $order || $order->status !== 'DELIVERED_PENDING_RECEPTION') {
            // La estación ya capturó la recepción; no enviar alerta
            return;
        }

        $order->loadMissing(['supplier', 'receivingLocation', 'receivingLocation.users']);

        $supplierName = $order->supplier->company_name ?? 'Proveedor';
        $deliveryDate = $order->supplier_delivered_at->format('d/m/Y H:i');
        $locationName = $order->receivingLocation->name ?? 'Sin ubicación';

        $mail = new DeliveryAlertDay0Mail(
            $order,
            $supplierName,
            $deliveryDate,
            $this->evidenceUrl,
            $locationName,
        );

        // Destinatarios: usuarios asignados a la estación (receptores)
        $recipients = $order->receivingLocation->users->pluck('email')->filter()->toArray();

        // Destinatarios: usuarios con rol buyer (Departamento de Compras) - CACHEADO
        $buyers = AlertRecipientService::getBuyers();
        $finanzas = AlertRecipientService::getSuperadmins();
        $recipients = array_unique(array_merge($recipients, $buyers, $finanzas));

        if (empty($recipients)) {
            Log::warning("SendDeliveryAlertDay0Job: Sin destinatarios para OC {$order->folio}");

            return;
        }

        Mail::to($recipients)->send($mail);

        Log::info("SendDeliveryAlertDay0Job: Alerta enviada para OC {$order->folio} a ".count($recipients).' destinatarios');
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
