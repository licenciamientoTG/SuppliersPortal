<?php

namespace App\Http\Controllers;

use App\Models\Requisition;
use App\Models\Rfq;
use Yajra\DataTables\Facades\DataTables;

class RfqInboxController extends Controller
{
    /**
     * Muestra la vista del buzón de pendientes.
     */
    public function pending()
    {
        return view('rfq.inbox.pending');
    }

    /**
     * Procesa los datos para el DataTable de RFQs pendientes.
     */
    public function pendingData()
    {
        // 1. Iniciamos el Query con el Scope y las relaciones necesarias
        $query = Rfq::query()
            ->with([
                'quotationGroup:id,name',
                'requisition:id,folio',
                // 🎯 TRAEMOS SOLO LOS QUE YA RESPONDIERON
                'suppliers' => function ($q) {
                    $q->select('suppliers.id', 'company_name')
                        ->whereNotNull('rfq_suppliers.responded_at');
                },
            ])
            ->withCount([
                'suppliers',
                'suppliers as responded_count' => function ($query) {
                    $query->whereNotNull('rfq_suppliers.responded_at');
                },
            ])
            ->pending();

        return DataTables::of($query)
            // 🎯 Columna de Progreso: Basada en PROVEEDORES, no en partidas
            ->addColumn('progress', function ($rfq) {
                $invited = $rfq->suppliers_count;
                $received = $rfq->responded_count;
                $percent = ($invited > 0) ? round(($received / $invited) * 100, 0) : 0;

                // 🎯 LISTA DE NOMBRES PARA EL TOOLTIP
                // Si nadie ha respondido, ponemos un mensaje de alerta
                $names = $rfq->suppliers->pluck('company_name')->implode('<br>• ');
                $tooltipTitle = $received > 0
                    ? '<b>Respondieron:</b><br>• '.$names
                    : 'Sin respuestas aún';

                return [
                    'percent' => $percent,
                    'label' => "{$received}/{$invited}",
                    'tooltip' => $tooltipTitle,
                ];
            })

            // 🎯 Columna de Vencimiento
            ->editColumn('response_deadline', function ($rfq) {
                if (! $rfq->response_deadline) {
                    return null;
                }

                return [
                    'display' => $rfq->response_deadline->format('d/m/Y H:i'),
                    'human' => $rfq->response_deadline->diffForHumans(),
                    'is_past' => $rfq->response_deadline->isPast(),
                    'is_urgent' => ! $rfq->response_deadline->isPast() && $rfq->response_deadline->diffInDays(now()) <= 2,
                ];
            })

            // 🎯 Etiquetas de Estado Global
            ->editColumn('status', function ($rfq) {
                $statusMap = [
                    'SENT' => [
                        'label' => 'Enviada',
                        'desc' => 'La solicitud está en el campo. Esperando que los proveedores respondan.',
                        'color' => 'info',
                        'icon' => 'ti-send',
                    ],
                    'RECEIVED' => [
                        'label' => 'Con Respuestas',
                        'desc' => '¡Fuego completo! Todos los proveedores invitados ya enviaron sus cotizaciones.',
                        'color' => 'success',
                        'icon' => 'ti-circle-check',
                    ],
                    'EVALUATED' => [
                        'label' => 'En Aprobación',
                        'desc' => 'La cotizacion ya fue adjudicada por Compras y esta en aprobacion.',
                        'color' => 'primary',
                        'icon' => 'ti-scale',
                    ],
                    'DRAFT' => [
                        'label' => 'Borrador',
                        'desc' => 'Solicitud en preparación. Aún no se ha disparado a los proveedores.',
                        'color' => 'secondary',
                        'icon' => 'ti-file-pencil',
                    ],
                ];

                $info = $statusMap[$rfq->status] ?? [
                    'label' => $rfq->status,
                    'desc' => 'Estado desconocido.',
                    'color' => 'dark',
                    'icon' => 'ti-help',
                ];

                return [
                    'code' => $rfq->status,
                    'label' => $info['label'],
                    'description' => $info['desc'],
                    'color' => $info['color'],
                    'icon' => $info['icon'],
                ];
            })

            ->addIndexColumn()
            ->make(true);
    }

    /**
     * Procesa los datos para el Paso 5 del Wizard filtrado por requisición.
     */
    public function analysisData(Requisition $requisition)
    {
        $canViewAuthorization = auth()->user()?->hasRole('superadmin') ?? false;

        $query = Rfq::query()
            ->with([
                'quotationGroup:id,name',
                'requisition:id,folio',
                'suppliers' => function ($q) {
                    $q->select('suppliers.id', 'company_name')
                        ->whereNotNull('rfq_suppliers.responded_at');
                },
            ])
            ->when($canViewAuthorization, function ($query) {
                $query->with([
                    'quotationSummary.currentApprover:id,name',
                    'quotationSummary.approver:id,name',
                    'quotationSummary.rejector:id,name',
                    'quotationSummary.authorizerRole:id,name',
                ]);
            })
            ->withCount([
                'suppliers',
                'suppliers as responded_count' => function ($query) {
                    $query->whereNotNull('rfq_suppliers.responded_at');
                },
            ])
            ->where('requisition_id', $requisition->id) // 🎯 FILTRO CRUCIAL
            ->whereNotIn('status', ['CANCELLED', 'REJECTED']); // No mostrar basura

        $dataTable = DataTables::of($query)
            ->addColumn('progress', function ($rfq) {
                $invited = $rfq->suppliers_count;
                $received = $rfq->responded_count;
                $percent = ($invited > 0) ? round(($received / $invited) * 100, 0) : 0;

                $names = $rfq->suppliers->pluck('company_name')->implode('<br>• ');
                $tooltipTitle = $received > 0 ? '<b>Respondieron:</b><br>• '.$names : 'Sin respuestas aún';

                return [
                    'percent' => $percent,
                    'label' => "{$received}/{$invited}",
                    'tooltip' => $tooltipTitle,
                ];
            })
            ->editColumn('response_deadline', function ($rfq) {
                if (! $rfq->response_deadline) {
                    return null;
                }

                return [
                    'display' => $rfq->response_deadline->format('d/m/Y H:i'),
                    'human' => $rfq->response_deadline->diffForHumans(),
                    'is_past' => $rfq->response_deadline->isPast(),
                ];
            })
            ->editColumn('status', function ($rfq) {
                // Reutiliza tu StatusMap anterior...
                $statusMap = [
                    'SENT' => ['label' => 'Enviada', 'color' => 'info', 'icon' => 'ti-send'],
                    'RECEIVED' => ['label' => 'Con Respuestas', 'color' => 'success', 'icon' => 'ti-circle-check'],
                    'DRAFT' => ['label' => 'Borrador', 'color' => 'secondary', 'icon' => 'ti-file-pencil'],
                    'EVALUATED' => ['label' => 'En Aprobación', 'color' => 'primary', 'icon' => 'ti-scale'],
                ];
                $info = $statusMap[$rfq->status] ?? ['label' => $rfq->status, 'color' => 'dark', 'icon' => 'ti-help'];

                return $info;
            });

        if ($canViewAuthorization) {
            $dataTable->addColumn('authorization', function (Rfq $rfq) {
                $summary = $rfq->quotationSummary;

                if (! $summary) {
                    return ['label' => 'Aún no se envía a autorización', 'recipient' => null, 'detail' => 'Pendiente de adjudicación', 'state' => 'waiting'];
                }

                if ($summary->approval_status === 'pending' && $summary->currentApprover) {
                    return ['label' => 'Enviada a autorizar', 'recipient' => $summary->currentApprover->name, 'detail' => $summary->authorizerRole?->name ?? 'Autorizador asignado', 'state' => 'pending'];
                }

                if ($summary->approval_status === 'approved') {
                    return ['label' => 'Autorizada', 'recipient' => $summary->approver?->name, 'detail' => 'Proceso de autorización concluido', 'state' => 'approved'];
                }

                if ($summary->approval_status === 'rejected') {
                    return ['label' => 'Rechazada', 'recipient' => $summary->rejector?->name, 'detail' => 'Proceso de autorización concluido', 'state' => 'rejected'];
                }

                return ['label' => 'Sin autorizador asignado', 'recipient' => null, 'detail' => 'Requiere revisión de la configuración', 'state' => 'warning'];
            });
        }

        return $dataTable->make(true);
    }

    /**
     * Devuelve el contenido HTML para el modal de la RFQ.
     */
    public function rfqModalContent(Rfq $rfq)
    {
        $rfq->load([
            'quotationGroup.items.productService',
            'requisitionItem.productService',
            'suppliers',
            'activities.causer', // 🎯 ESTA ES LA MUNICIÓN QUE FALTABA
        ]);

        return view('rfq.inbox.partials.rfq_info', compact('rfq'));
    }

    /**
     * Devuelve el contenido HTML para el modal de la Requisición.
     */
    public function reqModalContent(Requisition $requisition)
    {
        // 🎯 Cambiamos 'user' por 'requester'
        // 🎯 Cambiamos 'requisitionItem' por 'items'
        $requisition->load([
            'department',
            'requester', // Este es el nombre correcto en tu modelo
            'items',     // Requisition tiene 'items', no 'requisitionItem'
            'costCenter',
        ]);

        return view('rfq.inbox.partials.req_info', compact('requisition'));
    }
}
