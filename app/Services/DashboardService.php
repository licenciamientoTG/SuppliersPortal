<?php

namespace App\Services;

use App\Enum\RequisitionStatus;
use App\Models\BudgetMonthlyDistribution;
use App\Models\BudgetMovement;
use App\Models\DirectPurchaseOrder;
use App\Models\FinancialProvision;
use App\Models\ProductService;
use App\Models\PurchaseOrder;
use App\Models\QuotationSummary;
use App\Models\Requisition;
use App\Models\Rfq;
use App\Models\Supplier;
use App\Models\SupplierDeliveryEvidence;
use App\Models\SupplierDocument;
use App\Models\SupplierInvoice;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DashboardService
{
    private const INTERNAL_ROLE_ORDER = [
        'staff',
        'buyer',
        'authorizer',
        'receiver',
        'accounting',
        'department_head',
        'general_director',
        'catalog_admin',
        'superadmin',
    ];

    private const ROLE_LABELS = [
        'superadmin' => 'Super Administrador',
        'general_director' => 'Director General',
        'buyer' => 'Comprador',
        'accounting' => 'Contabilidad',
        'authorizer' => 'Autorizador',
        'catalog_admin' => 'Admin. de Catalogo',
        'department_head' => 'Jefe de Departamento',
        'staff' => 'Staff',
        'receiver' => 'Receptor',
        'supplier' => 'Proveedor',
    ];

    public function __construct(
        private readonly ModuleAccessService $moduleAccessService
    ) {
    }

    public function buildForUser(User $user): array
    {
        $roles = $this->resolveInternalRoles($user);

        $board = [
            'hero' => [
                'title' => 'Dashboard operativo',
                'subtitle' => 'Visibilidad accionable por rol para dar seguimiento al trabajo del portal.',
                'user_name' => $user->full_name ?: $user->name,
                'role_badges' => array_map(fn (string $role) => $this->labelForRole($role), $roles),
                'notification_summary' => $this->notificationSummaryForNotifiable($user),
            ],
            'quickActions' => [],
            'kpis' => [],
            'alerts' => [],
            'sections' => [],
        ];

        foreach ($roles as $role) {
            $roleBoard = $this->buildInternalRoleBoard($role, $user);
            $this->mergeBoard($board, $roleBoard);
        }

        if (empty($board['quickActions'])) {
            $board['quickActions'][] = $this->quickAction(
                'profile',
                'Editar perfil',
                route('profile.edit'),
                'ti-user-circle',
                'secondary'
            );
        }

        if (empty($board['kpis'])) {
            $board['alerts'][] = $this->alert(
                'empty-board',
                'secondary',
                'Sin actividad relevante',
                'Aun no hay elementos operativos asociados a tus roles actuales.',
                route('profile.edit'),
                'Ver perfil'
            );
        }

        $board['quickActions'] = array_values($board['quickActions']);
        $board['kpis'] = array_values($board['kpis']);
        $board['alerts'] = array_values($board['alerts']);
        $board['sections'] = array_values($board['sections']);

        return $board;
    }

    public function buildForSupplier(Supplier $supplier): array
    {
        $rfqs = Rfq::query()
            ->whereHas('suppliers', fn ($query) => $query->where('supplier_id', $supplier->id))
            ->with([
                'requisition:id,folio,description,status',
                'rfqResponses' => fn ($query) => $query
                    ->where('supplier_id', $supplier->id)
                    ->select('rfq_id', 'supplier_id', 'status', 'submitted_at'),
            ])
            ->latest('created_at')
            ->get();

        $recentInvoices = SupplierInvoice::query()
            ->where('supplier_id', $supplier->id)
            ->latest()
            ->limit(5)
            ->get();
        $invoiceTotal = SupplierInvoice::query()
            ->where('supplier_id', $supplier->id)
            ->count();

        $availableDeliveries = PurchaseOrder::query()
            ->where('supplier_id', $supplier->id)
            ->whereIn('status', ['ISSUED', 'PARTIALLY_RECEIVED', 'DELIVERED_PENDING_RECEPTION'])
            ->count()
            + DirectPurchaseOrder::query()
                ->where('supplier_id', $supplier->id)
                ->whereIn('status', ['ISSUED', 'PARTIALLY_RECEIVED', 'DELIVERED_PENDING_RECEPTION'])
                ->count();

        $evidences = SupplierDeliveryEvidence::query()
            ->where('uploaded_by_supplier_id', $supplier->id)
            ->count();

        $missingDocuments = $supplier->missingRequiredDocuments();
        $rfqStats = [
            'pending' => $rfqs->where('status', 'SENT')->count(),
            'draft' => DB::table('rfq_responses')
                ->where('supplier_id', $supplier->id)
                ->where('status', 'DRAFT')
                ->count(),
            'submitted' => DB::table('rfq_responses')
                ->where('supplier_id', $supplier->id)
                ->where('status', 'SUBMITTED')
                ->count(),
            'approved' => DB::table('rfq_responses')
                ->where('supplier_id', $supplier->id)
                ->where('status', 'APPROVED')
                ->count(),
        ];

        $board = [
            'hero' => [
                'title' => 'Portal de Proveedores',
                'subtitle' => 'Seguimiento rapido de RFQs, documentacion, facturacion y entregas.',
                'user_name' => $supplier->company_name,
                'role_badges' => ['Proveedor'],
                'notification_summary' => $this->notificationSummaryForNotifiable($supplier),
            ],
            'quickActions' => [
                $this->quickAction('supplier-docs', 'Documentacion', route('supplier.documents.index'), 'ti-checklist', 'warning'),
                $this->quickAction('supplier-rfqs', 'Mis RFQs', route('supplier.dashboard'), 'ti-file-invoice', 'primary'),
                $this->quickAction('supplier-invoices', 'Cargar factura', route('supplier.invoices.create'), 'ti-receipt-2', 'success'),
                $this->quickAction('supplier-deliveries', 'Registrar entrega', route('supplier.deliveries.index'), 'ti-truck-delivery', 'info'),
            ],
            'kpis' => [
                $this->kpi('supplier-pending-rfqs', 'RFQs pendientes', $rfqStats['pending'], 'ti-clock-hour-4', 'warning', 'Solicitudes abiertas por responder.'),
                $this->kpi('supplier-draft-rfqs', 'Borradores', $rfqStats['draft'], 'ti-file-text', 'secondary', 'Cotizaciones guardadas parcialmente.'),
                $this->kpi('supplier-submitted-rfqs', 'Enviadas', $rfqStats['submitted'], 'ti-send', 'info', 'Respuestas ya enviadas al portal.'),
                $this->kpi('supplier-approved-rfqs', 'Aprobadas', $rfqStats['approved'], 'ti-circle-check', 'success', 'Cotizaciones adjudicadas o aprobadas.'),
                $this->kpi('supplier-available-deliveries', 'Entregas activas', $availableDeliveries, 'ti-package-export', 'primary', 'Ordenes elegibles para registrar entrega.'),
                $this->kpi('supplier-uploaded-invoices', 'Facturas cargadas', $invoiceTotal, 'ti-file-invoice', 'success', 'Facturas recientes en seguimiento.'),
            ],
            'alerts' => [],
            'sections' => [
                [
                    'id' => 'supplier-rfq-section',
                    'title' => 'Mis RFQs recientes',
                    'icon' => 'ti-file-invoice',
                    'empty_message' => 'No tienes RFQs asignadas por el momento.',
                    'items' => $rfqs->take(5)->map(function (Rfq $rfq) use ($supplier) {
                        $response = $rfq->rfqResponses->firstWhere('supplier_id', $supplier->id);

                        return [
                            'id' => 'supplier-rfq-'.$rfq->id,
                            'title' => $rfq->folio,
                            'subtitle' => $rfq->requisition?->description ?: 'RFQ sin descripcion',
                            'meta' => $rfq->response_deadline
                                ? 'Fecha limite: '.$rfq->response_deadline->format('d/m/Y')
                                : 'Sin fecha limite definida',
                            'badge' => $response?->status ?? $rfq->status,
                            'badge_tone' => $this->toneForStatus($response?->status ?? $rfq->status),
                            'route' => route('supplier.rfq.show', $rfq),
                            'route_label' => $response?->status === 'DRAFT' ? 'Continuar' : 'Ver detalle',
                        ];
                    })->values()->all(),
                ],
                [
                    'id' => 'supplier-invoice-section',
                    'title' => 'Facturas recientes',
                    'icon' => 'ti-receipt-2',
                    'empty_message' => 'Aun no hay facturas cargadas.',
                    'items' => $recentInvoices->map(function (SupplierInvoice $invoice) {
                        return [
                            'id' => 'supplier-invoice-'.$invoice->id,
                            'title' => $invoice->uuid,
                            'subtitle' => format_money((float) $invoice->total, $invoice->currency),
                            'meta' => 'Emitida '.optional($invoice->issued_at)->format('d/m/Y'),
                            'badge' => $invoice->getStatusLabel(),
                            'badge_tone' => strtolower($invoice->getStatusBadgeClass()),
                            'route' => route('supplier.invoices.index'),
                            'route_label' => 'Ver facturas',
                        ];
                    })->values()->all(),
                ],
                [
                    'id' => 'supplier-deliveries-section',
                    'title' => 'Entregas y evidencias',
                    'icon' => 'ti-truck-delivery',
                    'empty_message' => 'No hay entregas registradas ni pendientes.',
                    'items' => collect([
                        [
                            'id' => 'supplier-deliveries-open',
                            'title' => 'Ordenes listas para entrega',
                            'subtitle' => (string) $availableDeliveries,
                            'meta' => 'Ordenes emitidas o parcialmente recibidas disponibles para registrar entrega.',
                            'badge' => $availableDeliveries > 0 ? 'Accion requerida' : 'Sin pendientes',
                            'badge_tone' => $availableDeliveries > 0 ? 'warning' : 'secondary',
                            'route' => route('supplier.deliveries.index'),
                            'route_label' => 'Ir a entregas',
                        ],
                        [
                            'id' => 'supplier-deliveries-evidences',
                            'title' => 'Evidencias cargadas',
                            'subtitle' => (string) $evidences,
                            'meta' => 'Remisiones o comprobantes de entrega subidos por tu empresa.',
                            'badge' => 'Seguimiento',
                            'badge_tone' => 'info',
                            'route' => route('supplier.deliveries.index'),
                            'route_label' => 'Ver entregas',
                        ],
                    ])->all(),
                ],
            ],
        ];

        if (! empty($missingDocuments)) {
            $board['alerts'][] = $this->alert(
                'supplier-missing-docs',
                'warning',
                'Tu alta aun requiere documentacion',
                'Tienes '.count($missingDocuments).' documento(s) faltante(s) antes de completar el proceso de alta.',
                route('supplier.documents.index'),
                'Completar documentacion'
            );
        }

        if ($supplier->document_status === 'in_review') {
            $board['alerts'][] = $this->alert(
                'supplier-review-docs',
                'info',
                'Documentacion en revision',
                'Tu informacion ya fue cargada y esta siendo revisada por el equipo de TotalGas.',
                route('supplier.documents.index'),
                'Ver documentos'
            );
        }

        if ($recentInvoices->where('status', SupplierInvoice::STATUS_UPLOADED)->isNotEmpty()) {
            $board['alerts'][] = $this->alert(
                'supplier-uploaded-invoices',
                'primary',
                'Facturas pendientes de conciliacion',
                'Tienes facturas cargadas que aun estan en seguimiento operativo.',
                route('supplier.invoices.index'),
                'Revisar facturas'
            );
        }

        return $board;
    }

    private function buildInternalRoleBoard(string $role, User $user): array
    {
        return match ($role) {
            'staff' => $this->buildStaffBoard($user),
            'buyer' => $this->buildBuyerBoard($user),
            'authorizer' => $this->buildAuthorizerBoard($user),
            'receiver' => $this->buildReceiverBoard(),
            'accounting' => $this->buildAccountingBoard(),
            'department_head' => $this->buildDepartmentHeadBoard(),
            'general_director' => $this->buildGeneralDirectorBoard($user),
            'catalog_admin' => $this->buildCatalogAdminBoard(),
            'superadmin' => $this->buildSuperadminBoard(),
            default => [],
        };
    }

    private function buildStaffBoard(User $user): array
    {
        $myRequisitions = Requisition::query()
            ->where('requested_by', $user->id)
            ->latest()
            ->limit(5)
            ->get();

        return [
            'quickActions' => [
                $this->quickAction('staff-new-requisition', 'Nueva requisicion', route('requisitions.create'), 'ti-plus', 'primary'),
                $this->quickAction('staff-list-requisitions', 'Mis requisiciones', route('requisitions.index'), 'ti-clipboard-list', 'secondary'),
                $this->quickAction('staff-contract-requisition', 'Req. por contrato', route('contracts.requisition.create'), 'ti-file-invoice', 'info'),
            ],
            'kpis' => [
                $this->kpi('staff-draft', 'Mis borradores', Requisition::query()->where('requested_by', $user->id)->draft()->count(), 'ti-file-draft', 'secondary', 'Requisiciones aun no enviadas a Compras.'),
                $this->kpi('staff-pending', 'Pendientes', Requisition::query()->where('requested_by', $user->id)->where('status', RequisitionStatus::PENDING->value)->count(), 'ti-clock-hour-4', 'warning', 'Requisiciones esperando validacion o seguimiento.'),
                $this->kpi('staff-paused', 'Pausadas', Requisition::query()->where('requested_by', $user->id)->paused()->count(), 'ti-player-pause', 'info', 'Esperan catalogo u otro desbloqueo.'),
                $this->kpi('staff-rejected', 'Rechazadas', Requisition::query()->where('requested_by', $user->id)->rejected()->count(), 'ti-circle-x', 'danger', 'Requieren correccion antes de reenviar.'),
            ],
            'sections' => [
                [
                    'id' => 'staff-requisitions',
                    'title' => 'Mis requisiciones recientes',
                    'icon' => 'ti-clipboard-list',
                    'empty_message' => 'Aun no has creado requisiciones.',
                    'items' => $myRequisitions->map(function (Requisition $requisition) {
                        return [
                            'id' => 'staff-req-'.$requisition->id,
                            'title' => $requisition->folio,
                            'subtitle' => Str::limit($requisition->description ?: 'Sin descripcion', 90),
                            'meta' => 'Actualizada '.$requisition->updated_at?->diffForHumans(),
                            'badge' => $requisition->statusLabel(),
                            'badge_tone' => $requisition->status->badgeClass(),
                            'route' => route('requisitions.show', $requisition),
                            'route_label' => 'Ver requisicion',
                        ];
                    })->values()->all(),
                ],
            ],
        ];
    }

    private function buildBuyerBoard(User $user): array
    {
        $activeRequisitions = Requisition::query()
            ->whereIn('status', [
                RequisitionStatus::PENDING->value,
                RequisitionStatus::IN_QUOTATION->value,
                RequisitionStatus::QUOTED->value,
                RequisitionStatus::PENDING_BUDGET_ADJUSTMENT->value,
            ]);

        $expiredRfqs = Rfq::query()
            ->where('status', 'SENT')
            ->whereNotNull('response_deadline')
            ->where('response_deadline', '<', now())
            ->count();

        $pendingResponses = DB::table('rfq_suppliers')
            ->join('rfqs', 'rfqs.id', '=', 'rfq_suppliers.rfq_id')
            ->whereIn('rfqs.status', ['SENT', 'RECEIVED', 'EVALUATED'])
            ->whereNull('rfq_suppliers.responded_at')
            ->count();

        $pendingApprovals = QuotationSummary::query()->pending()->assignedTo($user->id);
        $openOrderStatuses = ['ISSUED', 'PARTIALLY_RECEIVED', 'DELIVERED_PENDING_RECEPTION'];
        $pendingDocs = SupplierDocument::query()->where('status', 'pending_review')->count();

        return [
            'quickActions' => [
                $this->quickAction('buyer-rfqs', 'Gestionar RFQs', route('rfq.index'), 'ti-file-invoice', 'primary'),
                $this->quickAction('buyer-inbox', 'Pendientes de respuesta', route('rfq.inbox.pending'), 'ti-inbox', 'warning'),
                $this->quickAction('buyer-orders', 'Ordenes de compra', route('purchase-orders.index'), 'ti-shopping-cart', 'info'),
                $this->quickAction('buyer-doc-review', 'Revision documental', route('admin.review.index'), 'ti-files', 'success'),
            ],
            'kpis' => [
                $this->kpi('buyer-active-requisitions', 'Requisiciones activas', $activeRequisitions->count(), 'ti-clipboard-list', 'primary', 'Requisiciones en proceso comercial o de aprobacion.'),
                $this->kpi('buyer-rfq-draft', 'RFQs en borrador', Rfq::query()->where('status', 'DRAFT')->count(), 'ti-edit', 'secondary', 'Borradores listos para completar y enviar.'),
                $this->kpi('buyer-rfq-sent', 'RFQs enviadas', Rfq::query()->where('status', 'SENT')->count(), 'ti-send', 'info', 'Solicitudes activas enviadas a proveedores.'),
                $this->kpi('buyer-rfq-expired', 'RFQs vencidas', $expiredRfqs, 'ti-alert-triangle', 'danger', 'Solicitudes con fecha limite superada.'),
                $this->kpi('buyer-pending-approvals', 'Por aprobar', $pendingApprovals->count(), 'ti-stamp', 'warning', 'Adjudicaciones esperando aprobacion actual.'),
                $this->kpi('buyer-open-orders', 'Ordenes abiertas', PurchaseOrder::query()->whereIn('status', $openOrderStatuses)->count() + DirectPurchaseOrder::query()->whereIn('status', $openOrderStatuses)->count(), 'ti-package-export', 'success', 'Ordenes emitidas o en recepcion.'),
            ],
            'alerts' => [
                $pendingDocs > 0 ? $this->alert('buyer-pending-docs', 'warning', 'Documentos de proveedor pendientes', "Hay {$pendingDocs} documento(s) esperando revision.", route('admin.review.index'), 'Revisar documentos') : null,
                $pendingResponses > 0 ? $this->alert('buyer-pending-responses', 'info', 'Respuestas de proveedores pendientes', "Existen {$pendingResponses} invitaciones de proveedor sin respuesta.", route('rfq.inbox.pending'), 'Ir al inbox') : null,
            ],
            'sections' => [
                [
                    'id' => 'buyer-attention',
                    'title' => 'Bandeja operativa de compras',
                    'icon' => 'ti-briefcase',
                    'empty_message' => 'No hay elementos prioritarios en este momento.',
                    'items' => collect()
                        ->merge(
                            $pendingApprovals->latest()->limit(3)->get()->map(function (QuotationSummary $summary) {
                                return [
                                    'id' => 'buyer-summary-'.$summary->id,
                                    'title' => $summary->requisition?->folio ?: 'Adjudicacion pendiente',
                                    'subtitle' => format_money((float) $summary->total),
                                    'meta' => 'Aprobador actual: '.($summary->currentApprover?->full_name ?: 'Sin asignar'),
                                    'badge' => $summary->approval_status_label,
                                    'badge_tone' => 'warning',
                                    'route' => route('approvals.quotations.index'),
                                    'route_label' => 'Abrir aprobaciones',
                                ];
                            })
                        )
                        ->merge(
                            Rfq::query()
                                ->where('status', 'SENT')
                                ->whereNotNull('response_deadline')
                                ->orderBy('response_deadline')
                                ->limit(3)
                                ->get()
                                ->map(function (Rfq $rfq) {
                                    return [
                                        'id' => 'buyer-rfq-'.$rfq->id,
                                        'title' => $rfq->folio,
                                        'subtitle' => $rfq->requisition?->folio ?: 'RFQ activa',
                                        'meta' => 'Fecha limite: '.optional($rfq->response_deadline)->format('d/m/Y'),
                                        'badge' => $rfq->isExpired() ? 'Vencida' : 'Activa',
                                        'badge_tone' => $rfq->isExpired() ? 'danger' : 'info',
                                        'route' => route('rfq.show', $rfq),
                                        'route_label' => 'Ver RFQ',
                                    ];
                                })
                        )
                        ->values()
                        ->all(),
                ],
            ],
        ];
    }

    private function buildAuthorizerBoard(User $user): array
    {
        $quotationApprovals = QuotationSummary::query()->pending()->assignedTo($user->id);
        $directOrders = DirectPurchaseOrder::query()->assignedToApprover($user->id);
        $criticalBudgets = $this->criticalBudgetCount();

        return [
            'quickActions' => [
                $this->quickAction('authorizer-quotations', 'Aprobar cotizaciones', route('approvals.quotations.index'), 'ti-stamp', 'warning'),
                $this->quickAction('authorizer-orders', 'Ordenes de compra', route('purchase-orders.index'), 'ti-shopping-cart', 'primary'),
                $this->quickAction('authorizer-budget', 'Control presupuestal', route('budget_movements.dashboard'), 'ti-chart-bar', 'info'),
            ],
            'kpis' => [
                $this->kpi('authorizer-quotation-pending', 'Cotizaciones asignadas', $quotationApprovals->count(), 'ti-stamp', 'warning', 'Aprobaciones comerciales en tu bandeja.'),
                $this->kpi('authorizer-direct-orders', 'Compras directas pendientes', $directOrders->count(), 'ti-file-dollar', 'primary', 'OCDs pendientes de aprobacion.'),
                $this->kpi('authorizer-budget-pending', 'Movimientos pendientes', BudgetMovement::query()->pending()->count(), 'ti-arrows-transfer-up-down', 'info', 'Movimientos presupuestales por revisar.'),
                $this->kpi('authorizer-budget-critical', 'Alertas de presupuesto', $criticalBudgets, 'ti-alert-triangle', 'danger', 'Distribuciones criticas o agotadas.'),
            ],
            'sections' => [
                [
                    'id' => 'authorizer-queue',
                    'title' => 'Elementos por resolver',
                    'icon' => 'ti-list-check',
                    'empty_message' => 'No tienes autorizaciones pendientes.',
                    'items' => collect()
                        ->merge($quotationApprovals->latest()->limit(3)->get()->map(function (QuotationSummary $summary) {
                            return [
                                'id' => 'authorizer-quotation-'.$summary->id,
                                'title' => $summary->requisition?->folio ?: 'Adjudicacion pendiente',
                                'subtitle' => format_money((float) $summary->total),
                                'meta' => 'Solicitada por '.($summary->requester?->full_name ?: 'N/A'),
                                'badge' => 'Cotizacion',
                                'badge_tone' => 'warning',
                                'route' => route('approvals.quotations.index'),
                                'route_label' => 'Abrir bandeja',
                            ];
                        }))
                        ->merge($directOrders->latest()->limit(3)->get()->map(function (DirectPurchaseOrder $order) {
                            return [
                                'id' => 'authorizer-dpo-'.$order->id,
                                'title' => $order->folio,
                                'subtitle' => format_money((float) $order->total, $order->currency),
                                'meta' => 'Compra directa pendiente de aprobacion',
                                'badge' => $order->getStatusLabel(),
                                'badge_tone' => strtolower($order->getStatusBadgeClass()),
                                'route' => route('purchase-orders.index'),
                                'route_label' => 'Ver ordenes',
                            ];
                        }))
                        ->values()
                        ->all(),
                ],
            ],
        ];
    }

    private function buildReceiverBoard(): array
    {
        $pendingStatuses = ['ISSUED', 'PARTIALLY_RECEIVED', 'DELIVERED_PENDING_RECEPTION'];
        $regular = PurchaseOrder::query()->whereIn('status', $pendingStatuses)->count();
        $direct = DirectPurchaseOrder::query()->whereIn('status', $pendingStatuses)->count();

        return [
            'quickActions' => [
                $this->quickAction('receiver-overview', 'Recepciones', route('receptions.overview'), 'ti-truck-delivery', 'primary'),
                $this->quickAction('receiver-pending', 'Pendientes', route('receptions.pending'), 'ti-clipboard-check', 'warning'),
            ],
            'kpis' => [
                $this->kpi('receiver-regular', 'OC pendientes', $regular, 'ti-package-export', 'primary', 'Ordenes regulares listas para captura de recepcion.'),
                $this->kpi('receiver-direct', 'OCD pendientes', $direct, 'ti-package', 'info', 'Compras directas pendientes de recepcion.'),
                $this->kpi('receiver-total', 'Total por recibir', $regular + $direct, 'ti-list-check', 'warning', 'Carga operativa total en recepcion.'),
            ],
            'sections' => [
                [
                    'id' => 'receiver-summary',
                    'title' => 'Resumen de recepcion',
                    'icon' => 'ti-clipboard-check',
                    'empty_message' => 'No hay ordenes pendientes de captura.',
                    'items' => [
                        [
                            'id' => 'receiver-regular-item',
                            'title' => 'Ordenes regulares pendientes',
                            'subtitle' => (string) $regular,
                            'meta' => 'Emitidas, parciales o entregadas pendientes de captura.',
                            'badge' => $regular > 0 ? 'Atencion' : 'Sin pendientes',
                            'badge_tone' => $regular > 0 ? 'warning' : 'secondary',
                            'route' => route('receptions.pending'),
                            'route_label' => 'Abrir pendientes',
                        ],
                        [
                            'id' => 'receiver-direct-item',
                            'title' => 'Compras directas pendientes',
                            'subtitle' => (string) $direct,
                            'meta' => 'Mismas condiciones de recepcion para OCD.',
                            'badge' => $direct > 0 ? 'Atencion' : 'Sin pendientes',
                            'badge_tone' => $direct > 0 ? 'warning' : 'secondary',
                            'route' => route('receptions.pending'),
                            'route_label' => 'Abrir pendientes',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function buildAccountingBoard(): array
    {
        return [
            'quickActions' => [
                $this->quickAction('accounting-invoices', 'Facturas', route('invoices.index'), 'ti-receipt-2', 'primary'),
                $this->quickAction('accounting-provisions', 'Provisiones', route('financial-provisions.index'), 'ti-cash-banknote', 'warning'),
                $this->quickAction('accounting-budget', 'Control presupuestal', route('budget_movements.dashboard'), 'ti-chart-bar', 'info'),
            ],
            'kpis' => [
                $this->kpi('accounting-invoices-uploaded', 'Facturas cargadas', SupplierInvoice::query()->where('status', SupplierInvoice::STATUS_UPLOADED)->count(), 'ti-file-upload', 'warning', 'Facturas pendientes de conciliacion.'),
                $this->kpi('accounting-invoices-linked', 'Facturas vinculadas', SupplierInvoice::query()->where('status', SupplierInvoice::STATUS_LINKED)->count(), 'ti-link', 'success', 'Facturas ya reconciliadas con provision.'),
                $this->kpi('accounting-invoices-rejected', 'Facturas rechazadas', SupplierInvoice::query()->where('status', SupplierInvoice::STATUS_REJECTED)->count(), 'ti-circle-x', 'danger', 'Facturas con observaciones pendientes.'),
                $this->kpi('accounting-provisions-pending', 'Provisiones pendientes', FinancialProvision::query()->where('status', FinancialProvision::STATUS_PENDING_INVOICE)->count(), 'ti-hourglass', 'warning', 'Recepciones esperando factura.'),
                $this->kpi('accounting-provisions-review', 'Discrepancias', FinancialProvision::query()->where('status', FinancialProvision::STATUS_DISCREPANCY_REVIEW)->count(), 'ti-search', 'danger', 'Provisiones en revision por diferencia.'),
                $this->kpi('accounting-budget-critical', 'Presupuesto critico', $this->criticalBudgetCount(), 'ti-alert-triangle', 'info', 'Categorias con disponibilidad critica o agotada.'),
            ],
            'sections' => [
                [
                    'id' => 'accounting-finance-queue',
                    'title' => 'Bandeja financiera',
                    'icon' => 'ti-report-money',
                    'empty_message' => 'No hay movimientos financieros prioritarios.',
                    'items' => collect()
                        ->merge(
                            FinancialProvision::query()
                                ->whereIn('status', [
                                    FinancialProvision::STATUS_PENDING_INVOICE,
                                    FinancialProvision::STATUS_DISCREPANCY_REVIEW,
                                ])
                                ->latest()
                                ->limit(4)
                                ->get()
                                ->map(function (FinancialProvision $provision) {
                                    return [
                                        'id' => 'accounting-provision-'.$provision->id,
                                        'title' => 'Provision #'.$provision->id,
                                        'subtitle' => format_money((float) $provision->provision_amount, $provision->currency),
                                        'meta' => 'Proveedor: '.($provision->supplier?->company_name ?: 'N/A'),
                                        'badge' => $provision->getStatusLabel(),
                                        'badge_tone' => strtolower($provision->getStatusBadgeClass()),
                                        'route' => route('financial-provisions.show', $provision),
                                        'route_label' => 'Ver provision',
                                    ];
                                })
                        )
                        ->merge(
                            SupplierInvoice::query()
                                ->where('status', SupplierInvoice::STATUS_UPLOADED)
                                ->latest()
                                ->limit(3)
                                ->get()
                                ->map(function (SupplierInvoice $invoice) {
                                    return [
                                        'id' => 'accounting-invoice-'.$invoice->id,
                                        'title' => $invoice->uuid,
                                        'subtitle' => format_money((float) $invoice->total, $invoice->currency),
                                        'meta' => 'Proveedor: '.($invoice->supplier?->company_name ?: 'N/A'),
                                        'badge' => $invoice->getStatusLabel(),
                                        'badge_tone' => strtolower($invoice->getStatusBadgeClass()),
                                        'route' => route('invoices.index'),
                                        'route_label' => 'Ir a facturas',
                                    ];
                                })
                        )
                        ->values()
                        ->all(),
                ],
            ],
        ];
    }

    private function buildDepartmentHeadBoard(): array
    {
        return [
            'quickActions' => [
                $this->quickAction('dept-head-invoices', 'Facturas', route('invoices.index'), 'ti-receipt-2', 'primary'),
                $this->quickAction('dept-head-provisions', 'Provisiones', route('financial-provisions.index'), 'ti-cash-banknote', 'info'),
                $this->quickAction('dept-head-budget', 'Presupuesto', route('budget_movements.dashboard'), 'ti-chart-bar', 'warning'),
            ],
            'kpis' => [
                $this->kpi('dept-head-provisions-pending', 'Provisiones pendientes', FinancialProvision::query()->where('status', FinancialProvision::STATUS_PENDING_INVOICE)->count(), 'ti-hourglass', 'warning', 'Pendientes de factura en la operacion.'),
                $this->kpi('dept-head-provisions-invoiced', 'Provisiones facturadas', FinancialProvision::query()->where('status', FinancialProvision::STATUS_INVOICED)->count(), 'ti-circle-check', 'success', 'Provisiones ya conciliadas.'),
                $this->kpi('dept-head-budget-pending', 'Movimientos presupuestales', BudgetMovement::query()->pending()->count(), 'ti-arrows-transfer-up-down', 'info', 'Solicitudes de movimiento en curso.'),
                $this->kpi('dept-head-budget-critical', 'Alertas criticas', $this->criticalBudgetCount(), 'ti-alert-triangle', 'danger', 'Necesitan seguimiento de presupuesto.'),
            ],
        ];
    }

    private function buildGeneralDirectorBoard(User $user): array
    {
        return [
            'quickActions' => [
                $this->quickAction('director-approvals', 'Aprobaciones', route('approvals.quotations.index'), 'ti-crown', 'warning'),
                $this->quickAction('director-orders', 'Ordenes', route('purchase-orders.index'), 'ti-shopping-cart', 'primary'),
                $this->quickAction('director-budget', 'Presupuesto', route('budget_movements.dashboard'), 'ti-chart-bar', 'info'),
            ],
            'kpis' => [
                $this->kpi('director-approvals', 'Pendientes de aprobacion', QuotationSummary::query()->pending()->count(), 'ti-stamp', 'warning', 'Adjudicaciones pendientes en el sistema.'),
                $this->kpi('director-critical-budget', 'Presupuesto critico', $this->criticalBudgetCount(), 'ti-alert-triangle', 'danger', 'Distribuciones con riesgo operativo.'),
                $this->kpi('director-open-orders', 'Ordenes abiertas', PurchaseOrder::query()->whereIn('status', ['ISSUED', 'PARTIALLY_RECEIVED', 'DELIVERED_PENDING_RECEPTION'])->count() + DirectPurchaseOrder::query()->whereIn('status', ['ISSUED', 'PARTIALLY_RECEIVED', 'DELIVERED_PENDING_RECEPTION'])->count(), 'ti-package-export', 'primary', 'Carga total de ordenes activas.'),
                $this->kpi('director-active-volume', 'Compras activas', Requisition::query()->whereIn('status', [RequisitionStatus::PENDING->value, RequisitionStatus::IN_QUOTATION->value, RequisitionStatus::QUOTED->value])->count(), 'ti-briefcase', 'info', 'Volumen activo de requisiciones y cotizacion.'),
            ],
            'sections' => [
                [
                    'id' => 'director-executive',
                    'title' => 'Resumen ejecutivo',
                    'icon' => 'ti-chart-donut',
                    'empty_message' => 'No hay alertas ejecutivas activas.',
                    'items' => [
                        [
                            'id' => 'director-current-approvals',
                            'title' => 'Aprobaciones asignadas al usuario actual',
                            'subtitle' => (string) QuotationSummary::query()->pending()->assignedTo($user->id)->count(),
                            'meta' => 'Bandeja personal dentro del flujo de aprobacion.',
                            'badge' => 'Personal',
                            'badge_tone' => 'warning',
                            'route' => route('approvals.quotations.index'),
                            'route_label' => 'Abrir aprobaciones',
                        ],
                        [
                            'id' => 'director-system-alerts',
                            'title' => 'Alertas criticas de presupuesto',
                            'subtitle' => (string) $this->criticalBudgetCount(),
                            'meta' => 'Meses y categorias en estatus critico o agotado.',
                            'badge' => 'Sistema',
                            'badge_tone' => 'danger',
                            'route' => route('budget_movements.dashboard'),
                            'route_label' => 'Ver presupuesto',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function buildCatalogAdminBoard(): array
    {
        return [
            'quickActions' => [
                $this->quickAction('catalog-admin-products', 'Catalogo', route('products-services.index'), 'ti-package', 'primary'),
            ],
            'kpis' => [
                $this->kpi('catalog-admin-pending', 'Pendientes de aprobacion', ProductService::query()->pendingApproval()->count(), 'ti-clock-hour-4', 'warning', 'Productos o servicios esperando revision.'),
                $this->kpi('catalog-admin-rejected', 'Rechazados', ProductService::query()->rejected()->count(), 'ti-circle-x', 'danger', 'Requieren correccion o ajuste.'),
                $this->kpi('catalog-admin-active', 'Activos', ProductService::query()->active()->count(), 'ti-circle-check', 'success', 'Catalogo operativo disponible para requisiciones.'),
            ],
            'sections' => [
                [
                    'id' => 'catalog-admin-review',
                    'title' => 'Elementos por revisar',
                    'icon' => 'ti-package-import',
                    'empty_message' => 'No hay productos o servicios pendientes.',
                    'items' => ProductService::query()
                        ->whereIn('status', ['PENDING', 'REJECTED'])
                        ->latest()
                        ->limit(5)
                        ->get()
                        ->map(function (ProductService $product) {
                            return [
                                'id' => 'catalog-product-'.$product->id,
                                'title' => $product->getDisplayName(),
                                'subtitle' => $product->code,
                                'meta' => $product->category?->name ?: 'Sin categoria',
                                'badge' => $product->statusLabel(),
                                'badge_tone' => $product->statusColor(),
                                'route' => route('products-services.index'),
                                'route_label' => 'Abrir catalogo',
                            ];
                        })
                        ->values()
                        ->all(),
                ],
            ],
        ];
    }

    private function buildSuperadminBoard(): array
    {
        return [
            'quickActions' => [
                $this->quickAction('superadmin-users', 'Usuarios staff', route('users.staff.index'), 'ti-users', 'primary'),
                $this->quickAction('superadmin-roles', 'Roles y permisos', route('roles.catalog'), 'ti-shield-check', 'secondary'),
                $this->quickAction('superadmin-incidents', 'Incidentes', route('incidents.index'), 'ti-bug', 'danger'),
            ],
            'alerts' => [
                $this->alert(
                    'superadmin-full-access',
                    'secondary',
                    'Vista consolidada',
                    'Como superadmin estas viendo la composicion completa de widgets internos del sistema.',
                    route('roles.catalog'),
                    'Revisar roles'
                ),
            ],
        ];
    }

    private function resolveInternalRoles(User $user): array
    {
        $roles = collect($user->getRoleNames()->all())
            ->map(fn (string $role) => $this->moduleAccessService->normalizeRoleLabel($role))
            ->unique()
            ->values();

        if ($roles->contains('superadmin')) {
            $roles = collect(self::INTERNAL_ROLE_ORDER);
        } else {
            $roles = $roles->filter(fn (string $role) => in_array($role, self::INTERNAL_ROLE_ORDER, true))
                ->sortBy(fn (string $role) => array_search($role, self::INTERNAL_ROLE_ORDER, true))
                ->values();
        }

        return $roles->all();
    }

    private function mergeBoard(array &$board, array $roleBoard): void
    {
        foreach (['quickActions', 'kpis', 'alerts', 'sections'] as $group) {
            foreach (($roleBoard[$group] ?? []) as $item) {
                if ($item === null) {
                    continue;
                }

                $board[$group][$item['id']] = $item;
            }
        }
    }

    private function mapRecentNotifications(Collection $notifications): array
    {
        return $notifications->map(function (DatabaseNotification $notification) {
            $data = $notification->data;
            $text = $data['message']
                ?? $data['body']
                ?? $data['title']
                ?? Str::headline($data['type'] ?? 'Notificacion');

            return [
                'id' => $notification->id,
                'text' => Str::limit((string) $text, 120),
                'time' => $notification->created_at?->diffForHumans(),
            ];
        })->all();
    }

    private function notificationSummaryForNotifiable(object $notifiable): array
    {
        return [
            'unread_count' => rescue(fn () => $notifiable->unreadNotifications()->count(), 0, report: false),
            'recent' => rescue(fn () => $this->mapRecentNotifications($notifiable->notifications()->latest()->limit(3)->get()), [], report: false),
            'route' => route('notifications.index'),
        ];
    }

    private function labelForRole(string $role): string
    {
        return self::ROLE_LABELS[$role] ?? Str::headline($role);
    }

    private function criticalBudgetCount(): int
    {
        $currentYear = (int) now()->year;

        return BudgetMonthlyDistribution::query()
            ->whereHas('annualBudget', fn ($query) => $query->where('fiscal_year', $currentYear))
            ->get()
            ->filter(function (BudgetMonthlyDistribution $distribution) {
                $assigned = (float) $distribution->assigned_amount;
                $consumed = (float) $distribution->consumed_amount;
                $committed = (float) $distribution->committed_amount;
                $available = (float) $distribution->getAvailableAmount();
                $usage = $assigned > 0 ? (($consumed + $committed) / $assigned) : 0;

                return $available <= 0 || $usage > 0.7;
            })
            ->count();
    }

    private function toneForStatus(?string $status): string
    {
        return match (strtoupper((string) $status)) {
            'APPROVED', 'RECEIVED', 'LINKED', 'INVOICED', 'COMPLETED' => 'success',
            'PENDING', 'PENDING_APPROVAL', 'SENT', 'UPLOADED', 'DRAFT' => 'warning',
            'REJECTED', 'CANCELLED', 'EXPIRED' => 'danger',
            'EVALUATED', 'IN_REVIEW' => 'info',
            default => 'secondary',
        };
    }

    private function quickAction(string $id, string $label, string $route, string $icon, string $tone): array
    {
        return compact('id', 'label', 'route', 'icon', 'tone');
    }

    private function kpi(string $id, string $label, int $value, string $icon, string $tone, string $description): array
    {
        return compact('id', 'label', 'value', 'icon', 'tone', 'description');
    }

    private function alert(string $id, string $tone, string $title, string $body, ?string $route = null, ?string $routeLabel = null): array
    {
        return [
            'id' => $id,
            'tone' => $tone,
            'title' => $title,
            'body' => $body,
            'route' => $route,
            'route_label' => $routeLabel,
        ];
    }
}
