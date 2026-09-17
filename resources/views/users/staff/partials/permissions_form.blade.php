<div class="modal-header">
    <h5 class="modal-title">
        <i class="ti ti-key me-2 text-primary"></i> Permisos adicionales: {{ $user->name }}
    </h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">
    <div id="formErrors" class="d-none"></div>
    <div class="alert alert-info py-2 small">
        <i class="ti ti-info-circle me-1"></i>
        Estos permisos sólo agregan accesos individuales. Los permisos heredados por sus roles no se pueden retirar desde aquí.
    </div>

    <form id="userForm" action="{{ route('users.permissions.update', $user) }}" method="POST" data-form-type="permissions">
        @csrf
        @method('PATCH')

        <div class="row g-3">
            @foreach($viewPermissions as $category => $definitions)
                <div class="col-12 col-lg-6">
                    <div class="border rounded p-3 h-100">
                        <div class="text-uppercase text-muted fw-semibold small mb-2">{{ $category }}</div>
                        @foreach($definitions as $definition)
                            @php
                                $permission = $definition['permission'];
                                $inRole = $rolePermissions->contains($permission);
                                $direct = $directPermissions->contains($permission);
                            @endphp
                            <label class="d-flex align-items-start gap-2 mb-3 small">
                                <input class="form-check-input mt-1" type="checkbox" name="permissions[]"
                                    value="{{ $permission }}"
                                    @checked($direct || $inRole)
                                    @disabled($inRole)>
                                <span>
                                    <strong>{{ $definition['label'] }}</strong>
                                    @if($inRole)
                                        <span class="badge text-bg-light border ms-1">Por rol</span>
                                    @elseif($direct)
                                        <span class="badge text-bg-info ms-1">Directo</span>
                                    @endif
                                    <br><span class="text-muted">{{ $definition['description'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Guardar permisos</button>
        </div>
    </form>
</div>
