{{-- resources/views/partials/flash.blade.php --}}
@php
    // Las vistas que muestran sus propios errores junto a cada campo definen
    // @section('hide_global_errors') para no repetir la lista aquí.
    $showValidationErrors = $errors->any() && ! $__env->hasSection('hide_global_errors');
@endphp
@if (session()->has('success') ||
        session()->has('error') ||
        session()->has('warning') ||
        session()->has('danger') ||
        session()->has('info') ||
        $showValidationErrors)
    {{-- Fallback Bootstrap (si no hay SweetAlert) --}}
    <div class="d-none container mt-2" id="flash-fallback">
        @foreach (['success', 'warning', 'danger', 'error', 'info'] as $lvl)
            @if (session($lvl))
                <div class="alert alert-{{ $lvl === 'error' ? 'danger' : $lvl }} mb-2">
                    {!! session($lvl) !!}
                </div>
            @endif
        @endforeach
        @if ($showValidationErrors)
            <div class="alert alert-danger">
                <strong>Hubo errores:</strong>
                <ul class="mb-0">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif

@push('scripts')
    <script>
        (function() {
            const hasSwal = typeof window.Swal !== 'undefined';
            const payload = {
                success: @json(session('success')),
                error: @json(session('error')),
                warning: @json(session('warning')),
                danger: @json(session('danger')),
                info: @json(session('info')),
                errors: @json($showValidationErrors ? $errors->all() : []),
            };

            if (hasSwal) {
                const show = (icon, title, text) => {
                    window.Swal.fire({
                        icon,
                        title,
                        html: text,
                        timer: 3200,
                        showConfirmButton: false,
                        timerProgressBar: true
                    });
                };
                if (payload.success) show('success', 'Listo', payload.success);
                if (payload.error) show('error', 'Error', payload.error);
                if (payload.warning) show('warning', 'Atención', payload.warning);
                if (payload.danger) show('error', 'Error', payload.danger);
                if (payload.info) show('info', 'Info', payload.info);
                if (payload.errors && payload.errors.length) {
                    show('error', 'Validación', '<ul style="text-align:left;margin:0;padding-left:18px;">' + payload
                        .errors.map(e => `<li>${e}</li>`).join('') + '</ul>');
                }
            } else {
                // Muestra fallback Bootstrap si no hay SweetAlert2 cargado
                const fb = document.getElementById('flash-fallback');
                if (fb) fb.classList.remove('d-none');
            }
        })();
    </script>
@endpush
