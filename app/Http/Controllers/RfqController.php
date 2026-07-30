<?php

namespace App\Http\Controllers;

use App\Models\Requisition;
use App\Models\Rfq;
use App\Models\RfqResponse;
use App\Models\QuotationSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;
use App\Models\Supplier;
use App\Notifications\RfqSentToSuppliersNotification;
use App\Notifications\NewRfqForSupplierNotification;
use App\Services\BuyerNotificationService;
use App\Services\QuotationRejectionWorkflowService;

class RfqController extends Controller
{
    public function __construct(
        private QuotationRejectionWorkflowService $quotationRejectionWorkflowService,
    ) {}

    /**
     * Listado general de RFQs.
     * 
     * @return View
     */
    public function index(): View
    {
        return view('rfq.index');
    }

    /**
     * DataTable para listado de RFQs.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function datatable(Request $request): JsonResponse
    {

        $query = Rfq::with([
            'requisition',
            'quotationGroup',
            'requisitionItem',
            'suppliers'
        ])->select('rfqs.*');

        // Filtros
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', \Carbon\Carbon::parse($request->date_from)->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', \Carbon\Carbon::parse($request->date_to)->endOfDay());
        }

        return DataTables::of($query)
            ->addColumn('requisition_folio', function ($row) {
                return $row->requisition ? $row->requisition->folio : 'N/A';
            })
            ->addColumn('group_or_item', function ($row) {
                if ($row->quotation_group_id) {
                    return '<span class="badge bg-primary">
                            <i class="ti ti-folder"></i> Grupo: ' .
                        ($row->quotationGroup->name ?? 'N/A') .
                        '</span>';
                } elseif ($row->requisition_item_id) {
                    return '<span class="badge bg-secondary">
                            <i class="ti ti-file"></i> Partida Individual
                        </span>';
                }
                return '<span class="text-muted">N/A</span>';
            })
            ->addColumn('suppliers_count', function ($row) {
                return $row->suppliers->count();
            })
            // AQUÍ ESTÁ EL CAMBIO: De simple contador a lista de nombres con estilo
            ->addColumn('suppliers_list', function ($row) {
                if ($row->suppliers->isEmpty()) {
                    return '<span class="text-muted">Sin proveedores</span>';
                }

                // Mapeamos los nombres a badges. 
                // No pongas todos si son 50, o el DT va a parecer una sopa de letras.
                $badges = $row->suppliers->take(5)->map(function ($supplier) {
                    // Preparamos la información de contacto (escapando datos, ¡no confíes en nadie!)
                    $email = e($supplier->email ?? 'Sin correo');
                    $phone = e($supplier->phone ?? 'Sin teléfono');
                    $contact = e($supplier->contact_name ?? 'Sin contacto');

                    // El secreto está en este "title" que Bootstrap convertirá en Tooltip
                    $tooltipContent = "Contacto: $contact | Email: $email | Tel: $phone";

                    return '<span class="badge bg-light text-dark border shadow-sm" 
                      data-bs-toggle="tooltip" 
                      data-bs-placement="top" 
                      data-bs-html="true"
                      title="' . $tooltipContent . '">
                    <i class="ti ti-building-store me-1"></i>' . e($supplier->company_name) . '
                </span>';
                })->implode(' ');

                // Si hay más de 5, avisamos que hay más "soldados" en la lista
                if ($row->suppliers->count() > 5) {
                    $badges .= ' <span class="badge bg-secondary">+' . ($row->suppliers->count() - 5) . ' más</span>';
                }

                return '<div class="d-flex flex-wrap gap-1">' . $badges . '</div>';
            })
            ->addColumn('status_badge', function ($row) {
                $badges = [
                    'DRAFT' => ['class' => 'secondary', 'icon' => 'ti ti-pencil', 'label' => 'Borrador'],
                    'SENT' => ['class' => 'info', 'icon' => 'ti ti-send', 'label' => 'Enviada'],
                    'RESPONSES_RECEIVED' => ['class' => 'success', 'icon' => 'ti ti-check', 'label' => 'Con Respuestas'],
                    'RECEIVED' => ['class' => 'success', 'icon' => 'ti ti-check', 'label' => 'Con Respuestas'],
                    'EVALUATED' => ['class' => 'primary', 'icon' => 'ti ti-check-circle', 'label' => 'Evaluada'],
                    'REJECTED' => ['class' => 'warning', 'icon' => 'ti ti-alert-circle', 'label' => 'Rechazada'],
                    'CANCELLED' => ['class' => 'danger', 'icon' => 'ti ti-x', 'label' => 'Cancelada'],
                ];

                $badge = $badges[$row->status] ?? ['class' => 'secondary', 'icon' => 'ti ti-help', 'label' => $row->status];

                return '<span class="badge bg-' . $badge['class'] . ' status-badge">
                        <i class="' . $badge['icon'] . '"></i> ' . $badge['label'] . '
                    </span>';
            })
            ->addColumn('days_remaining', function ($row) {
                if (!$row->response_deadline) {
                    return '<span class="text-muted">-</span>';
                }

                $deadline = \Carbon\Carbon::parse($row->response_deadline);
                $today = \Carbon\Carbon::today();
                $daysRemaining = $today->diffInDays($deadline, false);

                if ($daysRemaining < 0) {
                    return '<span class="badge bg-danger days-remaining">
                            <i class="ti ti-alert-triangle"></i> Vencida
                        </span>';
                } elseif ($daysRemaining === 0) {
                    return '<span class="badge bg-warning days-remaining">
                            <i class="ti ti-clock"></i> Hoy
                        </span>';
                } elseif ($daysRemaining <= 3) {
                    return '<span class="badge bg-warning days-remaining">
                            ' . $daysRemaining . ' día' . ($daysRemaining > 1 ? 's' : '') . '
                        </span>';
                } else {
                    return '<span class="badge bg-success days-remaining">
                            ' . $daysRemaining . ' días
                        </span>';
                }
            })
            ->addColumn('action', function ($row) {
                $showUrl = route('rfq.show', $row->id);

                // RECOLECTAMOS LOS CORREOS AQUÍ
                $emails = $row->suppliers->pluck('email')->filter()->implode(', ');

                $actions = '<div class="btn-group" role="group">';

                // Botón Ver
                $actions .= '<a href="' . $showUrl . '" 
                           class="btn btn-sm btn-primary" 
                           data-bs-toggle="tooltip" 
                           title="Ver Detalle">
                            <i class="ti ti-eye"></i>
                         </a>';

                // Botón Enviar (solo si está en borrador)
                if ($row->status === 'DRAFT') {
                    // AÑADIMOS data-emails AL BOTÓN
                    $actions .= '<button type="button" 
                       class="btn btn-sm btn-success btn-send-rfq" 
                       data-rfq-id="' . $row->id . '" 
                       data-folio="' . $row->folio . '"
                       data-emails="' . e($emails) . '" 
                       data-bs-toggle="tooltip" 
                       title="Enviar a Proveedores">
                        <i class="ti ti-send"></i>
                     </button>';
                }

                // Botón Cancelar (si no está cancelada)
                if (!in_array($row->status, ['CANCELLED', 'COMPLETED', 'REJECTED'], true)) {
                    $actions .= '<button type="button" 
                               class="btn btn-sm btn-danger btn-cancel-rfq" 
                               data-rfq-id="' . $row->id . '" 
                               data-folio="' . $row->folio . '"
                               data-bs-toggle="tooltip" 
                               title="Cancelar RFQ">
                                <i class="ti ti-x"></i>
                             </button>';
                }

                $actions .= '</div>';

                return $actions;
            })
            ->editColumn('sent_at', fn($row) => $row->sent_at ? $row->sent_at->format('d/m/Y H:i') : null)
            ->editColumn('response_deadline', fn($row) => $row->response_deadline ?
                \Carbon\Carbon::parse($row->response_deadline)->format('d/m/Y') : null)
            ->rawColumns(['group_or_item', 'status_badge', 'days_remaining', 'action', 'suppliers_list'])
            ->make(true);
    }

    /**
     * DataTable para RFQs en el wizard (filtrado por requisición)
     * 
     * @param Request $request
     * @param Requisition $requisition
     * @return JsonResponse
     */
    public function wizardDatatable(Request $request, Requisition $requisition): JsonResponse
    {
        $query = Rfq::with([
            'quotationGroup',
            'requisitionItem',
            'suppliers'
        ])
            ->where('requisition_id', $requisition->id)
            ->select('rfqs.*');

        return DataTables::of($query)
            ->addColumn('group_or_item', function ($row) {
                if ($row->quotation_group_id) {
                    return '<span class="badge bg-primary">
                        <i class="ti ti-folder"></i> ' .
                        ($row->quotationGroup->name ?? 'N/A') .
                        '</span>';
                } elseif ($row->requisition_item_id) {
                    return '<span class="badge bg-secondary">
                        <i class="ti ti-file"></i> Partida Individual
                    </span>';
                }
                return '<span class="text-muted">N/A</span>';
            })
            ->addColumn('suppliers_list', function ($row) {
                if ($row->suppliers->isEmpty()) {
                    return '<span class="text-muted">Sin proveedores</span>';
                }

                $badges = $row->suppliers->take(3)->map(function ($supplier) {
                    $email = e($supplier->email ?? 'Sin correo');
                    $phone = e($supplier->phone ?? 'Sin teléfono');
                    $contact = e($supplier->contact_name ?? 'Sin contacto');
                    $tooltipContent = "Contacto: $contact | Email: $email | Tel: $phone";

                    return '<span class="badge bg-light text-dark border shadow-sm" 
                          data-bs-toggle="tooltip" 
                          data-bs-placement="top" 
                          title="' . $tooltipContent . '">
                        <i class="ti ti-building-store me-1"></i>' . e($supplier->company_name) . '
                    </span>';
                })->implode(' ');

                if ($row->suppliers->count() > 3) {
                    $badges .= ' <span class="badge bg-secondary">+' . ($row->suppliers->count() - 3) . ' más</span>';
                }

                return '<div class="d-flex flex-wrap gap-1">' . $badges . '</div>';
            })
            ->addColumn('status_badge', function ($row) {
                $badges = [
                    'DRAFT' => ['class' => 'warning', 'icon' => 'ti ti-pencil', 'label' => 'Borrador'],
                    'SENT' => ['class' => 'success', 'icon' => 'ti ti-circle-check', 'label' => 'Correo enviado'],
                    'RESPONSES_RECEIVED' => ['class' => 'success', 'icon' => 'ti ti-check', 'label' => 'Con Respuestas'],
                    'RECEIVED' => ['class' => 'success', 'icon' => 'ti ti-check', 'label' => 'Con Respuestas'],
                    'EVALUATED' => ['class' => 'primary', 'icon' => 'ti ti-check-circle', 'label' => 'Evaluada'],
                    'REJECTED' => ['class' => 'warning', 'icon' => 'ti ti-alert-circle', 'label' => 'Rechazada'],
                    'CANCELLED' => ['class' => 'danger', 'icon' => 'ti ti-x', 'label' => 'Cancelada'],
                ];

                $badge = $badges[$row->status] ?? ['class' => 'secondary', 'icon' => 'ti ti-help', 'label' => $row->status];

                $sentAt = $row->status === 'SENT' && $row->sent_at
                    ? '<small class="rfq-status-time">' . e($row->sent_at->format('d/m H:i')) . '</small>'
                    : '';

                return '<span class="badge bg-' . $badge['class'] . ' status-badge rfq-status-badge">
                    <i class="' . $badge['icon'] . '"></i> ' . $badge['label'] . '
                </span>' . $sentAt;
            })
            ->addColumn('days_remaining', function ($row) {
                if (!$row->response_deadline) {
                    return '<span class="text-muted">-</span>';
                }

                $deadline = \Carbon\Carbon::parse($row->response_deadline);
                $today = \Carbon\Carbon::today();
                $daysRemaining = $today->diffInDays($deadline, false);

                if ($daysRemaining < 0) {
                    return '<span class="badge bg-danger">Vencida</span>';
                } elseif ($daysRemaining === 0) {
                    return '<span class="badge bg-warning">Hoy</span>';
                } elseif ($daysRemaining <= 3) {
                    return '<span class="badge bg-warning">' . $daysRemaining . ' días</span>';
                } else {
                    return '<span class="badge bg-success">' . $daysRemaining . ' días</span>';
                }
            })
            ->addColumn('action', function ($row) {
                $emails = $row->suppliers->pluck('email')->filter()->implode(', ');
                $actions = '<div class="rfq-row-actions" role="group">';

                // Botón Enviar (solo si está en borrador)
                if ($row->status === 'DRAFT') {
                    $actions .= '<button type="button" 
                   class="btn btn-success btn-sm btn-send-single-rfq rfq-action-send"
                   data-rfq-id="' . $row->id . '" 
                   data-folio="' . $row->folio . '"
                   data-emails="' . e($emails) . '" 
                   data-bs-toggle="tooltip" 
                   title="Enviar">
                    <i class="ti ti-send"></i><span>Enviar</span>
                 </button>';
                }

                if ($row->status === 'SENT') {
                    $actions .= '<span class="rfq-delivery-confirmed" title="Correo enviado; este paquete ya no se puede modificar">
                        <i class="ti ti-circle-check"></i><span>Enviado</span>
                    </span>';
                }

                // Botón Ver detalles
                $actions .= '<button type="button" 
                       class="btn btn-outline-primary btn-sm btn-view-rfq-details rfq-action-view"
                       data-rfq-id="' . $row->id . '" 
                       data-bs-toggle="tooltip" 
                       title="Ver Detalles">
                        <i class="ti ti-eye"></i>
                     </button>';

                // Un borrador es el único estado que todavía puede cancelarse o reconfigurarse.
                if ($row->status === 'DRAFT') {
                    $actions .= '<button type="button" class="btn btn-sm btn-danger btn-cancel-rfq" data-rfq-id="' . $row->id . '" 
                               data-folio="' . $row->folio . '"
                               data-bs-toggle="tooltip" 
                               title="Cancelar RFQ">
                                <i class="ti ti-x"></i>
                             </button>';
                }


                $actions .= '</div>';
                return $actions;
            })
            ->editColumn('response_deadline', fn($row) => $row->response_deadline ?
                \Carbon\Carbon::parse($row->response_deadline)->format('d/m/Y') : null)
            ->rawColumns(['group_or_item', 'status_badge', 'days_remaining', 'action', 'suppliers_list'])
            ->make(true);
    }

    public function wizardSummary(Requisition $requisition)
    {
        try {
            $drafts = Rfq::where('requisition_id', $requisition->id)
                ->where('status', 'DRAFT')
                ->count();

            $sent = Rfq::where('requisition_id', $requisition->id)
                ->where('status', 'SENT')
                ->count();

            return response()->json([
                'success' => true,
                'drafts' => $drafts,
                'sent' => $sent
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Resumen de RFQs por estado
     * 
     * @return JsonResponse
     */
    public function summary(): JsonResponse
    {
        $summary = [
            'draft' => Rfq::where('status', 'DRAFT')->count(),
            'sent' => Rfq::where('status', 'SENT')->count(),
            'responded' => Rfq::where('status', 'RESPONSES_RECEIVED')->count(),
            'expired' => Rfq::where('status', 'SENT')
                ->where('response_deadline', '<', now())
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }

    /**
     * Bandeja de RFQs pendientes de respuesta.
     * 
     * @return View
     */
    public function pendingInbox(): View
    {
        return view('rfq.inbox.pending');
    }

    /**
     * Bandeja de RFQs con respuestas recibidas.
     * 
     * @return View
     */
    public function receivedInbox(): View
    {
        return view('rfq.inbox.received');
    }

    /**
     * Detalle de una RFQ.
     * 
     * @param Rfq $rfq
     * @return View
     */
    public function show(Rfq $rfq): View
    {
        $rfq->load([
            'requisition.requester',
            'requisition.company',
            'requisition.items.costCenter',
            'requisition.receivingLocation',
            'quotationGroup.items.expenseCategory',
            'quotationGroup.items.productService',
            'requisitionItem.expenseCategory',
            'requisitionItem.productService',
            'suppliers',
            'rfqResponses.supplier',
            'rfqResponses.requisitionItem',
            'rfqResponses.evaluator',
            'creator',
            'updater',
            'canceller',
        ]);

        return view('rfq.show', compact('rfq'));
    }

    /**
     * Devuelve la requisición origen de una RFQ para mostrarse en modal.
     */
    public function requisitionModal(Rfq $rfq): View
    {
        $rfq->load([
            'requisition.department',
            'requisition.requester',
            'requisition.items.costCenter',
            'requisition.receivingLocation',
        ]);

        abort_unless($rfq->requisition, 404, 'La RFQ no tiene una requisición asociada.');

        return view('rfq.inbox.partials.req_info', [
            'requisition' => $rfq->requisition,
        ]);
    }

    /**
     * Enviar RFQ a los proveedores
     * 
     * @param Rfq $rfq
     * @return JsonResponse
     */
    public function sendRFQ(Rfq $rfq): JsonResponse
    {
        try {
            // Validar que esté en estado DRAFT
            if ($rfq->status !== 'DRAFT') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden enviar RFQs en estado borrador'
                ], 400);
            }

            // Validar que tenga proveedores
            if ($rfq->suppliers->count() === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'La RFQ debe tener al menos un proveedor invitado'
                ], 400);
            }

            DB::beginTransaction();

            // Actualizar estado y fecha de envío
            $rfq->update([
                'status' => 'SENT',
                'sent_at' => now(),
                'updated_by' => Auth::id(),
            ]);

            // TODO: Enviar notificaciones por email a cada proveedor
            foreach ($rfq->suppliers as $supplier) {
                // Aquí irá la lógica de envío de correos
                // Mail::to($supplier->email)->send(new RfqInvitation($rfq, $supplier));

                Log::info('📧 Notificación de RFQ enviada', [
                    'rfq_id' => $rfq->id,
                    'rfq_folio' => $rfq->folio,
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->company_name,
                ]);
            }

            $this->notifyBuyersRfqWasSent($rfq->fresh(['requisition.requester', 'suppliers']));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'RFQ enviada exitosamente a ' . $rfq->suppliers->count() . ' proveedor(es)'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('❌ Error al enviar RFQ', [
                'rfq_id' => $rfq->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar la RFQ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancelar RFQ
     * 
     * @param Request $request
     * @param Rfq $rfq
     * @return JsonResponse
     */
    public function cancelRfq(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:500',
        ]);
        try {
            $rfq = Rfq::with(['requisition.requester', 'suppliers', 'rfqResponses', 'quotationGroup'])
                ->findOrFail($id);
            if ($rfq->status === 'COMPLETED') {
                return response()->json(['success' => false, 'message' => 'No se puede cancelar una solicitud completada.'], 422);
            }
            $this->quotationRejectionWorkflowService->cancelRejectedRfq($rfq, Auth::id(), $validated['reason']);
            Log::info('RFQ cancelada sin borrado', [
                'folio' => $rfq->folio,
                'user_id' => Auth::id(),
                'reason' => $validated['reason']
            ]);
            return response()->json([
                'success' => true,
                'message' => "La solicitud {$rfq->folio} fue cancelada y el expediente quedo resguardado."
            ]);
        } catch (\Exception $e) {
            Log::error('Error al cancelar la RFQ: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la cancelacion: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Vista para seleccionar proveedores por grupo/partida.
     * 
     * @param Requisition $requisition
     * @return View
     */
    public function selectSuppliers(Requisition $requisition)
    {
        // Cargar grupos con sus partidas
        $groups = $requisition->quotationGroups()
            ->with('items.productService.expenseCategory')
            ->get();

        // Validar que haya grupos
        if ($groups->isEmpty()) {
            return redirect()
                ->route('requisitions.quotation-planner.show', $requisition)
                ->with('error', 'Debes crear al menos un grupo antes de seleccionar proveedores');
        }

        // ✅ CORREGIDO: Obtener proveedores con status APPROVED
        $suppliers = Supplier::approved()
            ->orderBy('company_name')
            ->get();

        return view('rfq.select-suppliers', compact(
            'requisition',
            'groups',
            'suppliers'
        ));
    }

    /**
     * Crear RFQs para cada grupo con proveedores seleccionados
     * 
     * @param Request $request
     * @param Requisition $requisition
     * @return \Illuminate\Http\RedirectResponse
     */
    public function createRFQs(Request $request, Requisition $requisition)
    {
        $validated = $request->validate([
            'groups' => 'required|array|min:1',
            'groups.*.group_id' => 'required|exists:quotation_groups,id',
            'groups.*.supplier_ids' => 'required|array|min:1',
            'groups.*.supplier_ids.*' => 'exists:suppliers,id',
            'groups.*.response_deadline' => 'required|date|after:today',
            'groups.*.notes' => 'nullable|string',
        ], [
            'groups.*.supplier_ids.required' => 'Debes seleccionar al menos un proveedor',
            'groups.*.supplier_ids.min' => 'Debes seleccionar al menos un proveedor',
            'groups.*.response_deadline.required' => 'La fecha límite es obligatoria',
            'groups.*.response_deadline.after' => 'La fecha debe ser posterior a hoy',
        ]);

        DB::beginTransaction();
        try {
            $createdRFQs = [];

            foreach ($validated['groups'] as $groupData) {
                $group = \App\Models\QuotationGroup::findOrFail($groupData['group_id']);

                // Generar folio único
                $folio = app(\App\Services\Rfq\RfqFolioService::class)->next();

                // ✅ CORREGIDO: Usar 'folio' en lugar de 'rfq_number'
                $rfq = Rfq::create([
                    'folio' => $folio,  // ✅ Nombre correcto de la columna
                    'requisition_id' => $requisition->id,
                    'quotation_group_id' => $group->id,
                    'status' => 'DRAFT',
                    'response_deadline' => $groupData['response_deadline'],
                    'message' => $groupData['notes'] ?? null,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);

                // Asociar proveedores (tabla pivot rfq_suppliers)
                $supplierData = [];
                foreach ($groupData['supplier_ids'] as $supplierId) {
                    $supplierData[$supplierId] = [
                        'invited_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                $rfq->suppliers()->attach($supplierData);

                $createdRFQs[] = $rfq;

                Log::info('✅ RFQ creada', [
                    'rfq_id' => $rfq->id,
                    'folio' => $rfq->folio,
                    'group_id' => $group->id,
                    'suppliers_count' => count($groupData['supplier_ids']),
                ]);
            }

            // Actualizar estado de la requisición
            $requisition->update([
                'status' => 'IN_QUOTATION',
                'updated_by' => Auth::id(),
            ]);

            DB::commit();

            return redirect()
                ->route('rfq.index')
                ->with('success', count($createdRFQs) . ' solicitud(es) de cotización creadas exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('❌ Error al crear RFQs', [
                'requisition_id' => $requisition->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()
                ->withInput()
                ->with('error', 'Error al crear las solicitudes: ' . $e->getMessage());
        }
    }

    /**
     * Envía RFQs a los proveedores seleccionados.
     * 
     * @param Request $request
     * @param Requisition $requisition
     * @return JsonResponse
     */
    public function send(Request $request, Requisition $requisition): JsonResponse
    {
        $validated = $request->validate([
            'rfqs' => 'required|array|min:1',
            'rfqs.*.group_id' => 'nullable|integer|exists:quotation_groups,id',
            'rfqs.*.item_id' => 'nullable|integer|exists:requisition_items,id',
            'rfqs.*.supplier_ids' => 'required|array|min:2', // Mínimo 2 proveedores
            'rfqs.*.supplier_ids.*' => 'integer|exists:suppliers,id',
            'rfqs.*.message' => 'nullable|string',
            'rfqs.*.requirements' => 'nullable|string',
            'rfqs.*.expires_at' => 'required|date|after:today',
        ]);

        try {
            DB::beginTransaction();

            $createdRfqs = [];

            foreach ($validated['rfqs'] as $rfqData) {
                // Validar que tenga grupo O partida, no ambos
                if (!empty($rfqData['group_id']) && !empty($rfqData['item_id'])) {
                    throw new \Exception('Una RFQ no puede tener grupo Y partida individual.');
                }

                if (empty($rfqData['group_id']) && empty($rfqData['item_id'])) {
                    throw new \Exception('Una RFQ debe tener grupo O partida individual.');
                }

                // Crear RFQ para cada proveedor
                foreach ($rfqData['supplier_ids'] as $supplierId) {
                    $rfq = Rfq::create([
                        'folio' => Rfq::nextFolio(),
                        'requisition_id' => $requisition->id,
                        'quotation_group_id' => $rfqData['group_id'] ?? null,
                        'requisition_item_id' => $rfqData['item_id'] ?? null,
                        'supplier_id' => $supplierId,
                        'source' => 'portal',
                        'status' => 'sent',
                        'sent_at' => now(),
                        'expires_at' => $rfqData['expires_at'],
                        'message' => $rfqData['message'] ?? null,
                        'requirements' => $rfqData['requirements'] ?? null,
                        'created_by' => Auth::id(),
                    ]);

                    $createdRfqs[] = $rfq;

                    // TODO: Enviar notificación al proveedor
                    Log::info('📧 RFQ enviada', [
                        'rfq_id' => $rfq->id,
                        'folio' => $rfq->folio,
                        'supplier_id' => $supplierId,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '✅ RFQs enviadas exitosamente.',
                'data' => [
                    'rfqs_count' => count($createdRfqs),
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('❌ Error al enviar RFQs', [
                'requisition_id' => $requisition->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar RFQs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Comparar cotizaciones de una requisición.
     * 
     * @param Requisition $requisition
     * @return View
     */
    public function compare(Requisition $requisition): View
    {
        // Obtener todas las RFQs de la requisición con sus respuestas
        $rfqs = $requisition->rfqs()
            ->with([
                'responses.requisitionItem.product',
                'supplier',
                'quotationGroup',
                'requisitionItem'
            ])
            ->get();

        // Agrupar respuestas por partida para comparación
        $comparisonData = [];

        foreach ($rfqs as $rfq) {
            foreach ($rfq->responses as $response) {
                $itemId = $response->requisition_item_id;

                if (!isset($comparisonData[$itemId])) {
                    $comparisonData[$itemId] = [
                        'item' => $response->requisitionItem,
                        'responses' => []
                    ];
                }

                $comparisonData[$itemId]['responses'][] = [
                    'response' => $response,
                    'supplier' => $rfq->supplier,
                    'rfq' => $rfq,
                ];
            }
        }

        return view('rfq.compare', compact('requisition', 'comparisonData'));
    }

    /**
     * Aprobar cotización(es) ganadoras.
     * 
     * @param Request $request
     * @param Requisition $requisition
     * @return JsonResponse
     */
    public function approve(Request $request, Requisition $requisition): JsonResponse
    {
        $validated = $request->validate([
            'response_ids' => 'required|array|min:1',
            'response_ids.*' => 'integer|exists:rfq_responses,id',
            'justifications' => 'nullable|array',
            'justifications.*' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            foreach ($validated['response_ids'] as $index => $responseId) {
                $response = RfqResponse::findOrFail($responseId);

                $justification = $validated['justifications'][$index] ?? null;

                $response->approve(Auth::id(), $justification);

                Log::info('✅ Cotización aprobada', [
                    'response_id' => $response->id,
                    'item_id' => $response->requisition_item_id,
                    'supplier' => $response->rfq->supplier->name,
                ]);
            }

            // Crear o actualizar QuotationSummary
            $summary = QuotationSummary::firstOrCreate(
                ['requisition_id' => $requisition->id],
                [
                    'subtotal' => 0,
                    'iva_rate' => 16.00,
                    'iva_amount' => 0,
                    'total' => 0,
                ]
            );

            // Calcular totales basados en cotizaciones aprobadas
            $summary->calculateFromApprovedQuotations();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '✅ Cotizaciones aprobadas exitosamente.',
                'data' => [
                    'summary' => $summary,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('❌ Error al aprobar cotizaciones', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar cotizaciones: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Envía una RFQ individual (Cambia estado de DRAFT a SENT)
     * * Misión: Notificar a proveedores y usuarios vinculados evitando duplicidad de correos.
     * Target: Proveedores registrados en el Portal Drill Sergeant.
     *
     * @param Rfq $rfq
     * @return JsonResponse
     */
    public function sendSingle(Rfq $rfq, \App\Services\Rfq\RfqSendService $sendService): JsonResponse
    {
        try {
            $sent = $sendService->send($rfq);

            return response()->json([
                'success' => true,
                'message' => 'RFQ enviada exitosamente a ' . $sent->suppliers->count() . ' proveedor(es).',
            ]);
        } catch (\App\Exceptions\Rfq\InvalidRfqStateException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            // INFORME DE DAÑOS EN LOGS
            Log::error('❌ Falla crítica al enviar RFQ', [
                'rfq_id' => $rfq->id,
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error en el frente de batalla: ' . $e->getMessage()
            ], 500);
        }
    }
}
