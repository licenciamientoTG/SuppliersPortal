@extends('layouts.zircos-supplier')

@php
    // Verificamos si TODAS las partidas están ya en un estado que no sea DRAFT
    $allLocked = $items->every(function($item) use ($responses) {
        $resp = $responses->get($item->id);
        return $resp && $resp->status !== 'DRAFT';
    });

    // Moneda por defecto según las monedas que maneja el proveedor.
    // Solo es USD cuando el proveedor maneja USD pero NO MXN; en cualquier otro
    // caso (ambas, solo MXN, o ninguna) se usa MXN. Siempre es editable.
    $acceptedCurrencies = is_array($supplier->accepted_currencies) ? $supplier->accepted_currencies : [];
    $defaultCurrency = (in_array('USD', $acceptedCurrencies, true) && ! in_array('MXN', $acceptedCurrencies, true))
        ? 'USD'
        : 'MXN';

    $profilePaymentTerm = $supplier->default_payment_terms instanceof \App\Enum\PaymentTerm
        ? $supplier->default_payment_terms
        : \App\Enum\PaymentTerm::tryFrom((string) $supplier->default_payment_terms);
    $defaultPaymentTerms = old(
        'global_payment_terms',
        $responses->first()?->payment_terms ?? $profilePaymentTerm?->label() ?? $supplier->default_payment_terms ?? ''
    );

    $itemPurchasingNotes = $items
        ->pluck('notes')
        ->filter(fn ($note) => filled($note))
        ->unique()
        ->values();
@endphp

@section('title', 'Cotizar RFQ - ' . $rfq->folio)

@section('page.title', 'Cotizar RFQ')

{{-- Breadcrumbs personalizados --}}
@section('page.breadcrumbs')
    <li class="breadcrumb-item">
        <a href="{{ route('supplier.dashboard') }}">Dashboard</a>
    </li>
    <li class="breadcrumb-item active">RFQ {{ $rfq->folio }}</li>
@endsection

@section('content')
<div class="container-fluid py-4">

    <div class="row">
        {{-- COLUMNA IZQUIERDA: Información del RFQ (Sticky) --}}
        <div class="col-lg-4 col-xl-3 mb-4">
            <div class="sticky-top" style="top: 20px;">
                
                {{-- Card: Información General --}}
                <div class="card mb-3 shadow-sm">
                    <div class="card-header bg-primary text-white py-2">
                        <h6 class="mb-0">
                            <i class="ti ti-file-invoice me-2"></i>
                            Información del RFQ
                        </h6>
                    </div>
                    <div class="card-body p-3">
                        <div class="mb-3">
                            <small class="text-muted d-block">Folio RFQ</small>
                            <strong class="d-block">{{ $rfq->folio }}</strong>
                        </div>
                        
                        @if($rfq->quotationGroup)
                        <div class="mb-3">
                            <small class="text-muted d-block">Grupo de Cotización</small>
                            <span class="badge bg-info">
                                <i class="ti ti-layers me-1"></i>{{ $rfq->quotationGroup->name }}
                            </span>
                        </div>
                        @endif
                        
                        <div class="mb-3">
                            <small class="text-muted d-block">Estado</small>
                            @switch($rfq->status)
                                @case('SENT')
                                    <span class="badge bg-warning">
                                        <i class="ti ti-send me-1"></i>Recibida
                                    </span>
                                    @break
                                @case('RECEIVED')
                                    <span class="badge bg-info">
                                        <i class="ti ti-inbox me-1"></i>Respuestas Recibidas
                                    </span>
                                    @break
                                @case('EVALUATED')
                                    <span class="badge bg-primary">
                                        <i class="ti ti-chart-bar me-1"></i>Evaluada
                                    </span>
                                    @break
                                @case('COMPLETED')
                                    <span class="badge bg-success">
                                        <i class="ti ti-check me-1"></i>Completada
                                    </span>
                                    @break
                                @default
                                    <span class="badge bg-secondary">{{ $rfq->status }}</span>
                            @endswitch
                        </div>
                        
                        @if($rfq->sent_at)
                        <div class="mb-3">
                            <small class="text-muted d-block">Fecha de Envío</small>
                            <span class="d-block small">{{ $rfq->sent_at->format('d/m/Y H:i') }}</span>
                        </div>
                        @endif

                        <div class="mb-0">
                            <small class="text-muted d-block">Fecha Límite de Respuesta</small>
                            @if($rfq->response_deadline)
                                @php
                                    $daysRemaining = now()->diffInDays($rfq->response_deadline, false);
                                    $daysRemainingLabel = format_days($daysRemaining);
                                @endphp
                                <strong class="d-block">{{ $rfq->response_deadline->format('d/m/Y H:i') }}</strong>
                                @if($daysRemaining < 0)
                                    <span class="badge bg-danger mt-1">
                                        <i class="ti ti-alert-triangle me-1"></i>Vencida
                                    </span>
                                @elseif($daysRemaining === 0)
                                    <span class="badge bg-warning mt-1">
                                        <i class="ti ti-clock me-1"></i>Vence hoy
                                    </span>
                                @elseif($daysRemaining <= 3)
                                    <span class="badge bg-warning mt-1">
                                        <i class="ti ti-clock me-1"></i>{{ $daysRemainingLabel }} días
                                    </span>
                                @else
                                    <span class="badge bg-success mt-1">
                                        <i class="ti ti-calendar me-1"></i>{{ $daysRemainingLabel }} días
                                    </span>
                                @endif
                            @else
                                <span class="text-muted">No especificada</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Card: Instrucciones y Requisitos de Compras --}}
                @if($rfq->message || $rfq->requirements || $rfq->notes)
                <div class="card mb-3 shadow-sm border-warning border-2">
                    <div class="card-header bg-warning bg-opacity-10 py-2">
                        <h6 class="mb-0 text-warning-emphasis">
                            <i class="ti ti-alert-circle me-2"></i>
                            Instrucciones de Compras
                        </h6>
                    </div>
                    <div class="card-body p-3">
                        @if($rfq->message)
                        <div class="mb-3">
                            <label class="text-muted d-block fw-semibold" style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">
                                <i class="ti ti-message-circle me-1"></i>Mensaje al Proveedor
                            </label>
                            <p class="mb-0 small">{{ $rfq->message }}</p>
                        </div>
                        @endif

                        @if($rfq->requirements)
                        @if($rfq->message)<hr class="my-2">@endif
                        <div class="mb-3">
                            <label class="text-muted d-block fw-semibold" style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">
                                <i class="ti ti-list-check me-1"></i>Requisitos / Instrucciones Especiales
                            </label>
                            <p class="mb-0 small" style="white-space: pre-line;">{{ $rfq->requirements }}</p>
                        </div>
                        @endif

                    </div>
                </div>
                @endif

                {{-- Card: Información de la Requisición --}}
                @if($rfq->requisition)
                <div class="card mb-3 shadow-sm">
                    <div class="card-header bg-light py-2">
                        <h6 class="mb-0">
                            <i class="ti ti-clipboard-list me-2"></i>
                            Datos de la Requisición
                        </h6>
                    </div>
                    <div class="card-body p-3">
                        <div class="mb-2">
                            <small class="text-muted d-block">Folio Requisición</small>
                            <strong class="d-block">{{ $rfq->requisition->folio }}</strong>
                        </div>

                        @if($rfq->requisition->company)
                        <div class="mb-2">
                            <small class="text-muted d-block">Empresa</small>
                            <span class="d-block small fw-semibold">{{ $rfq->requisition->company->name }}</span>
                        </div>
                        @endif

                        @if($itemPurchasingNotes->isNotEmpty())
                        <div class="mb-2">
                            <small class="text-muted d-block">Nota de Compras</small>
                            @foreach($itemPurchasingNotes as $note)
                                <span class="d-block small" style="white-space: pre-line;">{{ $note }}</span>
                            @endforeach
                        </div>
                        @endif

                        @if($rfq->requisition->receivingLocation)
                        <div class="mb-2">
                            <small class="text-muted d-block">Punto de Entrega</small>
                            <span class="d-block small fw-semibold">
                                <i class="ti ti-map-pin me-1 text-danger"></i>
                                {{ $rfq->requisition->receivingLocation->code }} — {{ $rfq->requisition->receivingLocation->name }}
                            </span>
                        </div>
                        @endif

                        @if($rfq->requisition->description)
                        <div class="mb-0">
                            <small class="text-muted d-block">Descripción</small>
                            <p class="mb-0 small">{{ $rfq->requisition->description }}</p>
                        </div>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Card: Resumen --}}
                <div class="card shadow-sm">
                    <div class="card-header bg-light py-2">
                        <h6 class="mb-0">
                            <i class="ti ti-calculator me-2"></i>
                            Resumen de Cotización
                        </h6>
                    </div>
                    <div class="card-body p-3">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="text-muted fw-bold mb-0">Partidas cotizadas:</small>
                                <span id="summary-quoted-count" class="badge bg-light text-dark"></span>
                            </div>
                            <div id="summary-items" class="small">
                                {{-- Se llena dinámicamente con JavaScript --}}
                            </div>
                        </div>
                        
                        <hr class="my-2">
                        
                        <div class="d-flex justify-content-between mb-1">
                            <small class="text-muted">Subtotal (sin IVA):</small>
                            <strong class="small">$<span id="summary-subtotal">0.00</span></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <small class="text-muted">IVA total:</small>
                            <strong class="small text-info">$<span id="summary-iva">0.00</span></strong>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <strong class="text-primary">Total con IVA:</strong>
                            <h5 class="mb-0 text-primary">$<span id="grand-total">0.00</span></h5>
                        </div>
                        
                        <div class="mt-2 text-center">
                            <span class="badge bg-light text-dark">
                                <i class="ti ti-package me-1"></i>
                                {{ $items->count() }} partida(s)
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Botón Volver --}}
                <a href="{{ route('supplier.dashboard') }}" class="btn btn-outline-secondary w-100 mt-3">
                    <i class="ti ti-arrow-left me-2"></i>Volver al Dashboard
                </a>

            </div>
        </div>

        {{-- COLUMNA DERECHA: Formulario de Cotización --}}
        <div class="col-lg-8 col-xl-9">
            
            <form action="{{ route('supplier.rfq.quotation.save', $rfq) }}" 
                  method="POST" 
                  enctype="multipart/form-data"
                  id="quotation-form">
                @csrf

                <section class="tg-quote-hero mb-3" aria-labelledby="quote-form-title">
                    <div class="tg-quote-logo-wrap" aria-hidden="true">
                        <img src="{{ asset('images/logos/Logo.png') }}" alt="" class="tg-quote-logo-spinner">
                    </div>
                    <div class="tg-quote-hero-content">
                        <span class="tg-eyebrow"><i class="ti ti-pencil-check me-1"></i>Respuesta de cotización</span>
                        <h2 id="quote-form-title">Completa tu propuesta</h2>
                        <p class="mb-0">Guarda un borrador cuando quieras; envía la cotización cuando esté lista.</p>
                    </div>
                </section>

                {{-- Datos Generales de la Cotización --}}
                <div class="card mb-3 shadow-sm tg-quote-section">
                    <div class="card-header tg-section-header">
                        <div>
                            <span class="tg-section-kicker">Paso 1</span>
                            <h6 class="mb-0"><i class="ti ti-file-text me-2"></i>Datos generales</h6>
                        </div>
                        <span class="tg-optional-note">Folio, vigencia y PDF son opcionales</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-3">
                            <div class="col-md-6 col-xl-4">
                                <label for="supplier_quotation_number" class="form-label form-label-sm">
                                    Número de tu cotización <span class="tg-field-optional">Opcional</span>
                                </label>
                                <input type="text" 
                                    class="form-control form-control-sm @error('supplier_quotation_number') is-invalid @enderror" 
                                    id="supplier_quotation_number" 
                                    name="supplier_quotation_number"
                                    value="{{ old('supplier_quotation_number') }}"
                                    placeholder="Ej: COT-2025-001"
                                    {{ $allLocked ? 'disabled' : '' }}>
                                @error('supplier_quotation_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 col-xl-3">
                                <label for="validity_days" class="form-label form-label-sm">
                                    Vigencia (días) <span class="tg-field-optional">Opcional</span>
                                </label>
                                <input type="number" 
                                    class="form-control form-control-sm @error('validity_days') is-invalid @enderror" 
                                    id="validity_days" 
                                    name="validity_days"
                                    value="{{ old('validity_days', 30) }}"
                                    min="1"
                                    max="365"
                                    placeholder="30"
                                    {{ $allLocked ? 'disabled' : '' }}>
                                @error('validity_days')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- CAMPO GLOBAL PARCHADO --}}
                            <div class="col-md-12 col-xl-5">
                                <label for="global_payment_terms" class="form-label form-label-sm fw-bold">
                                    <i class="ti ti-credit-card me-1"></i>Condiciones de pago <span class="tg-required-mark">*</span>
                                    <span class="tg-required-label">Obligatorio al enviar</span>
                                </label>
                                <input type="text" 
                                    id="global_payment_terms" 
                                    name="global_payment_terms"
                                    class="form-control form-control-sm tg-required-input @error('global_payment_terms') is-invalid @enderror"
                                    placeholder="Ej: Crédito 30 días, Contado, etc."
                                    value="{{ $defaultPaymentTerms }}"
                                    {{ $allLocked ? 'disabled' : '' }}>
                                <div class="form-text">Se aplicarán a todas las partidas de esta cotización.</div>
                                @error('global_payment_terms')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- ========================================================== --}}
                            {{-- SECCIÓN DE CARGA DE PDF CON LÓGICA CONDICIONAL --}}
                            {{-- ========================================================== --}}
                            @php
                                // Obtener datos del pivote para verificar si hay PDF cargado
                                $pivotData = $rfq->suppliers->find($supplier->id)?->pivot;
                                $hasPdf = $pivotData && $pivotData->quotation_pdf_path && Storage::disk('public')->exists($pivotData->quotation_pdf_path);
                                $pdfUrl = $hasPdf ? Storage::disk('public')->url($pivotData->quotation_pdf_path) : null;
                                $pdfFileName = $hasPdf ? basename($pivotData->quotation_pdf_path) : null;
                            @endphp

                            <div class="col-12 col-md-6 col-lg-4">
                                <label class="form-label form-label-sm">
                                    <i class="ti ti-file-upload me-1"></i>Cotización en PDF 
                                    <span class="text-muted">(Opcional)</span>
                                </label>

                                {{-- SI HAY PDF CARGADO --}}
                                @if($hasPdf)
                                <div class="border border-success rounded-3 p-2 bg-success bg-opacity-10">
                                    {{-- Información del archivo --}}
                                    <div class="d-flex align-items-center mb-2 gap-2">
                                        <i class="ti ti-file-type-pdf text-danger fs-3"></i>
                                        <div class="flex-grow-1 overflow-hidden">
                                            <div class="text-truncate fw-semibold text-dark" 
                                                style="font-size: 0.8rem;" 
                                                title="{{ $pdfFileName }}">
                                                {{ $pdfFileName }}
                                            </div>
                                            <div class="text-success" style="font-size: 0.7rem;">
                                                <i class="ti ti-check"></i> Archivo cargado
                                            </div>
                                        </div>
                                    </div>
                                    
                                    {{-- Botones --}}
                                    <div class="d-grid gap-1">
                                        <a href="{{ $pdfUrl }}" 
                                        target="_blank" 
                                        class="btn btn-sm btn-primary w-100">
                                            <i class="ti ti-eye me-1"></i> Ver PDF
                                        </a>
                                        
                                        @if(!$allLocked)
                                            <div class="btn-group w-100">
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-warning" 
                                                        id="btnChangePDF">
                                                    <i class="ti ti-edit me-1"></i> Cambiar
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger" 
                                                        id="btnDeletePDF">
                                                    <i class="ti ti-trash"></i>
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                @else
                                    <div class="d-grid gap-2">
                                        <button type="button" 
                                                class="btn btn-outline-primary btn-sm" 
                                                id="btnUploadPDF"
                                                {{ $allLocked ? 'disabled' : '' }}>
                                            <i class="ti ti-upload me-2"></i>
                                            <span id="btnUploadText">Cargar PDF de Cotización</span>
                                        </button>
                                    </div>
                                @endif

                                {{-- Input file oculto (siempre presente) --}}
                                <input type="file" 
                                    id="quotation_pdf_file" 
                                    name="quotation_pdf_file" 
                                    accept=".pdf,application/pdf" 
                                    style="display: none;">

                                {{-- Campo hidden para indicar eliminación --}}
                                <input type="hidden" id="delete_pdf_flag" name="delete_pdf_flag" value="0">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Partidas a Cotizar --}}
                @if($items->isEmpty())
                    <div class="alert alert-warning">
                        <i class="ti ti-alert-triangle me-2"></i>
                        No hay partidas para cotizar en esta RFQ.
                    </div>
                @else
                    <div class="card shadow-sm tg-quote-section">
                        <div class="card-header tg-section-header">
                            <div>
                                <span class="tg-section-kicker">Paso 2</span>
                                <h6 class="mb-0"><i class="ti ti-list-details me-2"></i>Partidas a cotizar ({{ $items->count() }})</h6>
                            </div>
                            <span class="tg-required-legend"><span class="tg-required-mark">*</span> Obligatorio al enviar</span>
                        </div>
                        <div class="card-body p-2">
                            
                            @foreach($items as $index => $item)
                                @php
                                    $existingResponse = $responses->get($item->id);
                                    $isLocked = $existingResponse && $existingResponse->status !== 'DRAFT';
                                    $itemPrefix = "items[{$index}]";
                                @endphp
                                
                                <div class="quotation-item border rounded-3 p-3 mb-3 {{ $isLocked ? 'bg-light border-warning' : 'bg-white' }}" data-item-index="{{ $index }}">
    
                                    {{-- Header de la Partida --}}
                                    <div class="d-flex align-items-start justify-content-between mb-3">
                                        <div class="d-flex align-items-start flex-grow-1">
                                            <span class="badge bg-dark me-2 mt-1">{{ $index + 1 }}</span>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1 fw-bold">
                                                    {{ $item->description }}
                                                    @if($isLocked)
                                                        <i class="ti ti-lock text-warning ms-1" title="Partida bloqueada (ya enviada)"></i>
                                                    @endif
                                                </h6>
                                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                                    <small class="text-muted">
                                                        <i class="ti ti-package me-1"></i>{{ $item->quantity }} {{ $item->unit }}
                                                    </small>
                                                    @if(!empty($item->brand) || !empty($item->model))
                                                        <small class="text-muted">
                                                            <i class="ti ti-tag me-1"></i>
                                                            {{ implode(' / ', array_filter([$item->brand ?? null, $item->model ?? null])) }}
                                                        </small>
                                                    @endif
                                                    @if($item->expenseCategory)
                                                        <span class="badge bg-light text-dark border" style="font-size: 0.7rem;">
                                                            <i class="ti ti-folder me-1"></i>{{ $item->expenseCategory->name }}
                                                        </span>
                                                    @endif
                                                    @if($item->productService)
                                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25" style="font-size: 0.7rem;">
                                                            <i class="ti ti-package me-1"></i>{{ $item->productService->short_name ?? $item->productService->name ?? $item->description }}
                                                        </span>
                                                    @endif
                                                </div>
                                                @if(!empty($item->notes))
                                                    <div class="mt-2 p-2 rounded bg-info bg-opacity-10 border border-info border-opacity-25">
                                                        <small class="text-info-emphasis d-block">
                                                            <i class="ti ti-message-2 me-1"></i><strong>Observación del solicitante:</strong>
                                                        </small>
                                                        <small class="text-info-emphasis d-block mt-1">
                                                            {{ $item->notes }}
                                                        </small>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                        
                                        @if($existingResponse)
                                            @php
                                                $statusClasses = [
                                                    'DRAFT' => 'bg-secondary',
                                                    'SUBMITTED' => 'bg-info',
                                                    'APPROVED' => 'bg-success',
                                                    'REJECTED' => 'bg-danger'
                                                ];
                                                $statusLabels = [
                                                    'DRAFT' => 'Borrador',
                                                    'SUBMITTED' => 'Enviada',
                                                    'APPROVED' => 'Aprobada',
                                                    'REJECTED' => 'Rechazada'
                                                ];
                                            @endphp
                                            <span class="badge {{ $statusClasses[$existingResponse->status] ?? 'bg-secondary' }}">
                                                {{ $statusLabels[$existingResponse->status] ?? $existingResponse->status }}
                                            </span>
                                        @endif
                                    </div>

                                    {{-- El item_id lo dejamos habilitado para que el controlador sepa de qué partida hablamos --}}
                                    <input type="hidden" name="{{ $itemPrefix }}[item_id]" value="{{ $item->id }}" {{ $isLocked ? 'disabled' : '' }}>

                                    {{-- Marca de "no disponible" para esta partida --}}
                                    <input type="hidden"
                                           class="item-not-available-flag"
                                           name="{{ $itemPrefix }}[not_available]"
                                           value="{{ old("{$itemPrefix}[not_available]", ($existingResponse && $existingResponse->not_available) ? '1' : '0') }}"
                                           {{ $isLocked ? 'disabled' : '' }}>

                                    @unless($isLocked)
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input toggle-not-available" type="checkbox"
                                               id="not_available_{{ $index }}"
                                               {{ ($existingResponse && $existingResponse->not_available) ? 'checked' : '' }}>
                                        <label class="form-check-label small text-muted" for="not_available_{{ $index }}">
                                            <i class="ti ti-ban me-1"></i>No puedo cotizar esta partida
                                        </label>
                                    </div>
                                    @endunless

                                    <span class="badge bg-warning text-dark item-unavailable-badge mb-2 {{ ($existingResponse && $existingResponse->not_available) ? '' : 'd-none' }}">
                                        <i class="ti ti-ban me-1"></i>Marcada como no disponible
                                    </span>

                                    {{-- Campos del Formulario --}}
                                    <div class="row g-2">
                                        
                                        {{-- FILA 1: Precios y Cálculos --}}
                                        <div class="col-md-2">
                                            <label class="form-label-sm mb-1">Precio unitario <span class="tg-required-mark">*</span> <small class="text-muted">(sin IVA)</small></label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">$</span>
                                                <input type="number" 
                                                    step="0.01"
                                                    min="0.01"
                                                    class="form-control unit-price" 
                                                    name="{{ $itemPrefix }}[unit_price]"
                                                    value="{{ old("{$itemPrefix}[unit_price]", $existingResponse->unit_price ?? '') }}" 
                                                    required
                                                    {{ $isLocked ? 'disabled' : '' }}> {{-- DISABLED SI ESTÁ BLOQUEADO --}}
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-1">
                                            <label class="form-label-sm mb-1">Cantidad <span class="tg-required-mark">*</span></label>
                                            <input type="number" 
                                                step="0.001"
                                                min="0.001"
                                                class="form-control form-control-sm quantity" 
                                                name="{{ $itemPrefix }}[quantity]"
                                                value="{{ old("{$itemPrefix}[quantity]", $existingResponse->quantity ?? $item->quantity) }}" 
                                                required
                                                {{ $isLocked ? 'disabled' : '' }}> {{-- DISABLED --}}
                                        </div>
                                        
                                        <div class="col-md-2">
                                            <label class="form-label-sm mb-1">Subtotal <small class="text-muted">(sin IVA)</small></label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">$</span>
                                                <input type="text" 
                                                    class="form-control bg-light subtotal" 
                                                    readonly 
                                                    value="{{ old("{$itemPrefix}[subtotal]", $existingResponse ? number_format($existingResponse->subtotal, 2) : '0.00') }}">
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-2">
                                            <label class="form-label-sm mb-1">IVA <span class="tg-required-mark">*</span></label>
                                            <select class="form-select form-select-sm iva-rate" 
                                                    name="{{ $itemPrefix }}[iva_rate]"
                                                    {{ $isLocked ? 'disabled' : '' }}> {{-- DISABLED --}}
                                                <option value="16.00" {{ old("{$itemPrefix}[iva_rate]", $existingResponse->iva_rate ?? 16.00) == 16.00 ? 'selected' : '' }}>16%</option>
                                                <option value="8.00" {{ old("{$itemPrefix}[iva_rate]", $existingResponse->iva_rate ?? 16.00) == 8.00 ? 'selected' : '' }}>8%</option>
                                                <option value="0.00" {{ old("{$itemPrefix}[iva_rate]", $existingResponse->iva_rate ?? 16.00) == 0.00 ? 'selected' : '' }}>0%</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-2">
                                            <label class="form-label-sm mb-1">Monto IVA</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">$</span>
                                                <input type="text" 
                                                    class="form-control bg-light iva-amount" 
                                                    readonly 
                                                    value="{{ old("{$itemPrefix}[iva_amount]", $existingResponse ? number_format($existingResponse->iva_amount, 2) : '0.00') }}">
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <label class="form-label-sm mb-1 fw-bold">Total <small class="text-muted">(con IVA)</small></label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text fw-bold">$</span>
                                                <input type="text" 
                                                    class="form-control bg-success bg-opacity-10 fw-bold item-total" 
                                                    readonly 
                                                    value="{{ old("{$itemPrefix}[total]", $existingResponse ? number_format($existingResponse->total, 2) : '0.00') }}">
                                            </div>
                                        </div>

                                        <div class="col-md-3 d-none"> {{-- Lo ocultamos visualmente --}}
                                            <input type="hidden" 
                                                   class="item-payment-terms" 
                                                   name="{{ $itemPrefix }}[payment_terms]" 
                                                   value="{{ old("{$itemPrefix}[payment_terms]", $existingResponse->payment_terms ?? '') }}">
                                        </div>
                                        
                                        <div class="col-md-2">
                                            <label class="form-label-sm mb-1">Días de entrega <span class="tg-required-mark">*</span></label>
                                            <input type="number" 
                                                step="1"
                                                min="0"
                                                class="form-control form-control-sm" 
                                                name="{{ $itemPrefix }}[delivery_days]" 
                                                placeholder="Días"
                                                value="{{ old("{$itemPrefix}[delivery_days]", $existingResponse->delivery_days ?? '') }}"
                                                {{ $isLocked ? 'disabled' : '' }}> {{-- DISABLED --}}
                                        </div>
                                        
                                        <div class="col-md-2">
                                            <label class="form-label-sm mb-1">Moneda <span class="tg-field-optional">Opcional</span></label>
                                            <select class="form-select form-select-sm" name="{{ $itemPrefix }}[currency]" {{ $isLocked ? 'disabled' : '' }}>
                                                <option value="MXN" {{ old("{$itemPrefix}[currency]", $existingResponse->currency ?? $defaultCurrency) == 'MXN' ? 'selected' : '' }}>MXN ($)</option>
                                                <option value="USD" {{ old("{$itemPrefix}[currency]", $existingResponse->currency ?? $defaultCurrency) == 'USD' ? 'selected' : '' }}>USD (US$)</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-2">
                                            <label class="form-label-sm mb-1">Marca <span class="tg-field-optional">Opcional</span></label>
                                            <input type="text" 
                                                class="form-control form-control-sm" 
                                                name="{{ $itemPrefix }}[brand]" 
                                                placeholder="Marca"
                                                value="{{ old("{$itemPrefix}[brand]", $existingResponse->brand ?? '') }}"
                                                {{ $isLocked ? 'disabled' : '' }}> {{-- DISABLED --}}
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <label class="form-label-sm mb-1">Modelo <span class="tg-field-optional">Opcional</span></label>
                                            <input type="text" 
                                                class="form-control form-control-sm" 
                                                name="{{ $itemPrefix }}[model]" 
                                                placeholder="Modelo"
                                                value="{{ old("{$itemPrefix}[model]", $existingResponse->model ?? '') }}"
                                                {{ $isLocked ? 'disabled' : '' }}> {{-- DISABLED --}}
                                        </div>

                                        {{-- FILA 3: Adjuntos --}}
                                        <div class="col-md-3">
                                            <label class="form-label-sm mb-1">
                                                <i class="ti ti-paperclip me-1"></i>Adjunto PDF <span class="tg-field-optional">Opcional</span>
                                                @if($existingResponse && $existingResponse->attachment_path)
                                                    <a href="{{ route('supplier.quotation.download', $existingResponse) }}" 
                                                    class="text-primary ms-2" 
                                                    target="_blank" 
                                                    title="Ver adjunto actual">
                                                        <i class="ti ti-eye"></i> Ver actual
                                                    </a>
                                                @endif
                                            </label>
                                            <input type="file" 
                                                class="form-control form-control-sm" 
                                                name="{{ $itemPrefix }}[attachment]" 
                                                accept=".pdf"
                                                {{ $isLocked ? 'disabled' : '' }}> {{-- DISABLED --}}
                                        </div>

                                        {{-- FILA 4: Áreas de Texto --}}
                                        <div class="col-md-4">
                                            <label class="form-label-sm mb-1">Especificaciones técnicas <span class="tg-field-optional">Opcional</span></label>
                                            <textarea class="form-control form-control-sm" 
                                                    name="{{ $itemPrefix }}[specifications]" 
                                                    rows="2" 
                                                    placeholder="Detalles técnicos del producto/servicio"
                                                    {{ $isLocked ? 'disabled' : '' }}>{{ old("{$itemPrefix}[specifications]", $existingResponse->specifications ?? '') }}</textarea>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label class="form-label-sm mb-1">Garantía <span class="tg-field-optional">Opcional</span></label>
                                            <textarea class="form-control form-control-sm" 
                                                    name="{{ $itemPrefix }}[warranty_terms]" 
                                                    rows="2" 
                                                    placeholder="Términos de garantía"
                                                    {{ $isLocked ? 'disabled' : '' }}>{{ old("{$itemPrefix}[warranty_terms]", $existingResponse->warranty_terms ?? '') }}</textarea>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label class="form-label-sm mb-1">Notas adicionales <span class="tg-field-optional">Opcional</span></label>
                                            <textarea class="form-control form-control-sm" 
                                                    name="{{ $itemPrefix }}[notes]" 
                                                    rows="2" 
                                                    placeholder="Cualquier información adicional"
                                                    {{ $isLocked ? 'disabled' : '' }}>{{ old("{$itemPrefix}[notes]", $existingResponse->notes ?? '') }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Botones de Acción --}}
                    @if(in_array($rfq->status, ['SENT', 'RECEIVED']))
                        <div class="card mt-3 shadow-sm tg-submit-panel {{ $allLocked ? 'border-info' : 'border-primary' }}">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                    
                                    {{-- LADO IZQUIERDO: Información de estado --}}
                                    <div class="d-flex align-items-center">
                                        @if($allLocked)
                                            <div class="alert alert-info mb-0 py-2 px-3 border-0 shadow-none">
                                                <i class="ti ti-circle-check-filled me-2 fs-5"></i>
                                                <strong>Cotización Completada:</strong> Ya has enviado todas las partidas de esta solicitud.
                                            </div>
                                        @else
                                            <small class="text-muted">
                                                <i class="ti ti-info-circle-filled me-1 text-primary"></i>
                                                <strong>Borrador:</strong> guarda avances sin requisitos. <strong>Envío final:</strong> valida los campos marcados con <span class="tg-required-mark">*</span>.
                                            </small>
                                        @endif
                                    </div>

                                    {{-- LADO DERECHO: Acciones --}}
                                    <div class="text-end">
                                        @if(!$allLocked)
                                            <button type="submit" 
                                                    name="action" 
                                                    value="save_draft" 
                                                    class="btn btn-sm btn-outline-secondary me-2 px-3">
                                                <i class="ti ti-device-floppy me-1"></i>
                                                Guardar Borrador
                                            </button>

                                            <button type="submit" 
                                                    id="submit-quotation-btn" 
                                                    name="action" 
                                                    value="submit" 
                                                    class="btn btn-sm btn-primary px-4">
                                                <i class="ti ti-send me-1"></i>
                                                Enviar Cotización Final
                                            </button>
                                        @else
                                            {{-- Botón de solo lectura o retorno --}}
                                            <a href="{{ route('supplier.dashboard') }}" class="btn btn-sm btn-secondary px-4">
                                                <i class="ti ti-arrow-left me-1"></i>
                                                Volver al Listado
                                            </a>
                                        @endif
                                    </div>

                                </div>
                            </div>
                        </div>
                    @endif
                @endif

            </form>

        </div>
    </div>

</div>

{{-- Modal para Cargar/Cambiar PDF --}}
<div class="modal fade" id="uploadPDFModal" tabindex="-1" aria-labelledby="uploadPDFModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="uploadPDFModalLabel">
                    <i class="ti ti-file-upload me-2"></i>
                    <span id="modalTitle">Cargar Cotización en PDF</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info alert-sm mb-3">
                    <i class="ti ti-info-circle me-2"></i>
                    <small>Puedes adjuntar tu cotización interna en formato PDF como respaldo. Este archivo complementa la información que ingreses en el formulario.</small>
                </div>

                <div class="mb-3">
                    <label for="pdf_file_input" class="form-label">
                        Selecciona tu archivo PDF
                    </label>
                    <div class="file-drop-area" id="fileDropArea">
                        <div class="file-drop-icon">
                            <i class="ti ti-cloud-upload" style="font-size: 3rem; color: #6c757d;"></i>
                        </div>
                        <p class="file-drop-message mb-2">
                            Arrastra tu archivo aquí o haz clic para seleccionar
                        </p>
                        <input type="file" 
                               id="pdf_file_input" 
                               accept=".pdf,application/pdf" 
                               class="file-input"
                               style="display: none;">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="selectFileBtn">
                            <i class="ti ti-folder-open me-1"></i>Seleccionar archivo
                        </button>
                    </div>
                    
                    <div id="filePreview" class="mt-3" style="display: none;">
                        <div class="card">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center">
                                    <i class="ti ti-file-type-pdf text-danger me-3" style="font-size: 2.5rem;"></i>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1" id="previewFileName">archivo.pdf</h6>
                                        <small class="text-muted" id="previewFileSize">0 KB</small>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger" id="removePreviewFile">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-text">
                    <i class="ti ti-alert-circle me-1"></i>
                    Tamaño máximo: 5 MB | Solo archivos PDF
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="ti ti-x me-1"></i>Cancelar
                </button>
                <button type="button" class="btn btn-primary" id="confirmUploadPDF" disabled>
                    <i class="ti ti-check me-1"></i>Confirmar
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .tg-quote-hero {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.25rem 1.4rem;
        overflow: hidden;
        color: #23384d;
        background: #fff;
        border: 1px solid #e2e9f0;
        border-radius: .85rem;
        box-shadow: 0 .3rem 1rem rgba(35, 79, 119, .05);
        animation: tg-quote-enter .45s ease both;
    }

    .tg-quote-logo-wrap {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 3.1rem;
        height: 3.1rem;
        flex: 0 0 auto;
        isolation: isolate;
        animation: tg-quote-logo-arrival 1.15s cubic-bezier(.22, 1, .36, 1) both;
    }

    .tg-quote-logo-wrap::before {
        position: absolute;
        top: -62vh;
        left: 50%;
        z-index: 0;
        width: .3rem;
        height: 62vh;
        content: '';
        pointer-events: none;
        background: linear-gradient(180deg, transparent 0%, rgba(24, 138, 226, .14) 42%, rgba(75, 211, 150, .75) 100%);
        border-radius: 999px;
        box-shadow: 0 0 .65rem rgba(24, 138, 226, .55);
        opacity: 0;
        transform: translateX(-50%);
        animation: tg-quote-logo-trail 1.15s ease-out both;
    }

    .tg-quote-logo-spinner { position: relative; z-index: 1; width: 2.35rem; height: 2.35rem; object-fit: contain; animation: tg-quote-logo-spin 8s linear infinite; }
    .tg-quote-hero-content { flex: 1; min-width: 0; }
    .tg-quote-hero h2 { margin: .12rem 0 .25rem; color: #20354a; font-size: 1.2rem; font-weight: 700; }
    .tg-quote-hero p { color: #718096; font-size: .84rem; }
    .tg-eyebrow, .tg-section-kicker { color: #188ae2; font-size: .69rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }

    .tg-quote-section { overflow: hidden; border: 1px solid #e2e9f0; border-radius: .8rem; }
    #quotation-form > .tg-quote-section { animation: tg-quote-enter .45s ease both; }
    #quotation-form > .tg-quote-section:nth-of-type(3) { animation-delay: .08s; }
    .tg-section-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .8rem 1rem; background: #fff !important; border-bottom: 1px solid #e9eff5; }
    .tg-section-header h6 { color: #34465a; font-weight: 700; }
    .tg-optional-note, .tg-required-legend { padding: .25rem .48rem; border-radius: 999px; color: #64748b; background: #f4f7fa; font-size: .72rem; font-weight: 600; white-space: nowrap; }
    .tg-required-legend { color: #a84444; background: #fff3f3; }
    .tg-required-mark { color: #d64c4c; font-weight: 800; }
    .tg-required-label { display: inline-block; margin-left: .25rem; padding: .08rem .35rem; color: #a84444; background: #fff1f1; border-radius: 999px; font-size: .65rem; font-weight: 700; }
    .tg-field-optional { margin-left: .2rem; color: #7b8794; font-size: .66rem; font-weight: 500; }
    .tg-required-input { border-color: #9fc9ea; box-shadow: inset 0 0 0 1px rgba(24, 138, 226, .05); }
    .tg-submit-panel { border: 1px solid #e2e9f0; border-top-width: 3px !important; border-radius: .8rem; }

    .form-label-sm {
        font-size: 0.875rem;
        font-weight: 500;
        color: #6c757d;
    }
    
    .quotation-item {
        background-color: #f7fbff;
        border-color: #e2e9f0 !important;
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    
    .quotation-item:hover {
        background-color: #fff;
        border-color: #a9d5f3 !important;
        box-shadow: 0 .35rem .85rem rgba(31, 70, 110, .1);
        transform: translateY(-2px);
    }

    @media (min-width: 992px) {
        .sticky-top {
            position: sticky;
            z-index: 10;
        }
    }

    .file-drop-area {
        border: 2px dashed #dee2e6;
        border-radius: 8px;
        padding: 2rem;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .file-drop-area:hover {
        border-color: #0d6efd;
        background-color: #f8f9fa;
    }

    .file-drop-area.dragover {
        border-color: #0d6efd;
        background-color: #e7f3ff;
    }

    .file-drop-message {
        color: #6c757d;
        font-size: 0.95rem;
    }

    @keyframes tg-quote-enter {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes tg-quote-logo-spin { to { transform: rotate(360deg); } }
    @keyframes tg-quote-logo-arrival {
        0% { opacity: 0; transform: translateY(-105vh) scale(2.4); }
        72% { opacity: 1; transform: translateY(.45rem) scale(.9); }
        100% { opacity: 1; transform: translateY(0) scale(1); }
    }
    @keyframes tg-quote-logo-trail {
        0%, 10% { opacity: 0; }
        28% { opacity: .85; }
        70% { opacity: .35; }
        100% { opacity: 0; }
    }

    @media (max-width: 767.98px) {
        .tg-quote-hero, .tg-section-header { align-items: flex-start; flex-wrap: wrap; }
        .tg-optional-note, .tg-required-legend { white-space: normal; }
    }

    @media (prefers-reduced-motion: reduce) {
        .tg-quote-hero, .tg-quote-logo-wrap, .tg-quote-logo-wrap::before, .tg-quote-logo-spinner, #quotation-form > .tg-quote-section, .quotation-item { animation: none; transition: none; }
        .quotation-item:hover { transform: none; }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    
    // =========================================================================
    // Cálculo automático de subtotales, IVA y totales
    // =========================================================================
    function calculateItemTotals(index) {
        const $item = $(`.quotation-item[data-item-index="${index}"]`);
        const unitPrice = parseFloat($item.find('.unit-price').val()) || 0;
        const quantity = parseFloat($item.find('.quantity').val()) || 0;
        const ivaRate = parseFloat($item.find('.iva-rate').val()) || 0;
        
        // Calcular subtotal (sin IVA)
        const subtotal = unitPrice * quantity;
        
        // Calcular IVA
        const ivaAmount = subtotal * (ivaRate / 100);
        
        // Calcular total (con IVA)
        const total = subtotal + ivaAmount;
        
        // Actualizar campos
        $item.find('.subtotal').val(subtotal.toFixed(2));
        $item.find('.iva-amount').val(ivaAmount.toFixed(2));
        $item.find('.item-total').val(total.toFixed(2));
        
        // Actualizar el gran total y resumen
        calculateGrandTotal();
        updateSummaryPanel();
    }

    function updateSummaryPanel() {
        let summaryHtml = '';

        $('.quotation-item').each(function(index) {
            const $item = $(this);
            const itemNumber = index + 1;
            const isUnavailable = $item.find('.item-not-available-flag').val() === '1';

            if (isUnavailable) {
                summaryHtml += `
                    <div class="d-flex justify-content-between align-items-start mb-2 text-warning">
                        <div class="flex-grow-1 me-2">
                            <span class="badge bg-warning text-dark badge-sm me-1">${itemNumber}</span>
                            <small>No disponible</small>
                        </div>
                        <strong class="text-nowrap small">—</strong>
                    </div>`;
                return; // continue
            }

            const total = parseFloat($item.find('.item-total').val()) || 0;

            // Obtener descripción del header
            const description = $item.find('h6.fw-bold').text().trim();
            const shortDescription = description.length > 30
                ? description.substring(0, 30) + '...'
                : description;

            // Determinar color según si tiene valor
            const hasValue = total > 0;
            const textClass = hasValue ? 'text-dark' : 'text-muted';
            const badgeClass = hasValue ? 'bg-primary' : 'bg-secondary';

            summaryHtml += `
                <div class="d-flex justify-content-between align-items-start mb-2 ${textClass}">
                    <div class="flex-grow-1 me-2">
                        <span class="badge ${badgeClass} badge-sm me-1">${itemNumber}</span>
                        <small>${shortDescription}</small>
                    </div>
                    <strong class="text-nowrap small">$${total.toFixed(2)}</strong>
                </div>
            `;
        });

        if (summaryHtml === '') {
            summaryHtml = '<p class="text-muted text-center small mb-0">Sin partidas cotizadas</p>';
        }

        $('#summary-items').html(summaryHtml);

        // Contador "X de Y cotizadas": Y = total de partidas, X = partidas que
        // el proveedor sigue cotizando (no marcadas como "no disponible").
        const totalItems = $('.quotation-item').length;
        const quotedItems = $('.quotation-item').filter(function () {
            return $(this).find('.item-not-available-flag').val() !== '1';
        }).length;
        $('#summary-quoted-count').text(`${quotedItems} de ${totalItems} cotizadas`);
    }

    function calculateGrandTotal() {
        let grandTotal = 0;
        let grandSubtotal = 0;
        let grandIva = 0;

        $('.quotation-item').each(function() {
            if ($(this).find('.item-not-available-flag').val() === '1') {
                return; // no suma
            }
            const subtotal = parseFloat($(this).find('.subtotal').val()) || 0;
            const ivaAmount = parseFloat($(this).find('.iva-amount').val()) || 0;
            const total = parseFloat($(this).find('.item-total').val()) || 0;

            grandSubtotal += subtotal;
            grandIva += ivaAmount;
            grandTotal += total;
        });
        
        // Actualizar el resumen en el sidebar
        $('#summary-subtotal').text(grandSubtotal.toFixed(2));
        $('#summary-iva').text(grandIva.toFixed(2));
        $('#grand-total').text(grandTotal.toFixed(2));
    }

    // Escuchar cambios en precio, cantidad y tasa de IVA
    $('.unit-price, .quantity, .iva-rate').on('input change', function() {
        const index = $(this).closest('.quotation-item').data('item-index');
        calculateItemTotals(index);
    });

    // Calcular al cargar la página
    $('.quotation-item').each(function() {
        const index = $(this).data('item-index');
        calculateItemTotals(index);
    });

    // Inicializar el panel de resumen
    updateSummaryPanel();

    // =========================================================================
    // Marcar/desmarcar partida como "no disponible"
    // =========================================================================
    function applyNotAvailableState($item, isUnavailable) {
        const $fields = $item.find('.unit-price, .quantity, .iva-rate, input[name$="[delivery_days]"]');
        $item.find('.item-not-available-flag').val(isUnavailable ? '1' : '0');
        $item.find('.item-unavailable-badge').toggleClass('d-none', !isUnavailable);
        $item.toggleClass('opacity-50', isUnavailable);

        if (isUnavailable) {
            $item.find('.unit-price, .quantity').val('');
        }
        $fields.prop('disabled', isUnavailable).removeClass('is-invalid');

        const index = $item.data('item-index');
        calculateItemTotals(index);
    }

    $('.toggle-not-available').on('change', function() {
        const $item = $(this).closest('.quotation-item');
        applyNotAvailableState($item, this.checked);
    });

    // Estado inicial (por old() o datos guardados)
    $('.toggle-not-available').each(function() {
        if (this.checked) {
            applyNotAvailableState($(this).closest('.quotation-item'), true);
        }
    });

    // =========================================================================
    // Validación de archivos PDF
    // =========================================================================
    $('input[type="file"]').on('change', function() {
        const file = this.files[0];
        const $input = $(this);
        
        if (file) {
            // Validar tipo
            if (file.type !== 'application/pdf') {
                Swal.fire({
                    icon: 'error',
                    title: 'Archivo no válido',
                    text: 'Solo se permiten archivos PDF',
                    confirmButtonText: 'Entendido'
                });
                $input.val('');
                return;
            }
            
            // Validar tamaño (5MB)
            const maxSize = 5 * 1024 * 1024;
            if (file.size > maxSize) {
                const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
                Swal.fire({
                    icon: 'error',
                    title: 'Archivo muy grande',
                    html: `
                        <p>El archivo pesa <strong>${sizeMB} MB</strong></p>
                        <p>El tamaño máximo permitido es <strong>5 MB</strong></p>
                    `,
                    confirmButtonText: 'Entendido'
                });
                $input.val('');
                return;
            }
            
            // Mostrar nombre del archivo seleccionado (opcional)
            console.log(`✅ Archivo PDF válido: ${file.name} (${(file.size / 1024).toFixed(2)} KB)`);
        }
    });
    
    // =========================================================================
    // Tooltip para ayuda (opcional)
    // =========================================================================
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // =========================================================================
    // Confirmar antes de salir si hay cambios sin guardar
    // =========================================================================
    let formChanged = false;
    
    $('input, select, textarea').on('change', function() {
        formChanged = true;
    });
    
    $('#quotation-form').on('submit', function() {
        formChanged = false; // Permitir envío del formulario
    });
    
    $(window).on('beforeunload', function(e) {
        if (formChanged) {
            const message = 'Tienes cambios sin guardar. ¿Estás seguro de salir?';
            e.returnValue = message;
            return message;
        }
    });

    /**
    * Función Maestra: Confirmación con Estilo Estandarizado Zircos
    */
    function confirmAction(config) {
        // Agregar estilos CSS para los items con iconos
        const customStyles = `
            .step-item {
                display: flex;
                align-items: flex-start;
                margin-bottom: 0.5rem;
                padding: 0.625rem;
                border-radius: 8px;
                background: #f9fafb;
                border: 1px solid #e5e7eb;
                transition: all 0.2s ease;
            }
            .step-item:last-child {
                margin-bottom: 0;
            }
            .step-item:hover {
                background: #f3f4f6;
                border-color: #d1d5db;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }
            .step-icon {
                color: ${config.confirmColor};
                font-size: 1.15rem;
                margin-right: 0.625rem;
                margin-top: 0.125rem;
                flex-shrink: 0;
            }
            .step-content {
                flex-grow: 1;
                color: #374151;
                font-size: 0.85rem;
                line-height: 1.4;
            }
            .step-content strong {
                color: #1f2937;
                font-weight: 600;
            }
            .steps-container {
                margin: 0.75rem 0 1rem;
            }
            .swal-wide-modal {
                width: 520px !important;
                max-width: 90% !important;
            }
            .swal-wide-modal .card-body {
                padding: 0.875rem !important;
            }
            .swal-wide-modal .swal2-checkbox {
                margin-top: 1rem !important;
            }
        `;
        
        // Crear y agregar estilos temporalmente
        const styleSheet = document.createElement("style");
        styleSheet.textContent = customStyles;
        document.head.appendChild(styleSheet);
        
        Swal.fire({
            title: `<h5 class="mt-2">${config.title}</h5>`,
            html: `
                <div class="text-start">
                    <div class="card bg-light shadow-none border mb-3">
                        <div class="card-body p-3">
                            <h6 class="mb-3 text-primary">
                                <i class="${config.headerIcon} me-2"></i>
                                ${config.headerText}
                            </h6>
                            <div class="steps-container">
                                ${config.steps.map(step => `<div class="step-item">${step}</div>`).join('')}
                            </div>
                        </div>
                    </div>
                    <div class="form-check custom-checkbox ms-1">
                        <input class="form-check-input" type="checkbox" id="swal-checkbox-confirm">
                        <label class="form-check-label fw-bold text-dark" for="swal-checkbox-confirm" style="font-size: 0.85rem;">
                            ${config.checkboxText}
                        </label>
                    </div>
                </div>
            `,
            icon: config.icon,
            showCancelButton: true,
            confirmButtonText: config.confirmButtonText,
            cancelButtonText: 'Regresar',
            confirmButtonColor: config.confirmColor,
            cancelButtonColor: '#6c757d',
            customClass: {
                popup: 'swal-wide-modal',
                confirmButton: 'btn btn-primary px-4',
                cancelButton: 'btn btn-outline-secondary px-4'
            },
            width: 520,
            buttonsStyling: false,
            didOpen: () => {
                const confirmBtn = Swal.getConfirmButton();
                confirmBtn.disabled = true;
                const checkbox = document.getElementById('swal-checkbox-confirm');
                checkbox.addEventListener('change', (e) => {
                    confirmBtn.disabled = !e.target.checked;
                });
                
                // Agregar iconos a los botones
                if (config.actionValue === 'save_draft') {
                    confirmBtn.innerHTML = `<i class="ti ti-device-floppy me-2"></i>${config.confirmButtonText}`;
                } else if (config.actionValue === 'submit') {
                    confirmBtn.innerHTML = `<i class="ti ti-send me-2"></i>${config.confirmButtonText}`;
                }
                
                // Agregar icono al botón cancelar
                const cancelBtn = Swal.getCancelButton();
                if (cancelBtn) {
                    cancelBtn.innerHTML = `<i class="ti ti-arrow-back me-2"></i>Regresar`;
                }
            },
            willClose: () => {
                // Remover estilos cuando se cierre el modal
                document.head.removeChild(styleSheet);
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const hiddenInput = $('<input>').attr('type', 'hidden').attr('name', 'action').val(config.actionValue);
                $('#quotation-form').append(hiddenInput).submit();
                Swal.fire({
                    title: 'Procesando...',
                    didOpen: () => { Swal.showLoading(); }
                });
            }
        });
    }

    // --- EVENTO: BOTÓN BORRADOR ---
    $('button[value="save_draft"]').on('click', function(e) {
        e.preventDefault();
        confirmAction({
            title: 'Guardar Progreso',
            headerIcon: 'ti ti-device-floppy',
            headerText: '¿Qué pasará con este borrador?',
            actionValue: 'save_draft',
            confirmColor: '#4b5563', 
            icon: 'info',
            confirmButtonText: 'Guardar Borrador',
            checkboxText: 'Entiendo que esto no es un envío final',
            steps: [
                '<i class="ti ti-shield-lock step-icon"></i> <div class="step-content"><strong>Datos Protegidos:</strong> Sus avances quedarán almacenados de forma segura en nuestros servidores.</div>',
                '<i class="ti ti-history step-icon"></i> <div class="step-content"><strong>Continuidad:</strong> Puede cerrar su sesión y retomar la cotización en cualquier momento desde su Dashboard.</div>',
                '<i class="ti ti-eye-off step-icon"></i> <div class="step-content"><strong>Privacidad:</strong> El personal de TotalGas <strong>NO recibirá</strong> notificaciones ni podrá evaluar esta información mientras sea un borrador.</div>'
            ]
        });
    });

    // --- EVENTO: BOTÓN ENVIAR COTIZACIÓN ---
    $('#submit-quotation-btn').on('click', function(e) {
        e.preventDefault();
        
        // Validación de campos obligatorios antes de mostrar el modal
        if (!validateFieldsBeforeSubmit()) return;

        // Recolectar partidas marcadas como no disponibles
        const unavailableNames = [];
        $('.quotation-item').each(function() {
            if ($(this).find('.item-not-available-flag').val() === '1') {
                unavailableNames.push($(this).find('h6.fw-bold').text().trim());
            }
        });

        const grandTotal = $('#grand-total').text();
        const baseSteps = [
            `<i class="ti ti-currency-dollar step-icon"></i> <div class="step-content"><strong>Monto Total:</strong> Se enviará una oferta formal por <strong>$${grandTotal}</strong> (IVA incluido).</div>`,
            '<i class="ti ti-lock step-icon"></i> <div class="step-content"><strong>Bloqueo de Edición:</strong> Una vez enviada, la cotización quedará en estado <strong>RECIBIDA</strong> y no podrá ser modificada.</div>',
            '<i class="ti ti-bell-ringing step-icon"></i> <div class="step-content"><strong>Notificación:</strong> El departamento de compras será notificado inmediatamente para iniciar el proceso de comparativa.</div>'
        ];

        if (unavailableNames.length > 0) {
            const lista = unavailableNames.map(n => `• ${n}`).join('<br>');
            baseSteps.unshift(
                `<i class="ti ti-ban step-icon"></i> <div class="step-content"><strong>Sin incluir ${unavailableNames.length} partida(s):</strong><br>${lista}<br>El comprador será notificado de esta falta de producto.</div>`
            );
        }

        confirmAction({
            title: 'Enviar Cotización Formal',
            headerIcon: 'ti ti-send',
            headerText: 'Efectos del envío definitivo',
            actionValue: 'submit',
            confirmColor: '#1a5276',
            icon: 'warning',
            confirmButtonText: 'Confirmar Envío',
            checkboxText: 'Confirmo que los montos y documentos son correctos',
            steps: baseSteps
        });
    });

    function validateFieldsBeforeSubmit() {
        // Validar condiciones de pago globales
        const globalPaymentTerms = $('#global_payment_terms').val();
        if (!globalPaymentTerms) {
            Swal.fire('Atención', 'Debes especificar las condiciones de pago globales antes de enviar.', 'warning');
            $('#global_payment_terms').addClass('is-invalid').focus();
            return false;
        }
        $('#global_payment_terms').removeClass('is-invalid');

        // Validar precios unitarios
        let hasErrors = false;
        $('.quotation-item').each(function() {
            if ($(this).find('.item-not-available-flag').val() === '1') {
                return; // partida no disponible: no requiere precio
            }
            const unitPrice = $(this).find('.unit-price').val();
            if (!unitPrice || parseFloat(unitPrice) <= 0) {
                $(this).find('.unit-price').addClass('is-invalid');
                hasErrors = true;
            }
        });

        if (hasErrors) {
            Swal.fire('Atención', 'Faltan precios unitarios por llenar.', 'error');
            return false;
        }
        return true;
    }
    
});

$(document).ready(function() {
    // 1. Sincronización en tiempo real
    $('#global_payment_terms').on('input change', function() {
        const val = $(this).val();
        $('.item-payment-terms').val(val);
    });

    // 2. Sincronización inicial (por si hay datos de OLD o de base de datos)
    const initialGlobalVal = $('#global_payment_terms').val();
    if(initialGlobalVal) {
        $('.item-payment-terms').val(initialGlobalVal);
    }

    // 3. Sincronización final de condiciones de pago antes de enviar
    $('#quotation-form').on('submit', function(e) {
        const action = $('#quotation-form input[name="action"]').val();
        const globalVal = $('#global_payment_terms').val();

        // Las condiciones de pago globales solo son obligatorias en el envío final.
        // En borrador permitimos guardar un preliminar sin este dato.
        if (action === 'submit' && !globalVal) {
            e.preventDefault();
            Swal.close(); // Cerrar cualquier loader abierto
            Swal.fire('Atención', 'Debes especificar las condiciones de pago globales.', 'warning');
            return false;
        }

        // Aseguramos que todas las partidas tengan el valor (si se capturó)
        if (globalVal) {
            $('.item-payment-terms').val(globalVal);
        }
    });
});

// PDF Upload Modal
document.addEventListener('DOMContentLoaded', function() {
    const uploadPDFModal = new bootstrap.Modal(document.getElementById('uploadPDFModal'));
    const fileDropArea = document.getElementById('fileDropArea');
    const pdfFileInput = document.getElementById('pdf_file_input');
    const selectFileBtn = document.getElementById('selectFileBtn');
    const filePreview = document.getElementById('filePreview');
    const confirmUploadPDF = document.getElementById('confirmUploadPDF');
    const removePreviewFile = document.getElementById('removePreviewFile');
    const quotationPdfFile = document.getElementById('quotation_pdf_file');
    const deletePdfFlag = document.getElementById('delete_pdf_flag');
    
    // Botones dinámicos
    const btnUploadPDF = document.getElementById('btnUploadPDF');
    const btnChangePDF = document.getElementById('btnChangePDF');
    const btnDeletePDF = document.getElementById('btnDeletePDF');
    const modalTitle = document.getElementById('modalTitle');

    let selectedFile = null;
    const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB
    let isChangingPDF = false; // Flag para saber si estamos cambiando un PDF existente

    // =====================================================================
    // ABRIR MODAL - Cargar nuevo o Cambiar existente
    // =====================================================================
    
    // Botón "Cargar PDF" (cuando NO hay archivo)
    if (btnUploadPDF) {
        btnUploadPDF.addEventListener('click', function() {
            isChangingPDF = false;
            modalTitle.textContent = 'Cargar Cotización en PDF';
            uploadPDFModal.show();
        });
    }

    // Botón "Cambiar PDF" (cuando SÍ hay archivo y es borrador)
    if (btnChangePDF) {
        btnChangePDF.addEventListener('click', function() {
            isChangingPDF = true;
            modalTitle.textContent = 'Cambiar PDF de Cotización';
            uploadPDFModal.show();
        });
    }

    // =====================================================================
    // ELIMINAR PDF EXISTENTE
    // =====================================================================
    if (btnDeletePDF) {
        btnDeletePDF.addEventListener('click', function() {
            Swal.fire({
                title: '¿Eliminar PDF de Cotización?',
                text: 'Se eliminará el archivo PDF adjunto a esta cotización',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Marcar para eliminación
                    deletePdfFlag.value = '1';
                    
                    Swal.fire({
                        icon: 'info',
                        title: 'PDF marcado para eliminación',
                        text: 'El archivo se eliminará al guardar la cotización',
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });

                    // Cambiar visualmente el card
                    btnDeletePDF.closest('.card').classList.add('border-danger', 'opacity-50');
                    btnDeletePDF.closest('.card').querySelector('.text-success').innerHTML = 
                        '<i class="ti ti-alert-triangle text-warning"></i> Pendiente de eliminar';
                }
            });
        });
    }

    // =====================================================================
    // DRAG & DROP Y SELECCIÓN DE ARCHIVO
    // =====================================================================
    
    if (selectFileBtn) {
        selectFileBtn.addEventListener('click', function() {
            pdfFileInput.click();
        });
    }

    if (fileDropArea) {
        fileDropArea.addEventListener('click', function(e) {
            if (e.target !== selectFileBtn && !selectFileBtn.contains(e.target)) {
                pdfFileInput.click();
            }
        });

        // Prevenir comportamiento por defecto
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            fileDropArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // Efectos visuales
        ['dragenter', 'dragover'].forEach(eventName => {
            fileDropArea.addEventListener(eventName, () => {
                fileDropArea.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            fileDropArea.addEventListener(eventName, () => {
                fileDropArea.classList.remove('dragover');
            });
        });

        // Manejar drop
        fileDropArea.addEventListener('drop', function(e) {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFile(files[0]);
            }
        });
    }

    // Manejar selección
    if (pdfFileInput) {
        pdfFileInput.addEventListener('change', function(e) {
            if (this.files.length > 0) {
                handleFile(this.files[0]);
            }
        });
    }

    // =====================================================================
    // VALIDAR Y MOSTRAR ARCHIVO
    // =====================================================================
    function handleFile(file) {
        // Validar tipo
        if (file.type !== 'application/pdf') {
            Swal.fire({
                icon: 'error',
                title: 'Tipo de archivo inválido',
                text: 'Solo se permiten archivos PDF',
                confirmButtonColor: '#d33'
            });
            return;
        }

        // Validar tamaño
        if (file.size > MAX_FILE_SIZE) {
            Swal.fire({
                icon: 'error',
                title: 'Archivo muy grande',
                text: 'El archivo no debe superar los 5 MB',
                confirmButtonColor: '#d33'
            });
            return;
        }

        selectedFile = file;
        showFilePreview(file);
        confirmUploadPDF.disabled = false;
    }

    function showFilePreview(file) {
        const fileName = file.name;
        const fileSize = formatFileSize(file.size);

        document.getElementById('previewFileName').textContent = fileName;
        document.getElementById('previewFileSize').textContent = fileSize;
        filePreview.style.display = 'block';
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    // Eliminar preview
    if (removePreviewFile) {
        removePreviewFile.addEventListener('click', function() {
            selectedFile = null;
            pdfFileInput.value = '';
            filePreview.style.display = 'none';
            confirmUploadPDF.disabled = true;
        });
    }

    // =====================================================================
    // CONFIRMAR CARGA/CAMBIO
    // =====================================================================
    if (confirmUploadPDF) {
        confirmUploadPDF.addEventListener('click', function() {
            if (selectedFile) {
                // Asignar archivo al input hidden
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(selectedFile);
                quotationPdfFile.files = dataTransfer.files;

                // Resetear flag de eliminación (por si estaba marcado)
                deletePdfFlag.value = '0';

                // Cerrar modal
                uploadPDFModal.hide();

                // Limpiar modal
                resetModal();

                // Notificación
                const actionText = isChangingPDF ? 'cambiará' : 'adjuntará';
                Swal.fire({
                    icon: 'success',
                    title: 'PDF Seleccionado',
                    text: `El archivo se ${actionText} al guardar la cotización`,
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });

                // Nota: La página se recargará al guardar, así que no necesitamos
                // cambiar la UI aquí, simplemente se verá reflejado después del submit
            }
        });
    }

    // =====================================================================
    // RESETEAR MODAL
    // =====================================================================
    document.getElementById('uploadPDFModal').addEventListener('hidden.bs.modal', function() {
        resetModal();
    });

    function resetModal() {
        selectedFile = null;
        pdfFileInput.value = '';
        filePreview.style.display = 'none';
        confirmUploadPDF.disabled = true;
    }
});
</script>
@endpush
