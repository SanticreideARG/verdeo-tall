<!DOCTYPE html>
<html lang="es" class="h-full" data-theme="{{ \App\Models\Setting::get('tema', 'bosque') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Verdeo') }} — {{ $title ?? '' }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/favicon-192.png">
    <link rel="shortcut icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&family=Barlow+Condensed:wght@500;600;700&display=swap" rel="stylesheet">
    {{-- Apply saved theme before first paint (localStorage overrides server value for instant flash prevention) --}}
    <script>(function(){var valid=['bosque','carbon','aurora','cielo','natural','dark','light'];var t=localStorage.getItem('verdeo-theme');if(t&&valid.indexOf(t)>=0){document.documentElement.setAttribute('data-theme',t);}})();</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @keyframes verdeoFabRing {
            from { opacity:0; transform:translateY(10px); }
            to   { opacity:1; transform:translateY(0); }
        }
        @keyframes verdeoFabItem {
            from { opacity:0; transform:translateY(16px) scale(.7); }
            to   { opacity:1; transform:translateY(0) scale(1); }
        }
    </style>
    @stack('styles')
    @livewireStyles
</head>
<body class="h-full" x-data>

    <canvas id="verdeo-bg"></canvas>

    @php $hasSidebar = auth()->check() && ! auth()->user()->isColaborador() && ! auth()->user()->isCliente(); @endphp
    <div class="min-h-full relative z-10">

        {{-- Sidebar: always shown for Admin/Responsable, hidden for Colaborador/Cliente --}}
        <div class="verdeo-sidebar fixed inset-y-0 left-0 z-50 w-64 flex-col {{ $hasSidebar ? 'flex' : 'hidden' }}">

            {{-- Logo --}}
            <div class="flex items-center h-16 px-5 gap-3" style="border-bottom: 1px solid var(--vd-bdr-soft);">
                <img src="/images/verdeo-logo.png?v=2" alt="Verdeo"
                     class="w-9 h-9 rounded-full object-cover flex-shrink-0"
                     style="filter: drop-shadow(0 2px 8px rgba(58,125,68,0.5));"
                     onerror="this.style.display='none'">
                <span class="font-condensed font-bold text-lg tracking-wide" style="color: var(--vd-text);">Verdeo</span>
            </div>

            {{-- Nav --}}
            <nav class="flex-1 px-3 py-5 space-y-0.5 overflow-y-auto">

                {{-- Cocina: standalone solo para rol cocina (admin/responsable la ven en sección Comercial) --}}
                @if(auth()->user()->isCocina())
                <x-nav-link href="{{ route('cocina') }}" :active="request()->routeIs('cocina*')">
                    <svg width="16" height="16" fill="none" stroke="#4e9e5a" stroke-width="1.8" viewBox="0 0 24 24" class="mr-3 flex-shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/>
                    </svg>
                    Cocina
                </x-nav-link>
                @endif

                {{-- El resto del menú se oculta para el rol cocina --}}
                @if(!auth()->user()->isCocina())
                <x-nav-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">
                    <x-icon-home class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Dashboard
                </x-nav-link>
                <x-nav-link href="{{ route('conversaciones') }}" :active="request()->routeIs('conversaciones*')">
                    <x-icon-chat class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Conversaciones
                </x-nav-link>
                <x-nav-link href="{{ route('chat') }}" :active="request()->routeIs('chat*')">
                    <svg width="16" height="16" fill="none" stroke="#4e9e5a" stroke-width="1.8" viewBox="0 0 24 24" class="mr-3 flex-shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"/>
                    </svg>
                    Chat
                    @php $chatUnread = \App\Models\ChatMensaje::noLeidosPara(auth()->id()); @endphp
                    @if($chatUnread > 0)
                    <span class="ml-auto min-w-[18px] h-[18px] px-1 rounded-full flex items-center justify-center font-bold"
                          style="background:#4e9e5a;color:#fff;font-size:9px;">{{ $chatUnread }}</span>
                    @endif
                </x-nav-link>
                <x-nav-link href="{{ route('zonas') }}" :active="request()->routeIs('zonas*')">
                    <x-icon-map class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Zonas
                </x-nav-link>
                @if(! auth()->user()->isColaborador())
                @if(auth()->user()->isAdmin() || auth()->user()->isResponsableZona())
                <x-nav-link href="{{ route('mis-enlaces') }}" :active="request()->routeIs('mis-enlaces*')">
                    <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="#4e9e5a">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                    </svg>
                    Mis enlaces
                </x-nav-link>
                @endif
                <x-nav-link href="{{ route('estadisticas') }}" :active="request()->routeIs('estadisticas*')">
                    <x-icon-chart class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Estadísticas
                </x-nav-link>
                @endif

                <div class="pt-5 mt-4" style="border-top: 1px solid var(--vd-bdr-soft);">
                    <p class="px-3 mb-2 font-condensed font-bold tracking-widest uppercase text-xs"
                       style="color: var(--vd-muted-2); letter-spacing: 1.6px;">Comercial</p>
                    <x-nav-link href="{{ route('productos') }}" :active="request()->routeIs('productos*')">
                        <x-icon-product class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Productos
                    </x-nav-link>
                    <x-nav-link href="{{ route('ordenes') }}" :active="request()->routeIs('ordenes*')">
                        <x-icon-orders class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Órdenes
                    </x-nav-link>
                    @if(auth()->user()->isAdmin() || auth()->user()->isResponsableZona())
                    <x-nav-link href="{{ route('entregas') }}" :active="request()->routeIs('entregas*')">
                        <svg width="16" height="16" fill="none" stroke="#4e9e5a" stroke-width="1.8" viewBox="0 0 24 24" class="mr-3 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c-.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z"/>
                        </svg>
                        Entregas
                    </x-nav-link>
                    <x-nav-link href="{{ route('cocina') }}" :active="request()->routeIs('cocina*')">
                        <svg width="16" height="16" fill="none" stroke="#4e9e5a" stroke-width="1.8" viewBox="0 0 24 24" class="mr-3 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/>
                        </svg>
                        Cocina
                    </x-nav-link>
                    <x-nav-link href="{{ route('campanas') }}" :active="request()->routeIs('campanas*')">
                        <svg width="16" height="16" fill="none" stroke="#4e9e5a" stroke-width="1.8" viewBox="0 0 24 24" class="mr-3 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 011.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 001.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 010 3.46"/>
                        </svg>
                        Campañas
                    </x-nav-link>
                    @endif
                </div>

                @if(! auth()->user()->isColaborador())
                <div class="pt-5 mt-1" style="border-top: 1px solid var(--vd-bdr-soft);">
                    <p class="px-3 mb-2 font-condensed font-bold tracking-widest uppercase text-xs"
                       style="color: var(--vd-muted-2); letter-spacing: 1.6px;">Administración</p>
                    @if(auth()->user()->isAdmin() || auth()->user()->isResponsableZona())
                    {{-- Clientes (collapsible) --}}
                    <div x-data="{ open: {{ request()->routeIs('clientes*') ? 'true' : 'false' }} }">
                        <button @click="open = !open"
                                class="flex items-center w-full px-3 py-2 rounded-lg text-sm transition-colors duration-150"
                                :style="open ? 'background: var(--vd-nav-hover);' : ''"
                                onmouseover="this.style.background='var(--vd-nav-hover)'"
                                onmouseout="if(!$data.open) this.style.background=''">
                            <svg width="16" height="16" fill="none" stroke="#4e9e5a" stroke-width="1.8" viewBox="0 0 24 24" class="mr-3 flex-shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                            </svg>
                            <span class="flex-1 text-left" style="color: var(--vd-text-soft);">Clientes</span>
                            <svg :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"
                                 style="width:12px;height:12px;flex-shrink:0;transition:transform 0.2s;color:var(--vd-muted-2);">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" x-collapse class="pl-4">
                            <x-nav-link href="{{ route('clientes') }}" :active="request()->routeIs('clientes') || request()->routeIs('clientes.index')">
                                <svg width="14" height="14" fill="none" stroke="#4e9e5a" stroke-width="1.8" viewBox="0 0 24 24" class="mr-3 flex-shrink-0">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                                </svg>
                                Lista
                            </x-nav-link>
                            <x-nav-link href="{{ route('clientes.crm') }}" :active="request()->routeIs('clientes.crm')">
                                <svg width="14" height="14" fill="none" stroke="#4e9e5a" stroke-width="1.8" viewBox="0 0 24 24" class="mr-3 flex-shrink-0">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0020.25 18V6A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25z"/>
                                </svg>
                                CRM
                            </x-nav-link>
                        </div>
                    </div>
                    <x-nav-link href="{{ route('usuarios') }}" :active="request()->routeIs('usuarios*')">
                        <x-icon-users class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Usuarios
                    </x-nav-link>
                    @endif
                    <x-nav-link href="{{ route('ajustes') }}" :active="request()->routeIs('ajustes*')">
                        <x-icon-settings class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Ajustes
                    </x-nav-link>
                </div>
                @endif

                @if(! auth()->user()->isColaborador())
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
                @endif

                @if(! auth()->user()->isColaborador())
                <div class="pt-5 mt-1" style="border-top: 1px solid var(--vd-bdr-soft);">
                    <p class="px-3 mb-2 font-condensed font-bold tracking-widest uppercase text-xs"
                       style="color: var(--vd-muted-2); letter-spacing: 1.6px;">Herramientas</p>
                    @if(auth()->user()->isAdmin() || auth()->user()->isResponsableZona())
                    <x-nav-link href="{{ route('ai.chat') }}" :active="request()->routeIs('ai.*')">
                        <svg width="16" height="16" fill="none" stroke="#4e9e5a" stroke-width="1.8" viewBox="0 0 24 24" class="mr-3 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/>
                        </svg>
                        Asistente IA
                    </x-nav-link>
                    @endif
                    <x-nav-link href="{{ route('n8n') }}" target="_blank">
                        <x-icon-workflow class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> n8n Workflows
                    </x-nav-link>
                    <x-nav-link href="/horizon" target="_blank">
                        <x-icon-queue class="w-4 h-4 mr-3 flex-shrink-0" style="color: #4e9e5a;"/> Horizon
                    </x-nav-link>
                </div>
                @endif

                @if(auth()->user()->isAdmin())
                <div class="pt-5 mt-1" style="border-top: 1px solid var(--vd-bdr-soft);">
                    <p class="px-3 mb-2 font-condensed font-bold tracking-widest uppercase text-xs"
                       style="color: var(--vd-muted-2); letter-spacing: 1.6px;">Sistema</p>
                    <x-nav-link href="{{ route('sistema') }}" :active="request()->routeIs('sistema*')">
                        <svg width="16" height="16" fill="none" stroke="#4e9e5a" stroke-width="1.8" viewBox="0 0 24 24" class="mr-3 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Log de actividad
                    </x-nav-link>
                </div>
                @endif
                @endif {{-- end @if(!isCocina()) --}}

                {{-- Ayuda: visible para todos --}}
                <div class="pt-3 mt-1" style="border-top: 1px solid var(--vd-bdr-soft);">
                    <x-nav-link href="{{ route('ayuda') }}" :active="request()->routeIs('ayuda*')">
                        <svg width="16" height="16" fill="none" stroke="#4e9e5a" stroke-width="1.8" viewBox="0 0 24 24" class="mr-3 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"/>
                        </svg>
                        Ayuda
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
        <div class="{{ $hasSidebar ? 'pl-64' : '' }} flex flex-col min-h-screen">

            {{-- Topbar --}}
            <header class="verdeo-topbar sticky top-0 z-40 h-16 flex items-center gap-4 {{ $hasSidebar ? 'px-8' : 'px-4' }}">
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

                    {{-- Fecha, hora y clima (solo Admin/Responsable) --}}
                    @if($hasSidebar)
                    <div class="flex items-center gap-3 text-xs" style="color: var(--vd-muted);" x-data="{
                        hora: '',
                        fecha: '',
                        temp: null,
                        icono: '',
                        init() {
                            this.tick();
                            setInterval(() => this.tick(), 1000);
                            this.fetchClima();
                        },
                        tick() {
                            const now = new Date();
                            this.hora = now.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                            this.fecha = now.toLocaleDateString('es-AR', { weekday: 'short', day: 'numeric', month: 'short' });
                        },
                        async fetchClima() {
                            try {
                                const r = await fetch('https://wttr.in/?format=j1', { cache: 'no-store' });
                                const d = await r.json();
                                const c = d.current_condition[0];
                                this.temp = c.temp_C + '°';
                                const code = parseInt(c.weatherCode);
                                if ([113].includes(code))                          this.icono = '☀️';
                                else if ([116].includes(code))                     this.icono = '⛅';
                                else if ([119, 122].includes(code))                this.icono = '☁️';
                                else if ([143].includes(code))                     this.icono = '🌫️';
                                else if ([176,263,266,293,296,353].includes(code)) this.icono = '🌦️';
                                else if ([299,302,305,308,356,359].includes(code)) this.icono = '🌧️';
                                else if ([200,386,389,392,395].includes(code))     this.icono = '⛈️';
                                else if ([179,182,185,281,284,311,314,317,320,323,326,329,332,335,338,350,368,371,374,377].includes(code)) this.icono = '❄️';
                                else this.icono = '🌡️';
                            } catch(e) {}
                        }
                    }">
                        <template x-if="temp">
                            <span x-text="icono + ' ' + temp"
                                  class="font-mono" style="color: var(--vd-text-soft);"></span>
                        </template>
                        <div class="text-right" style="line-height: 1.3;">
                            <div class="font-mono font-semibold" style="color: var(--vd-text); font-size: 13px;" x-text="hora"></div>
                            <div style="font-size: 10px; color: var(--vd-muted-2); text-transform: capitalize;" x-text="fecha"></div>
                        </div>
                    </div>
                    <div style="width:1px; height:22px; background: var(--vd-bdr-soft);"></div>
                    @endif

                    {{-- Server badge (admin / responsable only) --}}
                    @if(auth()->check() && (auth()->user()->isAdmin() || auth()->user()->isResponsableZona()))
                    @php $dbEnv = session('db_env', 'alice'); @endphp
                    <a href="{{ route('ajustes') }}"
                       title="Servidor activo: {{ $dbEnv === 'betty' ? 'Betty · Producción' : 'Alice · Pruebas' }}"
                       class="text-[10px] font-bold px-2.5 py-1 rounded-full transition-all"
                       style="{{ $dbEnv === 'betty'
                           ? 'background: rgba(239,68,68,0.18); color: #f87171; border: 1px solid rgba(239,68,68,0.35);'
                           : 'background: rgba(78,158,90,0.12); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.3);' }}">
                        {{ $dbEnv === 'betty' ? 'BETTY' : 'ALICE' }}
                    </a>
                    @endif

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
            <main class="flex-1 py-8 {{ $hasSidebar ? 'px-8' : 'px-4 pb-28' }}">
                {{ $slot }}
            </main>
        </div>
    </div>


    {{-- ═══════════════════════════════════════════════════════════
         MOBILE BOTTOM DOCK — lg:hidden — Colaborador y Cliente only
         Glassmorphic LeafDock pill · sliding active indicator · FAB
         Colaborador : Conv | Zonas | FAB | Órdenes | Cuenta
    ═══════════════════════════════════════════════════════════ --}}
    @if(auth()->user()->isColaborador() || auth()->user()->isCliente())
    <nav class="lg:hidden fixed bottom-0 left-0 right-0 z-50 flex justify-center"
         style="padding: 0 16px max(16px, env(safe-area-inset-bottom));"
         x-data="{
             fab: false,
             active: '',
             init() {
                 this.updateActive();
                 document.addEventListener('livewire:navigated', () => {
                     this.updateActive();
                     this.fab = false;
                 });
             },
             updateActive() {
                 const p = window.location.pathname;
                 if (p === '/' || p.startsWith('/dashboard'))      this.active = 'dashboard';
                 else if (p.startsWith('/conversaciones'))         this.active = 'conv';
                 else if (p.startsWith('/ordenes'))                this.active = 'ordenes';
                 else if (p.startsWith('/zonas'))                  this.active = 'zonas';
                 else if (p.startsWith('/portal'))                 this.active = 'portal';
                 else if (p.startsWith('/mi-cuenta'))              this.active = 'cuenta';
                 else                                              this.active = '';
             },
         }"
         @click.outside="fab = false">

        {{-- FAB action ring (Colaborador) --}}
        <template x-if="fab">
            <div class="absolute flex gap-3"
                 style="bottom: max(88px, calc(env(safe-area-inset-bottom) + 72px)); animation: verdeoFabRing .35s cubic-bezier(.2,1.4,.4,1);">
                <a href="{{ route('zonas') }}" wire:navigate
                   class="flex flex-col items-center justify-center gap-1 text-white text-center"
                   style="width:56px;height:56px;border-radius:16px;background:linear-gradient(135deg,rgba(58,125,68,.95),rgba(78,158,90,.95));border:1px solid rgba(58,125,68,.5);box-shadow:0 6px 22px rgba(58,125,68,.5);font-size:9px;font-weight:700;letter-spacing:.5px;animation:verdeoFabItem .4s cubic-bezier(.2,1.4,.4,1) 0ms backwards;">
                    <svg width="20" height="20" fill="none" stroke="#fff" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z"/>
                    </svg>
                    Mapa
                </a>
                <a href="{{ route('ordenes') }}" wire:navigate
                   class="flex flex-col items-center justify-center gap-1 text-white text-center"
                   style="width:56px;height:56px;border-radius:16px;background:linear-gradient(135deg,rgba(58,125,68,.95),rgba(78,158,90,.95));border:1px solid rgba(58,125,68,.5);box-shadow:0 6px 22px rgba(58,125,68,.5);font-size:9px;font-weight:700;letter-spacing:.5px;animation:verdeoFabItem .4s cubic-bezier(.2,1.4,.4,1) 60ms backwards;">
                    <svg width="20" height="20" fill="none" stroke="#fff" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z"/>
                    </svg>
                    Órdenes
                </a>
                <a href="{{ route('chat') }}" wire:navigate
                   class="flex flex-col items-center justify-center gap-1 text-white text-center"
                   style="width:56px;height:56px;border-radius:16px;background:linear-gradient(135deg,rgba(58,125,68,.95),rgba(78,158,90,.95));border:1px solid rgba(58,125,68,.5);box-shadow:0 6px 22px rgba(58,125,68,.5);font-size:9px;font-weight:700;letter-spacing:.5px;animation:verdeoFabItem .4s cubic-bezier(.2,1.4,.4,1) 120ms backwards;">
                    <svg width="20" height="20" fill="none" stroke="#fff" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"/>
                    </svg>
                    Chat
                </a>
            </div>
        </template>

        {{-- Pill --}}
        <div class="relative flex items-center"
             style="background:rgba(11,24,40,0.88);backdrop-filter:blur(20px) saturate(1.4);-webkit-backdrop-filter:blur(20px) saturate(1.4);border:1px solid rgba(58,125,68,0.38);border-radius:9999px;padding:8px 10px;gap:4px;box-shadow:0 12px 40px rgba(0,0,0,0.6),0 0 30px rgba(58,125,68,0.18);">

            {{-- Sliding leaf indicator
                 Slot pixel positions (left):
                 slot-0=10px  slot-1=58px  [FAB≈60px wide]  slot-2=166px  slot-3=214px --}}
            <div class="absolute top-2 bottom-2 rounded-full pointer-events-none transition-all duration-300 ease-out"
                 style="width:44px;background:linear-gradient(135deg,#3a7d44,#4e9e5a);box-shadow:0 4px 14px rgba(58,125,68,0.5),inset 0 1px 0 rgba(255,255,255,0.15);z-index:0;"
                 :style="{
                     opacity: active ? 1 : 0,
                     left: ({
                         dashboard: '10px',
                         portal:    '10px',
                         conv:      '58px',
                         zonas:     '58px',
                         ordenes:   '166px',
                         cuenta:    '214px'
                     })[active] ?? '10px'
                 }">
            </div>

            {{-- CLIENTE: [Portal][Conv][FAB][Pedidos][Cuenta] — COLABORADOR: [Conv][Zonas][FAB][Ordenes][Cuenta] --}}

            @if(auth()->user()->isCliente())
            <a href="{{ route('portal') }}" wire:navigate
               class="relative z-10 flex items-center justify-center rounded-full"
               style="width:44px;height:44px;margin:0 2px;"
               :style="{ color: active==='portal' ? '#fff' : 'rgba(240,244,240,0.45)' }">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
                </svg>
            </a>
            <a href="{{ route('conversaciones') }}" wire:navigate
               class="relative z-10 flex items-center justify-center rounded-full"
               style="width:44px;height:44px;margin:0 2px;"
               :style="{ color: active==='conv' ? '#fff' : 'rgba(240,244,240,0.45)' }">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>
                </svg>
            </a>
            @else
            <a href="{{ route('conversaciones') }}" wire:navigate
               class="relative z-10 flex items-center justify-center rounded-full"
               style="width:44px;height:44px;margin:0 2px;"
               :style="{ color: active==='conv' ? '#fff' : 'rgba(240,244,240,0.45)' }">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>
                </svg>
            </a>
            <a href="{{ route('zonas') }}" wire:navigate
               class="relative z-10 flex items-center justify-center rounded-full"
               style="width:44px;height:44px;margin:0 2px;"
               :style="{ color: active==='zonas' ? '#fff' : 'rgba(240,244,240,0.45)' }">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z"/>
                </svg>
            </a>
            @endif

            <button type="button" @click="fab = !fab"
                    class="relative z-10 flex items-center justify-center rounded-full text-white transition-all duration-300"
                    style="width:50px;height:50px;margin:-12px 4px;border:2px solid rgba(11,24,40,0.6);box-shadow:0 6px 20px rgba(58,125,68,0.6),0 0 0 1px rgba(78,158,90,0.4);"
                    :style="{ background: fab ? 'linear-gradient(135deg,#c8a030,#d8b040)' : 'linear-gradient(135deg,#3a7d44,#4e9e5a)', transform: fab ? 'rotate(45deg) scale(1.05)' : 'none' }">
                <svg width="22" height="22" fill="none" stroke="#fff" stroke-width="2.2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
            </button>

            <a href="{{ route('ordenes') }}" wire:navigate
               class="relative z-10 flex items-center justify-center rounded-full"
               style="width:44px;height:44px;margin:0 2px;"
               :style="{ color: active==='ordenes' ? '#fff' : 'rgba(240,244,240,0.45)' }">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z"/>
                </svg>
            </a>

            <a href="{{ route('mi-cuenta') }}" wire:navigate
               class="relative z-10 flex items-center justify-center rounded-full"
               style="width:44px;height:44px;margin:0 2px;"
               :style="{ color: active==='cuenta' ? '#fff' : 'rgba(240,244,240,0.45)' }">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                </svg>
            </a>

        </div>
    </nav>
    @endif

    @livewireScripts

    <script>
    function verdeoToggleTheme() {
        var lightThemes = ['cielo', 'natural', 'light'];
        var cur  = document.documentElement.getAttribute('data-theme') || 'bosque';
        var next = lightThemes.indexOf(cur) >= 0 ? 'bosque' : 'natural';
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
