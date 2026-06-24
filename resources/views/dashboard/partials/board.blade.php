<div class="container-fluid py-4 dashboard-board">
    <div class="dashboard-hero card border-0 overflow-hidden mb-4">
        <div class="card-body p-4 p-xl-5">
            <div class="row g-4 align-items-center">
                <div class="col-xl-8">
                    <span class="dashboard-eyebrow">{{ $homeLabel ?? 'Dashboard' }}</span>
                    <h1 class="dashboard-title mt-2 mb-2">{{ $dashboard['hero']['title'] }}</h1>
                    <p class="dashboard-subtitle mb-3">{{ $dashboard['hero']['subtitle'] }}</p>

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge text-bg-light-subtle text-dark border border-light-subtle px-3 py-2">
                            <i class="ti ti-user me-1"></i>{{ $dashboard['hero']['user_name'] }}
                        </span>
                        @foreach (($dashboard['hero']['role_badges'] ?? []) as $badge)
                            <span class="badge dashboard-role-badge px-3 py-2">{{ $badge }}</span>
                        @endforeach
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="dashboard-notifications card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <div class="text-uppercase small fw-semibold text-muted">Notificaciones</div>
                                    <div class="fs-4 fw-bold">{{ $dashboard['hero']['notification_summary']['unread_count'] ?? 0 }}</div>
                                </div>
                                <a href="{{ $dashboard['hero']['notification_summary']['route'] ?? '#' }}" class="btn btn-sm btn-outline-primary">
                                    Ver todas
                                </a>
                            </div>

                            @forelse (($dashboard['hero']['notification_summary']['recent'] ?? []) as $notification)
                                <div class="dashboard-notification-item">
                                    <div class="fw-semibold">{{ $notification['text'] }}</div>
                                    <small class="text-muted">{{ $notification['time'] }}</small>
                                </div>
                            @empty
                                <div class="text-muted small">No hay notificaciones recientes.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach (($dashboard['quickActions'] ?? []) as $action)
            <div class="col-sm-6 col-lg-3">
                <a href="{{ $action['route'] }}" class="dashboard-action card border-0 h-100 tone-{{ $action['tone'] }}">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="dashboard-action-icon">
                            <i class="ti {{ $action['icon'] }}"></i>
                        </span>
                        <div>
                            <div class="fw-semibold">{{ $action['label'] }}</div>
                            <small class="text-muted">Acceso directo</small>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-4">
        @foreach (($dashboard['kpis'] ?? []) as $kpi)
            <div class="col-sm-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100 dashboard-kpi tone-{{ $kpi['tone'] }}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <div class="text-uppercase small fw-semibold text-muted">{{ $kpi['label'] }}</div>
                                <div class="display-6 fw-bold mb-0">{{ $kpi['value'] }}</div>
                            </div>
                            <span class="dashboard-kpi-icon">
                                <i class="ti {{ $kpi['icon'] }}"></i>
                            </span>
                        </div>
                        <p class="text-muted mb-0">{{ $kpi['description'] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if (!empty($dashboard['alerts']))
        <div class="row g-3 mb-4">
            @foreach ($dashboard['alerts'] as $alert)
                <div class="col-12">
                    <div class="card border-0 shadow-sm dashboard-alert tone-{{ $alert['tone'] }}">
                        <div class="card-body d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-start align-items-lg-center">
                            <div>
                                <div class="fw-bold mb-1">{{ $alert['title'] }}</div>
                                <div class="text-muted">{{ $alert['body'] }}</div>
                            </div>
                            @if (!empty($alert['route']))
                                <a href="{{ $alert['route'] }}" class="btn btn-sm btn-outline-dark">
                                    {{ $alert['route_label'] ?? 'Ver mas' }}
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="row g-4">
        @foreach (($dashboard['sections'] ?? []) as $section)
            <div class="col-12 col-xxl-6">
                <div class="card border-0 shadow-sm h-100 dashboard-section">
                    <div class="card-header bg-transparent border-0 pb-0">
                        <div class="d-flex align-items-center gap-2">
                            <span class="dashboard-section-icon">
                                <i class="ti {{ $section['icon'] }}"></i>
                            </span>
                            <h5 class="mb-0">{{ $section['title'] }}</h5>
                        </div>
                    </div>

                    <div class="card-body">
                        @if (!empty($section['items']))
                            <div class="dashboard-list">
                                @foreach ($section['items'] as $item)
                                    <div class="dashboard-list-item">
                                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                                            <div>
                                                <div class="fw-semibold">{{ $item['title'] }}</div>
                                                @if (!empty($item['subtitle']))
                                                    <div class="text-muted">{{ $item['subtitle'] }}</div>
                                                @endif
                                                @if (!empty($item['meta']))
                                                    <small class="text-muted d-block mt-1">{{ $item['meta'] }}</small>
                                                @endif
                                            </div>

                                            <div class="d-flex flex-column align-items-lg-end gap-2">
                                                @if (!empty($item['badge']))
                                                    <span class="badge text-bg-{{ $item['badge_tone'] ?? 'secondary' }}">
                                                        {{ $item['badge'] }}
                                                    </span>
                                                @endif

                                                @if (!empty($item['route']))
                                                    <a href="{{ $item['route'] }}" class="btn btn-sm btn-outline-primary">
                                                        {{ $item['route_label'] ?? 'Abrir' }}
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="dashboard-empty-state">
                                <i class="ti ti-layout-grid"></i>
                                <p class="mb-0">{{ $section['empty_message'] ?? 'Sin elementos por mostrar.' }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

@push('styles')
<style>
    .dashboard-board {
        --dash-primary: #0f3d75;
        --dash-primary-soft: #e8f0fb;
        --dash-surface: #f7f9fc;
        --dash-border: rgba(15, 61, 117, 0.12);
        --dash-shadow: 0 18px 50px rgba(15, 61, 117, 0.10);
    }

    .dashboard-hero {
        background:
            radial-gradient(circle at top right, rgba(255, 255, 255, 0.28), transparent 28%),
            linear-gradient(135deg, #0f3d75 0%, #145ca8 52%, #1e7cc2 100%);
        box-shadow: var(--dash-shadow);
        color: #fff;
    }

    .dashboard-eyebrow {
        display: inline-block;
        padding: 0.45rem 0.8rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.14);
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .dashboard-title {
        font-size: clamp(2rem, 3vw, 3rem);
        font-weight: 800;
        letter-spacing: -0.03em;
    }

    .dashboard-subtitle {
        max-width: 60ch;
        color: rgba(255, 255, 255, 0.82);
        font-size: 1rem;
    }

    .dashboard-role-badge {
        background: rgba(255, 255, 255, 0.18);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .dashboard-notifications {
        background: rgba(255, 255, 255, 0.96);
        border-radius: 1.25rem;
    }

    .dashboard-notification-item + .dashboard-notification-item {
        margin-top: 0.85rem;
        padding-top: 0.85rem;
        border-top: 1px solid rgba(15, 61, 117, 0.08);
    }

    .dashboard-action,
    .dashboard-kpi,
    .dashboard-alert,
    .dashboard-section {
        border-radius: 1.15rem;
    }

    .dashboard-action {
        text-decoration: none;
        color: inherit;
        box-shadow: 0 10px 25px rgba(15, 61, 117, 0.07);
        transition: transform 0.18s ease, box-shadow 0.18s ease;
        background: #fff;
    }

    .dashboard-action:hover {
        transform: translateY(-3px);
        box-shadow: 0 18px 35px rgba(15, 61, 117, 0.12);
    }

    .dashboard-action-icon,
    .dashboard-kpi-icon,
    .dashboard-section-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 1rem;
        width: 3rem;
        height: 3rem;
        font-size: 1.3rem;
    }

    .dashboard-action.tone-primary .dashboard-action-icon,
    .dashboard-kpi.tone-primary .dashboard-kpi-icon,
    .dashboard-alert.tone-primary {
        background: #e8f0fb;
        color: #145ca8;
    }

    .dashboard-action.tone-warning .dashboard-action-icon,
    .dashboard-kpi.tone-warning .dashboard-kpi-icon,
    .dashboard-alert.tone-warning {
        background: #fff3d6;
        color: #b7791f;
    }

    .dashboard-action.tone-info .dashboard-action-icon,
    .dashboard-kpi.tone-info .dashboard-kpi-icon,
    .dashboard-alert.tone-info {
        background: #dff5fb;
        color: #0f7c90;
    }

    .dashboard-action.tone-success .dashboard-action-icon,
    .dashboard-kpi.tone-success .dashboard-kpi-icon,
    .dashboard-alert.tone-success {
        background: #def7e8;
        color: #0f8c5a;
    }

    .dashboard-action.tone-danger .dashboard-action-icon,
    .dashboard-kpi.tone-danger .dashboard-kpi-icon,
    .dashboard-alert.tone-danger {
        background: #fde8ea;
        color: #be3b4f;
    }

    .dashboard-action.tone-secondary .dashboard-action-icon,
    .dashboard-kpi.tone-secondary .dashboard-kpi-icon,
    .dashboard-alert.tone-secondary {
        background: #edf1f7;
        color: #516074;
    }

    .dashboard-kpi {
        box-shadow: 0 12px 30px rgba(15, 61, 117, 0.08);
    }

    .dashboard-kpi .display-6 {
        line-height: 1;
    }

    .dashboard-list-item + .dashboard-list-item {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid rgba(15, 61, 117, 0.08);
    }

    .dashboard-empty-state {
        min-height: 160px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        color: #6b7280;
        background: linear-gradient(180deg, rgba(247, 249, 252, 0.4), rgba(255, 255, 255, 0.9));
        border: 1px dashed rgba(15, 61, 117, 0.15);
        border-radius: 1rem;
        text-align: center;
        padding: 1.5rem;
    }

    .dashboard-empty-state i {
        font-size: 2rem;
        color: #94a3b8;
    }

    @media (max-width: 767.98px) {
        .dashboard-title {
            font-size: 1.85rem;
        }

        .dashboard-subtitle {
            font-size: 0.95rem;
        }
    }
</style>
@endpush
