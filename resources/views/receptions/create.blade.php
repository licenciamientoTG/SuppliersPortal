@extends('layouts.zircos')

@section('title', 'Registrar Recepción — ' . ($order->folio ?? 'Nueva'))
@section('page.title', 'Registrar Recepción')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('receptions.overview') }}">Recepciones</a></li>
    <li class="breadcrumb-item active">Registrar Recepción</li>
@endsection

@push('styles')
<style>
@keyframes pulse-urgente {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.5; }
}
.urgente-pulse {
    animation: pulse-urgente 1.4s ease-in-out infinite;
}
</style>
@endpush

@section('content')
<form action="{{ $storeRoute }}" method="POST" enctype="multipart/form-data" id="reception-form">
    @csrf

    {{-- Hiddens globales --}}
    <input type="hidden" name="receivable_type" value="{{ get_class($order) }}">
    <input type="hidden" name="receivable_id" value="{{ $order->id }}">
    <input type="hidden" name="receivable_item_type" value="{{ $orderType === 'purchase_order' ? \App\Models\PurchaseOrderItem::class : \App\Models\DirectPurchaseOrderItem::class }}">

    <div class="row g-3">

        {{-- ── Errores generales ──────────────────────────────────────────── --}}
        @if(session('error'))
            <div class="col-12">
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="ti ti-alert-circle me-2"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        @endif

        @if($errors->any())
            <div class="col-12">
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="ti ti-alert-circle me-2"></i><strong>Corrige los errores antes de continuar.</strong>
                    <ul class="mb-0 mt-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        @endif

        {{-- ── Advertencia REPSE ──────────────────────────────────────────── --}}
        @if($repseWarning)
            <div class="col-12">
                <div class="alert alert-warning d-flex align-items-start gap-2 mb-0">
                    <i class="ti ti-alert-triangle fs-20 mt-1 flex-shrink-0"></i>
                    <div><strong>Atención — REPSE:</strong> {{ $repseWarning }}</div>
                </div>
            </div>
        @endif

        {{-- ╔══════════════════════════════════════════════════════════════╗
             ║  CARD 1 — Datos de la orden (solo lectura)                  ║
             ╚══════════════════════════════════════════════════════════════╝ --}}
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="ti ti-file-description"></i>
                    <h6 class="mb-0 flex-grow-1">Datos de la Orden</h6>
                    <span class="badge bg-{{ $order->getStatusBadgeClass() }}">{{ $order->getStatusLabel() }}</span>
                    @if($orderType === 'purchase_order')
                        <span class="badge bg-soft-primary text-primary">OC Estándar</span>
                    @else
                        <span class="badge bg-soft-warning text-warning">OC Directa</span>
                    @endif
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-sm-3">
                            <label class="form-label small fw-bold text-muted">Folio</label>
                            <p class="mb-0 fw-bold fs-15">{{ $order->folio ?? '—' }}</p>
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label small fw-bold text-muted">Proveedor</label>
                            <p class="mb-0">{{ $order->supplier->company_name ?? '—' }}</p>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label small fw-bold text-muted">Fecha Emisión</label>
                            <p class="mb-0">{{ $order->issued_at?->format('d/m/Y') ?? '—' }}</p>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label small fw-bold text-muted">Total</label>
                            <p class="mb-0 fw-bold">${{ number_format($order->total, 2) }} {{ $order->currency }}</p>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label small fw-bold text-muted">Punto de Entrega</label>
                            <p class="mb-0 small">{{ $order->receivingLocation?->name ?? '—' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ╔══════════════════════════════════════════════════════════════╗
             ║  CARD — Entrega del proveedor (solo si DELIVERED_PENDING)   ║
             ╚══════════════════════════════════════════════════════════════╝ --}}
        @if($order->status === 'DELIVERED_PENDING_RECEPTION' && isset($deliveryEvidence) && $deliveryEvidence)
        @php
            $daysLeft = $order->reception_deadline_at
                ? (int) now()->diffInWeekdays($order->reception_deadline_at, false)
                : null;
            $deadlineBadgeClass = match(true) {
                $daysLeft === null => 'bg-secondary',
                $daysLeft >= 3     => 'bg-success',
                $daysLeft === 2    => 'bg-warning text-dark',
                default            => 'bg-danger',
            };
            $deadlineLabel = match(true) {
                $daysLeft === null => '—',
                $daysLeft > 0      => "{$daysLeft} día(s) restante(s)",
                $daysLeft === 0    => 'Vence hoy',
                default            => 'Plazo vencido',
            };
        @endphp
        <div class="col-12">
            <div class="card border-danger shadow-sm">
                <div class="card-header bg-danger text-white d-flex align-items-center gap-2">
                    <i class="ti ti-truck-delivery fs-18"></i>
                    <h6 class="mb-0 text-white">Entrega registrada por el proveedor — pendiente de captura</h6>
                    <span class="badge bg-white text-danger ms-auto {{ $daysLeft !== null && $daysLeft <= 2 ? 'urgente-pulse' : '' }}">
                        <i class="ti ti-clock me-1"></i>{{ $deadlineLabel }}
                    </span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted">Fecha de entrega física</label>
                            <p class="mb-0 fw-semibold">
                                {{ $order->supplier_delivered_at?->format('d/m/Y H:i') ?? '—' }}
                            </p>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted">Recibió en la estación</label>
                            <p class="mb-0">{{ $order->physical_receiver_name ?: '—' }}</p>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted">Fecha límite de captura</label>
                            <p class="mb-0">
                                {{ $order->reception_deadline_at?->format('d/m/Y') ?? '—' }}
                                <span class="badge {{ $deadlineBadgeClass }} ms-1">{{ $deadlineLabel }}</span>
                            </p>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted">Observaciones del proveedor</label>
                            <p class="mb-0 small text-muted">{{ $order->delivery_observations ?: 'Sin observaciones.' }}</p>
                        </div>
                    </div>

                    {{-- Remisión digital --}}
                    <div class="mt-3">
                        <label class="form-label small fw-bold text-muted">Remisión digital cargada por el proveedor</label>
                        @if($deliveryEvidence->isPdf())
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <a href="{{ $deliveryEvidenceUrl }}" target="_blank" class="btn btn-sm btn-outline-danger">
                                    <i class="ti ti-file-type-pdf me-1"></i>Descargar PDF
                                </a>
                            </div>
                            <iframe src="{{ $deliveryEvidenceUrl }}"
                                    class="w-100 border rounded"
                                    style="height: 400px;"
                                    title="Remisión del proveedor">
                            </iframe>
                        @elseif($deliveryEvidence->isImage())
                            <div>
                                <img src="{{ $deliveryEvidenceUrl }}"
                                     alt="Remisión del proveedor"
                                     class="img-fluid rounded border"
                                     style="max-height: 400px; cursor: zoom-in;"
                                     data-bs-toggle="modal"
                                     data-bs-target="#modalRemision">
                            </div>
                            <div class="modal fade" id="modalRemision" tabindex="-1">
                                <div class="modal-dialog modal-xl modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h6 class="modal-title">Remisión del proveedor</h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body text-center">
                                            <img src="{{ $deliveryEvidenceUrl }}" alt="Remisión" class="img-fluid">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- ╔══════════════════════════════════════════════════════════════╗
             ║  CARD 2 — Datos generales de recepción                      ║
             ╚══════════════════════════════════════════════════════════════╝ --}}
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header">
                    <h6 class="mb-0"><i class="ti ti-clipboard-check me-2"></i>Datos Generales de Recepción</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">

                        <div class="col-md-4">
                            <label class="form-label small fw-bold" for="receiving_location_id">
                                Punto de Recepción <span class="text-danger">*</span>
                            </label>
                            <select name="receiving_location_id" id="receiving_location_id"
                                    class="form-select form-select-sm select2-enable @error('receiving_location_id') is-invalid @enderror"
                                    required>
                                <option value="">Seleccionar…</option>
                                @foreach($receivingLocations as $loc)
                                    <option value="{{ $loc->id }}"
                                        {{ old('receiving_location_id', $order->receiving_location_id) == $loc->id ? 'selected' : '' }}>
                                        [{{ $loc->code }}] {{ $loc->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('receiving_location_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold" for="received_at">
                                Fecha y Hora de Recepción <span class="text-danger">*</span>
                            </label>
                            <input type="datetime-local" name="received_at" id="received_at"
                                   class="form-control form-control-sm @error('received_at') is-invalid @enderror"
                                   value="{{ old('received_at', now()->format('Y-m-d\TH:i')) }}"
                                   required>
                            @error('received_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold" for="delivery_reference">
                                Nº de Remisión / Referencia
                            </label>
                            <input type="text" name="delivery_reference" id="delivery_reference"
                                   class="form-control form-control-sm @error('delivery_reference') is-invalid @enderror"
                                   value="{{ old('delivery_reference') }}"
                                   maxlength="100"
                                   placeholder="Folio del documento del proveedor">
                            @error('delivery_reference')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold" for="remission_file">
                                Archivo de Remisión <span class="text-muted fw-normal">(opcional)</span>
                            </label>
                            <input type="file" name="remission_file" id="remission_file"
                                   class="form-control form-control-sm @error('remission_file') is-invalid @enderror"
                                   accept=".pdf,.jpg,.jpeg,.png">
                            <div class="form-text">PDF, JPG o PNG · Máx. 10 MB</div>
                            @error('remission_file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-8">
                            <label class="form-label small fw-bold" for="notes">
                                Notas u Observaciones
                            </label>
                            <textarea name="notes" id="notes"
                                      class="form-control form-control-sm @error('notes') is-invalid @enderror"
                                      rows="2" maxlength="1000"
                                      placeholder="Estado general del empaque, condiciones de entrega, etc.">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                    </div>
                </div>
            </div>
        </div>

        {{-- ╔══════════════════════════════════════════════════════════════╗
             ║  CARD 3 — Partidas                                          ║
             ╚══════════════════════════════════════════════════════════════╝ --}}
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="mb-0"><i class="ti ti-list-details me-2"></i>Partidas a Recibir</h6>
                    <small class="text-muted">Al menos una partida debe tener cantidad mayor a cero</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-centered mb-0" id="items-table">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="min-width:200px">Descripción</th>
                                    <th class="text-center" style="width:100px">Ordenado</th>
                                    <th class="text-center" style="width:100px">Ya Recibido</th>
                                    <th class="text-center" style="width:90px">Pendiente</th>
                                    <th class="text-center" style="width:120px">
                                        A Recibir <span class="text-danger">*</span>
                                    </th>
                                    <th class="text-center" style="min-width:200px">
                                        Conformidad <span class="text-danger">*</span>
                                    </th>
                                    <th class="text-center" style="min-width:160px">
                                        Evidencia Fotográfica
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($order->items as $i => $item)
                                    @php
                                        $alreadyReceived = (float) $item->quantity_received;
                                        $pending         = (float) $item->quantity_pending;
                                        $fullyReceived   = $item->isFullyReceived();
                                        $oldReceived     = old("items.{$i}.quantity_received");
                                        $oldConformity   = old("items.{$i}.conformity", 'CONFORME');
                                    @endphp

                                    {{-- ── Fila principal ─────────────────────────────── --}}
                                    <tr class="{{ $fullyReceived ? 'table-secondary' : '' }}"
                                        data-pending="{{ $pending }}"
                                        data-index="{{ $i }}">

                                        <input type="hidden" name="items[{{ $i }}][receivable_item_id]" value="{{ $item->id }}">

                                        {{-- Descripción --}}
                                        <td class="ps-3">
                                            <span class="fw-semibold small">{{ $item->description }}</span>
                                            @if($fullyReceived)
                                                <span class="badge bg-success ms-1">Completo</span>
                                            @endif
                                            <div class="small text-muted">{{ $item->unit ?? '' }}</div>
                                        </td>

                                        {{-- Cant. ordenada --}}
                                        <td class="text-center small text-muted">
                                            {{ number_format((float) $item->quantity, 3) }}
                                        </td>

                                        {{-- Ya recibido --}}
                                        <td class="text-center small text-muted">
                                            {{ number_format($alreadyReceived, 3) }}
                                        </td>

                                        {{-- Pendiente --}}
                                        <td class="text-center">
                                            <span class="fw-bold {{ $pending > 0 ? 'text-primary' : 'text-success' }}">
                                                {{ number_format($pending, 3) }}
                                            </span>
                                        </td>

                                        {{-- Cant. a recibir --}}
                                        <td class="text-center">
                                            <input type="number"
                                                   name="items[{{ $i }}][quantity_received]"
                                                   class="form-control form-control-sm text-center qty-received"
                                                   value="{{ $oldReceived !== null ? $oldReceived : ($pending > 0 ? $pending : 0) }}"
                                                   min="0"
                                                   max="{{ $pending }}"
                                                   step="0.001"
                                                   data-max="{{ $pending }}"
                                                   data-index="{{ $i }}"
                                                   {{ $fullyReceived ? 'disabled' : 'required' }}>
                                            @error("items.{$i}.quantity_received")
                                                <div class="text-danger" style="font-size:0.75rem">{{ $message }}</div>
                                            @enderror
                                        </td>

                                        {{-- Conformidad --}}
                                        <td class="text-center">
                                            @if(! $fullyReceived)
                                                <input type="hidden" name="items[{{ $i }}][conformity]" value="CONFORME" class="conformity-hidden-{{ $i }}">
                                                <div class="d-flex justify-content-center gap-2">
                                                    <div class="form-check form-check-inline mb-0">
                                                        <input class="form-check-input conformity-radio"
                                                               type="radio"
                                                               name="items[{{ $i }}][conformity]"
                                                               id="conf_ok_{{ $i }}"
                                                               value="CONFORME"
                                                               data-index="{{ $i }}"
                                                               {{ $oldConformity !== 'NO_CONFORME' ? 'checked' : '' }}>
                                                        <label class="form-check-label text-success fw-semibold small" for="conf_ok_{{ $i }}">
                                                            <i class="ti ti-circle-check"></i> Conforme
                                                        </label>
                                                    </div>
                                                    <div class="form-check form-check-inline mb-0">
                                                        <input class="form-check-input conformity-radio"
                                                               type="radio"
                                                               name="items[{{ $i }}][conformity]"
                                                               id="conf_fail_{{ $i }}"
                                                               value="NO_CONFORME"
                                                               data-index="{{ $i }}"
                                                               {{ $oldConformity === 'NO_CONFORME' ? 'checked' : '' }}>
                                                        <label class="form-check-label text-danger fw-semibold small" for="conf_fail_{{ $i }}">
                                                            <i class="ti ti-circle-x"></i> No Conforme
                                                        </label>
                                                    </div>
                                                </div>
                                                @error("items.{$i}.conformity")
                                                    <div class="text-danger" style="font-size:0.75rem">{{ $message }}</div>
                                                @enderror
                                            @else
                                                <span class="badge bg-secondary">—</span>
                                            @endif
                                        </td>

                                        {{-- Evidencia fotográfica --}}
                                        <td class="text-center">
                                            @if(! $fullyReceived)
                                                <div>
                                                    <label class="form-label small mb-1" id="photo-label-{{ $i }}">
                                                        <span class="text-muted">Opcional</span>
                                                    </label>
                                                    <input type="file"
                                                           name="items[{{ $i }}][photos][]"
                                                           id="photos_{{ $i }}"
                                                           class="form-control form-control-sm item-photos"
                                                           accept=".jpg,.jpeg,.png"
                                                           multiple
                                                           data-index="{{ $i }}">
                                                    <div class="form-text" style="font-size:0.7rem">JPG/PNG · Máx. 5 fotos · 5 MB c/u</div>
                                                    @error("items.{$i}.photos")
                                                        <div class="text-danger" style="font-size:0.75rem">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            @else
                                                <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                    </tr>

                                    {{-- ── Fila de no conformidad (oculta por defecto) ── --}}
                                    <tr id="nonconf-row-{{ $i }}"
                                        class="bg-danger-subtle {{ $oldConformity !== 'NO_CONFORME' ? 'd-none' : '' }}">
                                        <td colspan="7" class="py-2 px-3">
                                            <div class="row g-2 align-items-start">
                                                <div class="col-md-3">
                                                    <label class="form-label small fw-bold text-danger" for="nonconf_type_{{ $i }}">
                                                        Tipo de No Conformidad <span class="text-danger">*</span>
                                                    </label>
                                                    <select name="items[{{ $i }}][nonconformity_type]"
                                                            id="nonconf_type_{{ $i }}"
                                                            class="form-select form-select-sm nonconformity-type @error("items.{$i}.nonconformity_type") is-invalid @enderror">
                                                        <option value="">Seleccionar…</option>
                                                        @foreach(\App\Models\ReceptionItem::NONCONFORMITY_TYPES as $key => $label)
                                                            <option value="{{ $key }}"
                                                                {{ old("items.{$i}.nonconformity_type") === $key ? 'selected' : '' }}>
                                                                {{ $label }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    @error("items.{$i}.nonconformity_type")
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                                <div class="col-md-9">
                                                    <label class="form-label small fw-bold text-danger" for="nonconf_notes_{{ $i }}">
                                                        Descripción de la No Conformidad <span class="text-danger">*</span>
                                                        <span class="fw-normal text-muted">(mínimo 100 caracteres)</span>
                                                    </label>
                                                    <textarea name="items[{{ $i }}][nonconformity_notes]"
                                                              id="nonconf_notes_{{ $i }}"
                                                              class="form-control form-control-sm nonconformity-notes @error("items.{$i}.nonconformity_notes") is-invalid @enderror"
                                                              rows="2"
                                                              maxlength="2000"
                                                              data-index="{{ $i }}"
                                                              placeholder="Describa detalladamente la no conformidad: qué se recibió, en qué condiciones, cómo difiere de lo solicitado...">{{ old("items.{$i}.nonconformity_notes") }}</textarea>
                                                    <div class="d-flex justify-content-between">
                                                        @error("items.{$i}.nonconformity_notes")
                                                            <div class="text-danger" style="font-size:0.75rem">{{ $message }}</div>
                                                        @else
                                                            <div></div>
                                                        @enderror
                                                        <small class="text-muted char-counter-{{ $i }}">
                                                            {{ strlen(old("items.{$i}.nonconformity_notes", '')) }}/100
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>

                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Botones ──────────────────────────────────────────────────── --}}
        <div class="col-12 d-flex justify-content-end gap-2 pb-4">
            <a href="{{ route('receptions.overview') }}" class="btn btn-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i>Cancelar
            </a>
            <button type="button" id="btn-submit" class="btn btn-success btn-sm">
                <i class="ti ti-package-import me-1"></i>Registrar Recepción
            </button>
        </div>

    </div>{{-- /row --}}
</form>
@endsection

@push('scripts')
<script>
$(document).ready(function () {

    // ─── Select2 ─────────────────────────────────────────────────────────────
    $('.select2-enable').select2({ width: '100%', placeholder: 'Seleccionar…' });

    // ─── Conformidad: mostrar / ocultar fila de no conformidad ───────────────
    $(document).on('change', '.conformity-radio', function () {
        const idx       = $(this).data('index');
        const isNoConf  = $(this).val() === 'NO_CONFORME';
        const $noConfRow = $('#nonconf-row-' + idx);
        const $photoLabel = $('#photo-label-' + idx);
        const $photoInput = $('#photos_' + idx);

        if (isNoConf) {
            $noConfRow.removeClass('d-none');
            $noConfRow.find('select.nonconformity-type').attr('required', true);
            $noConfRow.find('textarea.nonconformity-notes').attr('required', true);
            $photoLabel.html('<span class="text-danger fw-bold">Obligatorio <i class="ti ti-asterisk" style="font-size:0.6rem"></i></span>');
            $photoInput.attr('required', true);
        } else {
            $noConfRow.addClass('d-none');
            $noConfRow.find('select.nonconformity-type').removeAttr('required').val('');
            $noConfRow.find('textarea.nonconformity-notes').removeAttr('required').val('');
            $photoLabel.html('<span class="text-muted">Opcional</span>');
            $photoInput.removeAttr('required');
        }
    });

    // Inicializar estado de filas que volvieron con old() en NO_CONFORME
    $('.conformity-radio:checked').each(function () {
        if ($(this).val() === 'NO_CONFORME') $(this).trigger('change');
    });

    // ─── Contador de caracteres en notas de no conformidad ───────────────────
    $(document).on('input', '.nonconformity-notes', function () {
        const idx = $(this).data('index');
        const len = $(this).val().length;
        const $counter = $('.char-counter-' + idx);
        $counter.text(len + '/100');
        $counter.toggleClass('text-danger', len < 100).toggleClass('text-success', len >= 100);
    });

    // ─── Cant. a recibir: bloqueo si excede pendiente ────────────────────────
    $(document).on('change input', '.qty-received', function () {
        const max = parseFloat($(this).data('max')) || 0;
        const val = parseFloat($(this).val()) || 0;

        if (val > max) {
            $(this).val(max.toFixed(3));
            Swal.fire({
                icon: 'warning',
                title: 'Cantidad excedida',
                html: 'La cantidad a recibir no puede superar la pendiente de <strong>' + max.toFixed(3) + '</strong>.',
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#f7b731',
            });
        }
    });

    // ─── Validación de fotos por ítem (máx. 5) ───────────────────────────────
    $(document).on('change', '.item-photos', function () {
        if (this.files.length > 5) {
            Swal.fire({
                icon: 'warning',
                title: 'Demasiadas fotos',
                text: 'Solo se permiten un máximo de 5 fotos por partida.',
                confirmButtonText: 'Entendido',
            });
            this.value = '';
        }
    });

    // ─── Validaciones antes de submit ────────────────────────────────────────
    function validateRemissionFile() {
        const file = document.getElementById('remission_file').files[0];
        if (!file) return { valid: true }; // la remisión es opcional

        const allowed = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!allowed.includes(file.type)) {
            return { valid: false, message: 'Solo se permiten archivos PDF, JPG o PNG.' };
        }
        if (file.size > 10 * 1024 * 1024) {
            return { valid: false, message: 'El archivo no puede superar los 10 MB.' };
        }
        return { valid: true };
    }

    function validateItems() {
        let hasAnyReceived = false;
        const errors = [];

        $('#items-table tbody tr:not(.table-secondary):not([id^="nonconf-row"])').each(function () {
            const $row    = $(this);
            const idx     = $row.data('index');
            if (idx === undefined) return; // fila auxiliar

            const pending    = parseFloat($row.data('pending')) || 0;
            const received   = parseFloat($row.find('.qty-received').val()) || 0;
            const conformity = $row.find('.conformity-radio:checked').val() || 'CONFORME';

            if (received > 0) hasAnyReceived = true;

            if (received > (pending + 0.0005)) {
                const desc = $row.find('td.ps-3 .fw-semibold').text().trim().substring(0, 45);
                errors.push('"' + desc + '": la cantidad excede el pendiente (' + pending.toFixed(3) + ').');
            }

            if (received > 0 && conformity === 'NO_CONFORME') {
                const $noConfRow = $('#nonconf-row-' + idx);

                if (! $noConfRow.find('select.nonconformity-type').val()) {
                    const desc = $row.find('td.ps-3 .fw-semibold').text().trim().substring(0, 45);
                    errors.push('"' + desc + '": selecciona el tipo de no conformidad.');
                }

                const notes = $noConfRow.find('textarea.nonconformity-notes').val().trim();
                if (notes.length < 100) {
                    const desc = $row.find('td.ps-3 .fw-semibold').text().trim().substring(0, 45);
                    errors.push('"' + desc + '": la descripción de no conformidad debe tener al menos 100 caracteres (actual: ' + notes.length + ').');
                }

                const $photoInput = $('#photos_' + idx);
                if (! $photoInput[0] || $photoInput[0].files.length === 0) {
                    const desc = $row.find('td.ps-3 .fw-semibold').text().trim().substring(0, 45);
                    errors.push('"' + desc + '": adjunta al menos 1 foto de evidencia (obligatorio para partidas NO CONFORMES).');
                }
            }
        });

        if (!hasAnyReceived) {
            errors.unshift('Debes registrar al menos una partida con cantidad a recibir mayor a cero.');
        }

        return errors;
    }

    function buildConfirmSummary() {
        let conformeCount = 0, noConformeCount = 0;

        $('#items-table tbody tr:not(.table-secondary):not([id^="nonconf-row"])').each(function () {
            const idx = $(this).data('index');
            if (idx === undefined) return;
            const received   = parseFloat($(this).find('.qty-received').val()) || 0;
            if (received <= 0) return;
            const conformity = $(this).find('.conformity-radio:checked').val() || 'CONFORME';
            if (conformity === 'NO_CONFORME') noConformeCount++; else conformeCount++;
        });

        let html = 'Esta acción registrará la recepción y actualizará el estado de la orden.<br><br>';
        html += '<div class="d-flex justify-content-center gap-3">';
        html += '<span class="badge bg-success fs-14"><i class="ti ti-circle-check me-1"></i>' + conformeCount + ' conforme(s)</span>';
        if (noConformeCount > 0) {
            html += '<span class="badge bg-danger fs-14"><i class="ti ti-circle-x me-1"></i>' + noConformeCount + ' no conforme(s)</span>';
        }
        html += '</div><br><strong>¿Confirmas el registro?</strong>';
        return html;
    }

    // ─── Botón submit con confirmación SweetAlert2 ───────────────────────────
    $('#btn-submit').on('click', function () {

        const fileCheck = validateRemissionFile();
        if (!fileCheck.valid) {
            Swal.fire({ icon: 'error', title: 'Archivo inválido', text: fileCheck.message });
            return;
        }

        const itemErrors = validateItems();
        if (itemErrors.length > 0) {
            Swal.fire({
                icon: 'error',
                title: 'Revisa las partidas',
                html: '<ul class="text-start mb-0">' +
                      itemErrors.map(function(e) { return '<li>' + e + '</li>'; }).join('') +
                      '</ul>',
            });
            return;
        }

        Swal.fire({
            title: 'Confirmar Recepción',
            html: buildConfirmSummary(),
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="ti ti-check me-1"></i>Sí, registrar',
            cancelButtonText: 'Revisar',
            confirmButtonColor: '#0acf97',
            cancelButtonColor: '#6c757d',
        }).then(function (result) {
            if (result.isConfirmed) {
                $('#btn-submit').prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-1"></span>Guardando…');
                document.getElementById('reception-form').submit();
            }
        });
    });

});
</script>
@endpush
