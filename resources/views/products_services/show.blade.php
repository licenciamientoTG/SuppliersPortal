@extends('layouts.zircos')

@section('title', 'Detalle Producto/Servicio')
@section('page.title', $productService->code)
@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('products-services.index') }}">Catálogo</a></li>
    <li class="breadcrumb-item active">{{ $productService->code }}</li>
@endsection

@section('content')
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <!-- Columna Principal (izquierda) -->
        <div class="col-lg-8">
            {{-- Información General --}}
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="ti ti-package"></i> Información General</h5>
                    @php
                        $status = App\Enum\ProductServiceStatus::from($productService->status);
                        $cls = App\Enum\ProductServiceStatus::badgeClass($status);
                    @endphp
                    <span class="badge bg-{{ $cls }} fs-6">{{ $productService->statusLabel() }}</span>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <strong>Código:</strong>
                        </div>
                        <div class="col-md-9">
                            {{ $productService->code }}
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <strong>Descripción Técnica:</strong>
                        </div>
                        <div class="col-md-9">
                            <p class="mb-0">{{ $productService->technical_description }}</p>
                        </div>
                    </div>

                    @if ($productService->rejection_reason)
                        <div class="row">
                            <div class="col-12">
                                <div class="alert alert-danger">
                                    <strong><i class="ti ti-alert-triangle me-2"></i>Motivo del Rechazo:</strong><br>
                                    {{ $productService->rejection_reason }}
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Información Adicional --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-info-square"></i> Información Adicional</h5>
                </div>
                <div class="card-body">
                    {{-- Tipo --}}
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <strong>Tipo:</strong>
                        </div>
                        <div class="col-md-9">
                            <span class="badge bg-{{ $productService->product_type === 'SERVICIO' ? 'info' : 'primary' }}">
                                {{ $productService->product_type }}
                            </span>
                        </div>
                    </div>

                    {{-- Nombre Corto --}}
                    @if($productService->short_name)
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <strong>Nombre Corto:</strong>
                        </div>
                        <div class="col-md-9">
                            {{ $productService->short_name }}
                        </div>
                    </div>
                    @endif

                    {{-- Marca y Modelo --}}
                    @if($productService->brand || $productService->model)
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <strong>Marca / Modelo:</strong>
                        </div>
                        <div class="col-md-9">
                            {{ $productService->brand ?? '—' }} / {{ $productService->model ?? '—' }}
                        </div>
                    </div>
                    @endif

                    {{-- Unidad de Medida --}}
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <strong>Unidad de Medida:</strong>
                        </div>
                        <div class="col-md-9">
                            <span class="badge bg-secondary">{{ $productService->unit_of_measure }}</span>
                        </div>
                    </div>

                    {{-- Especificaciones Técnicas (JSON) --}}
                    @php
                        $specifications = is_string($productService->specifications) 
                            ? json_decode($productService->specifications, true) 
                            : $productService->specifications;
                    @endphp

                    @if(!empty($specifications))
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <strong>Especificaciones:</strong>
                        </div>
                        <div class="col-md-9">
                            <table class="table table-sm table-bordered">
                                @foreach((array)$specifications as $key => $value)
                                    <tr>
                                        <td class="fw-bold text-capitalize">{{ str_replace('_', ' ', $key) }}</td>
                                        <td>{{ is_array($value) ? json_encode($value) : $value }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    </div>
                    @endif

                    {{-- Observaciones --}}
                    @if($productService->observations)
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <strong>Observaciones:</strong>
                        </div>
                        <div class="col-md-9">
                            <p class="mb-0">{{ $productService->observations }}</p>
                        </div>
                    </div>
                    @endif

                    {{-- Notas Internas (solo para Compras) --}}
                    @if($productService->internal_notes && (auth()->user()->hasRole('buyer') || auth()->user()->hasRole('superadmin')))
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Notas Internas:</strong>
                        </div>
                        <div class="col-md-9">
                            <div class="alert alert-warning mb-0">
                                <i class="ti ti-lock me-2"></i>{{ $productService->internal_notes }}
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Auditoría --}}
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-history"></i> Auditoría</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-md-3">
                            <strong>Creado por:</strong>
                        </div>
                        <div class="col-md-9">
                            {{ $productService->creator?->name ?? '—' }}
                            <small class="text-muted">{{ $productService->created_at?->format('d/m/Y H:i') }}</small>
                        </div>
                    </div>

                    @if ($productService->approved_by)
                        <div class="row mb-2">
                            <div class="col-md-3">
                                <strong>Aprobado por:</strong>
                            </div>
                            <div class="col-md-9">
                                {{ $productService->approver?->name ?? '—' }}
                                <small class="text-muted">{{ $productService->approved_at?->format('d/m/Y H:i') }}</small>
                            </div>
                        </div>
                    @endif

                    @if ($productService->updated_at && $productService->updated_at != $productService->created_at)
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Última actualización:</strong>
                            </div>
                            <div class="col-md-9">
                                <small class="text-muted">{{ $productService->updated_at?->format('d/m/Y H:i') }}</small>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Columna Lateral (derecha) -->
        <div class="col-lg-4">
            {{-- Estado Activo/Inactivo --}}
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <strong>Disponible para Requisiciones:</strong>
                        @if($productService->is_active && $productService->status === 'ACTIVE')
                            <span class="badge bg-success">
                                <i class="ti ti-check-circle"></i> SÍ
                            </span>
                        @else
                            <span class="badge bg-danger">
                                <i class="ti ti-x-circle"></i> NO
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Información Comercial --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-shopping-cart"></i> Información Comercial</h5>
                </div>
                <div class="card-body">
                    {{-- Proveedor Sugerido --}}
                    @if($productService->defaultVendor)
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Proveedor Sugerido:</strong>
                        </div>
                        <div class="col-md-8">
                            {{ $productService->defaultVendor->company_name }}
                            <small class="text-muted">(Sugerencia del catálogo)</small>
                        </div>
                    </div>
                    @endif

                    {{-- Tiempo de Entrega --}}
                    @if($productService->lead_time_days)
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Tiempo de Entrega:</strong>
                        </div>
                        <div class="col-md-8">
                            <i class="ti ti-clock me-1"></i>{{ $productService->lead_time_days }} días
                        </div>
                    </div>
                    @endif

                    {{-- Cantidad Mínima / Máxima --}}
                    @if($productService->minimum_quantity || $productService->maximum_quantity)
                    <div class="row">
                        <div class="col-md-4">
                            <strong>Restricciones de Cantidad:</strong>
                        </div>
                        <div class="col-md-8">
                            @if($productService->minimum_quantity)
                                <span class="badge bg-info">Mín: {{ $productService->minimum_quantity }}</span>
                            @endif
                            @if($productService->maximum_quantity)
                                <span class="badge bg-warning">Máx: {{ $productService->maximum_quantity }}</span>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Información Financiera --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-cash"></i> Información Financiera</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Precio Estimado:</strong>
                        </div>
                        <div class="col-6 text-end">
                            <span class="fs-5">{{ number_format($productService->estimated_price, 2) }}</span>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <strong>Moneda:</strong>
                        </div>
                        <div class="col-6 text-end">
                            {{ $productService->currency_code }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Clasificaci�n Presupuestal --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-tags"></i> Clasificaci�n Presupuestal</h5>
                </div>
                <div class="card-body">

                    <div class="row mb-2">
                        <div class="col-5">
                            <strong>Cuentas:</strong>
                        </div>
                        <div class="col-7">
                            @forelse ($productService->expenseCategories as $category)
                                <span class="badge bg-light text-dark border me-1 mb-1">[{{ $category->code }}] {{ $category->name }}</span>
                            @empty
                                Sin clasificar
                            @endforelse
                        </div>
                    </div>

                    <div class="row mb-2">
                        <div class="col-5">
                            <strong>Subcuentas:</strong>
                        </div>
                        <div class="col-7">
                            @forelse ($productService->budgetCedulas as $cedula)
                                <span class="badge bg-light text-dark border me-1 mb-1">{{ $cedula->name }}</span>
                            @empty
                                Sin clasificar
                            @endforelse
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-5">
                            <strong>Inventariable:</strong>
                        </div>
                        <div class="col-7">
                            @if ($productService->is_inventoriable)
                                <span class="badge bg-success">Sí</span>
                            @else
                                <span class="badge bg-secondary">No</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Acciones --}}
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-tool"></i> Acciones</h5>
                </div>
                <div class="card-body">
                    <a href="{{ route('products-services.index') }}" class="btn btn-light w-100 mb-2">
                        <i class="ti ti-arrow-left me-2"></i>Volver al Listado
                    </a>

                    @if ($productService->status != 'REJECTED')
                        <a href="{{ route('products-services.edit', $productService) }}"
                            class="btn btn-primary w-100 mb-2">
                            <i class="ti ti-edit me-2"></i>Editar
                        </a>
                    @endif

                    @if (auth()->user()->hasRole('catalog_admin') || auth()->user()->hasRole('superadmin'))
                        @if ($productService->status === 'PENDING')
                            <hr>
                            <form action="{{ route('products-services.approve', $productService) }}" method="POST"
                                class="mb-2">
                                @csrf
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="ti ti-check me-2"></i>Aprobar
                                </button>
                            </form>

                            <button type="button" class="btn btn-warning w-100" data-bs-toggle="modal"
                                data-bs-target="#rejectModal">
                                <i class="ti ti-x me-2"></i>Rechazar
                            </button>
                        @endif

                        @if ($productService->status === 'ACTIVE')
                            <hr>
                            <form action="{{ route('products-services.deactivate', $productService) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-secondary w-100">
                                    <i class="ti ti-circle-off me-2"></i>Desactivar
                                </button>
                            </form>
                        @endif

                        @if ($productService->status === 'INACTIVE')
                            <hr>
                            <form action="{{ route('products-services.reactivate', $productService) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-success w-100 js-reactivate-btn">
                                    <i class="ti ti-circle-check me-2"></i>Reactivar
                                </button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Modal para rechazar --}}
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('products-services.reject', $productService) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="rejectModalLabel">Rechazar Producto/Servicio</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="rejection_reason" class="form-label">Motivo del Rechazo <span
                                    class="text-danger">*</span></label>
                            <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="4" required minlength="10"
                                maxlength="500" placeholder="Explica claramente el motivo del rechazo..."></textarea>
                            <div class="form-text">Mínimo 10 caracteres, máximo 500.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">Rechazar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).on('click', '.js-reactivate-btn', function(e) {
            e.preventDefault();
            const form = $(this).closest('form');

            Swal.fire({
                title: '¿Reactivar producto?',
                text: 'El producto estará disponible para requisiciones nuevamente.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, reactivar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
                customClass: {
                    confirmButton: 'btn btn-success',
                    cancelButton: 'btn btn-light'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) form.submit();
            });
        });
    </script>
@endpush
