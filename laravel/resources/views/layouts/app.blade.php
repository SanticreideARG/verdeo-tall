<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Verdeo') }} — {{ $title ?? '' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&family=Barlow+Condensed:wght@500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full" x-data>

    {{-- Particle background --}}
    <canvas id="verdeo-bg"></canvas>

    <div class="min-h-full relative z-10">

        {{-- Sidebar --}}
        <div class="fixed inset-y-0 left-0 z-50 w-64 flex flex-col"
             style="background: linear-gradient(180deg, rgba(11,24,40,0.95), rgba(15,32,53,0.92));
                    border-right: 1px solid rgba(58,125,68,0.28);
                    backdrop-filter: blur(14px) saturate(1.3);
                    -webkit-backdrop-filter: blur(14px) saturate(1.3);
                    box-shadow: 4px 0 30px rgba(0,0,0,0.4);">

            {{-- Logo --}}
            <div class="flex items-center h-16 px-5 gap-3" style="border-bottom: 1px solid rgba(255,255,255,0.07);">
                <img src="/images/verdeo-logo.png" alt="Verdeo"
                     class="w-9 h-9 rounded-full object-cover"
                     style="filter: drop-shadow(0 2px 8px rgba(58,125,68,0.5));"
                     onerror="this.style.display='none'">
                <div>
                    <span class="font-condensed font-bold text-lg tracking-wide text-white">Verdeo</span>
                    <span class="font-condensed text-lg tracking-wide" style="color: #4e9e5a;"> Admin</span>
                </div>
            </div>

            {{-- Nav --}}
            <nav class="flex-1 px-3 py-5 space-y-0.5 overflow-y-auto">
                <x-nav-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">
                    <x-icon-home class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Dashboard
                </x-nav-link>
                <x-nav-link href="{{ route('conversaciones') }}" :active="request()->routeIs('conversaciones*')">
                    <x-icon-chat class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Conversaciones
                </x-nav-link>
                <x-nav-link href="{{ route('zonas') }}" :active="request()->routeIs('zonas*')">
                    <x-icon-map class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Zonas
                </x-nav-link>
                <x-nav-link href="{{ route('estadisticas') }}" :active="request()->routeIs('estadisticas*')">
                    <x-icon-chart class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Estadísticas
                </x-nav-link>

                <div class="pt-5 mt-4" style="border-top: 1px solid rgba(255,255,255,0.07);">
                    <p class="px-3 mb-2 font-condensed font-bold tracking-widest uppercase text-xs"
                       style="color: rgba(240,244,240,0.4); letter-spacing: 1.6px;">Comercial</p>
                    <x-nav-link href="{{ route('productos') }}" :active="request()->routeIs('productos*')">
                        <x-icon-product class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Productos
                    </x-nav-link>
                    <x-nav-link href="{{ route('ordenes') }}" :active="request()->routeIs('ordenes*')">
                        <x-icon-orders class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Órdenes
                    </x-nav-link>
                </div>

                <div class="pt-5 mt-1" style="border-top: 1px solid rgba(255,255,255,0.07);">
                    <p class="px-3 mb-2 font-condensed font-bold tracking-widest uppercase text-xs"
                       style="color: rgba(240,244,240,0.4); letter-spacing: 1.6px;">Administración</p>
                    <x-nav-link href="{{ route('usuarios') }}" :active="request()->routeIs('usuarios*')">
                        <x-icon-users class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Usuarios
                    </x-nav-link>
                    <x-nav-link href="{{ route('ajustes') }}" :active="request()->routeIs('ajustes*')">
                        <x-icon-settings class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Ajustes
                    </x-nav-link>
                </div>

                <div class="pt-5 mt-1" style="border-top: 1px solid rgba(255,255,255,0.07);">
                    <p class="px-3 mb-2 font-condensed font-bold tracking-widest uppercase text-xs"
                       style="color: rgba(240,244,240,0.4); letter-spacing: 1.6px;">Herramientas</p>
                    <x-nav-link href="{{ route('n8n') }}" target="_blank">
                        <x-icon-workflow class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> n8n Workflows
                    </x-nav-link>
                    <x-nav-link href="/horizon" target="_blank">
                        <x-icon-queue class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Horizon
                    </x-nav-link>
                </div>
            </nav>

            {{-- User chip --}}
            <div class="px-3 py-4" style="border-top: 1px solid rgba(255,255,255,0.07);">
                <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl"
                     style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.07);">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0"
                         style="background: linear-gradient(135deg, #3a7d44, #4e9e5a);">
                        {{ strtoupper(substr(auth()->user()->name ?? 'V', 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold truncate" style="color: #f0f4f0;">
                            {{ auth()->user()->name ?? 'Verdeo' }}
                        </p>
                        <p class="font-condensed text-xs uppercase tracking-wide" style="color: rgba(240,244,240,0.4); font-size: 10px; letter-spacing: 1px;">
                            {{ \App\Models\User::rolesLabels()[auth()->user()->role ?? ''] ?? 'Usuario' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main content --}}
        <div class="pl-64 flex flex-col min-h-screen">

            {{-- Topbar --}}
            <header class="verdeo-topbar sticky top-0 z-40 h-16 flex items-center px-8 gap-4">
                <h1 class="font-condensed font-bold text-xl tracking-wide flex-1" style="color: #f0f4f0; letter-spacing: 0.5px;">
                    {{ $title ?? '' }}
                </h1>
                <div class="flex items-center gap-3">
                    @if(session('success'))
                        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                             class="badge-green px-3 py-1.5 text-xs">
                            {{ session('success') }}
                        </div>
                    @endif
                    {{ $actions ?? '' }}
                </div>
            </header>

            {{-- Page --}}
            <main class="flex-1 px-8 py-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts

    {{-- Particle background script --}}
    <script>
    (function () {
        const canvas = document.getElementById('verdeo-bg');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const COLORS = ['#3a7d44', '#4e9e5a', '#c8a030', '#8ab4c8', '#2d6e38'];
        let W, H, particles, mouse = { x: -9999, y: -9999 };

        function resize() {
            W = canvas.width  = window.innerWidth;
            H = canvas.height = window.innerHeight;
        }

        function Particle() {
            this.x     = Math.random() * W;
            this.y     = Math.random() * H;
            this.vx    = (Math.random() - 0.5) * 0.35;
            this.vy    = (Math.random() - 0.5) * 0.35;
            this.r     = Math.random() * 1.6 + 0.5;
            this.color = COLORS[Math.floor(Math.random() * COLORS.length)];
            this.alpha = Math.random() * 0.45 + 0.15;
        }

        function init() {
            resize();
            const count = Math.min(90, Math.floor(W * H / 15000));
            particles = Array.from({ length: count }, () => new Particle());
        }

        function draw() {
            ctx.clearRect(0, 0, W, H);
            const maxD = 125;

            for (let i = 0; i < particles.length; i++) {
                const a = particles[i];
                for (let j = i + 1; j < particles.length; j++) {
                    const b = particles[j];
                    const dx = a.x - b.x, dy = a.y - b.y;
                    const d  = Math.sqrt(dx * dx + dy * dy);
                    if (d < maxD) {
                        ctx.beginPath();
                        ctx.moveTo(a.x, a.y);
                        ctx.lineTo(b.x, b.y);
                        ctx.strokeStyle = `rgba(58,125,68,${0.09 * (1 - d / maxD)})`;
                        ctx.lineWidth   = 0.5;
                        ctx.stroke();
                    }
                }
                const mx = a.x - mouse.x, my = a.y - mouse.y;
                const md = Math.sqrt(mx * mx + my * my);
                if (md < 110) {
                    ctx.beginPath();
                    ctx.moveTo(a.x, a.y);
                    ctx.lineTo(mouse.x, mouse.y);
                    ctx.strokeStyle = `rgba(78,158,90,${0.16 * (1 - md / 110)})`;
                    ctx.lineWidth   = 0.7;
                    ctx.stroke();
                }
            }

            for (const p of particles) {
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                ctx.fillStyle   = p.color;
                ctx.globalAlpha = p.alpha;
                ctx.fill();
                ctx.globalAlpha = 1;
                p.x += p.vx; p.y += p.vy;
                if (p.x < 0) p.x = W; if (p.x > W) p.x = 0;
                if (p.y < 0) p.y = H; if (p.y > H) p.y = 0;
            }
            requestAnimationFrame(draw);
        }

        window.addEventListener('resize', init);
        window.addEventListener('mousemove', e => { mouse.x = e.clientX; mouse.y = e.clientY; });
        window.addEventListener('touchmove', e => {
            mouse.x = e.touches[0].clientX;
            mouse.y = e.touches[0].clientY;
        }, { passive: true });

        init();
        draw();
    })();
    </script>
</body>
</html>
