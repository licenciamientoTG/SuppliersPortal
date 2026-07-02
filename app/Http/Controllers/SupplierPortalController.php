<?php

namespace App\Http\Controllers;

use App\Models\Rfq;
use App\Models\RfqResponse;
use App\Models\Supplier;
use App\Models\SupplierRfq;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SupplierPortalController extends Controller
{
    /**
     * Verifica que el proveedor tenga acceso a la RFQ
     */
    private function verifySupplierAccess(Rfq $rfq): void
    {
        $supplier = Auth::guard('supplier')->user();

        if (!$supplier) {
            abort(403, 'No tienes un perfil de proveedor asociado');
        }

        if (!$rfq->suppliers->contains($supplier->id)) {
            abort(403, 'No tienes acceso a esta RFQ');
        }
    }

    /**
     * Determina si una partida fue marcada como no disponible por el proveedor
     */
    private function isItemNotAvailable(array $itemData): bool
    {
        return ! empty($itemData['not_available']) && (string) $itemData['not_available'] === '1';
    }

    /**
     * Valida los datos del formulario de cotización
     *
     * @return array<string, mixed>
     */
    private function validateQuotationData(Request $request): array
    {
        // En modo borrador permitimos guardar avances parciales: descartamos las
        // partidas que el proveedor aún no cotiza (sin precio unitario) para que no
        // sean obligatorias ni se almacenen como respuestas vacías. El envío final
        // sigue exigiendo todas las partidas pendientes.
        if ($request->input('action') === 'save_draft' && is_array($request->input('items'))) {
            $items = array_filter(
                $request->input('items'),
                function ($item) {
                    $hasPrice = isset($item['unit_price']) && trim((string) $item['unit_price']) !== '';
                    $notAvailable = $this->isItemNotAvailable($item);

                    return $hasPrice || $notAvailable;
                }
            );
            $request->merge(['items' => $items]);
        }

        return $request->validate([
            'items' => 'nullable|array',
            'items.*.item_id' => 'required|exists:requisition_items,id',
            'items.*.not_available' => 'nullable|boolean',
            'items.*.unit_price' => ['exclude_if:items.*.not_available,1', 'required', 'numeric', 'min:0'],
            'items.*.quantity' => ['exclude_if:items.*.not_available,1', 'required', 'numeric', 'min:0.01'],
            'items.*.iva_rate' => ['exclude_if:items.*.not_available,1', 'required', 'numeric', 'in:0,8,16'],
            'items.*.currency' => 'nullable|string|in:MXN,USD,EUR',
            'items.*.delivery_days' => [
                'exclude_if:items.*.not_available,1',
                Rule::requiredIf(fn () => $request->input('action') === 'submit'),
                'nullable',
                'integer',
                'min:0',
            ],
            'items.*.payment_terms' => 'nullable|string|max:255',
            'items.*.warranty_terms' => 'nullable|string|max:500',
            'items.*.brand' => 'nullable|string|max:100',
            'items.*.model' => 'nullable|string|max:100',
            'items.*.specifications' => 'nullable|string',
            'items.*.notes' => 'nullable|string',
            'items.*.attachment' => 'nullable|file|mimes:pdf|max:5120',
            'supplier_quotation_number' => 'nullable|string|max:100',
            'validity_days' => 'nullable|integer|min:1|max:365',
            'quotation_pdf_file' => 'nullable|file|mimes:pdf|max:5120',
            'action' => 'required|in:save_draft,submit',
        ]);
    }

    /**
     * Calcula los totales financieros de una partida
     *
     * @return array<string, float>
     */
    private function calculateItemTotals(array $itemData): array
    {
        $notAvailable = $this->isItemNotAvailable($itemData);

        if ($notAvailable) {
            return [
                'unit_price' => 0,
                'quantity' => 0,
                'subtotal' => 0,
                'iva_rate' => 0,
                'iva_amount' => 0,
                'total' => 0,
            ];
        }

        $unitPrice = $itemData['unit_price'];
        $quantity = $itemData['quantity'];
        $ivaRate = $itemData['iva_rate'];

        $subtotal = $unitPrice * $quantity;
        $ivaAmount = $subtotal * ($ivaRate / 100);
        $total = $subtotal + $ivaAmount;

        return [
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'subtotal' => $subtotal,
            'iva_rate' => $ivaRate,
            'iva_amount' => $ivaAmount,
            'total' => $total,
        ];
    }

    /**
     * Guarda o actualiza una respuesta de cotización
     */
    private function saveQuotationItem(
        Rfq $rfq,
        int $supplierId,
        int $itemId,
        array $itemData,
        string $action
    ): RfqResponse {
        $totals = $this->calculateItemTotals($itemData);
        $notAvailable = $this->isItemNotAvailable($itemData);

        return RfqResponse::updateOrCreate(
            [
                'rfq_id' => $rfq->id,
                'supplier_id' => $supplierId,
                'requisition_item_id' => $itemId,
            ],
            [
                'unit_price' => $totals['unit_price'],
                'quantity' => $totals['quantity'],
                'subtotal' => $totals['subtotal'],
                'iva_rate' => $totals['iva_rate'],
                'iva_amount' => $totals['iva_amount'],
                'total' => $totals['total'],
                'not_available' => $notAvailable,
                'currency' => $itemData['currency'] ?? 'MXN',
                'delivery_days' => $itemData['delivery_days'] ?? null,
                'payment_terms' => $itemData['payment_terms'] ?? null,
                'warranty_terms' => $itemData['warranty_terms'] ?? null,
                'brand' => $itemData['brand'] ?? null,
                'model' => $itemData['model'] ?? null,
                'specifications' => $itemData['specifications'] ?? null,
                'notes' => $itemData['notes'] ?? null,
                'supplier_quotation_number' => $itemData['supplier_quotation_number'] ?? null,
                'validity_days' => $itemData['validity_days'] ?? 30,
                'status' => $action === 'submit' ? 'SUBMITTED' : 'DRAFT',
                'submitted_at' => $action === 'submit' ? now() : null,
                'quotation_date' => $action === 'submit' ? now() : null,
            ]
        );
    }

    /**
     * Maneja la subida de archivos adjuntos por partida
     */
    private function handleItemAttachment(RfqResponse $response, array $itemData, int $supplierId): void
    {
        if (!isset($itemData['attachment'])) {
            return;
        }

        if ($response->attachment_path && Storage::disk('public')->exists($response->attachment_path)) {
            Storage::disk('public')->delete($response->attachment_path);
        }

        $path = $itemData['attachment']->store("suppliers/{$supplierId}/quotations", 'public');
        $response->update(['attachment_path' => $path]);
    }

    /**
     * Maneja el PDF de cotización global del proveedor
     */
    private function handleQuotationPdf(Request $request, Rfq $rfq, int $supplierId): void
    {
        $pivot = DB::table('rfq_suppliers')
            ->where('rfq_id', $rfq->id)
            ->where('supplier_id', $supplierId)
            ->first();

        if (!$pivot) {
            return;
        }

        // Eliminar PDF si se marcó
        if ($request->input('delete_pdf_flag') == '1') {
            $this->deleteQuotationPdf($pivot);
            Log::info("PDF de cotización eliminado para Proveedor {$supplierId} en RFQ {$rfq->id}");
            return;
        }

        // Guardar nuevo PDF
        if ($request->hasFile('quotation_pdf_file')) {
            $this->saveNewQuotationPdf($pivot, $request, $rfq, $supplierId);
            Log::info("PDF de cotización guardado para Proveedor {$supplierId} en RFQ {$rfq->id}");
        }
    }

    /**
     * Elimina el PDF de cotización existente
     */
    private function deleteQuotationPdf(object $pivot): void
    {
        if ($pivot->quotation_pdf_path && Storage::disk('public')->exists($pivot->quotation_pdf_path)) {
            Storage::disk('public')->delete($pivot->quotation_pdf_path);
        }

        DB::table('rfq_suppliers')
            ->where('rfq_id', $pivot->rfq_id)
            ->where('supplier_id', $pivot->supplier_id)
            ->update([
                'quotation_pdf_path' => null,
                'updated_at' => now()
            ]);
    }

    /**
     * Guarda un nuevo PDF de cotización
     */
    private function saveNewQuotationPdf(object $pivot, Request $request, Rfq $rfq, int $supplierId): void
    {
        if ($pivot->quotation_pdf_path && Storage::disk('public')->exists($pivot->quotation_pdf_path)) {
            Storage::disk('public')->delete($pivot->quotation_pdf_path);
        }

        $pdfPath = $request->file('quotation_pdf_file')->store(
            "suppliers/{$supplierId}/rfq_{$rfq->id}",
            'public'
        );

        DB::table('rfq_suppliers')
            ->where('rfq_id', $rfq->id)
            ->where('supplier_id', $supplierId)
            ->update([
                'quotation_pdf_path' => $pdfPath,
                'updated_at' => now()
            ]);
    }

    /**
     * Actualiza el pivote rfq_suppliers cuando se envía una cotización
     */
    private function updateRfqPivot(Rfq $rfq, int $supplierId): void
    {
        $rfq->suppliers()->updateExistingPivot($supplierId, [
            'responded_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Verifica si todos los proveedores respondieron y actualiza el estado de la RFQ
     */
    private function checkRfqCompletion(Rfq $rfq): void
    {
        $rfq->refreshCompletionStatus();
    }

    /**
     * Procesa todas las partidas de la cotización
     */
    private function processQuotationItems(
        Rfq $rfq,
        int $supplierId,
        array $items,
        string $action
    ): void {
        foreach ($items as $itemData) {
            $itemId = $itemData['item_id'];

            // Verificar estatus actual en BD
            $existingResponse = RfqResponse::where([
                'rfq_id' => $rfq->id,
                'supplier_id' => $supplierId,
                'requisition_item_id' => $itemId,
            ])->first();

            // Si ya existe y NO es borrador, saltamos esta partida
            if ($existingResponse && $existingResponse->status !== 'DRAFT') {
                continue;
            }

            // Agregar datos comunes a todas las partidas
            $itemData['supplier_quotation_number'] = $itemData['supplier_quotation_number'] ?? null;
            $itemData['validity_days'] = $itemData['validity_days'] ?? 30;

            $response = $this->saveQuotationItem($rfq, $supplierId, $itemId, $itemData, $action);
            $this->handleItemAttachment($response, $itemData, $supplierId);
        }
    }
    /**
     * Dashboard del proveedor - Lista de RFQs asignadas
     * 
     * @return \Illuminate\View\View
     */
    public function dashboard()
    {
        $supplier = Auth::guard('supplier')->user();

        if (!$supplier) {
            abort(403, 'No tienes un perfil de proveedor asociado');
        }

        // Obtener RFQs donde este proveedor está invitado
        return view('supplier.dashboard', [
            'dashboard' => app(DashboardService::class)->buildForSupplier($supplier),
        ]);
    }

    /**
     * Ver detalle de una RFQ específica
     * 
     * @param Rfq $rfq
     * @return \Illuminate\View\View
     */
    public function showRfq(Rfq $rfq)
    {
        $supplier = Auth::guard('supplier')->user();

        if (!$supplier) {
            abort(403, 'No tienes un perfil de proveedor asociado');
        }

        // Verificar que el proveedor esté invitado a esta RFQ
        if (!$rfq->suppliers->contains($supplier->id)) {
            abort(403, 'No tienes acceso a esta RFQ');
        }

        // Cargar relaciones
        $rfq->load([
            'requisition.company',
            'requisition.receivingLocation',
            'quotationGroup.items.expenseCategory',
            'quotationGroup.items.productService',
            'requisitionItem.expenseCategory',
            'requisitionItem.productService',
        ]);

        // Obtener respuestas existentes de este proveedor
        $responses = RfqResponse::where('rfq_id', $rfq->id)
            ->where('supplier_id', $supplier->id)
            ->with('requisitionItem')
            ->get()
            ->keyBy('requisition_item_id');

        // Obtener partidas a cotizar
        if ($rfq->quotation_group_id) {
            $items = $rfq->quotationGroup->items;
        } elseif ($rfq->requisition_item_id) {
            $items = collect([$rfq->requisitionItem]);
        } else {
            $items = collect([]);
        }

        return view('supplier.rfq-detail', compact('rfq', 'supplier', 'items', 'responses'));
    }

    /**
     * Guardar cotización (borrador o envío final)
     *
     * Esta función maneja AMBAS acciones:
     * 1. Guardar como BORRADOR (sin enviar)
     * 2. Enviar cotización FINAL (ya no se puede editar)
     *
     * La diferencia está en el campo 'action' que viene del formulario:
     * - action='save_draft' → Guarda como BORRADOR (status='DRAFT', submitted_at=null)
     * - action='submit' → Envía cotización (status='SUBMITTED', submitted_at=ahora)
     *
     * @param Request $request - Datos del formulario
     * @param Rfq $rfq - RFQ a la que se está respondiendo
     * @return \Illuminate\Http\RedirectResponse
     */
    public function saveQuotation(Request $request, Rfq $rfq)
    {
        // =========================================================================
        // PASO 1: VERIFICAR ACCESO DEL PROVEEDOR
        // =========================================================================
        $this->verifySupplierAccess($rfq);
        $supplier = Auth::guard('supplier')->user();

        // =========================================================================
        // PASO 2: VALIDAR DATOS DEL FORMULARIO
        // =========================================================================
        $validated = $this->validateQuotationData($request);

        // Si no hay nada que procesar y es un envío, avisamos al usuario
        if (empty($validated['items']) && $validated['action'] === 'submit') {
            return back()->with('error', 'No hay partidas nuevas o editables para enviar.');
        }

        // =========================================================================
        // PASO 3: PROCESAR COTIZACIÓN
        // =========================================================================
        DB::beginTransaction();
        try {
            // Procesar partidas
            if (!empty($validated['items'])) {
                $this->processQuotationItems($rfq, $supplier->id, $validated['items'], $validated['action']);
            }

            // Manejar PDF global de cotización
            $this->handleQuotationPdf($request, $rfq, $supplier->id);

            // Actualizar pivote y verificar completado si se envió
            if ($validated['action'] === 'submit') {
                $this->updateRfqPivot($rfq, $supplier->id);
                $this->checkRfqCompletion($rfq);
            }

            DB::commit();

            $message = $validated['action'] === 'submit'
                ? '<i class="ti ti-circle-check-filled text-success me-2"></i>La cotización de las partidas pendientes ha sido enviada.'
                : '<i class="ti ti-device-floppy text-info me-2"></i>Cambios guardados en borrador correctamente.';

            return redirect()
                ->route('supplier.rfq.show', $rfq)
                ->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en saveQuotation', ['error' => $e->getMessage()]);

            return back()
                ->withInput()
                ->with('error', 'Error en la operación: ' . $e->getMessage());
        }
    }

    /**
     * Ver historial de cotizaciones del proveedor
     * 
     * @return \Illuminate\View\View
     */
    public function quotationHistory()
    {
        $supplier = Auth::guard('supplier')->user();

        $responses = RfqResponse::where('supplier_id', $supplier->id)
            ->with([
                'rfq.requisition',
                'requisitionItem',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('supplier.quotation-history', compact('responses', 'supplier'));
    }

    /**
     * Descargar archivo adjunto de cotización
     * 
     * @param RfqResponse $response
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadAttachment(RfqResponse $response)
    {
        $supplier = Auth::guard('supplier')->user();

        // Verificar que la respuesta pertenezca al proveedor
        if ($response->supplier_id !== $supplier->id) {
            abort(403, 'No tienes acceso a este archivo');
        }

        if (!$response->attachment_path || !Storage::disk('public')->exists($response->attachment_path)) {
            abort(404, 'Archivo no encontrado');
        }

        return Storage::disk('public')->download($response->attachment_path);
    }

    /**
     * Eliminar borrador de cotización
     * 
     * @param RfqResponse $response
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteDraft(RfqResponse $response)
    {
        $supplier = Auth::guard('supplier')->user();

        // Verificar acceso y que sea borrador
        if ($response->supplier_id !== $supplier->id) {
            abort(403, 'No tienes acceso a esta cotización');
        }

        if ($response->status !== 'DRAFT') {
            return back()->with('error', 'Solo puedes eliminar borradores');
        }

        // ✅ Eliminar archivo adjunto si existe (usando el helper del modelo)
        if ($response->attachment_path) {
            Storage::disk('public')->delete($response->attachment_path);
        }

        $response->delete();

        return back()->with('success', 'Borrador eliminado correctamente');
    }
}
