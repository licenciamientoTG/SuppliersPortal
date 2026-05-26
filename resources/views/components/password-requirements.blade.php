{{--
  Componente: password-requirements
  Props:
    - input-id   : ID del campo de contraseña a observar (default: 'password')
    - confirm-id : ID del campo de confirmación (default: 'password_confirmation')
    - theme      : 'dark' (fondo azul, texto blanco) | 'light' (fondo claro, texto oscuro)

  Política:
    - Mínimo 10 caracteres
    - Al menos una mayúscula
    - Al menos una minúscula
    - Al menos un número
    - Al menos un símbolo especial
--}}
@props([
    'inputId'   => 'password',
    'confirmId' => 'password_confirmation',
    'theme'     => 'dark',
])

@php $uid = 'pwreq_' . Str::random(6); @endphp

<div id="{{ $uid }}" class="pw-req-box pw-req-{{ $theme }}" style="display:none;" aria-live="polite">
    <p class="pw-req-title">La contraseña debe tener:</p>
    <ul class="pw-req-list">
        <li data-rule="length">Mínimo 8 caracteres</li>
        <li data-rule="upper">Al menos una mayúscula (A–Z)</li>
        <li data-rule="lower">Al menos una minúscula (a–z)</li>
        <li data-rule="number">Al menos un número (0–9)</li>
        <li data-rule="symbol">Al menos un carácter especial (!&#64;#$%...)</li>
        @if($confirmId)
        <li data-rule="match">Las contraseñas coinciden</li>
        @endif
    </ul>
</div>

<style>
/* ── Base ─────────────────────────────────────────────────── */
.pw-req-box {
    margin-top: 10px;
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 13px;
    line-height: 1.5;
}
.pw-req-title {
    font-weight: 600;
    margin: 0 0 8px 0;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.pw-req-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.pw-req-list li {
    display: flex;
    align-items: center;
    gap: 8px;
    transition: color .2s ease;
}
.pw-req-list li::before {
    content: '✗';
    font-size: 12px;
    font-weight: 700;
    flex-shrink: 0;
    transition: content .2s, color .2s;
}
.pw-req-list li.ok::before { content: '✓'; }

/* ── Tema oscuro (auth pages) ─────────────────────────────── */
.pw-req-dark {
    background: rgba(0, 0, 0, 0.25);
    border: 1px solid rgba(255, 255, 255, 0.12);
}
.pw-req-dark .pw-req-title { color: rgba(255,255,255,0.6); }
.pw-req-dark .pw-req-list li         { color: rgba(255,255,255,0.55); }
.pw-req-dark .pw-req-list li::before { color: #ff6b6b; }
.pw-req-dark .pw-req-list li.ok      { color: rgba(255,255,255,0.9); }
.pw-req-dark .pw-req-list li.ok::before { color: #A9CA48; }

/* ── Tema claro (dashboard) ──────────────────────────────── */
.pw-req-light {
    background: #f8f9fa;
    border: 1px solid #e5e7eb;
}
.pw-req-light .pw-req-title { color: #666; }
.pw-req-light .pw-req-list li         { color: #888; }
.pw-req-light .pw-req-list li::before { color: #e53e3e; }
.pw-req-light .pw-req-list li.ok      { color: #2d3748; }
.pw-req-light .pw-req-list li.ok::before { color: #38a169; }
</style>

<script>
(function () {
    function init() {
        const BOX       = document.getElementById('{{ $uid }}');
        const inputEl   = document.getElementById('{{ $inputId }}');
        const confirmEl = document.getElementById('{{ $confirmId }}');

        if (!inputEl || !BOX) return;

        const rules = {
            length : v => v.length >= 8,
            upper  : v => /[A-Z]/.test(v),
            lower  : v => /[a-z]/.test(v),
            number : v => /[0-9]/.test(v),
            symbol : v => /[^A-Za-z0-9]/.test(v),
        };

        function evaluate() {
            const val = inputEl.value;
            BOX.style.display = val.length ? 'block' : 'none';

            let allOk = true;
            Object.entries(rules).forEach(([key, fn]) => {
                const li = BOX.querySelector(`[data-rule="${key}"]`);
                if (!li) return;
                const ok = fn(val);
                li.classList.toggle('ok', ok);
                if (!ok) allOk = false;
            });

            const matchLi = BOX.querySelector('[data-rule="match"]');
            if (matchLi && confirmEl) {
                const matches = val.length > 0 && val === confirmEl.value;
                matchLi.classList.toggle('ok', matches);
                if (!matches) allOk = false;
            }

            const form = inputEl.closest('form');
            if (form) {
                const btn = form.querySelector('[type="submit"]');
                if (btn) {
                    btn.disabled = val.length > 0 && !allOk;
                    btn.style.opacity = (val.length > 0 && !allOk) ? '0.5' : '';
                    btn.style.cursor  = (val.length > 0 && !allOk) ? 'not-allowed' : '';
                }
            }
        }

        inputEl.addEventListener('input', evaluate);
        if (confirmEl) confirmEl.addEventListener('input', evaluate);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
