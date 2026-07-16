@extends('layouts.zircos')

@section('title', 'Catálogo de documentos')
@section('page.title', 'Catálogo de documentos')
@section('content')
<div class="d-flex justify-content-end mb-3"><a href="{{ route('supplier-document-types.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i> Nuevo documento</a></div>
<div class="card"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>Documento</th><th>Aplicación</th><th>Renovación</th><th>Vigencia</th><th>Estado</th><th></th></tr></thead><tbody>
@forelse($types as $type)
<tr><td><div class="fw-semibold">{{ $type->name }}</div><small class="text-muted">{{ $type->code }}</small></td><td>{{ collect([ $type->applies_to_physical ? 'Física' : null, $type->applies_to_legal ? 'Moral' : null, $type->requires_repse ? 'REPSE' : null ])->filter()->implode(', ') }}</td><td>{{ $type->renewal_mode === 'periodic' ? 'Cada '.$type->renewal_interval_days.' días' : 'Una sola vez' }}</td><td>{{ $type->validity_source === 'qr' ? 'QR' : 'Confirmada por revisor' }}</td><td><span class="badge {{ $type->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $type->is_active ? 'Activo' : 'Inactivo' }}</span> @if(!$type->is_required)<span class="badge bg-light text-dark">Opcional</span>@endif</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('supplier-document-types.edit', $type) }}" title="Editar"><i class="ti ti-pencil"></i></a></td></tr>
@empty <tr><td colspan="6" class="text-center text-muted py-4">No hay documentos configurados.</td></tr> @endforelse
</tbody></table></div></div></div>
@endsection
