<div class="dashboard-hero card border-0 overflow-hidden mb-4">
    <div class="card-body p-4 p-xl-5">
        <div class="row g-4 align-items-center">
            <div class="col-xl-8">
                <span class="dashboard-eyebrow">{{ $dashboard['hero']['eyebrow'] ?? ($homeLabel ?? 'Inicio') }}</span>
                <h1 class="dashboard-title mt-2 mb-2">{{ $dashboard['hero']['title'] }}</h1>
                <p class="dashboard-subtitle mb-3">{{ $dashboard['hero']['subtitle'] }}</p>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge dashboard-user-badge px-3 py-2">
                        <i class="ti ti-user me-1"></i>{{ $dashboard['hero']['user_name'] }}
                    </span>
                    @if (! empty($dashboard['hero']['context_badge']))
                        <span class="badge dashboard-context-badge px-3 py-2">{{ $dashboard['hero']['context_badge'] }}</span>
                    @endif
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
