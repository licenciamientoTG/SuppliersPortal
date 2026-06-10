@extends('layouts.zircos-supplier')

@section('title', 'Registrar Entrega — ' . ($order->folio ?? 'OC'))
@section('page.title', 'Registrar Entrega')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('supplier.dashboard') }}">Portal</a></li>
    <li class="breadcrumb-item"><a href="{{ route('supplier.deliveries.index') }}">Entregas</a></li>
    <li class="breadcrumb-item active">Registrar Entrega</li>
@endsection

@section('content')
<div class="row g-3">

    {{-- Errores de validación --}}
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

    {{-- ================================================================== --}}
    {{-- DATOS DE LA ORDEN DE COMPRA (solo lectura)                         --}}
    {{-- ================================================================== --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">
                    <i class="ti ti-file-invoice me-1"></i>
                    Datos de la Orden de Compra
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-muted">Folio</label>
                        <p class="fs-5 fw-bold mb-0">{{ $order->folio }}</p>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-muted">Total</label>
                        <p class="fs-5 mb-0">{{ format_money($order->total, $order->currency, true) }}</p>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-muted">Fecha de Emisión</label>
                        <p class="mb-0">{{ $order->issued_at?->format('d/m/Y') ?? '—' }}</p>
                    </div>
                </div>

                {{-- Punto de entrega --}}
                @if($location)
                <div class="row g-3 mt-1">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted">Punto de Entrega</label>
                        <p class="mb-0">
                            <span class="badge bg-soft-info text-info">{{ $location->code }}</span>
                            {{ $location->name }}
                        </p>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted">Dirección</label>
                        <p class="mb-0">{{ $location->full_address ?: '—' }}</p>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted">Tipo de Ubicación</label>
                        <p class="mb-0">{{ $location->type_name }}</p>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Partidas de la OC --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">
                    <i class="ti ti-list-details me-1"></i>
                    Partidas de la Orden
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Descripción</th>
                                <th class="text-center">Cantidad</th>
                                <th>Unidad</th>
                                <th class="text-end">P. Unitario</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $i => $item)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $item->description ?? $item->concept ?? '—' }}</td>
                                <td class="text-center">{{ number_format($item->quantity, 2) }}</td>
                                <td>{{ $item->unit ?? $item->unit_of_measure ?? '—' }}</td>
                                <td class="text-end">{{ format_money($item->unit_price, $order->currency) }}</td>
                                <td class="text-end">{{ format_money($item->total ?? ($item->quantity * $item->unit_price), $order->currency) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- BLOQUEO DE ESTACIÓN                                                --}}
    {{-- Si la estación tiene OCs en DELIVERED_PENDING_RECEPTION, mostrar   --}}
    {{-- mensaje de bloqueo y NO mostrar el formulario                      --}}
    {{-- ================================================================== --}}
    @if($isLocationBlocked)
        <div class="col-12">
            <div class="alert alert-warning border-warning">
                <h5 class="alert-heading">
                    <i class="ti ti-lock me-1"></i>
                    Estación Bloqueada para Nuevas Entregas
                </h5>
                <p class="mb-2">
                    La estación <strong>{{ $location->name }}</strong> tiene entregas pendientes de captura por parte del receptor.
                    No se pueden registrar nuevas entregas hasta que se resuelvan las siguientes OCs:
                </p>
                <ul class="mb-0">
                    @foreach($blockingOrders as $blocking)
                        <li>
                            <strong>{{ $blocking->folio }}</strong>
                            — Proveedor: {{ $blocking->supplier->company_name ?? '—' }}
                            — Entregada el: {{ $blocking->supplier_delivered_at?->format('d/m/Y') ?? '—' }}
                            — Límite: {{ $blocking->reception_deadline_at?->format('d/m/Y') ?? '—' }}
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="text-center">
                <a href="{{ route('supplier.deliveries.index') }}" class="btn btn-secondary">
                    <i class="ti ti-arrow-left me-1"></i> Volver al listado
                </a>
            </div>
        </div>
    @else

    {{-- ================================================================== --}}
    {{-- FORMULARIO DE REGISTRO DE ENTREGA                                  --}}
    {{-- ================================================================== --}}
    <div class="col-12">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0 text-white">
                    <i class="ti ti-truck-delivery me-1"></i>
                    Registrar Entrega
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('supplier.deliveries.store') }}"
                      method="POST"
                      enctype="multipart/form-data"
                      id="delivery-form">
                    @csrf

                    <input type="hidden" name="order_type" value="{{ $orderType }}">
                    <input type="hidden" name="order_id" value="{{ $order->id }}">

                    <div class="row g-3">
                        {{-- Remisión digital (OBLIGATORIO) --}}
                        <div class="col-md-6">
                            <label for="remission_file" class="form-label fw-semibold">
                                Remisión Digital <span class="text-danger">*</span>
                            </label>
                            <input type="file"
                                   class="form-control @error('remission_file') is-invalid @enderror"
                                   id="remission_file"
                                   name="remission_file"
                                   accept=".pdf,.jpg,.jpeg,.png"
                                   required>
                            <div class="form-text">PDF, JPG o PNG. Máximo 10 MB.</div>
                            @error('remission_file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Fecha de entrega física (OBLIGATORIO) --}}
                        <div class="col-md-6">
                            <label for="delivered_at" class="form-label fw-semibold">
                                Fecha de Entrega Física <span class="text-danger">*</span>
                            </label>
                            <input type="datetime-local"
                                   class="form-control @error('delivered_at') is-invalid @enderror"
                                   id="delivered_at"
                                   name="delivered_at"
                                   value="{{ old('delivered_at') }}"
                                   max="{{ now()->format('Y-m-d\TH:i') }}"
                                   required>
                            @error('delivered_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Nombre de quien recibió (OPCIONAL) --}}
                        <div class="col-md-6">
                            <label for="physical_receiver_name" class="form-label fw-semibold">
                                Nombre de quien recibió en la estación
                                <span class="text-muted fw-normal">(opcional)</span>
                            </label>
                            <input type="text"
                                   class="form-control @error('physical_receiver_name') is-invalid @enderror"
                                   id="physical_receiver_name"
                                   name="physical_receiver_name"
                                   value="{{ old('physical_receiver_name') }}"
                                   maxlength="150"
                                   placeholder="Nombre de la persona que recibió">
                            @error('physical_receiver_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Observaciones (OPCIONAL) --}}
                        <div class="col-md-6">
                            <label for="delivery_observations" class="form-label fw-semibold">
                                Observaciones
                                <span class="text-muted fw-normal">(opcional)</span>
                            </label>
                            <textarea class="form-control @error('delivery_observations') is-invalid @enderror"
                                      id="delivery_observations"
                                      name="delivery_observations"
                                      rows="2"
                                      maxlength="2000"
                                      placeholder="Observaciones adicionales sobre la entrega">{{ old('delivery_observations') }}</textarea>
                            @error('delivery_observations')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Información sobre el proceso --}}
                    <div class="alert alert-info mt-3 mb-3">
                        <i class="ti ti-info-circle me-1"></i>
                        <strong>Importante:</strong> Al registrar esta entrega, la estación receptora tendrá
                        <strong>3 días hábiles</strong> para capturar la recepción en el sistema.
                        Se enviarán alertas automáticas si no se registra a tiempo.
                    </div>

                    {{-- Botones --}}
                    <div class="d-flex justify-content-between">
                        <a href="{{ route('supplier.deliveries.index') }}" class="btn btn-secondary">
                            <i class="ti ti-arrow-left me-1"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary" id="btn-submit">
                            <i class="ti ti-truck-delivery me-1"></i> Registrar Entrega
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @endif

</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('delivery-form');
    if (!form) return;

    const btnSubmit = document.getElementById('btn-submit');

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        // Validar que se adjuntó remisión
        const fileInput = document.getElementById('remission_file');
        if (!fileInput.files.length) {
            Swal.fire('Error', 'Debes adjuntar la remisión digital.', 'error');
            return;
        }

        // Validar tamaño del archivo (10 MB)
        const maxSize = 10 * 1024 * 1024;
        if (fileInput.files[0].size > maxSize) {
            Swal.fire('Error', 'El archivo no debe superar 10 MB.', 'error');
            return;
        }

        // Validar formato
        const allowedFormats = ['pdf', 'jpg', 'jpeg', 'png'];
        const ext = fileInput.files[0].name.split('.').pop().toLowerCase();
        if (!allowedFormats.includes(ext)) {
            Swal.fire('Error', 'Formato no permitido. Solo PDF, JPG o PNG.', 'error');
            return;
        }

        // Validar fecha
        const deliveredAt = document.getElementById('delivered_at');
        if (!deliveredAt.value) {
            Swal.fire('Error', 'La fecha de entrega es obligatoria.', 'error');
            return;
        }

        // Confirmar con SweetAlert2
        Swal.fire({
            title: 'Confirmar Entrega',
            html: `
                <p>Vas a registrar la entrega para la OC <strong>{{ $order->folio }}</strong>.</p>
                <p>La estación tendrá <strong>3 días hábiles</strong> para capturar la recepción.</p>
                <p class="text-muted">Esta acción no se puede deshacer.</p>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, registrar entrega',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Registrando...';
                form.submit();
            }
        });
    });
});
</script>
@endsection
