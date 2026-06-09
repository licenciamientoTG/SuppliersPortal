<?php

namespace App\Http\Controllers;

use App\Jobs\SendDeliveryAlertDay0Job;
use App\Jobs\SendDeliveryAlertDay2Job;
use App\Jobs\SendDeliveryAlertDay3Job;
use App\Models\DirectPurchaseOrder;
use App\Models\PurchaseOrder;
use App\Models\ReceivingLocation;
use App\Models\SupplierDeliveryEvidence;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Controlador de Entregas del Proveedor.
 *
 * Permite al proveedor registrar entregas físicas (subir remisión)
 * cuando la estación receptora aún no ha capturado la recepción.
 * Gestiona el bloqueo por estación y las alertas escalonadas.
 */
class SupplierDeliveryController extends Controller
{
    /**
     * Listado de OCs del proveedor que pueden recibir entrega.
     * Muestra OCs en estado ISSUED o PARTIALLY_RECEIVED.
     */
    public function index()
    {
        $supplier = Auth::guard('supplier')->user();

        if (!$supplier) {
            abort(403, 'No tienes un perfil de proveedor asociado.');
        }

        $purchaseOrders = PurchaseOrder::where('supplier_id', $supplier->id)
            ->whereIn('status', ['ISSUED', 'PARTIALLY_RECEIVED', 'DELIVERED_PENDING_RECEPTION'])
            ->with(['receivingLocation', 'items'])
            ->get()
            ->each(fn($o) => $o->order_type = 'standard');

        $directOrders = DirectPurchaseOrder::where('supplier_id', $supplier->id)
            ->whereIn('status', ['ISSUED', 'PARTIALLY_RECEIVED', 'DELIVERED_PENDING_RECEPTION'])
            ->with(['receivingLocation', 'items'])
            ->get()
            ->each(fn($o) => $o->order_type = 'direct');

        // `merge()` en colecciones Eloquent usa la llave primaria del modelo.
        // Si una OC normal y una OCD comparten el mismo `id`, una pisa a la otra.
        $orders = $purchaseOrders->concat($directOrders)->sortByDesc('issued_at')->values();

        return view('supplier.deliveries.index', compact('orders'));
    }

    /**
     * Formulario para registrar entrega contra una OC.
     * Verifica bloqueo de estación antes de mostrar el formulario.
     */
    public function create(Request $request)
    {
        $supplier = Auth::guard('supplier')->user();
        $order = $this->resolveOrder($request->query('type'), $request->query('id'));

        if (!$order) {
            abort(404, 'Orden de compra no encontrada.');
        }

        // Validar que la OC pertenece al proveedor autenticado
        if ((int) $order->supplier_id !== (int) $supplier->id) {
            abort(403, 'Esta orden de compra no pertenece a tu cuenta.');
        }

        // Validar que la OC está en un estatus que permite entrega
        if (!$order->canReceiveSupplierDelivery()) {
            return redirect()->route('supplier.deliveries.index')
                ->with('error', 'Esta orden de compra no está en un estatus que permita registrar entrega.');
        }

        $order->loadMissing(['receivingLocation', 'items']);
        $location = $order->receivingLocation;

        // Verificar bloqueo dinámico: ¿la estación tiene OCs en DELIVERED_PENDING_RECEPTION?
        $isLocationBlocked = $location ? $location->hasDeliveryPendingReception() : false;
        $blockingOrders = $isLocationBlocked ? $location->getOrdersPendingReception() : collect();

        $orderType = $request->query('type');

        return view('supplier.deliveries.create', compact(
            'order',
            'orderType',
            'location',
            'isLocationBlocked',
            'blockingOrders',
        ));
    }

    /**
     * Registra la entrega del proveedor.
     *
     * 1. Valida datos y archivo de remisión
     * 2. Guarda evidencia en storage
     * 3. Actualiza estatus de OC a DELIVERED_PENDING_RECEPTION
     * 4. Calcula fecha límite (3 días hábiles)
     * 5. Despacha alertas escalonadas (Día 0, 2, 3)
     */
    public function store(Request $request)
    {
        $supplier = Auth::guard('supplier')->user();

        $request->validate([
            'order_type'              => 'required|in:standard,direct',
            'order_id'                => 'required|integer',
            'remission_file'          => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'delivered_at'            => 'required|date|before_or_equal:now',
            'physical_receiver_name'  => 'nullable|string|max:150',
            'delivery_observations'   => 'nullable|string|max:2000',
        ], [
            'remission_file.required' => 'La remisión es obligatoria.',
            'remission_file.mimes'    => 'La remisión debe ser PDF, JPG o PNG.',
            'remission_file.max'      => 'La remisión no debe superar 10 MB.',
            'delivered_at.required'   => 'La fecha de entrega es obligatoria.',
            'delivered_at.before_or_equal' => 'La fecha de entrega no puede ser futura.',
        ]);

        $order = $this->resolveOrder($request->order_type, $request->order_id);

        if (!$order || (int) $order->supplier_id !== (int) $supplier->id) {
            abort(403, 'Orden de compra inválida.');
        }

        if (!$order->canReceiveSupplierDelivery()) {
            return back()->with('error', 'Esta orden de compra ya no permite registrar entrega.');
        }

        // Verificar bloqueo de estación
        $location = $order->receivingLocation;
        if ($location && $location->hasDeliveryPendingReception()) {
            return back()->with('error', 'Esta estación tiene entregas pendientes de captura. No se pueden registrar nuevas entregas hasta que se resuelvan.');
        }

        DB::transaction(function () use ($request, $order, $supplier) {
            // 1. Guardar archivo de remisión
            $file = $request->file('remission_file');
            $extension = strtolower($file->getClientOriginalExtension());
            $path = $file->store("supplier-deliveries/{$supplier->id}", 'public');

            // 2. Crear registro de evidencia
            SupplierDeliveryEvidence::create([
                'evidenceable_type' => get_class($order),
                'evidenceable_id'   => $order->id,
                'file_path'         => $path,
                'file_format'       => $extension,
                'uploaded_by'       => Auth::guard('web')->id(),
                'uploaded_at'       => now(),
            ]);

            // 3. Calcular fecha límite: 3 días hábiles desde hoy
            $deliveredAt = Carbon::parse($request->delivered_at);
            $deadline = self::addBusinessDays($deliveredAt, 3);

            // 4. Actualizar la OC
            $order->update([
                'status'                 => 'DELIVERED_PENDING_RECEPTION',
                'supplier_delivered_at'  => $deliveredAt,
                'reception_deadline_at'  => $deadline,
                'physical_receiver_name' => $request->physical_receiver_name,
                'delivery_observations'  => $request->delivery_observations,
            ]);

            // 5. Determinar tipo de orden para los Jobs
            $orderType = $order instanceof DirectPurchaseOrder ? 'direct' : 'standard';
            $evidenceUrl = Storage::disk('public')->url($path);

            // 6. Despachar alertas escalonadas
            // Día 0 — Inmediata
            SendDeliveryAlertDay0Job::dispatch($orderType, $order->id, $evidenceUrl);

            // Día 2 — 2 días hábiles después (1 día antes del vencimiento)
            $day2 = self::addBusinessDays($deliveredAt, 2);
            SendDeliveryAlertDay2Job::dispatch($orderType, $order->id)
                ->delay($day2->startOfDay()->addHours(9)); // Enviar a las 9 AM

            // Día 3 — 3 días hábiles después (al vencimiento)
            SendDeliveryAlertDay3Job::dispatch($orderType, $order->id)
                ->delay($deadline->copy()->startOfDay()->addHours(9)); // Enviar a las 9 AM
        });

        return redirect()->route('supplier.deliveries.index')
            ->with('success', "Entrega registrada exitosamente para la OC {$order->folio}. La estación tiene 3 días hábiles para capturar la recepción.");
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    /**
     * Resuelve la orden de compra según su tipo e ID.
     */
    private function resolveOrder(?string $type, $id)
    {
        if (!$type || !$id) {
            return null;
        }

        return $type === 'direct'
            ? DirectPurchaseOrder::find($id)
            : PurchaseOrder::find($id);
    }

    /**
     * Suma N días hábiles a una fecha (excluye sábados y domingos).
     *
     * Ejemplo: Si hoy es viernes y se suman 3 días hábiles,
     * el resultado es miércoles de la siguiente semana.
     */
    public static function addBusinessDays(Carbon $startDate, int $days): Carbon
    {
        $date = $startDate->copy();
        $added = 0;

        while ($added < $days) {
            $date->addDay();

            // Solo contar si no es sábado (6) ni domingo (0)
            if (!$date->isWeekend()) {
                $added++;
            }
        }

        return $date;
    }
}
