@php
    $unassigned = $requisition->items()
        ->whereDoesntHave('quotationGroups', fn ($query) => $query->active())
        ->with('productService', 'expenseCategory')
        ->get();
    $quotationGroups = $requisition->quotationGroups()
        ->active()
        ->with('items.productService', 'items.expenseCategory')
        ->get();
@endphp

<section class="tg-workspace" aria-labelledby="planner-title">
    <header class="tg-workspace-intro">
        <div>
            <span class="tg-section-kicker">Configuración de solicitudes</span>
            <h3 id="planner-title">Organiza las partidas que se cotizarán juntas</h3>
            <p>Une productos que deben recibir la misma solicitud. Puedes seleccionar varias partidas o arrastrarlas a un grupo.</p>
        </div>
        <div class="tg-metric-grid" aria-label="Avance de agrupación">
            <div><span>Grupos</span><strong id="groupsCount">{{ $quotationGroups->count() }}</strong></div>
            <div><span>Agrupadas</span><strong class="text-success" id="assignedItemsCount">{{ $requisition->items->count() - $unassigned->count() }}</strong></div>
            <div><span>Pendientes</span><strong class="text-warning" id="unassignedItemsCount">{{ $unassigned->count() }}</strong></div>
        </div>
    </header>

    <div class="tg-planner-layout">
        <aside class="tg-item-bank">
            <div class="tg-panel-heading">
                <div><span class="tg-panel-eyebrow">Origen</span><h4>Partidas pendientes</h4></div>
                <span class="tg-counter">{{ $unassigned->count() }}</span>
            </div>
            <div class="tg-bulk-actions">
                <button type="button" class="btn btn-sm btn-light" id="selectAllItems"><i class="ti ti-checks me-1"></i> Todas</button>
                <button type="button" class="btn btn-sm btn-light" id="deselectAllItems">Limpiar</button>
                <button type="button" class="btn btn-sm btn-primary ms-auto" id="addSelectedToGroup" disabled><i class="ti ti-folder-plus me-1"></i><span id="selectedCountText">Crear grupo</span></button>
            </div>
            <div id="unassignedItemsList" class="tg-item-list">
                @forelse($unassigned as $item)
                    <article class="item-card tg-item-card" data-item-id="{{ $item->id }}" draggable="true">
                        <label class="tg-item-select" for="item-{{ $item->id }}"><input class="form-check-input item-checkbox" type="checkbox" value="{{ $item->id }}" id="item-{{ $item->id }}"><span class="visually-hidden">Seleccionar {{ $item->productService?->short_name ?? $item->description }}</span></label>
                        <div class="tg-item-content"><strong class="item-name">{{ $item->productService?->short_name ?? $item->description }}</strong><span>{{ number_format($item->quantity, 3) }} {{ $item->unit }}</span><small>{{ $item->expenseCategory?->name ?? 'Sin categoría' }}</small></div>
                        <i class="ti ti-grip-vertical tg-grip" aria-hidden="true"></i>
                    </article>
                @empty
                    <div class="tg-planner-ready" role="status">
                        <span class="tg-planner-ready-mark"><i class="ti ti-circle-check"></i></span>
                        <strong>Paquetes listos</strong>
                        <span>Todas las partidas ya están agrupadas.</span>
                        <small><i class="ti ti-arrow-right"></i> Continúa a proveedores para configurar invitaciones.</small>
                    </div>
                @endforelse
            </div>
        </aside>

        <div class="tg-groups-board">
            <div class="tg-panel-heading"><div><span class="tg-panel-eyebrow">Destino</span><h4>Solicitudes a preparar</h4></div><span class="tg-counter" id="groupsCountBadge">{{ $quotationGroups->count() }}</span></div>
            <div id="groupsList" class="tg-groups-list">
                @forelse($quotationGroups as $group)
                    <article class="group-card tg-quote-group" data-group-id="{{ $group->id }}">
                        <header><div><span class="tg-group-label">Solicitud de cotización</span><h5 class="group-name-display">{{ $group->name }}</h5></div><div class="d-flex align-items-center gap-2"><span class="tg-counter">{{ $group->items->count() }} partidas</span><button type="button" class="btn btn-sm btn-light text-danger delete-group-btn" data-group-id="{{ $group->id }}" aria-label="Eliminar grupo {{ $group->name }}"><i class="ti ti-trash"></i></button></div></header>
                        <div class="group-items-drop-zone tg-group-items" data-group-id="{{ $group->id }}">
                            @foreach($group->items as $item)
                                <div class="group-item-mini tg-group-item" data-item-id="{{ $item->id }}"><span>{{ $item->productService?->short_name ?? $item->description }}</span><small>{{ number_format($item->quantity, 3) }} {{ $item->unit }}</small><button type="button" class="btn btn-sm btn-link text-danger remove-item-btn" data-item-id="{{ $item->id }}" data-group-id="{{ $group->id }}" aria-label="Quitar partida"><i class="ti ti-x"></i></button></div>
                            @endforeach
                        </div>
                    </article>
                @empty
                    <div class="tg-board-empty"><i class="ti ti-folders"></i><h5>Aún no hay grupos</h5><p>Arrastra una partida aquí o selecciona varias para crear la primera solicitud.</p></div>
                @endforelse
            </div>
            <button type="button" id="newGroupDropZone" class="drop-zone tg-new-group-zone"><i class="ti ti-folder-plus"></i><span>Suelta partidas aquí para crear una nueva solicitud</span></button>
        </div>
    </div>
</section>

<style>
    .tg-workspace { color:#24364b; }.tg-workspace-intro { display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; margin-bottom:1.25rem; }.tg-section-kicker,.tg-panel-eyebrow,.tg-group-label { display:block; color:#188ae2; font-size:.7rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; }.tg-workspace h3 { margin:.25rem 0; font-size:1.1rem; }.tg-workspace-intro p { margin:0; color:#718096; font-size:.86rem; }.tg-metric-grid { display:flex; min-width:300px; border:1px solid #e2e9f0; border-radius:.7rem; background:#fff; }.tg-metric-grid > div { flex:1; padding:.6rem .75rem; text-align:center; border-right:1px solid #e2e9f0; }.tg-metric-grid > div:last-child { border:0; }.tg-metric-grid span { display:block; color:#718096; font-size:.68rem; }.tg-metric-grid strong { font-size:1.1rem; }.tg-planner-layout { display:grid; grid-template-columns:minmax(280px,.75fr) minmax(0,1.25fr); gap:1rem; }.tg-item-bank,.tg-groups-board { border:1px solid #e2e9f0; border-radius:.75rem; background:#fff; }.tg-panel-heading { display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding:1rem; border-bottom:1px solid #e2e9f0; }.tg-panel-heading h4 { margin:.1rem 0 0; font-size:.95rem; }.tg-counter { display:inline-flex; justify-content:center; min-width:1.6rem; padding:.2rem .45rem; border-radius:2rem; color:#1269ac; background:#eaf6ff; font-size:.72rem; font-weight:700; }.tg-bulk-actions { display:flex; gap:.4rem; align-items:center; padding:.75rem; border-bottom:1px solid #eef2f6; }.tg-item-list,.tg-groups-list { max-height:430px; overflow:auto; padding:.75rem; }.tg-item-card { display:grid; grid-template-columns:auto 1fr auto; gap:.7rem; align-items:flex-start; margin-bottom:.55rem; padding:.75rem; border:1px solid #e2e9f0; border-radius:.6rem; background:#fff; cursor:grab; }.tg-item-card:hover,.tg-item-card.selected { border-color:#9dd4f5; background:#f7fbff; box-shadow:0 4px 12px rgba(28,80,120,.08); }.tg-item-content strong,.tg-item-content span,.tg-item-content small { display:block; }.tg-item-content strong { font-size:.84rem; }.tg-item-content span { color:#526274; margin-top:.2rem; font-size:.78rem; }.tg-item-content small { margin-top:.3rem; color:#718096; font-size:.7rem; }.tg-grip { color:#a0aec0; }.tg-quote-group { margin-bottom:.75rem; overflow:hidden; border:1px solid #e2e9f0; border-radius:.65rem; }.tg-quote-group header { display:flex; justify-content:space-between; align-items:center; gap:.75rem; padding:.75rem 1rem; background:#f7fbff; }.tg-quote-group h5 { margin:.15rem 0 0; font-size:.9rem; }.tg-group-items { min-height:72px; padding:.5rem; }.tg-group-item { display:grid; grid-template-columns:1fr auto auto; align-items:center; gap:.5rem; padding:.5rem; border-bottom:1px solid #eef2f6; font-size:.8rem; }.tg-group-item:last-child { border-bottom:0; }.tg-group-item small { color:#718096; }.tg-new-group-zone { display:flex; align-items:center; justify-content:center; gap:.5rem; width:calc(100% - 1.5rem); min-height:72px; margin:.75rem; border:1px dashed #9dcff0; border-radius:.6rem; color:#1269ac; background:#f7fbff; font-size:.82rem; }.tg-empty-state,.tg-board-empty { display:flex; min-height:160px; align-items:center; justify-content:center; flex-direction:column; gap:.4rem; color:#718096; text-align:center; font-size:.82rem; }.tg-empty-state i,.tg-board-empty i { color:#4bd396; font-size:1.8rem; }.tg-board-empty h5,.tg-board-empty p { margin:0; }.tg-board-empty p { max-width:260px; }
    .tg-planner-ready { position:relative; display:flex; min-height:250px; flex-direction:column; align-items:center; justify-content:center; gap:.4rem; overflow:hidden; padding:1.25rem; color:#2b7d58; text-align:center; }.tg-planner-ready::before { position:absolute; width:116px; height:116px; border:1px solid rgba(75,211,150,.32); border-radius:50%; content:""; animation:tg-ready-ring 2.1s ease-out infinite; }.tg-planner-ready-mark { position:relative; z-index:1; display:inline-flex; align-items:center; justify-content:center; width:3.25rem; height:3.25rem; margin-bottom:.35rem; border-radius:50%; color:#218b64; background:#e8fbf2; box-shadow:0 0 0 7px rgba(75,211,150,.1); font-size:1.55rem; animation:tg-ready-check 1.7s ease-in-out infinite; }.tg-planner-ready strong { position:relative; z-index:1; font-size:.95rem; }.tg-planner-ready > span:not(.tg-planner-ready-mark) { position:relative; z-index:1; color:#5f7b6d; font-size:.78rem; }.tg-planner-ready small { position:relative; z-index:1; margin-top:.45rem; padding:.35rem .55rem; border-radius:999px; color:#218b64; background:#f0fcf6; font-size:.7rem; font-weight:700; }.tg-planner-ready small i { margin-right:.18rem; }.tg-planner-ready::after { position:absolute; bottom:20%; left:20%; width:60%; height:2px; background:#4bd396; content:""; opacity:.35; animation:tg-ready-line 1.8s ease-in-out infinite; }.tg-item-bank:has(.tg-planner-ready) { border-color:#b9ead0; background:#fbfffc; }.tg-item-bank:has(.tg-planner-ready) .tg-panel-heading { border-bottom-color:#dff4e9; }
    @keyframes tg-ready-ring { 0% { opacity:.75; transform:scale(.45); } 75%,100% { opacity:0; transform:scale(1.3); } } @keyframes tg-ready-check { 0%,100% { transform:translateY(0) scale(1); } 50% { transform:translateY(-4px) scale(1.06); } } @keyframes tg-ready-line { 0%,100% { opacity:.15; transform:scaleX(.6); } 50% { opacity:.75; transform:scaleX(1); } }
    @media (max-width:991.98px) { .tg-workspace-intro { flex-direction:column; }.tg-metric-grid { width:100%; min-width:0; }.tg-planner-layout { grid-template-columns:1fr; } }
</style>
