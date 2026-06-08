@extends('layouts.zircos-supplier')

@section('title', $announcement->title)

@section('page.title', 'Comunicados')
@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('supplier.announcements.inbox') }}">Comunicados</a></li>
    <li class="breadcrumb-item active">{{ \Illuminate\Support\Str::limit($announcement->title, 40) }}</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="ti ti-speakerphone me-2"></i>{{ $announcement->title }}
        </h5>
        <div class="d-flex gap-2">
            <a href="{{ route('supplier.announcements.pdf', $announcement) }}" target="_blank" class="btn btn-sm btn-outline-info">
                <i class="ti ti-file-text me-1"></i> PDF
            </a>
            <form action="{{ route('supplier.announcements.dismiss', $announcement) }}" method="POST" class="d-inline js-dismiss-form">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="ti ti-eye-off me-1"></i> No volver a mostrar
                </button>
            </form>
        </div>
    </div>

    <div class="card-body">
        <div class="mb-3 text-muted">
            <small>
                <strong>Fecha de publicación:</strong> {{ optional($announcement->published_at)->format('d/m/Y H:i') ?? '—' }} |
                <strong>Visible hasta:</strong> {{ optional($announcement->visible_until)->format('d/m/Y H:i') ?? 'Sin fecha de caducidad' }} |
                <strong>Estado:</strong> {{ $announcement->should_display ? 'Activo' : 'Inactivo/Oculto' }}
            </small>
        </div>

        @if($announcement->cover_url)
            <div class="mb-3">
                <img src="{{ asset('storage/'.$announcement->cover_path) }}" alt="Cover" class="img-fluid rounded">
            </div>
        @endif

        <p class="lead">{{ $announcement->description }}</p>
    </div>
</div>
@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-dismiss-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault(); // evita submit inmediato

            Swal.fire({
                title: '¿Ocultar este comunicado?',
                text: 'Ya no volverás a ver este comunicado en tu bandeja.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, ocultar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit(); // ahora sí enviamos el form
                }
            });
        });
    });
});
</script>
@endpush
