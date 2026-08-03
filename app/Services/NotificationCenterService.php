<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\DirectPurchaseOrder;
use App\Models\FinancialProvision;
use App\Models\ProductService;
use App\Models\PurchaseOrder;
use App\Models\QuotationSummary;
use App\Models\Reception;
use App\Models\Requisition;
use App\Models\Rfq;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\SupplierDocumentRequirement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class NotificationCenterService
{
    /** Eventos informativos: se conservan en historial, pero no deben inflar pendientes. */
    private const INFORMATIONAL_TYPES = [
        'rfq_sent_to_suppliers', 'buyer_rfq_sent', 'rfq_cancelled_for_supplier',
        'rfq_cancelled_for_requester', 'quotation_approval_approved',
        'quotation_approval_rejected', 'direct_purchase_order_approved',
        'direct_purchase_order_rejected', 'direct_purchase_order_returned',
        'ocd_closed_by_inactivity', 'po_closed_by_inactivity', 'purchase_order_issued',
        'reception_completed', 'supplier_invoice_uploaded', 'supplier_document_accepted',
        'supplier_document_rejected', 'supplier_document_file_completed',
        'supplier_account_approved', 'supplier_account_rejected', 'staff_welcome',
        'delegated_approval_action', 'supplier_welcome', 'requisition_submitted',
        'requisition_in_quotation', 'requisition_reactivated', 'buyer_rfq_cancelled',
        'buyer_requisition_cancelled', 'buyer_quotation_pending_approval',
    ];

    public function queryForUser(User|Supplier $authenticatable): Builder
    {
        if ($authenticatable instanceof Supplier) {
            return DatabaseNotification::query()
                ->where('notifiable_type', Supplier::class)
                ->where('notifiable_id', $authenticatable->id);
        }

        $supplierId = $authenticatable->supplier?->id;

        return DatabaseNotification::query()
            ->where(function (Builder $query) use ($authenticatable, $supplierId) {
                $query->where(function (Builder $userQuery) use ($authenticatable) {
                    $userQuery
                        ->where('notifiable_type', User::class)
                        ->where('notifiable_id', $authenticatable->id);
                });

                if ($supplierId) {
                    $query->orWhere(function (Builder $supplierQuery) use ($supplierId) {
                        $supplierQuery
                            ->where('notifiable_type', Supplier::class)
                            ->where('notifiable_id', $supplierId);
                    });
                }
            });
    }

    public function unreadCountForUser(User|Supplier $authenticatable): int
    {
        return (int) $this->queryForUser($authenticatable)
            ->whereNull('read_at')
            ->count();
    }

    public function recentForUser(User|Supplier $authenticatable, int $limit = 5): Collection
    {
        return $this->queryForUser($authenticatable)
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function findForUser(User|Supplier $authenticatable, string $notificationId): ?DatabaseNotification
    {
        return $this->queryForUser($authenticatable)
            ->where('id', $notificationId)
            ->first();
    }

    public function markAllAsReadForUser(User|Supplier $authenticatable): void
    {
        $this->queryForUser($authenticatable)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Marca como leídas las alertas que ya no representan una acción.
     * Conserva el registro para auditoría en el historial.
     */
    public function resolveObsoleteUnreadForUser(User|Supplier $authenticatable): void
    {
        $this->queryForUser($authenticatable)
            ->whereNull('read_at')
            ->latest()
            ->limit(250)
            ->get()
            ->each(function (DatabaseNotification $notification) use ($authenticatable): void {
                $normalizedData = $this->normalizeLegacyData($notification->data);
                if ($normalizedData !== $notification->data) {
                    $notification->data = $normalizedData;
                    $notification->save();
                }

                if (! $this->isActionable($notification, $authenticatable)) {
                    $notification->markAsRead();
                }
            });
    }

    /** @return string|null URL segura y vigente, o null si el evento ya no tiene destino. */
    public function targetFor(DatabaseNotification $notification): ?string
    {
        $data = $this->normalizeLegacyData($notification->data);

        if (! $this->relatedResourceExists($data)) {
            return null;
        }

        $url = $data['url'] ?? null;
        if (! is_string($url) || $url === '' || $url === '#') {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return null;
        }
        $configuredHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $requestHost = request()?->getHost();
        $host = $parts['host'] ?? null;

        if ($host && ! in_array($host, array_filter([$configuredHost, $requestHost]), true)) {
            return null;
        }

        $path = $parts['path'] ?? '/';
        try {
            Route::getRoutes()->match(Request::create($path, 'GET'));
        } catch (\Throwable) {
            return null;
        }

        return $url;
    }

    private function isActionable(DatabaseNotification $notification, User|Supplier $authenticatable): bool
    {
        $data = $this->normalizeLegacyData($notification->data);
        $type = $data['type'] ?? null;

        if (in_array($type, self::INFORMATIONAL_TYPES, true)) {
            return false;
        }

        return match ($type) {
            'new_rfq' => $authenticatable instanceof Supplier
                && Rfq::query()->whereKey($data['rfq_id'] ?? null)
                    ->whereHas('suppliers', fn (Builder $query) => $query
                        ->where('suppliers.id', $authenticatable->id)
                        ->whereNull('rfq_suppliers.responded_at'))
                    ->exists(),
            'new_requisition_for_purchasing' => Requisition::query()
                ->whereKey($data['requisition_id'] ?? null)
                ->where('status', 'PENDING')
                ->exists(),
            'quotation_approval_request' => QuotationSummary::query()
                ->whereKey($data['summary_id'] ?? null)
                ->where('approval_status', 'pending')
                ->exists(),
            'new_direct_purchase_order', 'contract_po_pending_approval' => $this->pendingOrderExists($data),
            'financial_provision_pending_invoice' => FinancialProvision::query()
                ->whereKey($data['financial_provision_id'] ?? null)
                ->where('status', FinancialProvision::STATUS_PENDING_INVOICE)
                ->exists(),
            'financial_provision_discrepancy' => FinancialProvision::query()
                ->whereKey($data['financial_provision_id'] ?? null)
                ->where('status', FinancialProvision::STATUS_DISCREPANCY_REVIEW)
                ->exists(),
            default => true,
        };
    }

    private function pendingOrderExists(array $data): bool
    {
        $id = $data['ocd_id'] ?? $data['purchase_order_id'] ?? null;
        $model = isset($data['ocd_id']) ? DirectPurchaseOrder::class : PurchaseOrder::class;

        return $model::query()->whereKey($id)->where('status', 'PENDING_APPROVAL')->exists();
    }

    private function relatedResourceExists(array $data): bool
    {
        $references = [
            'rfq_id' => Rfq::class,
            'requisition_id' => Requisition::class,
            'summary_id' => QuotationSummary::class,
            'ocd_id' => DirectPurchaseOrder::class,
            'purchase_order_id' => PurchaseOrder::class,
            'financial_provision_id' => FinancialProvision::class,
            'contract_id' => Contract::class,
            'reception_id' => Reception::class,
            'supplier_document_id' => SupplierDocument::class,
            'requirement_id' => SupplierDocumentRequirement::class,
            'product_service_id' => ProductService::class,
            'supplier_id' => Supplier::class,
        ];

        foreach ($references as $key => $model) {
            if (array_key_exists($key, $data) && ! $model::query()->whereKey($data[$key])->exists()) {
                return false;
            }
        }

        return true;
    }

    /** Normaliza las filas históricas creadas antes de que todas las notificaciones compartieran contrato. */
    private function normalizeLegacyData(array $data): array
    {
        if (! isset($data['type']) && isset($data['financial_provision_id'])) {
            $data['type'] = isset($data['supplier_invoice_id'])
                ? 'financial_provision_discrepancy'
                : 'financial_provision_pending_invoice';
            $data['url'] ??= route('financial-provisions.show', $data['financial_provision_id']);
            $data['message'] ??= $data['type'] === 'financial_provision_discrepancy'
                ? 'Una provisión tiene una diferencia con la factura.'
                : 'Una provisión está pendiente de factura.';
        }

        if (! isset($data['type']) && isset($data['supplier_invoice_id'])) {
            $data['type'] = 'supplier_invoice_uploaded';
            $data['url'] ??= route('invoices.index');
            $data['message'] ??= 'Un proveedor cargó una factura.';
        }

        return $data;
    }
}
