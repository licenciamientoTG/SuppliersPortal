@extends('layouts.zircos-supplier')

@section('title', 'Mis RFQs')
@section('page.title', 'Mis RFQs')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('supplier.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Mis RFQs</li>
@endsection

@section('content')
<div class="container-fluid py-4 supplier-rfqs-page">
    <section class="supplier-rfqs-header mb-3">
        <img src="{{ asset('images/logos/Logo.png') }}" alt="TotalGas" class="supplier-rfqs-logo">
        <div>
            <span class="supplier-rfqs-eyebrow">Cotizaciones</span>
            <h1>Mis RFQs</h1>
            <p class="mb-0">Revisa y responde las solicitudes de cotización que tienes asignadas.</p>
        </div>
        <span class="supplier-rfqs-count">{{ $rfqs->total() }} {{ $rfqs->total() === 1 ? 'RFQ' : 'RFQs' }}</span>
    </section>

    <section class="card supplier-rfqs-list">
        <div class="card-body p-0">
            @forelse($rfqs as $rfq)
                @php
                    $response = $rfq->rfqResponses->first();
                    $responseStatus = $response?->status;
                    $isDraft = $responseStatus === 'DRAFT';
                    $isSubmitted = $responseStatus && $responseStatus !== 'DRAFT';
                    $statusLabel = $isDraft ? 'Borrador' : ($isSubmitted ? 'Enviada' : 'Pendiente de responder');
                    $statusClass = $isDraft ? 'secondary' : ($isSubmitted ? 'success' : 'warning');
                    $deadlineDays = $rfq->response_deadline ? now()->diffInDays($rfq->response_deadline, false) : null;
                @endphp
                <article class="supplier-rfq-row">
                    <div class="supplier-rfq-icon"><i class="ti ti-file-invoice"></i></div>
                    <div class="supplier-rfq-main">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <h2>{{ $rfq->folio }}</h2>
                            <span class="badge text-bg-{{ $statusClass }}">{{ $statusLabel }}</span>
                        </div>
                        <p>{{ $rfq->quotationGroup?->name ?? $rfq->requisition?->description ?? 'Solicitud de cotización' }}</p>
                        <span class="supplier-rfq-meta"><i class="ti ti-clipboard-text me-1"></i>{{ $rfq->requisition?->folio ?? 'Sin requisición' }}</span>
                    </div>
                    <div class="supplier-rfq-deadline">
                        @if($rfq->response_deadline)
                            <small>Fecha límite</small>
                            <strong class="{{ $deadlineDays !== null && $deadlineDays < 0 ? 'text-danger' : ($deadlineDays !== null && $deadlineDays <= 3 ? 'text-warning' : '') }}">
                                {{ $rfq->response_deadline->format('d/m/Y') }}
                            </strong>
                        @else
                            <small>Fecha límite</small><strong>Sin definir</strong>
                        @endif
                    </div>
                    <a href="{{ route('supplier.rfq.show', $rfq) }}" class="btn btn-sm {{ $isSubmitted ? 'btn-outline-primary' : 'btn-primary' }} supplier-rfq-action">
                        <i class="ti {{ $isSubmitted ? 'ti-eye' : 'ti-pencil' }} me-1"></i>{{ $isSubmitted ? 'Ver' : ($isDraft ? 'Continuar' : 'Cotizar') }}
                    </a>
                </article>
            @empty
                <div class="supplier-rfqs-empty">
                    <i class="ti ti-file-off"></i>
                    <h2>No tienes RFQs asignadas</h2>
                    <p class="mb-0">Cuando Compras te invite a cotizar, aparecerá aquí.</p>
                </div>
            @endforelse
        </div>
        @if($rfqs->hasPages())
            <div class="card-footer bg-white border-top-0 pt-0">{{ $rfqs->links() }}</div>
        @endif
    </section>
</div>
@endsection

@push('styles')
<style>
    .supplier-rfqs-page { max-width: 1120px; }
    .supplier-rfqs-header { display:flex; align-items:center; gap:1rem; padding:1.2rem 1.35rem; background:#fff; border:1px solid #e2e9f0; border-radius:.85rem; box-shadow:0 .3rem 1rem rgba(35,79,119,.05); animation:supplier-rfqs-enter .35s ease both; }
    .supplier-rfqs-logo { width:2.4rem; height:2.4rem; object-fit:contain; animation:supplier-rfqs-spin 8s linear infinite; }
    .supplier-rfqs-eyebrow { color:#188ae2; font-size:.68rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
    .supplier-rfqs-header h1 { margin:.12rem 0 .22rem; color:#20354a; font-size:1.25rem; font-weight:700; }
    .supplier-rfqs-header p { color:#718096; font-size:.84rem; }
    .supplier-rfqs-count { margin-left:auto; padding:.35rem .6rem; color:#1269ac; background:#eaf6ff; border-radius:999px; font-size:.75rem; font-weight:700; white-space:nowrap; }
    .supplier-rfqs-list { overflow:hidden; border:1px solid #e2e9f0; border-radius:.85rem; box-shadow:0 .25rem .9rem rgba(35,79,119,.04); }
    .supplier-rfq-row { display:flex; align-items:center; gap:1rem; padding:1rem 1.15rem; border-bottom:1px solid #edf1f5; transition:background .18s ease, transform .18s ease; }
    .supplier-rfq-row:last-child { border-bottom:0; }
    .supplier-rfq-row:hover { background:#f7fbff; transform:translateY(-1px); }
    .supplier-rfq-icon { display:inline-flex; align-items:center; justify-content:center; width:2.45rem; height:2.45rem; color:#188ae2; background:#eaf6ff; border-radius:.7rem; font-size:1.15rem; }
    .supplier-rfq-main { flex:1; min-width:0; }
    .supplier-rfq-main h2 { margin:0; color:#2b4258; font-size:.95rem; font-weight:700; }
    .supplier-rfq-main p { margin:.2rem 0; overflow:hidden; color:#68798d; font-size:.82rem; text-overflow:ellipsis; white-space:nowrap; }
    .supplier-rfq-meta, .supplier-rfq-deadline small { color:#8493a3; font-size:.72rem; }
    .supplier-rfq-deadline { display:flex; flex-direction:column; min-width:6.7rem; padding-left:1rem; border-left:1px solid #e8eef3; }
    .supplier-rfq-deadline strong { color:#50657b; font-size:.8rem; }
    .supplier-rfq-action { min-width:5.75rem; }
    .supplier-rfqs-empty { padding:4rem 1.5rem; color:#738196; text-align:center; }
    .supplier-rfqs-empty > i { display:block; margin-bottom:.7rem; color:#b5c4d1; font-size:2.3rem; }
    .supplier-rfqs-empty h2 { color:#52697e; font-size:1rem; font-weight:700; }
    @keyframes supplier-rfqs-enter { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }
    @keyframes supplier-rfqs-spin { to { transform:rotate(360deg); } }
    @media (max-width: 767.98px) { .supplier-rfqs-header { align-items:flex-start; flex-wrap:wrap; } .supplier-rfqs-count { margin-left:3.4rem; } .supplier-rfq-row { align-items:flex-start; flex-wrap:wrap; } .supplier-rfq-main { min-width:calc(100% - 3.5rem); } .supplier-rfq-deadline { margin-left:3.5rem; padding-left:0; border-left:0; } .supplier-rfq-action { margin-left:auto; } }
    @media (prefers-reduced-motion: reduce) { .supplier-rfqs-header, .supplier-rfqs-logo { animation:none; } .supplier-rfq-row { transition:none; } .supplier-rfq-row:hover { transform:none; } }
</style>
@endpush
