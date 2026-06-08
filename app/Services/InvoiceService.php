<?php

namespace App\Services;

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
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(
        private CfdiXmlParser $parser,
        private FinancialProvisionService $financialProvisionService,
    ) {
    }

    public function upload(
        Supplier $supplier,
        Model $order,
        UploadedFile $xmlFile,
        UploadedFile $pdfFile,
        ?User $uploader,
        string $origin,
    ): SupplierInvoice {
        $data = $this->parser->parse($xmlFile->get());
        $this->validateInvoiceData($supplier, $data);

        return DB::transaction(function () use ($supplier, $order, $xmlFile, $pdfFile, $uploader, $origin, $data) {
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
                    'uploaded_origin' => $origin,
                    'status' => SupplierInvoice::STATUS_UPLOADED,
                ]);
            } catch (\Throwable $e) {
                Storage::disk('public')->delete([$xmlPath, $pdfPath]);
                throw $e;
            }

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
                $this->notifyAccounting(new SupplierInvoiceUploadedNotification($invoice));
            }

            return $invoice->fresh(['financialProvision', 'receivable']);
        });
    }

    public function reject(SupplierInvoice $invoice, string $reason): SupplierInvoice
    {
        $invoice->update([
            'status' => SupplierInvoice::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

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

    private function validateInvoiceData(Supplier $supplier, array $data): void
    {
        if (($data['issuer_rfc'] ?? '') !== strtoupper((string) $supplier->rfc)) {
            throw ValidationException::withMessages([
                'xml_file' => 'El RFC emisor del XML no coincide con el RFC del proveedor.',
            ]);
        }

        if (SupplierInvoice::where('uuid', $data['uuid'])->exists()) {
            throw ValidationException::withMessages([
                'xml_file' => 'El UUID de esta factura ya fue registrado.',
            ]);
        }
    }

    private function notifyAccounting(object $notification): void
    {
        $users = User::role('accounting')->get();
        if ($users->isNotEmpty()) {
            Notification::send($users, $notification);
        }
    }
}
