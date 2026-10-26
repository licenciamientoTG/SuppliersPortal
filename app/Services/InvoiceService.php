<?php

namespace App\Services;

use App\Models\CfdiRejectionCode;
use App\Models\CfdiValidationLog;
use App\Models\Company;
use App\Models\DirectPurchaseOrder;
use App\Models\FinancialProvision;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Notifications\SupplierInvoiceUploadedNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class InvoiceService
{
    public function __construct(
        private CfdiXmlParser $parser,
        private FinancialProvisionService $financialProvisionService,
        private CfdiValidationLogger $validationLogger = new CfdiValidationLogger,
    ) {}

    public function upload(
        Supplier $supplier,
        Model $order,
        UploadedFile $xmlFile,
        UploadedFile $pdfFile,
        ?User $uploader,
        string $origin,
    ): SupplierInvoice {
        $xmlContents = $xmlFile->get();

        $logContext = [
            'xml_sha256' => hash('sha256', $xmlContents),
            'channel' => $origin === SupplierInvoice::ORIGIN_SUPPLIER
                ? CfdiValidationLog::CHANNEL_PORTAL
                : CfdiValidationLog::CHANNEL_INTERNAL,
            'supplier_id' => $supplier->id,
            'user_id' => $uploader?->id,
        ];

        try {
            $data = $this->parser->parse($xmlContents);
        } catch (RuntimeException $exception) {
            $this->logRejection($logContext, [], CfdiRejectionCode::XML_MALFORMADO);

            throw ValidationException::withMessages([
                'xml_file' => $exception->getMessage(),
            ]);
        }

        $this->validateInvoiceData($supplier, $data, $logContext);

        return DB::transaction(function () use ($supplier, $order, $xmlFile, $pdfFile, $uploader, $origin, $data, $logContext) {
            $xmlPath = $xmlFile->store("supplier-invoices/{$supplier->id}/xml", 'public');
            $pdfPath = $pdfFile->store("supplier-invoices/{$supplier->id}/pdf", 'public');

            try {
                $invoice = SupplierInvoice::create([
                    'supplier_id' => $supplier->id,
                    'receivable_type' => get_class($order),
                    'receivable_id' => $order->id,
                    'uuid' => $data['uuid'],
                    'xml_path' => $xmlPath,
                    'pdf_path' => $pdfPath,
                    'issuer_rfc' => $data['issuer_rfc'],
                    'receiver_rfc' => $data['receiver_rfc'],
                    'subtotal' => $data['subtotal'],
                    'iva_amount' => $data['iva_amount'],
                    'total' => $data['total'],
                    'currency' => $data['currency'] ?: ($order->currency ?? 'MXN'),
                    'issued_at' => $data['issued_at'],
                    'uploaded_by' => $uploader?->id,
                    'uploaded_by_supplier_id' => $origin === SupplierInvoice::ORIGIN_SUPPLIER ? $supplier->id : null,
                    'uploaded_origin' => $origin,
                    'status' => SupplierInvoice::STATUS_UPLOADED,
                ]);
            } catch (\Throwable $e) {
                Storage::disk('public')->delete([$xmlPath, $pdfPath]);
                throw $e;
            }

            $this->validationLogger->log(array_merge($logContext, [
                'uuid' => $data['uuid'],
                'issuer_rfc' => $data['issuer_rfc'],
                'issuer_name' => $data['issuer_name'] ?? null,
                'receiver_rfc' => $data['receiver_rfc'],
                'company_id' => $this->validationLogger->resolveCompanyId($data['receiver_rfc']),
                'cfdi_type' => $data['cfdi_type'] ?? null,
                'total' => $data['total'],
                'currency' => $data['currency'],
                'result' => CfdiValidationLog::RESULT_ACCEPTED,
                'supplier_invoice_id' => $invoice->id,
            ]));

            $provision = FinancialProvision::where('supplier_id', $supplier->id)
                ->where('receivable_type', get_class($order))
                ->where('receivable_id', $order->id)
                ->whereIn('status', [
                    FinancialProvision::STATUS_PENDING_INVOICE,
                    FinancialProvision::STATUS_DISCREPANCY_REVIEW,
                ])
                ->whereNull('supplier_invoice_id')
                ->oldest()
                ->first();

            if ($provision) {
                $this->financialProvisionService->reconcile($provision, $invoice);
            } elseif ($origin === SupplierInvoice::ORIGIN_SUPPLIER) {
                $this->notifyAccounting(new SupplierInvoiceUploadedNotification($invoice), $invoice);
            }

            return $invoice->fresh(['financialProvision', 'receivable']);
        });
    }

    public function reject(SupplierInvoice $invoice, string $reason): SupplierInvoice
    {
        $invoice->markRejected($reason);

        return $invoice->fresh();
    }

    public function resolveOrder(string $type, int $id): PurchaseOrder|DirectPurchaseOrder
    {
        return match ($type) {
            'direct' => DirectPurchaseOrder::findOrFail($id),
            'standard' => PurchaseOrder::findOrFail($id),
            default => throw ValidationException::withMessages(['order_type' => 'Tipo de orden inválido.']),
        };
    }

    private function validateInvoiceData(Supplier $supplier, array $data, array $logContext): void
    {
        if (($data['issuer_rfc'] ?? '') === '') {
            $this->logRejection($logContext, $data, CfdiRejectionCode::RFC_EMISOR_AUSENTE);

            throw ValidationException::withMessages([
                'xml_file' => 'El XML no contiene el RFC del emisor.',
            ]);
        }

        if (($data['receiver_rfc'] ?? '') === '') {
            $this->logRejection($logContext, $data, CfdiRejectionCode::RFC_RECEPTOR_AUSENTE);

            throw ValidationException::withMessages([
                'xml_file' => 'El XML no contiene el RFC del receptor.',
            ]);
        }

        if (($data['issuer_rfc'] ?? '') !== strtoupper((string) $supplier->rfc)) {
            $this->logRejection($logContext, $data, CfdiRejectionCode::RFC_NO_COINCIDE);

            throw ValidationException::withMessages([
                'xml_file' => 'El RFC emisor del XML no coincide con el RFC del proveedor.',
            ]);
        }

        $efosStatus = $supplier->efos_status;
        if (in_array($efosStatus, ['Definitivo', 'Presunto'], true)) {
            $this->logRejection($logContext, $data, CfdiRejectionCode::EFOS_69B);

            throw ValidationException::withMessages([
                'xml_file' => "El proveedor aparece en la lista 69-B del SAT (EFOS) con situación {$efosStatus}. No se puede registrar la factura.",
            ]);
        }

        $activeCompanyRfcs = Company::query()
            ->where('is_active', true)
            ->whereNotNull('rfc')
            ->pluck('rfc')
            ->map(fn (string $rfc) => strtoupper(trim($rfc)));

        if ($activeCompanyRfcs->isNotEmpty() && ! $activeCompanyRfcs->contains($data['receiver_rfc'])) {
            $this->logRejection($logContext, $data, CfdiRejectionCode::RFC_RECEPTOR_INVALIDO);

            throw ValidationException::withMessages([
                'xml_file' => 'El RFC receptor del XML no corresponde a una empresa activa de TotalGas.',
            ]);
        }

        if (($data['total'] ?? 0) <= 0) {
            $this->logRejection($logContext, $data, CfdiRejectionCode::TOTAL_INVALIDO);

            throw ValidationException::withMessages([
                'xml_file' => 'El XML no contiene un total válido.',
            ]);
        }

        if (SupplierInvoice::where('uuid', $data['uuid'])->exists()) {
            $this->logRejection($logContext, $data, CfdiRejectionCode::UUID_DUPLICADO);

            throw ValidationException::withMessages([
                'xml_file' => 'El UUID de esta factura ya fue registrado.',
            ]);
        }
    }

    /**
     * Escribe el intento rechazado en cfdi_validation_logs (RF-01). $data puede venir
     * incompleto (p. ej. XML_MALFORMADO, antes de que exista cualquier dato parseado).
     */
    private function logRejection(array $logContext, array $data, string $rejectionCode): void
    {
        $receiverRfc = $data['receiver_rfc'] ?? null;

        $this->validationLogger->log(array_merge($logContext, [
            'uuid' => $data['uuid'] ?? null,
            'issuer_rfc' => $data['issuer_rfc'] ?? null,
            'issuer_name' => $data['issuer_name'] ?? null,
            'receiver_rfc' => $receiverRfc,
            'company_id' => $this->validationLogger->resolveCompanyId($receiverRfc),
            'cfdi_type' => $data['cfdi_type'] ?? null,
            'total' => $data['total'] ?? null,
            'currency' => $data['currency'] ?? null,
            'result' => CfdiValidationLog::RESULT_REJECTED,
            'rejection_code' => $rejectionCode,
        ]));
    }

    private function notifyAccounting(object $notification, ?SupplierInvoice $invoice = null): void
    {
        try {
            $users = User::role('accounting')->get();
            if ($users->isNotEmpty()) {
                Notification::send($users, $notification);
            }
        } catch (Throwable $exception) {
            Log::error('Failed to notify accounting after supplier invoice upload.', [
                'supplier_invoice_id' => $invoice?->id,
                'uuid' => $invoice?->uuid,
                'supplier_id' => $invoice?->supplier_id,
                'exception' => $exception,
            ]);
        }
    }
}
