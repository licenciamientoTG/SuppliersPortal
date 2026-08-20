<div class="dropdown">
    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="ti ti-dots-vertical"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <li>
            <a class="dropdown-item" href="{{ route('users.staff.show', $user) }}">
                <i class="ti ti-eye me-2 text-secondary"></i> Ver
            </a>
        </li>
        <li>
            <a class="dropdown-item js-open-user-modal" href="#" data-url="{{ route('users.edit', $user) }}">
                <i class="ti ti-pencil me-2 text-primary"></i> Editar
            </a>
        </li>
        <li>
            <a class="dropdown-item js-open-user-modal" href="#" data-url="{{ route('users.roles.edit', $user) }}">
                <i class="ti ti-shield-lock me-2 text-secondary"></i> Roles
            </a>
        </li>
        <li>
            <a class="dropdown-item js-open-user-modal" href="#" data-url="{{ route('users.companies.edit', $user) }}">
                <i class="ti ti-building me-2 text-secondary"></i> Empresas
            </a>
        </li>
        <li>
            <a class="dropdown-item js-open-cost-centers-modal"
                href="javascript:void(0)"
                data-url="{{ route('users.cost-centers.edit', $user->id) }}"
                data-has-companies="{{ $user->companies->isNotEmpty() ? 'true' : 'false' }}"
                data-user-name="{{ $user->name }}">
                <i class="ti ti-building-bank me-2 text-secondary"></i> Centros de Costo
            </a>
        </li>
        <li>
            <a class="dropdown-item js-open-user-modal" href="#" data-url="{{ route('users.products', $user) }}">
                <i class="ti ti-package me-2 text-secondary"></i> Productos/Servicios
            </a>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
            <a class="dropdown-item js-toggle-active" href="#" data-url="{{ route('users.toggle', $user) }}">
                @if($user->is_active)
                    <i class="ti ti-user-off me-2 text-warning"></i> Desactivar
                @else
                    <i class="ti ti-user-check me-2 text-success"></i> Activar
                @endif
            </a>
        </li>
        <li>
            <a class="dropdown-item text-danger js-delete-user" href="#"
                data-url="{{ route('users.destroy', $user) }}"
                data-name="{{ $user->name }}">
                <i class="ti ti-trash me-2"></i> Eliminar
            </a>
        </li>
    </ul>
</div>
