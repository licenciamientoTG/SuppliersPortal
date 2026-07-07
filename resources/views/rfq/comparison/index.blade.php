@extends('layouts.zircos')

@section('title', 'Análisis Comparativo - ' . $rfq->folio)

@section('page.title', 'Análisis Comparativo')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('rfq.index') }}">RFQs</a></li>
    <li class="breadcrumb-item active">Comparativo {{ $rfq->folio }}</li>
@endsection

@section('content')
<div class="container-fluid">
    {{-- ENCABEZADO DE OPERACIONES --}}
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="float-end d-flex align-items-center">
                    <span class="badge bg-primary text-white fs-14 shadow-sm">RFQ: {{ $rfq->folio }}</span>
                    @if($itemsNobodyQuoted->isNotEmpty())
                        <button type="button" class="btn btn-outline-primary btn-sm ms-2" id="btnGenerateComplementaryRfq">
                            <i class="ti ti-file-plus me-1"></i>Generar RFQ con partidas faltantes ({{ $itemsNobodyQuoted->count() }})
                        </button>
                    @endif
                </div>
                <h4 class="page-title">Análisis Comparativo de Cotizaciones</h4>
            </div>
        </div>
    </div>

    {{-- ⚠️ ALERTA DE RECHAZO PREVIO --}}
    @if($rfq->quotationSummary?->approval_status === 'rejected')
        <div class="row mb-3">
            <div class="col-12">
                <div class="alert alert-danger border-2 shadow-sm mb-0">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="ti ti-ban fs-30"></i>
                        </div>
                        <div>
                            <h5 class="alert-heading fw-bold mb-1">ADJUDICACIÓN RECHAZADA ANTERIORMENTE</h5>
                            <p class="mb-1 text-dark"><strong>Motivo:</strong> {{ $rfq->quotationSummary->rejection_reason }}</p>
                            <small class="text-muted">Por: <strong>{{ $rfq->quotationSummary->rejector?->name }}</strong> el {{ $rfq->quotationSummary->rejected_at?->format('d/m/Y H:i') }}</small>
                        </div>
                    </div>
                    <div class="mt-3 d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-warning btn-sm" id="btnCancelRejectedRfq">
                            <i class="ti ti-ban me-1"></i>Cancelar Cotización
                        </button>
                        <button type="button" class="btn btn-outline-dark btn-sm" id="btnCancelRejectedRequisition">
                            <i class="ti ti-archive me-1"></i>Cancelar Requisición
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- 🎯 VALIDACIÓN PRESUPUESTAL VISUAL --}}
    <div class="row mb-4">
        <div class="col-12">
            <div id="budget-panel" class="card border-3 border-top border-success shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar-sm me-3">
                            <span class="avatar-title rounded-circle bg-success">
                                <i class="ti ti-check fs-20"></i>
                            </span>
                        </div>
                        <div>
                            <h5 class="mb-1 fw-bold text-success">✓ CONTROL PRESUPUESTAL ACTIVO</h5>
                            <p class="text-muted mb-0 small">
                                Centro de Costos: <strong>{{ $rfq->requisition->primaryCostCenter()?->name ?? 'N/A' }}</strong> |
                                Periodo: <strong>{{ now()->translatedFormat('F Y') }}</strong>
                            </p>
                        </div>
                        <div class="ms-auto text-end">
                            <small class="text-muted d-block italic">Disponible para esta compra</small>
                            <h4 class="mb-0 text-dark fw-bold">
                                {{ $presupuestoDisponible !== null ? '$' . number_format($presupuestoDisponible, 2) : 'Se valida al adjudicar' }}
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 📊 MATRIZ VISUAL DE COMPARACIÓN --}}
    <div class="card shadow border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-centered mb-0 border-light table-hover">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width: 300px;" class="border-bottom-0 align-middle ps-3">Especificaciones / Partidas</th>
                            @foreach($rfq->suppliers as $supplier)
                                @php 
                                    $hasResponded = !is_null($supplier->pivot->responded_at);
                                    $selection = $supplierDiagnostics[$supplier->id] ?? ['allowed' => false, 'reasons' => []];
                                    
                                    // Cálculo de montos para nivel dinámico vía ApprovalService
                                    $subtotal = $rfq->rfqResponses->where('supplier_id', $supplier->id)->where('not_available', false)->sum('subtotal');
                                    $iva = $rfq->rfqResponses->where('supplier_id', $supplier->id)->where('not_available', false)->sum('iva_amount');
                                    $totalProyectado = $subtotal + $iva;

                                    $nivelAsignado = $approvalLevels->first(function($lvl) use ($totalProyectado) {
                                        return $totalProyectado >= $lvl->min_amount && 
                                               (is_null($lvl->max_amount) || $totalProyectado <= $lvl->max_amount);
                                    });
                                @endphp
                                
                                @php
                                    // Vigencia: tomamos la respuesta con menor validity_days (la que vence antes)
                                    $supplierResponses = $rfq->rfqResponses->where('supplier_id', $supplier->id);
                                    $minValidity = $supplierResponses->whereNotNull('validity_days')->min('validity_days');
                                    $quotationDate = $supplierResponses->whereNotNull('quotation_date')->min('quotation_date');
                                    $expiryDate = ($quotationDate && $minValidity)
                                        ? \Carbon\Carbon::parse($quotationDate)->addDays($minValidity)
                                        : null;
                                    $isExpired = $expiryDate && $expiryDate->isPast();
                                    $daysUntilExpiry = $expiryDate
                                        ? max(0, now()->startOfDay()->diffInDays($expiryDate->copy()->startOfDay(), false))
                                        : null;
                                    $expiresSoon = $daysUntilExpiry !== null && !$isExpired && $daysUntilExpiry <= 3;

                                    // Monedas usadas por este proveedor
                                    $currencies = $supplierResponses->pluck('currency')->unique()->filter()->values();
                                    $hasMixedCurrency = $currencies->count() > 1;
                                @endphp
                                <th class="text-center {{ $hasResponded ? 'bg-soft-light' : 'bg-soft-secondary' }} {{ $isExpired ? 'border-danger border-2' : '' }}" style="min-width: 250px;">
                                    <div class="fw-bold fs-14 {{ $hasResponded ? 'text-primary' : 'text-muted' }} {{ $isExpired ? 'text-danger' : '' }}">
                                        {{ $supplier->company_name }}
                                    </div>

                                    <div class="small text-muted mt-1">
                                        {{ $rfq->quotedItemCountForSupplier($supplier->id) }} de {{ $items->count() }} partidas cotizadas
                                    </div>

                                    @if($hasResponded)
                                        <div class="d-flex flex-wrap justify-content-center gap-1 mt-1">
                                            @php
                                                $isManualEntry = $supplierResponses->where('entry_source', 'buyer_manual')->isNotEmpty();
                                            @endphp
                                            @if($isManualEntry)
                                                <span class="badge bg-soft-secondary text-secondary border fs-9" title="Capturada por el comprador, no enviada por el proveedor">
                                                    <i class="ti ti-pencil"></i> CAPTURADA MANUALMENTE
                                                </span>
                                            @endif

                                            @if($nivelAsignado)
                                                <span class="badge bg-soft-{{ $nivelAsignado->color_tag }} text-{{ $nivelAsignado->color_tag }} border border-{{ $nivelAsignado->color_tag }} border-opacity-25 fs-10"
                                                      title="{{ $nivelAsignado->description }}">
                                                    <i class="ti ti-shield-check me-1"></i>{{ strtoupper($nivelAsignado->label) }}
                                                </span>
                                            @endif

                                            @foreach($currencies as $cur)
                                                <span class="badge {{ $cur === 'USD' ? 'bg-warning text-dark' : 'bg-soft-secondary text-secondary' }} fs-9 border">
                                                    {{ $cur }}
                                                </span>
                                            @endforeach

                                            @if($isExpired)
                                                <span class="badge bg-danger fs-9" title="La cotización venció el {{ $expiryDate->format('d/m/Y') }}">
                                                    <i class="ti ti-alert-triangle me-1"></i>OFERTA VENCIDA
                                                </span>
                                            @elseif($expiresSoon)
                                                <span class="badge bg-warning text-dark fs-9" title="Vence el {{ $expiryDate->format('d/m/Y') }}">
                                                    <i class="ti ti-clock me-1"></i>VENCE EN {{ $daysUntilExpiry }}d
                                                </span>
                                            @else
                                                <span class="badge bg-success fs-9 shadow-sm">OFERTA VIGENTE</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="badge bg-warning fs-9 mt-1">SIN RESPUESTA</span>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            <tr>
                                <td class="bg-light ps-3">
                                    <span class="fw-bold text-dark">{{ $item->description }}</span><br>
                                    <small class="text-muted">Cant: {{ number_format($item->quantity, 2) }} {{ $item->unit }}</small>
                                </td>
                                @foreach($rfq->suppliers as $supplier)
                                    @php
                                        $resp = $rfq->rfqResponses->where('supplier_id', $supplier->id)->where('requisition_item_id', $item->id)->first();
                                    @endphp
                                    <td class="{{ $resp ? ($resp->not_available ? 'text-center' : '') : 'bg-soft-danger text-center' }}">
                                        @if($resp && $resp->not_available)
                                            <span class="badge bg-soft-warning text-warning border border-warning border-opacity-25 fs-11">
                                                <i class="ti ti-ban me-1"></i>Producto no disponible
                                            </span>
                                        @elseif($resp)
                                            {{-- 💰 PRECIO, MARCA Y MONEDA --}}
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="fs-15 fw-bold text-dark">
                                                    {{ $resp->currency === 'USD' ? 'US$' : '$' }}{{ number_format($resp->unit_price, 2) }}
                                                    @if($resp->currency && $resp->currency !== 'MXN')
                                                        <span class="badge bg-warning text-dark fs-9 ms-1">{{ $resp->currency }}</span>
                                                    @endif
                                                </span>
                                                <span class="badge bg-soft-info text-info fs-9 border border-info border-opacity-10">
                                                    {{ $resp->brand ?? 'Sin marca' }}
                                                </span>
                                            </div>

                                            {{-- 🚚 DÍAS DE ENTREGA --}}
                                            @if($resp->delivery_days)
                                                <div class="mb-1">
                                                    <span class="badge bg-soft-success text-success border border-success border-opacity-25 fs-9">
                                                        <i class="ti ti-truck me-1"></i>{{ $resp->delivery_days }} dias
                                                    </span>
                                                </div>
                                            @endif

                                            {{-- ESPECIFICACIONES TÉCNICAS (NUEVO) --}}
                                            @if($resp->specifications)
                                                <div class="mb-1 p-1 bg-light rounded border-start border-primary border-2">
                                                    <small class="d-block text-dark fw-semibold" style="font-size: 10px;">ESPECIFICACIONES:</small>
                                                    <small class="text-muted d-block lh-sm" style="font-size: 10px;">{{ Str::limit($resp->specifications, 100) }}</small>
                                                </div>
                                            @endif

                                            {{-- GARANTÍA Y ADJUNTO (NUEVO) --}}
                                            <div class="d-flex gap-1 mb-1">
                                                @if($resp->warranty_terms)
                                                    <span class="badge bg-soft-dark text-dark border border-dark border-opacity-10 fs-9" title="Garantía ofrecida">
                                                        <i class="ti ti-shield-check me-1"></i>{{ $resp->warranty_terms }}
                                                    </span>
                                                @endif
                                                
                                                @if($resp->attachment_path)
                                                    <a href="{{ asset('storage/' . $resp->attachment_path) }}" target="_blank" 
                                                       class="badge bg-soft-primary text-primary border border-primary border-opacity-10 fs-9 text-decoration-none" 
                                                       title="Ver archivo adjunto">
                                                        <i class="ti ti-paperclip me-1"></i>VER DOC
                                                    </a>
                                                @endif
                                            </div>

                                            {{-- 📝 NOTAS ADICIONALES (NUEVO) --}}
                                            @if($resp->notes)
                                                <div class="p-1" style="background-color: #fffcf0; border: 1px dashed #f6e05e; border-radius: 4px;">
                                                    <small class="text-muted italic d-block" style="font-size: 10px;">
                                                        <i class="ti ti-message-2 me-1"></i>{{ $resp->notes }}
                                                    </small>
                                                </div>
                                            @endif
                                        @else
                                            <span class="text-danger fw-bold fs-11"><i class="ti ti-clock-exclamation me-1"></i>SIN OFERTA</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach

                        {{-- CRITERIOS TÉCNICOS GLOBALES --}}
                        <tr class="table-secondary text-dark fw-bold small">
                            <td class="ps-3"><i class="ti ti-wallet me-1 text-primary"></i>Condiciones de Pago</td>
                            @foreach($rfq->suppliers as $supplier)
                                @php $fResp = $rfq->rfqResponses->where('supplier_id', $supplier->id)->first(); @endphp
                                <td class="text-center">{{ $fResp->payment_terms ?? '—' }}</td>
                            @endforeach
                        </tr>

                        {{-- DÍAS DE ENTREGA MÁXIMO --}}
                        <tr class="table-secondary text-dark fw-bold small">
                            <td class="ps-3">
                                <i class="ti ti-truck me-1 text-warning"></i>Entrega Máx. (días)
                            </td>
                            @foreach($rfq->suppliers as $supplier)
                                @php
                                    $maxDays = $rfq->rfqResponses->where('supplier_id', $supplier->id)->whereNotNull('delivery_days')->max('delivery_days');
                                @endphp
                                <td class="text-center">
                                    @if($maxDays)
                                        <span class="{{ $onTime ? 'text-success' : 'text-danger' }} fw-bold">
                                            {{ $maxDays }} días
                                        </span>
                                        @if(!$onTime)
                                            <br><small class="text-danger">
                                                <i class="ti ti-alert-triangle me-1"></i>No cumple fecha
                                            </small>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>

                        {{-- 💰 TOTALES GLOBALES --}}
                        <tr class="table-dark">
                            <td class="text-end fw-bold ps-3 border-0">TOTAL FINAL (IVA INCLUIDO)</td>
                            @foreach($rfq->suppliers as $supplier)
                                @php 
                                    $hasResponded = !is_null($supplier->pivot->responded_at);
                                    $subtotal = $rfq->rfqResponses->where('supplier_id', $supplier->id)->where('not_available', false)->sum('subtotal');
                                    $iva = $rfq->rfqResponses->where('supplier_id', $supplier->id)->where('not_available', false)->sum('iva_amount');
                                    $total = $subtotal + $iva;
                                    $isOver = $presupuestoDisponible !== null ? $total > $presupuestoDisponible : false;
                                @endphp
                                <td class="text-center border-0">
                                    @if($hasResponded)
                                        <h4 class="mb-0 text-white">${{ number_format($total, 2) }}</h4>
                                        <small class="text-light fs-10 d-block">Monto IVA: ${{ number_format($iva, 2) }}</small>
                                        @if($isOver)
                                            <span class="badge bg-danger fs-10 mt-1 shadow-sm"><i class="ti ti-alert-triangle me-1"></i>EXCEDE PRESUPUESTO</span>
                                        @endif
                                    @else
                                        <h5 class="text-muted mb-0">---</h5>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="small text-muted italic ps-3 py-3 border-0">
                                * Los precios incluyen impuestos y cargos logísticos configurados por el proveedor.
                            </td>
                            @foreach($rfq->suppliers as $supplier)
                                @php 
                                    $hasResponded = !is_null($supplier->pivot->responded_at);
                                    $sub = $rfq->rfqResponses->where('supplier_id', $supplier->id)->where('not_available', false)->sum('subtotal');
                                    $tax = $rfq->rfqResponses->where('supplier_id', $supplier->id)->where('not_available', false)->sum('iva_amount');
                                    $totalFinal = $sub + $tax;
                                @endphp
                                <td class="text-center py-3 border-0">
                                    @if($hasResponded)
                                        @php
                                            $maxDelivery = $rfq->rfqResponses->where('supplier_id', $supplier->id)->whereNotNull('delivery_days')->max('delivery_days');
                                            $supplierCurrencies = $rfq->rfqResponses->where('supplier_id', $supplier->id)->pluck('currency')->unique()->filter()->implode(', ');
                                            $supplierExpired = false;
                                            $sResps = $rfq->rfqResponses->where('supplier_id', $supplier->id);
                                            $sMinVal = $sResps->whereNotNull('validity_days')->min('validity_days');
                                            $sQDate  = $sResps->whereNotNull('quotation_date')->min('quotation_date');
                                            if ($sMinVal && $sQDate) {
                                                $supplierExpired = \Carbon\Carbon::parse($sQDate)->addDays($sMinVal)->isPast();
                                            }
                                        @endphp
                                        @if($selection['allowed'])
                                        <button type="button"
                                                class="btn btn-primary btn-sm btn-select-winner shadow-sm px-4 rounded-pill"
                                                data-supplier-id="{{ $supplier->id }}"
                                                data-supplier-name="{{ $supplier->company_name }}"
                                                data-total="{{ number_format($totalFinal, 2) }}"
                                                data-delivery="{{ $maxDelivery ?? '—' }}"
                                                data-currency="{{ $supplierCurrencies ?: 'MXN' }}"
                                                {{ (($presupuestoDisponible !== null && $totalFinal > $presupuestoDisponible) || $supplierExpired) ? 'disabled' : '' }}
                                                title="{{ $supplierExpired ? 'No se puede adjudicar: la oferta está vencida' : '' }}">
                                            <i class="ti ti-trophy me-1"></i>Adjudicar
                                        </button>
                                        @else
                                            <button type="button"
                                                    class="btn btn-outline-danger btn-sm btn-show-restrictions shadow-sm px-4 rounded-pill"
                                                    data-supplier-name="{{ $supplier->company_name }}"
                                                    data-reasons='@json($selection["reasons"])'>
                                                <i class="ti ti-lock-exclamation me-1"></i>Bloqueada
                                            </button>
                                            @if(!empty($selection['reasons']))
                                                <div class="mt-2 text-danger small fw-semibold">
                                                    {{ $selection['reasons'][0] }}
                                                </div>
                                            @endif
                                        @endif
                                    @else
                                        <button class="btn btn-light btn-sm rounded-pill" disabled>En espera</button>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- MODAL DE ADJUDICACIÓN --}}
<div class="modal fade" id="modalAdjudicar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="awardForm" action="{{ route('rfq.comparison.select', $rfq) }}" method="POST" class="modal-content border-0 shadow-lg">
            @csrf
            <input type="hidden" name="supplier_id" id="winner_id">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title" id="awardModalTitle"><i class="ti ti-shield-check me-2"></i>Confirmar Selección de Proveedor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="card bg-light border-0 mb-3 shadow-none">
                    <div class="card-body">
                        <p class="mb-1 text-muted small">Proveedor Ganador Seleccionado:</p>
                        <h5 class="fw-bold text-primary mb-2" id="winner_name"></h5>
                        <hr class="my-2">
                        <div class="row g-2 text-center">
                            <div class="col-6">
                                <small class="text-muted d-block">Monto Total (IVA inc.)</small>
                                <strong class="fs-15 text-dark" id="winner_total"></strong>
                                <small class="text-muted d-block" id="winner_currency_label"></small>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Días de Entrega Máx.</small>
                                <strong class="fs-15 text-dark" id="winner_delivery"></strong>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold"><i class="ti ti-message-dots me-1"></i>Justificación Técnica para Aprobación</label>
                    <textarea name="justification" class="form-control border-primary border-opacity-25" rows="4" 
                              placeholder="Especifique el criterio de selección: mejor precio, menor tiempo de entrega, calidad superior..." 
                              required></textarea>
                </div>

                <div class="mb-0">
                    <label class="form-label fw-bold"><i class="ti ti-notes me-1"></i>Notas Adicionales (Internas)</label>
                    <input type="text" name="notes" class="form-control" placeholder="Observaciones que verá el nivel aprobador">
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary px-4 shadow rounded-pill">
                    <i class="ti ti-device-floppy me-1"></i><span id="awardSubmitText">Enviar a Aprobación</span>
                </button>
            </div>
        </form>
    </div>
</div>
<form id="cancelRejectedRfqForm" action="{{ route('rfq.comparison.cancel-rejected', $rfq) }}" method="POST" class="d-none">
    @csrf
    <input type="hidden" name="reason" id="cancelRejectedRfqReason">
</form>
<form id="cancelRejectedRequisitionForm" action="{{ route('requisitions.workflow.cancel', $rfq->requisition_id) }}" method="POST" class="d-none">
    @csrf
    <input type="hidden" name="reason" id="cancelRejectedRequisitionReason">
</form>

@if($itemsNobodyQuoted->isNotEmpty())
<div class="modal fade" id="complementaryRfqModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('rfq.comparison.generate-complementary', $rfq) }}">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="ti ti-file-plus me-2"></i>Generar RFQ con partidas faltantes</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Estas partidas no fueron cotizadas por ningún proveedor. Selecciona cuáles incluir y a qué proveedores enviar la nueva solicitud.</p>

                    <label class="fw-bold small d-block mb-2">Partidas a incluir</label>
                    @foreach($itemsNobodyQuoted as $item)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="item_ids[]" value="{{ $item->id }}" id="citem_{{ $item->id }}" checked>
                            <label class="form-check-label small" for="citem_{{ $item->id }}">
                                {{ $item->description }} <span class="text-muted">({{ number_format($item->quantity, 2) }} {{ $item->unit }})</span>
                            </label>
                        </div>
                    @endforeach

                    <hr>

                    <label class="fw-bold small d-block mb-2">Proveedores destino</label>
                    <select name="supplier_ids[]" class="form-select form-select-sm" multiple size="6" required>
                        @foreach($approvedSuppliers as $sup)
                            <option value="{{ $sup->id }}">{{ $sup->company_name }}</option>
                        @endforeach
                    </select>

                    <div class="row g-2 mt-2">
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Fecha límite de respuesta</label>
                            <input type="date" name="response_deadline" class="form-control form-control-sm" required min="{{ now()->addDay()->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Mensaje (opcional)</label>
                            <input type="text" name="message" class="form-control form-control-sm" placeholder="Instrucciones para el proveedor">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="ti ti-send me-1"></i>Generar y enviar</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.getElementById('btnGenerateComplementaryRfq')?.addEventListener('click', function () {
        new bootstrap.Modal(document.getElementById('complementaryRfqModal')).show();
    });
</script>
@endpush
@endif
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $('.btn-select-winner').on('click', function() {
            const id       = $(this).data('supplier-id');
            const name     = $(this).data('supplier-name');
            const total    = $(this).data('total');
            const delivery = $(this).data('delivery');
            const currency = $(this).data('currency');

            $('#winner_id').val(id);
            $('#winner_name').text(name);
            $('#winner_total').text('$' + total);
            $('#winner_delivery').text(delivery !== '—' ? delivery + ' días' : '—');
            $('#winner_currency_label').text(currency !== 'MXN' ? '⚠ Cotización en ' + currency : '');
            $('#modalAdjudicar').modal('show');
        });

        $('.btn-show-restrictions').on('click', function() {
            const name = $(this).data('supplier-name');
            const reasons = $(this).data('reasons') || [];
            const html = Array.isArray(reasons) && reasons.length
                ? '<ul class="text-start mb-0 ps-3">' + reasons.map(reason => `<li>${reason}</li>`).join('') + '</ul>'
                : '<p class="mb-0">No se pudo determinar el motivo del bloqueo.</p>';

            Swal.fire({
                icon: 'warning',
                title: 'Adjudicación bloqueada',
                html: `<p class="mb-3"><strong>${name}</strong> no puede adjudicarse en este momento.</p>${html}`,
                confirmButtonText: 'Entendido'
            });
        });
    });
</script>
@endpush

@push('scripts')
<script>
    $(document).ready(function() {
        const rejectedFlow = @json($rfq->isRejected() && $rfq->quotationSummary?->approval_status === 'rejected');
        const selectAction = @json(route('rfq.comparison.select', $rfq));
        const reawardAction = @json(route('rfq.comparison.reaward', $rfq));

        $('.btn-select-winner').off('click').on('click', function() {
            const id = $(this).data('supplier-id');
            const name = $(this).data('supplier-name');
            const total = $(this).data('total');
            const delivery = $(this).data('delivery');
            const currency = $(this).data('currency');

            $('#awardForm').attr('action', rejectedFlow ? reawardAction : selectAction);
            $('#awardModalTitle').html(
                rejectedFlow
                    ? '<i class="ti ti-refresh me-2"></i>Confirmar Nueva Vuelta de Adjudicacion'
                    : '<i class="ti ti-shield-check me-2"></i>Confirmar Seleccion de Proveedor'
            );
            $('#awardSubmitText').text(
                rejectedFlow ? 'Crear nueva vuelta y enviar a aprobacion' : 'Enviar a Aprobacion'
            );
            $('#winner_id').val(id);
            $('#winner_name').text(name);
            $('#winner_total').text('$' + total);
            $('#winner_delivery').text(delivery !== '—' ? delivery + ' dias' : '—');
            $('#winner_currency_label').text(currency !== 'MXN' ? 'Cotizacion en ' + currency : '');
            $('#modalAdjudicar').modal('show');
        });

        $('#btnCancelRejectedRfq').off('click').on('click', function() {
            Swal.fire({
                icon: 'warning',
                title: 'Cancelar cotizacion',
                input: 'textarea',
                inputLabel: 'Motivo de cancelacion',
                inputPlaceholder: 'Describe por que Compras decide cerrar esta cotizacion...',
                inputAttributes: {
                    maxlength: 500
                },
                showCancelButton: true,
                confirmButtonText: 'Cancelar cotizacion',
                cancelButtonText: 'Volver',
                preConfirm: (value) => {
                    const reason = (value || '').trim();

                    if (reason.length < 10) {
                        Swal.showValidationMessage('Captura un motivo de al menos 10 caracteres.');
                        return false;
                    }

                    return reason;
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    $('#cancelRejectedRfqReason').val(result.value);
                    $('#cancelRejectedRfqForm').trigger('submit');
                }
            });
        });

        $('#btnCancelRejectedRequisition').off('click').on('click', function() {
            Swal.fire({
                icon: 'warning',
                title: 'Cancelar requisicion completa',
                html: '<p class="mb-2">Esta accion cerrara la requisicion y todas sus RFQ activas sin borrar registros.</p>',
                input: 'textarea',
                inputLabel: 'Motivo de cancelacion',
                inputPlaceholder: 'Explica por que Compras decide cerrar la requisicion completa...',
                inputAttributes: {
                    maxlength: 500
                },
                showCancelButton: true,
                confirmButtonText: 'Cancelar requisicion',
                cancelButtonText: 'Volver',
                preConfirm: (value) => {
                    const reason = (value || '').trim();

                    if (reason.length < 10) {
                        Swal.showValidationMessage('Captura un motivo de al menos 10 caracteres.');
                        return false;
                    }

                    return reason;
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    $('#cancelRejectedRequisitionReason').val(result.value);
                    $('#cancelRejectedRequisitionForm').trigger('submit');
                }
            });
        });
    });
</script>
@endpush
