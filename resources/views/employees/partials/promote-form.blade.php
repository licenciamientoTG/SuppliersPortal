@php
    $employeePhotoUrl = $employee->photo ? \Illuminate\Support\Facades\Storage::url($employee->photo) : null;
    $employeeMeta = collect([
        $employee->employee_number ? 'No. '.$employee->employee_number : null,
        $employee->job_title,
        $employee->company,
    ])->filter()->implode(' · ');
@endphp

<div class="modal-header">
    <div class="d-flex align-items-center gap-3 min-w-0">
        @if ($employeePhotoUrl)
            <img src="{{ $employeePhotoUrl }}" class="rounded-circle flex-shrink-0"
                 style="width:44px;height:44px;object-fit:cover;" alt="">
        @else
            <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center flex-shrink-0 fw-semibold"
                 style="width:44px;height:44px;" aria-hidden="true">
                {{ strtoupper(mb_substr($employee->first_name ?? '?', 0, 1)) }}
            </div>
        @endif
        <div class="min-w-0">
            <h5 class="modal-title fw-semibold mb-0">Crear usuario para {{ $employee->full_name }}</h5>
            @if ($employeeMeta !== '')
                <p class="text-muted mb-0 text-truncate promote-meta">{{ $employeeMeta }}</p>
            @endif
        </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<form id="promoteForm"
      action="{{ route('employees.promote', $employee->id) }}"
      method="POST"
      enctype="multipart/form-data"
      novalidate>
    @csrf

    <div class="modal-body">

        {{-- ── Acceso ─────────────────────────────────────────────────── --}}
        <h6 class="promote-section">Acceso al portal</h6>

        <div class="row g-3 mb-4">
            <div class="col-12">
                <label for="promoteEmail" class="form-label mb-1">Correo electrónico <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="email" name="email" id="promoteEmail" class="form-control"
                       value="{{ $employee->email }}"
                       autocomplete="off" inputmode="email" required
                       aria-describedby="promoteEmailHelp">
                <div class="invalid-feedback" id="err-email"></div>
                <div class="form-text" id="promoteEmailHelp">Debe ser un dominio corporativo del grupo TotalGas. Aquí llegará el correo de bienvenida.</div>
            </div>

            <div class="col-12">
                <label for="promotePassword" class="form-label mb-1">Contraseña <span class="text-danger" aria-hidden="true">*</span></label>
                <div class="input-group has-validation">
                    <input type="password" name="password" id="promotePassword" class="form-control promote-password"
                           required minlength="8" autocomplete="new-password"
                           spellcheck="false" autocapitalize="off"
                           aria-describedby="promotePasswordHelp">
                    <button type="button" class="btn btn-outline-secondary" id="promotePasswordToggle"
                            aria-label="Mostrar contraseña" aria-pressed="false" aria-controls="promotePassword"
                            data-bs-toggle="tooltip" title="Mostrar contraseña">
                        <i class="ti ti-eye" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="promotePasswordGenerate">
                        <i class="ti ti-refresh me-1" aria-hidden="true"></i>Generar
                    </button>
                    <div class="invalid-feedback" id="err-password"></div>
                </div>
                <div class="form-text" id="promotePasswordHelp">
                    <span id="promotePasswordCounter">Mínimo 8 caracteres.</span>
                    Se enviará al empleado en el correo de bienvenida.
                </div>
            </div>
        </div>

        {{-- ── Datos del usuario ──────────────────────────────────────── --}}
        <h6 class="promote-section">Datos del usuario</h6>

        <div class="row g-3 mb-4">
            <div class="col-12">
                <label for="promoteName" class="form-label mb-1">Nombre completo <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="name" id="promoteName" class="form-control"
                       value="{{ $employee->full_name }}" maxlength="180"
                       autocomplete="off" required>
                <div class="invalid-feedback" id="err-name"></div>
            </div>
            <div class="col-md-6">
                <label for="promotePhone" class="form-label mb-1">Teléfono</label>
                <input type="tel" name="phone" id="promotePhone" class="form-control"
                       value="{{ $employee->phone }}" maxlength="30"
                       autocomplete="off" inputmode="tel">
                <div class="invalid-feedback" id="err-phone"></div>
            </div>
            <div class="col-md-6">
                <label for="promoteJobTitle" class="form-label mb-1">Puesto</label>
                <input type="text" name="job_title" id="promoteJobTitle" class="form-control"
                       value="{{ $employee->job_title }}" maxlength="120"
                       autocomplete="off">
                <div class="invalid-feedback" id="err-job_title"></div>
            </div>

            <div class="col-12">
                <span class="form-label d-block mb-1" id="promoteAvatarLabel">Foto de perfil</span>
                <div class="promote-avatar" id="avatarDropZone">
                    <img id="avatarPreviewPromote"
                         src="{{ $employeePhotoUrl ?? '' }}"
                         class="rounded-circle flex-shrink-0 {{ $employeePhotoUrl ? '' : 'd-none' }}"
                         style="width:56px;height:56px;object-fit:cover;"
                         alt="">
                    <div id="avatarDropIcon"
                         class="promote-avatar__placeholder rounded-circle flex-shrink-0 {{ $employeePhotoUrl ? 'd-none' : '' }}"
                         aria-hidden="true">
                        <i class="ti ti-camera"></i>
                    </div>
                    <div class="min-w-0">
                        <label for="avatarInputPromote" class="btn btn-sm btn-outline-primary mb-1">
                            <i class="ti ti-upload me-1" aria-hidden="true"></i><span id="avatarActionText">{{ $employeePhotoUrl ? 'Cambiar foto' : 'Subir foto' }}</span>
                        </label>
                        <div class="form-text mt-0" id="avatarHelpText">
                            @if ($employeePhotoUrl)
                                Se usará la foto del catálogo de empleados. También puedes arrastrar otra aquí.
                            @else
                                Opcional. Arrastra una imagen aquí o elígela. JPG, PNG o WEBP, máx. 2 MB.
                            @endif
                        </div>
                    </div>
                    <input type="file" name="avatar" id="avatarInputPromote"
                           accept="image/png,image/jpeg,image/webp"
                           class="visually-hidden"
                           aria-labelledby="promoteAvatarLabel"
                           aria-describedby="avatarHelpText">
                </div>
                <div class="invalid-feedback d-block" id="err-avatar"></div>
            </div>
        </div>

        {{-- ── Roles ──────────────────────────────────────────────────── --}}
        <fieldset>
            <legend class="promote-section">Roles</legend>
            <p class="form-text mt-0 mb-2">Definen qué módulos verá el usuario. Puedes cambiarlos después en Usuarios.</p>
            <div class="row g-2">
                @foreach($roles as $role)
                    @php($roleLabel = \Illuminate\Support\Facades\Lang::has("roles.{$role->name}") ? __("roles.{$role->name}") : $role->name)
                    <div class="col-sm-6 col-lg-4">
                        <input class="btn-check" type="checkbox"
                               name="roles[]" value="{{ $role->name }}"
                               id="role_{{ $role->id }}" autocomplete="off">
                        <label class="btn btn-outline-secondary w-100 text-start promote-role" for="role_{{ $role->id }}">
                            <i class="ti ti-check promote-role__check" aria-hidden="true"></i>{{ $roleLabel }}
                        </label>
                    </div>
                @endforeach
            </div>
            <div class="invalid-feedback d-block" id="err-roles"></div>
        </fieldset>

        {{-- Errores generales --}}
        <div id="promoteGeneralError" class="alert alert-danger mt-3 mb-0 d-none" role="alert"></div>

    </div>

    <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="promoteSubmitBtn">
            <i class="ti ti-user-plus me-1"></i>Crear usuario
        </button>
    </div>
</form>

<style>
    #promoteModalContent .min-w-0 { min-width: 0; }
    #promoteModalContent .promote-meta { font-size: 13px; }
    #promoteModalContent .promote-section {
        font-size: 14px;
        font-weight: 600;
        margin: 0 0 .75rem;
        padding: 0;
        float: none;
        width: auto;
    }
    #promoteModalContent .promote-password { font-variant-numeric: tabular-nums; letter-spacing: .02em; }
    #promoteModalContent .promote-avatar {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: .75rem;
        border: 1px dashed var(--bs-border-color);
        border-radius: var(--bs-border-radius);
        transition: border-color .15s ease-out, background-color .15s ease-out;
    }
    #promoteModalContent .promote-avatar.is-dragover {
        border-color: var(--bs-primary);
        background-color: var(--bs-primary-bg-subtle);
    }
    #promoteModalContent .promote-avatar:focus-within {
        border-color: var(--bs-primary);
    }
    #promoteModalContent .promote-avatar__placeholder {
        width: 56px;
        height: 56px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        color: var(--bs-secondary-color);
        background-color: var(--bs-tertiary-bg);
    }
    #promoteModalContent .promote-role {
        font-weight: 400;
        display: flex;
        align-items: center;
        gap: .5rem;
    }
    #promoteModalContent .promote-role__check { visibility: hidden; }
    #promoteModalContent .btn-check:checked + .promote-role .promote-role__check { visibility: visible; }
</style>

<script>
(function () {
    // ── Contraseña: mostrar/ocultar, generar y contador ─────────────────
    const password  = document.getElementById('promotePassword');
    const toggle    = document.getElementById('promotePasswordToggle');
    const generate  = document.getElementById('promotePasswordGenerate');
    const counter   = document.getElementById('promotePasswordCounter');

    function setVisible(visible) {
        password.type = visible ? 'text' : 'password';
        const label = visible ? 'Ocultar contraseña' : 'Mostrar contraseña';
        toggle.setAttribute('aria-pressed', visible ? 'true' : 'false');
        toggle.setAttribute('aria-label', label);
        toggle.setAttribute('data-bs-original-title', label);
        toggle.querySelector('i').className = visible ? 'ti ti-eye-off' : 'ti ti-eye';
    }

    function updateCounter() {
        const length = password.value.length;
        if (length === 0) {
            counter.textContent = 'Mínimo 8 caracteres.';
            counter.className = '';
        } else if (length < 8) {
            counter.textContent = `Faltan ${8 - length} caracteres.`;
            counter.className = 'text-warning-emphasis';
        } else {
            counter.textContent = `${length} caracteres.`;
            counter.className = 'text-success';
        }
    }

    toggle.addEventListener('click', () => {
        setVisible(password.type === 'password');
        password.focus();
    });

    generate.addEventListener('click', () => {
        // Sin caracteres ambiguos (0/O, 1/l/I) para que sea fácil de dictar o copiar.
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%*?';
        const bytes = new Uint32Array(12);
        crypto.getRandomValues(bytes);
        password.value = Array.from(bytes, b => chars[b % chars.length]).join('');
        password.classList.remove('is-invalid');
        setVisible(true);
        updateCounter();
        password.focus();
        password.select();
    });

    password.addEventListener('input', updateCounter);

    if (window.bootstrap && bootstrap.Tooltip) {
        bootstrap.Tooltip.getOrCreateInstance(toggle);
    }

    // ── Foto: arrastrar, elegir y validar en cliente ────────────────────
    const dropZone   = document.getElementById('avatarDropZone');
    const fileInput  = document.getElementById('avatarInputPromote');
    const preview    = document.getElementById('avatarPreviewPromote');
    const icon       = document.getElementById('avatarDropIcon');
    const helpText   = document.getElementById('avatarHelpText');
    const actionText = document.getElementById('avatarActionText');
    const avatarErr  = document.getElementById('err-avatar');
    const allowed    = ['image/png', 'image/jpeg', 'image/webp'];

    function showPreview(file) {
        avatarErr.textContent = '';
        if (!file) return;

        if (!allowed.includes(file.type)) {
            avatarErr.textContent = 'El archivo debe ser una imagen JPG, PNG o WEBP.';
            fileInput.value = '';
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            avatarErr.textContent = 'La imagen pesa más de 2 MB. Elige una más ligera.';
            fileInput.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.classList.remove('d-none');
            icon.classList.add('d-none');
            actionText.textContent = 'Cambiar foto';
            helpText.textContent = `${file.name} · se usará como foto de perfil.`;
        };
        reader.readAsDataURL(file);
    }

    fileInput.addEventListener('change', () => showPreview(fileInput.files[0]));
    dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('is-dragover'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('is-dragover'));
    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.classList.remove('is-dragover');
        const file = e.dataTransfer.files[0];
        if (file) {
            const dt = new DataTransfer();
            dt.items.add(file);
            fileInput.files = dt.files;
            showPreview(file);
        }
    });
})();
</script>
