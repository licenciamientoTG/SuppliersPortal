<?php

namespace App\Services;

use App\Models\DirectPurchaseOrder;
use App\Models\DirectPurchaseOrderItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Reception;
use App\Models\ReceptionItem;
use App\Models\User;
use App\Notifications\ReceptionCompletedNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReceptionService
{
    /**
     * Orquesta la recepción completa de una orden dentro de una transacción.
     * Este es el único punto de entrada que el controlador debe usar.
     *
     * @param  PurchaseOrder|DirectPurchaseOrder  $order
     * @param  array  $itemsData  Formato esperado por elemento:
     *                            ['receivable_item_id' => int, 'quantity_received' => float,
     *                            'conformity' => string, 'nonconformity_type' => ?string,
     *                            'nonconformity_notes' => ?string, 'photos' => ?array]
     * @param  array  $data  ['delivery_reference' => ?string, 'notes' => ?string, 'received_at' => ?Carbon]
     *
     * @throws \RuntimeException Si la orden no puede recibirse (ver validateCanReceive).
     */
    public function receive(Model $order, array $itemsData, User $receiver, array $data): Reception
    {
        $this->validateCanReceive($order);

        $reception = DB::transaction(function () use ($order, $itemsData, $receiver, $data) {

            // 1. Crear cabecera de recepción
            $reception = Reception::create([
                'folio' => Reception::generateNextFolio(),
                'receivable_type' => get_class($order),
                'receivable_id' => $order->id,
                'receiving_location_id' => $data['receiving_location_id'] ?? $order->receiving_location_id,
                'received_by' => $receiver->id,
                'status' => Reception::STATUS_PENDING,
                'delivery_reference' => $data['delivery_reference'] ?? null,
                'remission_path' => $data['remission_path'] ?? null,
                'notes' => $data['notes'] ?? null,
                'received_at' => $data['received_at'] ?? now(),
            ]);

            // 2. Procesar cada línea recibida
            $itemClass = $this->resolveItemClass($order);

            // Pre-cargar todos los ítems de una sola vez para evitar N+1
            $itemIds = collect($itemsData)->pluck('receivable_item_id')->all();
            $loadedItems = $itemClass::whereIn('id', $itemIds)->get()->keyBy('id');

            $this->validateReceptionQuantities($order, $itemsData, $loadedItems);

            foreach ($itemsData as $lineData) {
                $item = $loadedItems[$lineData['receivable_item_id']]
                    ?? throw new \RuntimeException("Ítem {$lineData['receivable_item_id']} no encontrado.");
                $quantityReceived = max(0, (float) ($lineData['quantity_received'] ?? 0));
                $conformity = $lineData['conformity'] ?? ReceptionItem::CONFORMITY_OK;

                ReceptionItem::create([
                    'reception_id' => $reception->id,
                    'receivable_item_type' => get_class($item),
                    'receivable_item_id' => $item->id,
                    'quantity_received' => $quantityReceived,
                    'conformity' => $conformity,
                    'nonconformity_type' => $lineData['nonconformity_type'] ?? null,
                    'nonconformity_notes' => $lineData['nonconformity_notes'] ?? null,
                    'photos' => $lineData['photos'] ?? null,
                ]);

                // Solo se acumulan las cantidades CONFORMES en el ítem de la orden.
                // Si el ítem es NO_CONFORME, queda pendiente para que el proveedor
                // lo reponga — la orden no avanzará a RECEIVED hasta que esas unidades
                // sean recibidas conformes en una recepción posterior.
                if ($quantityReceived > 0 && $conformity === ReceptionItem::CONFORMITY_OK) {
                    $item->increment('quantity_received', $quantityReceived);
                }
            }

            // 3. Recalcular el estado agregado de la orden
            $newOrderStatus = $this->calculateOrderReceptionStatus($order);

            // 4. Actualizar estado de la recepción en función del resultado
            $reception->update([
                'status' => $newOrderStatus === 'RECEIVED'
                    ? Reception::STATUS_COMPLETED
                    : Reception::STATUS_PARTIAL,
            ]);

            // 5. Persistir el nuevo estado en la orden
            $this->updateOrderStatus($order, $newOrderStatus, $receiver);

            // 6. Si la orden quedó totalmente recibida, marcar el compromiso presupuestal

            Log::info("Recepción {$reception->folio} registrada para la orden {$order->folio}.");

            return $reception->load('items.receivableItem');
        });

        // Notificar al creador de la orden y al equipo de Compras (fuera de la transacción
        // para garantizar que el commit ya ocurrió antes de despachar el trabajo).
        app(BudgetAllocationService::class)->consumeOrder($order);
        app(FinancialProvisionService::class)->createForReception($reception);

        try {
            $this->notifyReception($reception, $order);
        } catch (Throwable $exception) {
            Log::error('Failed to send reception completion notifications.', [
                'reception_id' => $reception->id,
                'reception_folio' => $reception->folio,
                'order_id' => $order->id,
                'order_folio' => $order->folio,
                'exception' => $exception,
            ]);
        }

        return $reception;
    }

    /**
     * Verifica que una orden puede pasar al flujo de recepción.
     * Lanza RuntimeException con mensaje legible si hay algún impedimento.
     *
     * @throws \RuntimeException
     */
    public function validateCanReceive(Model $order): void
    {
        if (! $order->canBeReceived()) {
            throw new \RuntimeException(
                "La orden {$order->folio} no puede recibirse en su estado actual ({$order->getStatusLabel()})."
            );
        }

        $location = $order->receivingLocation;

        if (! $location) {
            throw new \RuntimeException(
                "La orden {$order->folio} no tiene una ubicación de recepción asignada."
            );
        }

        if (! $location->is_active) {
            throw new \RuntimeException(
                "La ubicación '{$location->name}' está inactiva y no puede recibir mercancía."
            );
        }

        if ($location->portal_blocked) {
            throw new \RuntimeException(
                "La ubicación '{$location->name}' tiene el portal bloqueado. Contacta al Administrador."
            );
        }
    }

    /**
     * Verifica el estado del REPSE del proveedor cuando la orden involucra servicios.
     *
     * Para OCD: solo aplica si al menos un ítem tiene categoría "Servicio" (código SER).
     * Para OC estándar: aplica si el proveedor está marcado como prestador de servicios
     *                   especializados (los ítems de OC no tienen categoría de gasto).
     *
     * No lanza excepción — devuelve un mensaje de advertencia o null.
     * El controlador decide cómo mostrarlo al usuario.
     */
    public function validateRepseIfService(Model $order): ?string
    {
        $supplier = $order->supplier;

        if (! $supplier || ! $supplier->requiresRepseRegistration()) {
            return null;
        }

        // Para OCD: solo alertar si la orden contiene al menos un ítem de Servicios
        if ($order instanceof DirectPurchaseOrder) {
            $order->loadMissing('items.expenseCategory');
            $hasServiceItems = $order->items->contains(
                fn ($item) => $item->expenseCategory?->isService() ?? false
            );

            if (! $hasServiceItems) {
                return null;
            }
        }

        if (! $supplier->hasValidRepseRegistration()) {
            return "El proveedor '{$supplier->company_name}' tiene el registro REPSE vencido o sin número de registro. "
                .'Consulta con el área de Compras antes de continuar.';
        }

        $daysLeft = $supplier->repseExpiresIn();
        if ($daysLeft !== null && $daysLeft <= 30) {
            return "El REPSE del proveedor '{$supplier->company_name}' vence en {$daysLeft} día(s). "
                .'Notifica a Compras para su renovación.';
        }

        return null;
    }

    /**
     * Determina el nuevo estado de la orden examinando todos sus ítems.
     * Recarga los ítems desde BD para obtener los valores ya actualizados por increment().
     *
     * @return 'RECEIVED'|'PARTIALLY_RECEIVED'
     */
    public function calculateOrderReceptionStatus(Model $order): string
    {
        $items = $order->items()->get();

        if ($items->isEmpty()) {
            return 'RECEIVED';
        }

        return $items->every(fn ($item) => $item->isFullyReceived())
            ? 'RECEIVED'
            : 'PARTIALLY_RECEIVED';
    }

    /**
     * Actualiza el estado de la orden y los timestamps de auditoría correspondientes.
     */
    public function updateOrderStatus(Model $order, string $newStatus, User $receiver): void
    {
        $updates = ['status' => $newStatus];

        if ($newStatus === 'RECEIVED') {
            $updates['received_by'] = $receiver->id;
            $updates['received_at'] = now();
        }

        $order->update($updates);
    }

    // ─── Helpers privados ──────────────────────────────────────────────────────

    /**
     * Resuelve la clase del modelo de ítem según el tipo de orden.
     *
     * @throws \RuntimeException
     */
    private function resolveItemClass(Model $order): string
    {
        return match (true) {
            $order instanceof PurchaseOrder => PurchaseOrderItem::class,
            $order instanceof DirectPurchaseOrder => DirectPurchaseOrderItem::class,
            default => throw new \RuntimeException(
                'Tipo de orden no soportado para recepción: '.get_class($order)
            ),
        };
    }

    /**
     * Calcula los días transcurridos desde que la orden fue emitida (issued_at)
     * y devuelve un badge HTML con semáforo de colores:
     *   🟢 Verde   → 0–7 días   (en tiempo)
     *   🟡 Amarillo → 8–15 días  (demorada)
     *   🔴 Rojo    → 16+ días   (crítica)
     *
     * @param  PurchaseOrder|DirectPurchaseOrder  $order
     */
    private function validateReceptionQuantities(Model $order, array $itemsData, \Illuminate\Support\Collection $loadedItems): void
    {
        $totalsByItem = [];

        foreach ($itemsData as $lineData) {
            $itemId = (int) ($lineData['receivable_item_id'] ?? 0);
            $item = $loadedItems[$itemId] ?? null;

            if (! $item) {
                throw new \RuntimeException("Item {$itemId} no encontrado.");
            }

            if (! $this->itemBelongsToOrder($order, $item)) {
                throw new \RuntimeException("El item {$itemId} no pertenece a la orden {$order->folio}.");
            }

            $totalsByItem[$itemId] = ($totalsByItem[$itemId] ?? 0.0)
                + max(0, (float) ($lineData['quantity_received'] ?? 0));
        }

        foreach ($totalsByItem as $itemId => $requestedQuantity) {
            $pending = (float) $loadedItems[$itemId]->quantity_pending;

            if ($requestedQuantity > $pending + 0.0005) {
                throw new \RuntimeException(
                    "La cantidad recibida del item {$itemId} ({$requestedQuantity}) supera la cantidad pendiente ({$pending})."
                );
            }
        }
    }

    private function itemBelongsToOrder(Model $order, Model $item): bool
    {
        return match (true) {
            $order instanceof PurchaseOrder && $item instanceof PurchaseOrderItem => (int) $item->purchase_order_id === (int) $order->id,
            $order instanceof DirectPurchaseOrder && $item instanceof DirectPurchaseOrderItem => (int) $item->direct_purchase_order_id === (int) $order->id,
            default => false,
        };
    }

    public function getElapsedDaysBadge(Model $order): string
    {
        if (! $order->issued_at) {
            return '<span class="badge bg-secondary">Sin fecha emisión</span>';
        }

        $days = (int) $order->issued_at->diffInDays(now());

        [$color, $icon] = match (true) {
            $days <= 7 => ['success', 'ti-circle-check'],
            $days <= 15 => ['warning', 'ti-alert-triangle'],
            default => ['danger',  'ti-circle-x'],
        };

        return '<span class="badge bg-'.$color.'">'
            .'<i class="ti '.$icon.' me-1"></i>'
            .$days.' día(s)'
            .'</span>';
    }

    /**
     * Envía la notificación de recepción al creador de la orden y a todos los compradores.
     * Se deduplica por ID para que el creador no reciba la notificación dos veces si
     * él mismo tiene el rol 'buyer'.
     */
    private function notifyReception(Reception $reception, Model $order): void
    {
        $notification = new ReceptionCompletedNotification($reception);

        $notifiables = collect();

        // Siempre notificar al creador de la orden (puede ser buyer o solicitante en OCD)
        if ($order->creator) {
            $notifiables->push($order->creator);
        }

        // Notificar a todos los compradores activos - CACHEADO para evitar N+1
        $buyers = Cache::remember('buyers_list', 3600, function () {
            return User::role('buyer')->get(['id', 'name', 'email']);
        });
        $buyers->each(fn ($u) => $notifiables->push($u));

        app(SafeNotificationService::class)->notify(
            $notification,
            $notifiables->unique('id'),
            'de recepción completada',
            $reception->folio,
        );
    }
}
