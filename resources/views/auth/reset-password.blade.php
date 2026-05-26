<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer contraseña — Portal de Proveedores</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', Arial, sans-serif;
        }

        body, html { height: 100%; overflow-x: hidden; }

        .dark-background {
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            background: linear-gradient(-45deg, #0a0a0a, #1a1a2e, #16213e, #0f1419);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            z-index: -3;
        }

        @keyframes gradientShift {
            0%   { background-position: 0% 50%; }
            50%  { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        #tsparticles {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: -2;
        }

        .main-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        .header {
            margin-bottom: 30px;
            text-align: center;
        }

        .header h1 {
            color: #ffffff;
            font-size: 32px;
            font-weight: 600;
            text-shadow: 0 0 20px rgba(169, 202, 72, 0.5);
            animation: titlePulse 3s ease-in-out infinite;
        }

        @keyframes titlePulse {
            0%, 100% { text-shadow: 0 0 20px rgba(169, 202, 72, 0.5); }
            50%       { text-shadow: 0 0 30px rgba(169, 202, 72, 0.8), 0 0 40px rgba(169, 202, 72, 0.3); }
        }

        /* Tarjeta principal */
        .reset-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 25px;
            width: 100%;
            max-width: 900px;
            display: flex;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
        }

        /* Columna izquierda: logo */
        .logo-section {
            flex: 1;
            background: rgba(255, 255, 255, 0.95);
            padding: 50px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16px;
            position: relative;
            text-align: center;
        }

        .logo-section::before {
            content: '';
            position: absolute;
            top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: conic-gradient(transparent, rgba(169, 202, 72, 0.1), transparent);
            animation: logoRotate 10s linear infinite;
        }

        @keyframes logoRotate {
            0%   { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .logo-section img {
            max-width: 80%;
            height: auto;
            position: relative;
            z-index: 1;
            filter: drop-shadow(0 10px 20px rgba(0, 0, 0, 0.1));
        }

        .logo-section .lock-icon {
            position: relative;
            z-index: 1;
            width: 54px;
            height: 54px;
            background: linear-gradient(135deg, #1a4b96, #2d5aa0);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 20px rgba(26, 75, 150, 0.35);
            margin-top: 8px;
        }

        .logo-section .lock-icon svg {
            width: 26px;
            height: 26px;
            fill: #A9CA48;
        }

        .logo-section .logo-caption {
            position: relative;
            z-index: 1;
            font-size: 13px;
            color: #666;
            font-weight: 400;
        }

        /* Columna derecha: formulario */
        .form-section {
            flex: 1;
            background: linear-gradient(135deg, rgba(26, 75, 150, 0.92), rgba(45, 90, 160, 0.92));
            backdrop-filter: blur(20px);
            padding: 50px;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .form-section::before {
            content: '';
            position: absolute;
            top: -2px; left: -2px; right: -2px; bottom: -2px;
            background: linear-gradient(45deg, transparent, rgba(169, 202, 72, 0.2), transparent);
            z-index: -1;
        }

        .form-title {
            margin-bottom: 6px;
        }

        .form-title h2 {
            color: #A9CA48;
            font-size: 26px;
            font-weight: 600;
            animation: welcomeGlow 2s ease-in-out infinite alternate;
        }

        @keyframes welcomeGlow {
            from { text-shadow: 0 0 10px rgba(169, 202, 72, 0.5); }
            to   { text-shadow: 0 0 20px rgba(169, 202, 72, 0.8); }
        }

        .form-subtitle {
            color: rgba(255, 255, 255, 0.7);
            font-size: 13px;
            margin-bottom: 28px;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.85);
            margin-bottom: 8px;
        }

        /* Input wrapper para el ícono del ojo */
        .input-wrapper {
            position: relative;
        }

        .modern-input {
            width: 100%;
            padding: 14px 20px;
            border: 2px solid rgba(169, 202, 72, 0.3);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            outline: none;
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }

        .modern-input.has-toggle {
            padding-right: 48px;
        }

        .modern-input:focus {
            border-color: #A9CA48;
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 25px rgba(169, 202, 72, 0.4);
            transform: translateY(-2px);
        }

        .modern-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        /* Email pre-llenado: legible pero sutilmente deshabilitado */
        .modern-input[readonly] {
            background: rgba(255, 255, 255, 0.07);
            color: rgba(255, 255, 255, 0.65);
            cursor: default;
        }

        /* Botón para mostrar/ocultar contraseña */
        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            color: rgba(255, 255, 255, 0.5);
            display: flex;
            align-items: center;
            transition: color 0.2s ease;
        }

        .toggle-password:hover { color: #A9CA48; }
        .toggle-password svg { width: 18px; height: 18px; }

        /* Mensajes de error */
        .error-message {
            background: rgba(255, 107, 107, 0.18);
            border: 1px solid rgba(255, 107, 107, 0.45);
            color: #ffb3b3;
            padding: 9px 12px;
            border-radius: 8px;
            margin-top: 8px;
            font-size: 13px;
        }

        /* Sección de acciones */
        .button-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-top: 28px;
        }

        .back-link {
            color: rgba(255, 255, 255, 0.75);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .back-link:hover {
            color: #A9CA48;
            text-shadow: 0 0 10px rgba(169, 202, 72, 0.5);
        }

        .reset-button {
            background: linear-gradient(45deg, #A9CA48, #7BC525);
            border: none;
            padding: 14px 32px;
            border-radius: 12px;
            color: #1a4b96;
            font-weight: 700;
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(169, 202, 72, 0.3);
            position: relative;
            overflow: hidden;
            letter-spacing: 0.3px;
        }

        .reset-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(169, 202, 72, 0.5);
        }

        .reset-button:active { transform: translateY(-1px); }

        /* Responsive */
        @media (max-width: 768px) {
            .reset-container { flex-direction: column; }
            .logo-section, .form-section { padding: 32px; }
            .header h1 { font-size: 22px; }
            .button-section { flex-direction: column; gap: 12px; }
            .reset-button { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="dark-background"></div>
    <div id="tsparticles"></div>

    <div class="main-container" id="mainContainer">
        <div class="header">
            <h1>Portal de Proveedores</h1>
        </div>

        <div class="reset-container" id="resetContainer">

            {{-- Columna izquierda: branding --}}
            <div class="logo-section">
                <img src="{{ asset('images/logos/logo_TotalGas_ver.png') }}" alt="TotalGas Logo">
                <div class="lock-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                    </svg>
                </div>
                <p class="logo-caption">Restablece tu acceso de forma segura</p>
            </div>

            {{-- Columna derecha: formulario --}}
            <div class="form-section">
                <div class="form-title">
                    <h2>Nueva contraseña</h2>
                </div>
                <p class="form-subtitle">
                    Elige una contraseña segura para proteger tu cuenta.
                </p>

                <form method="POST" action="{{ route('password.store') }}" id="resetForm" novalidate>
                    @csrf
                    <input type="hidden" name="token" value="{{ $request->route('token') }}">

                    {{-- Email (pre-llenado, readonly) --}}
                    <div class="form-group">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            class="modern-input"
                            value="{{ old('email', $request->email) }}"
                            readonly
                            autocomplete="username"
                        >
                        @error('email')
                            <div class="error-message">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Nueva contraseña --}}
                    <div class="form-group">
                        <label for="password" class="form-label">Nueva contraseña</label>
                        <div class="input-wrapper">
                            <input
                                id="password"
                                name="password"
                                type="password"
                                class="modern-input has-toggle"
                                placeholder="Mínimo 8 caracteres"
                                required
                                autocomplete="new-password"
                                autofocus
                            >
                            <button type="button" class="toggle-password" aria-label="Mostrar contraseña" data-target="password">
                                <svg id="eye-password" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                            </button>
                        </div>
                        <x-password-requirements input-id="password" confirm-id="password_confirmation" theme="dark" />
                        @error('password')
                            <div class="error-message">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Confirmar contraseña --}}
                    <div class="form-group">
                        <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
                        <div class="input-wrapper">
                            <input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                class="modern-input has-toggle"
                                placeholder="Repite la contraseña"
                                required
                                autocomplete="new-password"
                            >
                            <button type="button" class="toggle-password" aria-label="Mostrar confirmación" data-target="password_confirmation">
                                <svg id="eye-password_confirmation" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                            </button>
                        </div>
                        @error('password_confirmation')
                            <div class="error-message">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="button-section">
                        <a href="{{ route('login') }}" class="back-link">
                            ← Volver al inicio de sesión
                        </a>
                        <button type="submit" class="reset-button" id="resetBtn">
                            Restablecer contraseña
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/tsparticles@2/tsparticles.bundle.min.js"></script>
    <script>
        // tsParticles — idéntico al login
        tsParticles.load("tsparticles", {
            fpsLimit: 60,
            background: { color: { value: "transparent" } },
            particles: {
                number: { value: 120, density: { enable: true, area: 900 } },
                color: { value: ["#A9CA48", "#ffffff", "#4a8fd4", "#7BC525"] },
                shape: { type: "circle" },
                opacity: {
                    value: 0.5, random: true,
                    animation: { enable: true, speed: 1, minimumValue: 0.2, sync: false }
                },
                size: {
                    value: 3, random: true,
                    animation: { enable: true, speed: 2, minimumValue: 1, sync: false }
                },
                links: { enable: true, distance: 130, color: "#A9CA48", opacity: 0.25, width: 1 },
                move: { enable: true, speed: 0.8, direction: "none", random: true, straight: false, outModes: { default: "out" } }
            },
            interactivity: {
                detectsOn: "window",
                events: {
                    onHover: { enable: true, mode: ["repulse", "grab"] },
                    onClick:  { enable: true, mode: "push" }
                },
                modes: {
                    repulse: { distance: 160, duration: 0.6, speed: 1 },
                    grab: { distance: 200, links: { opacity: 0.6 } },
                    push: { quantity: 4 }
                }
            },
            detectRetina: true
        });

        // Parallax con el mouse
        document.addEventListener('mousemove', function(e) {
            const mouseX = (e.clientX / window.innerWidth)  - 0.5;
            const mouseY = (e.clientY / window.innerHeight) - 0.5;
            const c = document.getElementById('resetContainer');
            const h = document.querySelector('.header');
            c.style.transform = `perspective(1000px) rotateY(${mouseX * 5}deg) rotateX(${mouseY * -5}deg) translateZ(0)`;
            h.style.transform = `translateX(${mouseX * 20}px) translateY(${mouseY * 20}px)`;
        });

        // Animación de entrada
        window.addEventListener('load', function() {
            const c = document.getElementById('resetContainer');
            const h = document.querySelector('.header');
            h.style.opacity = '0';
            h.style.transform = 'translateY(-50px)';
            c.style.opacity = '0';
            c.style.transform = 'translateY(100px) scale(0.9)';
            setTimeout(() => {
                h.style.transition = 'all 1s cubic-bezier(0.25,0.46,0.45,0.94)';
                c.style.transition = 'all 1.2s cubic-bezier(0.25,0.46,0.45,0.94)';
                h.style.opacity = '1';
                h.style.transform = 'translateY(0)';
                c.style.opacity = '1';
                c.style.transform = 'translateY(0) scale(1)';
            }, 300);
        });

        // Toggle mostrar/ocultar contraseña
        document.querySelectorAll('.toggle-password').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const targetId = this.dataset.target;
                const input = document.getElementById(targetId);
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                const svg = document.getElementById('eye-' + targetId);
                svg.innerHTML = isPassword
                    ? '<path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/>'
                    : '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>';
            });
        });

        // Ripple en botón
        document.getElementById('resetBtn').addEventListener('click', function(e) {
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const ripple = document.createElement('span');
            ripple.style.cssText = `position:absolute;width:${size}px;height:${size}px;left:${e.clientX-rect.left-size/2}px;top:${e.clientY-rect.top-size/2}px;background:rgba(255,255,255,0.3);border-radius:50%;transform:scale(0);animation:ripple .6s linear;pointer-events:none;`;
            const style = document.createElement('style');
            style.textContent = '@keyframes ripple{to{transform:scale(4);opacity:0;}}';
            document.head.appendChild(style);
            this.appendChild(ripple);
            setTimeout(() => { ripple.remove(); style.remove(); }, 600);
        });

        // Focus/blur en inputs
        document.querySelectorAll('.modern-input:not([readonly])').forEach(function(input) {
            input.addEventListener('focus', function() { this.style.transform = 'translateY(-2px) scale(1.01)'; });
            input.addEventListener('blur',  function() { this.style.transform = 'translateY(0) scale(1)'; });
        });
    </script>
</body>
</html>
