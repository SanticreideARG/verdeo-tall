<!DOCTYPE html>
<html lang="es" class="h-full" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Verdeo') }} — {{ $title ?? '' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&family=Barlow+Condensed:wght@500;600;700&display=swap" rel="stylesheet">
    {{-- Apply saved theme before first paint --}}
    <script>(function(){var t=localStorage.getItem('verdeo-theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full" x-data>

    <canvas id="verdeo-bg"></canvas>

    <div class="min-h-full relative z-10">

        {{-- Sidebar --}}
        <div class="verdeo-sidebar fixed inset-y-0 left-0 z-50 w-64 flex flex-col">

            {{-- Logo --}}
            <div class="flex items-center h-16 px-5 gap-3" style="border-bottom: 1px solid var(--vd-bdr-soft);">
                <img src="/images/verdeo-logo.png" alt="Verdeo"
                     class="w-9 h-9 rounded-full object-cover flex-shrink-0"
                     style="filter: drop-shadow(0 2px 8px rgba(58,125,68,0.5));"
                     onerror="this.style.display='none'">
                <div>
                    <span class="font-condensed font-bold text-lg tracking-wide" style="color: var(--vd-text);">Verdeo</span>
                    <span class="font-condensed text-lg tracking-wide" style="color: var(--vd-green-lt);"> Admin</span>
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
                <x-nav-link href="{{ route('enlaces') }}" :active="request()->routeIs('enlaces*')">
                    <x-icon-link class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Enlaces
                </x-nav-link>
                <x-nav-link href="{{ route('estadisticas') }}" :active="request()->routeIs('estadisticas*')">
                    <x-icon-chart class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Estadísticas
                </x-nav-link>

                <div class="pt-5 mt-4" style="border-top: 1px solid var(--vd-bdr-soft);">
                    <p class="px-3 mb-2 font-condensed font-bold tracking-widest uppercase text-xs"
                       style="color: var(--vd-muted-2); letter-spacing: 1.6px;">Comercial</p>
                    <x-nav-link href="{{ route('productos') }}" :active="request()->routeIs('productos*')">
                        <x-icon-product class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Productos
                    </x-nav-link>
                    <x-nav-link href="{{ route('ordenes') }}" :active="request()->routeIs('ordenes*')">
                        <x-icon-orders class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Órdenes
                    </x-nav-link>
                </div>

                <div class="pt-5 mt-1" style="border-top: 1px solid var(--vd-bdr-soft);">
                    <p class="px-3 mb-2 font-condensed font-bold tracking-widest uppercase text-xs"
                       style="color: var(--vd-muted-2); letter-spacing: 1.6px;">Administración</p>
                    @if(auth()->user()->isAdmin() || auth()->user()->isResponsableZona())
                    <x-nav-link href="{{ route('usuarios') }}" :active="request()->routeIs('usuarios*')">
                        <x-icon-users class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Usuarios
                    </x-nav-link>
                    @endif
                    <x-nav-link href="{{ route('ajustes') }}" :active="request()->routeIs('ajustes*')">
                        <x-icon-settings class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Ajustes
                    </x-nav-link>
                </div>

                <div class="pt-5 mt-1" style="border-top: 1px solid var(--vd-bdr-soft);"
                     x-data="{ open: {{ request()->routeIs('marketing.*') ? 'true' : 'false' }} }">
                    <button @click="open = !open"
                            class="flex items-center justify-between w-full px-3 mb-1 py-1 rounded-lg transition-colors duration-150"
                            onmouseover="this.style.background='var(--vd-nav-hover)'"
                            onmouseout="this.style.background=''">
                        <p class="font-condensed font-bold tracking-widest uppercase text-xs"
                           style="color: var(--vd-muted-2); letter-spacing: 1.6px;">Marketing de Redes</p>
                        <svg :class="open ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"
                             style="width:14px;height:14px;flex-shrink:0;transition:transform 0.2s;color:var(--vd-muted-2);">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open" x-collapse>
                        <x-nav-link href="{{ route('marketing.email') }}" :active="request()->routeIs('marketing.email')">
                            <x-icon-email class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Email
                        </x-nav-link>
                        <x-nav-link href="{{ route('marketing.whatsapp') }}" :active="request()->routeIs('marketing.whatsapp')">
                            <x-icon-whatsapp class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> WhatsApp
                        </x-nav-link>
                        <x-nav-link href="{{ route('marketing.facebook') }}" :active="request()->routeIs('marketing.facebook')">
                            <x-icon-facebook class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Facebook
                        </x-nav-link>
                        <x-nav-link href="{{ route('marketing.instagram') }}" :active="request()->routeIs('marketing.instagram')">
                            <x-icon-instagram class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Instagram
                        </x-nav-link>
                        <x-nav-link href="{{ route('marketing.otros') }}" :active="request()->routeIs('marketing.otros')">
                            <x-icon-megaphone class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Otros
                        </x-nav-link>
                    </div>
                </div>

                <div class="pt-5 mt-1" style="border-top: 1px solid var(--vd-bdr-soft);">
                    <p class="px-3 mb-2 font-condensed font-bold tracking-widest uppercase text-xs"
                       style="color: var(--vd-muted-2); letter-spacing: 1.6px;">Herramientas</p>
                    <x-nav-link href="{{ route('n8n') }}" target="_blank">
                        <x-icon-workflow class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> n8n Workflows
                    </x-nav-link>
                    <x-nav-link href="/horizon" target="_blank">
                        <x-icon-queue class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Horizon
                    </x-nav-link>
                </div>
            </nav>

            {{-- User chip --}}
            <div class="px-3 py-4" style="border-top: 1px solid var(--vd-bdr-soft);">
                <a href="{{ route('mi-cuenta') }}" wire:navigate
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors duration-150"
                   style="background: var(--vd-input-bg); border: 1px solid var(--vd-bdr-soft);"
                   onmouseover="this.style.borderColor='rgba(78,158,90,0.45)'; this.style.background='var(--vd-nav-hover)'"
                   onmouseout="this.style.borderColor='var(--vd-bdr-soft)'; this.style.background='var(--vd-input-bg)'">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0"
                         style="background: linear-gradient(135deg, #3a7d44, #4e9e5a);">
                        {{ strtoupper(substr(auth()->user()->name ?? 'V', 0, 1)) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold truncate" style="color: var(--vd-text);">
                            {{ auth()->user()->name ?? 'Verdeo' }}
                        </p>
                        <p class="font-condensed text-xs uppercase tracking-wide" style="color: var(--vd-muted); font-size: 10px; letter-spacing: 1px;">
                            {{ \App\Models\User::rolesLabels()[auth()->user()->role ?? ''] ?? 'Usuario' }}
                        </p>
                    </div>
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                         style="color: var(--vd-muted-2); flex-shrink: 0;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        </div>

        {{-- Main content --}}
        <div class="pl-64 flex flex-col min-h-screen">

            {{-- Topbar --}}
            <header class="verdeo-topbar sticky top-0 z-40 h-16 flex items-center px-8 gap-4">
                <h1 class="font-condensed font-bold text-xl tracking-wide flex-1" style="color: var(--vd-text); letter-spacing: 0.5px;">
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

                    {{-- Theme toggle --}}
                    <button class="theme-toggle" onclick="verdeoToggleTheme()" title="Cambiar tema">
                        <svg class="ic-sun" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
                        </svg>
                        <svg class="ic-moon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
                        </svg>
                    </button>
                </div>
            </header>

            {{-- Page --}}
            <main class="flex-1 px-8 py-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts

    <script>
    function verdeoToggleTheme() {
        var cur  = document.documentElement.getAttribute('data-theme') || 'dark';
        var next = cur === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('verdeo-theme', next);
        window.dispatchEvent(new Event('verdeo-theme-change'));
    }

    (function () {
        const canvas = document.getElementById('verdeo-bg');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const COLORS = ['#3a7d44','#4e9e5a','#c8a030','#8ab4c8','#2d6e38'];
        let W, H, particles, mouse = { x: -9999, y: -9999 };

        function isDark() { return document.documentElement.getAttribute('data-theme') !== 'light'; }
        function resize() { W = canvas.width = window.innerWidth; H = canvas.height = window.innerHeight; }

        function Particle() {
            this.x = Math.random() * W; this.y = Math.random() * H;
            this.vx = (Math.random() - 0.5) * 0.35; this.vy = (Math.random() - 0.5) * 0.35;
            this.r  = Math.random() * 1.6 + 0.5;
            this.color = COLORS[Math.floor(Math.random() * COLORS.length)];
            this.alpha = isDark() ? Math.random() * 0.45 + 0.15 : Math.random() * 0.25 + 0.08;
        }

        function init() {
            resize();
            particles = Array.from({ length: Math.min(90, Math.floor(W * H / 15000)) }, () => new Particle());
        }

        function draw() {
            ctx.clearRect(0, 0, W, H);
            const dark = isDark();
            const maxD = 125;
            for (let i = 0; i < particles.length; i++) {
                const a = particles[i];
                for (let j = i + 1; j < particles.length; j++) {
                    const b = particles[j], dx = a.x - b.x, dy = a.y - b.y, d = Math.sqrt(dx*dx+dy*dy);
                    if (d < maxD) {
                        ctx.beginPath(); ctx.moveTo(a.x,a.y); ctx.lineTo(b.x,b.y);
                        ctx.strokeStyle = `rgba(58,125,68,${(dark?0.09:0.05)*(1-d/maxD)})`;
                        ctx.lineWidth = 0.5; ctx.stroke();
                    }
                }
                const mx = a.x - mouse.x, my = a.y - mouse.y, md = Math.sqrt(mx*mx+my*my);
                if (md < 110) {
                    ctx.beginPath(); ctx.moveTo(a.x,a.y); ctx.lineTo(mouse.x,mouse.y);
                    ctx.strokeStyle = `rgba(78,158,90,${(dark?0.16:0.09)*(1-md/110)})`;
                    ctx.lineWidth = 0.7; ctx.stroke();
                }
            }
            for (const p of particles) {
                ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, Math.PI*2);
                ctx.fillStyle = p.color; ctx.globalAlpha = p.alpha; ctx.fill(); ctx.globalAlpha = 1;
                p.x += p.vx; p.y += p.vy;
                if (p.x < 0) p.x = W; if (p.x > W) p.x = 0;
                if (p.y < 0) p.y = H; if (p.y > H) p.y = 0;
            }
            requestAnimationFrame(draw);
        }

        window.addEventListener('resize', init);
        window.addEventListener('mousemove', e => { mouse.x = e.clientX; mouse.y = e.clientY; });
        window.addEventListener('touchmove', e => { mouse.x = e.touches[0].clientX; mouse.y = e.touches[0].clientY; }, { passive: true });
        window.addEventListener('verdeo-theme-change', init);
        init(); draw();
    })();
    </script>
</body>
</html>
