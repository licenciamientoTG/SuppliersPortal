{{-- resources/views/auth/lock-screen.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pantalla de bloqueo - Portal de Proveedores</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', Arial, sans-serif;
        }

        body, html {
            height: 100%;
            overflow-x: hidden;
        }

        .dark-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: linear-gradient(-45deg, #0a0a0a, #1a1a2e, #16213e, #0f1419);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            z-index: -3;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        #tsparticles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
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
            50% { text-shadow: 0 0 30px rgba(169, 202, 72, 0.8), 0 0 40px rgba(169, 202, 72, 0.3); }
        }

        .lock-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 25px;
            width: 100%;
            max-width: 900px;
            display: flex;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            transition: transform 0.3s ease;
        }

        .logo-section {
            flex: 1;
            background: rgba(255, 255, 255, 0.95);
            padding: 50px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 18px;
            position: relative;
            text-align: center;
        }

        .logo-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: conic-gradient(transparent, rgba(169, 202, 72, 0.1), transparent);
            animation: logoRotate 10s linear infinite;
        }

        @keyframes logoRotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .logo-section img {
            max-width: 100%;
            height: auto;
            position: relative;
            z-index: 1;
            filter: drop-shadow(0 10px 20px rgba(0, 0, 0, 0.1));
        }

        .avatar-panel {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .avatar-frame {
            width: 84px;
            height: 84px;
            padding: 4px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1a4b96, #A9CA48);
            box-shadow: 0 10px 24px rgba(26, 75, 150, 0.25);
        }

        .avatar {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            display: block;
            border: 3px solid #ffffff;
            background: #ffffff;
        }

        .user-name {
            color: #1a4b96;
            font-size: 15px;
            font-weight: 600;
            max-width: 260px;
            overflow-wrap: anywhere;
        }

        .logo-caption {
            position: relative;
            z-index: 1;
            color: #666666;
            font-size: 13px;
            line-height: 1.5;
            max-width: 280px;
        }

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
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, transparent, rgba(169, 202, 72, 0.2), transparent);
            z-index: -1;
        }

        .form-title {
            margin-bottom: 8px;
        }

        .form-title h2 {
            color: #A9CA48;
            font-size: 28px;
            font-weight: 600;
            animation: welcomeGlow 2s ease-in-out infinite alternate;
        }

        @keyframes welcomeGlow {
            from { text-shadow: 0 0 10px rgba(169, 202, 72, 0.5); }
            to { text-shadow: 0 0 20px rgba(169, 202, 72, 0.8); }
        }

        .form-subtitle {
            color: rgba(255, 255, 255, 0.78);
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.85);
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: rgba(255, 255, 255, 0.62);
            pointer-events: none;
        }

        .modern-input {
            width: 100%;
            padding: 14px 20px 14px 48px;
            border: 2px solid rgba(169, 202, 72, 0.3);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            outline: none;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .modern-input:focus {
            border-color: #A9CA48;
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 25px rgba(169, 202, 72, 0.4);
            transform: translateY(-2px);
        }

        .modern-input::placeholder {
            color: rgba(255, 255, 255, 0.55);
        }

        .error-message {
            background: rgba(255, 107, 107, 0.18);
            border: 1px solid rgba(255, 107, 107, 0.45);
            color: #ffb3b3;
            padding: 9px 12px;
            border-radius: 8px;
            margin-top: 8px;
            font-size: 13px;
        }

        .button-section {
            display: block;
            margin-top: 28px;
        }

        .unlock-button {
            background: linear-gradient(45deg, #A9CA48, #7BC525);
            border: none;
            padding: 14px 32px;
            border-radius: 12px;
            color: #1a4b96;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(169, 202, 72, 0.3);
            position: relative;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
        }

        .unlock-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(169, 202, 72, 0.5);
        }

        .unlock-button:active {
            transform: translateY(-1px);
        }

        .unlock-button:disabled {
            cursor: wait;
            opacity: 0.75;
            transform: none;
        }

        .button-icon {
            width: 17px;
            height: 17px;
        }

        .loading-spinner {
            width: 18px;
            height: 18px;
            border: 2px solid rgba(26, 75, 150, 0.3);
            border-top-color: #1a4b96;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .hidden {
            display: none;
        }

        .lock-footer {
            margin-top: 26px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.14);
        }

        .footer-text {
            color: rgba(255, 255, 255, 0.68);
            font-size: 13px;
            margin-bottom: 10px;
        }

        .logout-button {
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.82);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 0;
            transition: all 0.3s ease;
        }

        .logout-button:hover {
            color: #A9CA48;
            text-shadow: 0 0 10px rgba(169, 202, 72, 0.5);
        }

        .logout-button svg {
            width: 15px;
            height: 15px;
        }

        @media (max-width: 768px) {
            .lock-container {
                flex-direction: column;
                max-width: 100%;
            }

            .logo-section,
            .form-section {
                padding: 32px;
            }

            .header h1 {
                font-size: 24px;
            }

            .button-section {
                justify-content: stretch;
            }

            .unlock-button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="dark-background"></div>
    <div id="tsparticles"></div>

    <div class="main-container">
        <div class="header">
            <h1>Portal de Proveedores</h1>
        </div>

        <div class="lock-container" id="lockContainer">
            @php
                $avatar = method_exists($user, 'getAttribute') && $user->avatar
                    ? asset('storage/'.$user->avatar)
                    : asset('images/users/avatar-1.jpg');
            @endphp

            <div class="logo-section">
                <img src="{{ asset('images/logos/logo_TotalGas_ver.png') }}" alt="TotalGas Logo">
                <div class="avatar-panel">
                    <div class="avatar-frame">
                        <img class="avatar" src="{{ $avatar }}" alt="Avatar de {{ $user->name ?? $user->email }}">
                    </div>
                    <div class="user-name">{{ $user->name ?? $user->email }}</div>
                </div>
                <p class="logo-caption">Tu sesión está protegida. Verifica tu contraseña para continuar.</p>
            </div>

            <div class="form-section">
                <div class="form-title">
                    <h2>Sesión bloqueada</h2>
                </div>
                <p class="form-subtitle">
                    Ingresa tu contraseña para regresar de forma segura al portal.
                </p>

                <form method="POST" action="{{ route('lockscreen.unlock') }}" id="unlockForm" novalidate>
                    @csrf

                    <div class="form-group">
                        <label for="passwordInput" class="form-label">Contraseña</label>
                        <div class="input-wrapper">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM8.9 8V6c0-1.71 1.39-3.1 3.1-3.1s3.1 1.39 3.1 3.1v2H8.9z"/>
                            </svg>
                            <input
                                type="password"
                                name="password"
                                class="modern-input"
                                placeholder="Ingresa tu contraseña"
                                autocomplete="off"
                                autocapitalize="off"
                                spellcheck="false"
                                data-lpignore="true"
                                data-1p-ignore="true"
                                data-bwignore="true"
                                autofocus
                                required
                                id="passwordInput"
                            >
                        </div>

                        @error('password')
                            <div class="error-message">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="button-section">
                        <button type="submit" class="unlock-button" id="unlockButton">
                            <span class="btn-text">Desbloquear</span>
                            <svg class="button-icon btn-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5C9.79 1 7.9 2.43 7.24 4.41c-.17.52.11 1.09.63 1.26.52.17 1.09-.11 1.26-.63C9.53 3.82 10.68 3 12 3c1.66 0 3 1.34 3 3v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/>
                            </svg>
                            <span class="loading-spinner hidden"></span>
                        </button>
                    </div>
                </form>

                <div class="lock-footer">
                    <div class="footer-text">¿No eres {{ $user->name ?? 'tú' }}?</div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="logout-button">
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M10.09 15.59 11.5 17l5-5-5-5-1.41 1.41L12.67 11H3v2h9.67l-2.58 2.59zM19 3H5c-1.1 0-2 .9-2 2v4h2V5h14v14H5v-4H3v4c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/>
                            </svg>
                            Iniciar sesión con otra cuenta
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/tsparticles@2/tsparticles.bundle.min.js"></script>
    <script>
        tsParticles.load("tsparticles", {
            fpsLimit: 60,
            background: { color: { value: "transparent" } },
            particles: {
                number: { value: 120, density: { enable: true, area: 900 } },
                color: { value: ["#A9CA48", "#ffffff", "#4a8fd4", "#7BC525"] },
                shape: { type: "circle" },
                opacity: {
                    value: 0.5,
                    random: true,
                    animation: { enable: true, speed: 1, minimumValue: 0.2, sync: false }
                },
                size: {
                    value: 3,
                    random: true,
                    animation: { enable: true, speed: 2, minimumValue: 1, sync: false }
                },
                links: { enable: true, distance: 130, color: "#A9CA48", opacity: 0.25, width: 1 },
                move: { enable: true, speed: 0.8, direction: "none", random: true, straight: false, outModes: { default: "out" } }
            },
            interactivity: {
                detectsOn: "window",
                events: {
                    onHover: { enable: true, mode: ["repulse", "grab"] },
                    onClick: { enable: true, mode: "push" }
                },
                modes: {
                    repulse: { distance: 160, duration: 0.6, speed: 1 },
                    grab: { distance: 200, links: { opacity: 0.6 } },
                    push: { quantity: 4 }
                }
            },
            detectRetina: true
        });

        document.addEventListener('mousemove', function(e) {
            const mouseX = (e.clientX / window.innerWidth) - 0.5;
            const mouseY = (e.clientY / window.innerHeight) - 0.5;
            const container = document.getElementById('lockContainer');
            const header = document.querySelector('.header');

            container.style.transform = `perspective(1000px) rotateY(${mouseX * 5}deg) rotateX(${mouseY * -5}deg) translateZ(0)`;
            header.style.transform = `translateX(${mouseX * 20}px) translateY(${mouseY * 20}px)`;
        });

        window.addEventListener('load', function() {
            const container = document.getElementById('lockContainer');
            const header = document.querySelector('.header');

            header.style.opacity = '0';
            header.style.transform = 'translateY(-50px)';
            container.style.opacity = '0';
            container.style.transform = 'translateY(100px) scale(0.9)';

            setTimeout(() => {
                header.style.transition = 'all 1s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
                container.style.transition = 'all 1.2s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
                header.style.opacity = '1';
                header.style.transform = 'translateY(0)';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0) scale(1)';
            }, 300);
        });

        document.getElementById('unlockForm').addEventListener('submit', function() {
            const button = document.getElementById('unlockButton');
            const btnText = button.querySelector('.btn-text');
            const btnIcon = button.querySelector('.btn-icon');
            const spinner = button.querySelector('.loading-spinner');

            button.disabled = true;
            btnText.textContent = 'Verificando...';
            btnIcon.classList.add('hidden');
            spinner.classList.remove('hidden');
        });

        document.getElementById('unlockButton').addEventListener('click', function(e) {
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

        document.querySelectorAll('.modern-input').forEach(function(input) {
            input.addEventListener('focus', function() { this.style.transform = 'translateY(-2px) scale(1.01)'; });
            input.addEventListener('blur', function() { this.style.transform = 'translateY(0) scale(1)'; });
        });
    </script>
</body>
</html>
