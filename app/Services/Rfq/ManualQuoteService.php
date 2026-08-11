<?php

namespace App\Services\Rfq;

use App\Exceptions\Rfq\AwardNotAllowedException;
use App\Exceptions\Rfq\DuplicateSupplierRfcException;
use App\Models\QuotationGroup;
use App\Models\Requisition;
use App\Models\Rfq;
use App\Models\RfqResponse;
use App\Models\Supplier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Captura manual de cotizaciones por el comprador (entry_source=buyer_manual),
 * incluyendo el alta de proveedores externos. Compartido por wizard y tablero.
 */
class ManualQuoteService
{
    private const DIRECT_AWARD_JUSTIFICATION = 'Adjudicación directa por precio conocido capturado manualmente.';

    public function __construct(
        private RfqDraftService $drafts,
        private RfqAwardService $awards,
        private BudgetBlockedNoticeService $budgetBlockedNotices,
    ) {}

    /**
     * Reglas de validación Livewire para las partidas capturadas,
     * parametrizadas por el prefijo de la propiedad del componente.
     */
    public static function itemRules(string $prefix = 'manualQuoteItems'): array
    {
        return [
            $prefix => 'required|array|min:1',
            "{$prefix}.*.not_available" => 'boolean',
            "{$prefix}.*.unit_price" => ["exclude_if:{$prefix}.*.not_available,true", 'required', 'numeric', 'min:0'],
            "{$prefix}.*.iva_rate" => ["exclude_if:{$prefix}.*.not_available,true", 'required', 'numeric', 'in:0,8,16'],
            "{$prefix}.*.currency" => 'nullable|string|in:MXN,USD,EUR',
            "{$prefix}.*.delivery_days" => ["exclude_if:{$prefix}.*.not_available,true", 'required', 'integer', 'min:0'],
            "{$prefix}.*.payment_terms" => 'nullable|string|max:255',
            "{$prefix}.*.warranty_terms" => 'nullable|string|max:500',
            "{$prefix}.*.brand" => 'nullable|string|max:100',
            "{$prefix}.*.model" => 'nullable|string|max:100',
            "{$prefix}.*.specifications" => 'nullable|string',
        ];
    }

    /**
     * Reglas de validación Livewire para el alta de proveedor externo nuevo.
     */
    public static function newSupplierRules(string $prefix = 'manualQuoteNewSupplier'): array
    {
        return [
            "{$prefix}.company_name" => 'required|string|max:150',
            "{$prefix}.rfc" => 'required|string|regex:/^[A-ZÑ\&]{3,4}[0-9]{6}[A-Z0-9]{3}$/i',
            "{$prefix}.postal_code" => 'required|digits:5',
            "{$prefix}.email" => 'nullable|email|max:150|unique:suppliers,email',
            "{$prefix}.phone_number" => 'nullable|string|max:15',
        ];
    }

    /**
     * Resuelve el proveedor de una cotización manual: reusa el seleccionado
     * por id, o crea uno externo nuevo.
     *
     * @throws DuplicateSupplierRfcException si el RFC ya existe
     */
    public function resolveSupplier(?int $supplierId, array $newSupplierData): Supplier
    {
        if ($supplierId) {
            return Supplier::findOrFail($supplierId);
        }

        $rfc = strtoupper(trim($newSupplierData['rfc']));
        $existing = Supplier::where('rfc', $rfc)->first();

        if ($existing) {
            throw new DuplicateSupplierRfcException($existing);
        }

        $companyName = $newSupplierData['company_name'];

        return Supplier::create([
            'first_name' => $companyName,
            'last_name' => '',
            'email' => ($newSupplierData['email'] ?? null) ?: Str::uuid().'@externo.invalido',
            'password' => Str::random(40),
            'is_active' => false,
            'company_name' => $companyName,
            'rfc' => $rfc,
            'address' => '',
            'phone_number' => ($newSupplierData['phone_number'] ?? null) ?: '0000000000',
            'contact_person' => ($newSupplierData['contact_person'] ?? null) ?: $companyName,
            'supplier_type' => 'product_service',
            'postal_code' => $newSupplierData['postal_code'],
            'approval_status' => 'approved',
            'document_status' => 'approved',
            'is_external' => true,
        ]);
    }

    public function editableBudgetBlockedSupplierId(Rfq $rfq): ?int
    {
        if ($rfq->source !== 'external' || $rfq->status !== 'RECEIVED' || $rfq->quotationSummary()->exists()) {
            return null;
        }

        $supplierIds = $rfq->rfqResponses()
            ->where('entry_source', 'buyer_manual')
            ->where('status', 'SUBMITTED')
            ->pluck('supplier_id')
            ->unique()
            ->values();

        if ($supplierIds->count() !== 1) {
            return null;
        }

        $rfq->loadMissing(['requisition.requester', 'requisition.items.costCenter', 'rfqResponses.requisitionItem']);

        return $this->budgetBlockedNotices->isBlockedOnlyByBudget($rfq, (int) $supplierIds->first())
            ? (int) $supplierIds->first()
            : null;
    }

    /**
     * Guarda la cotización manual completa de un grupo para un proveedor.
     *
     * @param  array<int, array>  $itemsData  datos por requisition_item_id
     */
    public function save(
        Requisition $requisition,
        QuotationGroup $group,
        Supplier $supplier,
        array $itemsData,
        string $quotationDate,
        int $validityDays,
        ?UploadedFile $attachment,
        int $userId
    ): array {
        if (! $requisition->validated_at) {
            throw new \DomainException('Firma la validación técnica antes de capturar un precio conocido.');
        }

        if ((int) $group->requisition_id !== (int) $requisition->id) {
            throw new \DomainException('El grupo no pertenece a la requisición en proceso.');
        }

        $group->loadMissing('items');

        $rfq = DB::transaction(function () use ($requisition, $group, $supplier, $itemsData, $quotationDate, $validityDays, $attachment, $userId) {
            $rfq = $this->drafts->ensureExternalDraftForGroup($requisition, $group, $userId);

            $editableSupplierId = $this->editableBudgetBlockedSupplierId($rfq);
            if ($rfq->status === 'RECEIVED' && $editableSupplierId !== (int) $supplier->id) {
                throw new \DomainException('Esta compra directa sólo puede editarse cuando está bloqueada únicamente por presupuesto y conserva el proveedor original.');
            }

            $rfq->suppliers()->syncWithoutDetaching([
                $supplier->id => ['invited_at' => now(), 'responded_at' => now()],
            ]);

            $attachmentPath = $attachment
                ? $attachment->store("rfq_responses/manual/{$requisition->id}", 'public')
                : null;

            foreach ($itemsData as $itemId => $itemData) {
                $existingResponse = RfqResponse::query()
                    ->where('rfq_id', $rfq->id)
                    ->where('supplier_id', $supplier->id)
                    ->where('requisition_item_id', $itemId)
                    ->first();
                $notAvailable = (bool) ($itemData['not_available'] ?? false);
                $unitPrice = $notAvailable ? 0 : (float) $itemData['unit_price'];
                $ivaRate = $notAvailable ? 0 : (float) $itemData['iva_rate'];
                $quantity = (float) ($group->items->firstWhere('id', (int) $itemId)->quantity ?? 1);
                $subtotal = $unitPrice * $quantity;
                $ivaAmount = $subtotal * ($ivaRate / 100);

                RfqResponse::updateOrCreate(
                    [
                        'rfq_id' => $rfq->id,
                        'supplier_id' => $supplier->id,
                        'requisition_item_id' => $itemId,
                    ],
                    [
                        'quotation_date' => $quotationDate,
                        'validity_days' => $validityDays,
                        'unit_price' => $unitPrice,
                        'quantity' => $quantity,
                        'subtotal' => $subtotal,
                        'iva_rate' => $ivaRate,
                        'iva_amount' => $ivaAmount,
                        'total' => $subtotal + $ivaAmount,
                        'currency' => $itemData['currency'] ?? 'MXN',
                        'delivery_days' => $notAvailable ? null : ($itemData['delivery_days'] ?? null),
                        'payment_terms' => $itemData['payment_terms'] ?? null,
                        'warranty_terms' => $itemData['warranty_terms'] ?? null,
                        'brand' => $itemData['brand'] ?? null,
                        'model' => $itemData['model'] ?? null,
                        'specifications' => $itemData['specifications'] ?? null,
                        'not_available' => $notAvailable,
                        'attachment_path' => $attachmentPath ?? $existingResponse?->attachment_path,
                        'status' => 'SUBMITTED',
                        'submitted_at' => now(),
                        'entry_source' => 'buyer_manual',
                        'entered_by' => $userId,
                    ]
                );
            }

            $rfq->refreshCompletionStatus();

            return $rfq;
        });

        try {
            $summary = $this->awards->award(
                $rfq,
                $supplier->id,
                self::DIRECT_AWARD_JUSTIFICATION,
                null,
                $userId,
            );

            return ['rfq' => $rfq->fresh(), 'summary' => $summary, 'award_error' => null];
        } catch (AwardNotAllowedException|\Throwable $exception) {
            // La captura es evidencia válida aunque presupuesto, vigencia o
            // autorización impidan avanzar. Queda RECEIVED para corregirse.
            return ['rfq' => $rfq->fresh(), 'summary' => null, 'award_error' => $exception->getMessage()];
        }
    }
}
