@extends('layouts.zircos')

@section('title', $board['title'])
@section('page.title', $board['title'])
@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item">Control y Monitoreo</li>
    <li class="breadcrumb-item active">{{ $board['title'] }}</li>
@endsection

@push('styles')
<style>
    .monitor-hero,.monitor-kpi,.monitor-panel{border:1px solid #e2e9f0;border-radius:.8rem;background:#fff;box-shadow:0 5px 18px rgba(28,80,120,.05)}
    .monitor-hero{display:flex;align-items:flex-start;gap:1rem;padding:1.2rem;margin-bottom:1rem;background:#f7fbff}.monitor-icon{display:flex;align-items:center;justify-content:center;width:3rem;height:3rem;border-radius:.75rem;background:#e7f4fd;color:#188ae2;font-size:1.45rem}.monitor-kicker{color:#718096;font-size:.69rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase}.monitor-hero h4{margin:.15rem 0;color:#24364b}.monitor-hero p{margin:0;color:#637588}.monitor-scope{margin-left:auto;white-space:nowrap}.monitor-kpi{height:100%;padding:.9rem}.monitor-kpi span,.monitor-kpi strong{display:block}.monitor-kpi span{color:#718096;font-size:.7rem;font-weight:700;text-transform:uppercase}.monitor-kpi strong{margin-top:.25rem;color:#24364b;font-size:1.35rem}.monitor-kpi.danger strong{color:#d9534f}.monitor-kpi.warning strong{color:#c58a00}.monitor-kpi.success strong{color:#218b64}.monitor-panel{overflow:hidden;margin-top:1rem}.monitor-panel header{display:flex;align-items:center;gap:.5rem;padding:.85rem 1rem;border-bottom:1px solid #e9eef3;background:#fbfdff}.monitor-panel header i{color:#188ae2}.monitor-panel h5{margin:0;font-size:.95rem}.monitor-row{transition:background .18s ease,transform .18s ease}.monitor-row:hover{background:#f7fbff;transform:translateY(-1px)}.monitor-empty{padding:2.5rem 1rem;color:#718096;text-align:center}.monitor-empty i{display:block;margin-bottom:.5rem;color:#188ae2;font-size:1.7rem}@media (prefers-reduced-motion:reduce){.monitor-row{transition:none}.monitor-row:hover{transform:none}}@media(max-width:767.98px){.monitor-hero{flex-wrap:wrap}.monitor-scope{margin-left:0}}
</style>
@endpush

@section('content')
<section class="monitor-hero">
    <div class="monitor-icon"><i class="ti {{ $board['icon'] }}"></i></div>
    <div><span class="monitor-kicker">Control y Monitoreo</span><h4>{{ $board['title'] }}</h4><p>{{ $board['description'] }}</p></div>
    <span class="badge {{ $board['is_global'] ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-light text-secondary border' }} monitor-scope">{{ $board['is_global'] ? 'Alcance global' : 'Alcance asignado' }}</span>
</section>
<nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Monitores disponibles">
    @moduleAccess('monitoring_alerts')<a class="btn btn-sm {{ request()->routeIs('monitoring.alerts') ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('monitoring.alerts') }}">Alertas</a>@endmoduleAccess
    @moduleAccess('monitoring_operations')<a class="btn btn-sm {{ request()->routeIs('monitoring.operations') ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('monitoring.operations') }}">Compras</a>@endmoduleAccess
    @moduleAccess('monitoring_budget')<a class="btn btn-sm {{ request()->routeIs('monitoring.budget') ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('monitoring.budget') }}">Presupuesto</a>@endmoduleAccess
    @moduleAccess('monitoring_suppliers')<a class="btn btn-sm {{ request()->routeIs('monitoring.suppliers') ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('monitoring.suppliers') }}">Proveedores</a>@endmoduleAccess
    @moduleAccess('monitoring_security')<a class="btn btn-sm {{ request()->routeIs('monitoring.security') ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('monitoring.security') }}">Seguridad</a>@endmoduleAccess
</nav>
<div class="row g-3">@foreach($board['kpis'] as $kpi)<div class="col-sm-6 col-xl"><div class="monitor-kpi {{ $kpi['tone'] }}"><span>{{ $kpi['label'] }}</span><strong>{{ $kpi['value'] }}</strong></div></div>@endforeach</div>
@foreach($board['sections'] as $section)
    <section class="monitor-panel"><header><i class="ti {{ $section['icon'] }}"></i><h5>{{ $section['title'] }}</h5></header>
        @if(count($section['items']))
            <div class="table-responsive"><table class="table align-middle mb-0"><thead class="table-light"><tr>@foreach($section['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr></thead><tbody>@foreach($section['items'] as $item)
                <tr class="monitor-row"><td class="fw-semibold">{{ $item['label'] }}</td><td>{{ $item['count'] }}</td><td><span class="text-muted">{{ $item['context'] }}</span>@if(isset($item['badge']))<br><span class="badge text-bg-{{ $item['tone'] === 'agotado' ? 'dark' : ($item['tone'] === 'crítico' ? 'danger' : $item['tone']) }} mt-1">{{ $item['badge'] }}</span>@endif</td><td><a href="{{ $item['url'] }}" class="btn btn-sm btn-outline-primary">{{ $item['action'] }}</a></td></tr>
            @endforeach</tbody></table></div>
        @else <div class="monitor-empty"><i class="ti ti-circle-check"></i><strong>Sin pendientes para mostrar</strong><div>Los datos disponibles no requieren atención en este momento.</div></div>@endif
    </section>
@endforeach
@endsection
