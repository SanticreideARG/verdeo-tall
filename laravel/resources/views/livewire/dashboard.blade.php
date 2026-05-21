<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Conversacion;
use App\Models\Orden;
use App\Models\User;
use App\Models\Zona;
use Illuminate\Support\Facades\Redis;

new #[Layout('layouts.app', ['title' => 'Dashboard'])] class extends Component {

    public int   $totalConversaciones   = 0;
    public int   $conversacionesHoy     = 0;
    public int   $mensajesEnCola        = 0;
    public int   $ordenesPendientes     = 0;
    public int   $ordenesHoy            = 0;
    public array $porZona               = [];
    public array $ventasPorZona         = [];
    public array $ultimasConversaciones = [];
    public array $calendarioSemanal     = [];

    public function mount(): void
    {
        if (auth()->user()->isCliente()) {
            $this->redirect(route('portal'), navigate: true);
            return;
        }
        if (auth()->user()->isCocina()) {
            $this->redirect(route('cocina'), navigate: true);
            return;
        }

        $this->totalConversaciones = Conversacion::activas()->count();
        $this->conversacionesHoy   = Conversacion::hoy()->count();
        $this->ordenesPendientes   = Orden::where('estado', 'pendiente')->count();
        $this->ordenesHoy          = Orden::whereDate('created_at', today())->count();

        $weekStart = now()->startOfWeek(\Carbon\Carbon::MONDAY);
        $ordenesXdia = Orden::selectRaw('DATE(created_at) as dia, count(*) as total')
            ->whereBetween('created_at', [$weekStart->copy()->startOfDay(), $weekStart->copy()->addDays(6)->endOfDay()])
            ->groupBy('dia')
            ->pluck('total', 'dia');
        $this->calendarioSemanal = collect(range(0, 6))->map(function ($offset) use ($weekStart, $ordenesXdia) {
            $d = $weekStart->copy()->addDays($offset);
            return [
                'fecha'   => $d->toDateString(),
                'dia'     => mb_strtoupper(substr($d->locale('es')->isoFormat('ddd'), 0, 3)),
                'numero'  => (int) $d->format('j'),
                'ordenes' => (int) ($ordenesXdia->get($d->toDateString(), 0)),
                'hoy'     => $d->isToday(),
                'pasado'  => $d->isPast() && !$d->isToday(),
            ];
        })->all();

        try {
            $this->mensajesEnCola = Redis::llen('queues:default') + Redis::llen('queues:whatsapp');
        } catch (\Exception) {
            $this->mensajesEnCola = 0;
        }

        $activas = Conversacion::activas()
            ->selectRaw('zona, count(*) as total')
            ->groupBy('zona')
            ->pluck('total', 'zona');

        $this->porZona = Zona::where('activa', true)
            ->orderBy('nombre')
            ->get()
            ->map(fn($z) => [
                'zona'    => $z->nombre,
                'numero'  => $z->whatsapp ?? '—',
                'slug'    => $z->slug,
                'activas' => $activas->get($z->slug, 0),
            ])
            ->all();

        $zonaLabels = ['bsas' => 'Buenos Aires', 'valle_nqn' => 'Valle NQN', 'cordoba' => 'Córdoba', 'mendoza' => 'Mendoza'];
        $ventasRaw  = Orden::selectRaw('zona, count(*) as total_ordenes, COALESCE(sum(total),0) as total_ingresos')
            ->whereBetween('created_at', [now()->subDays(29)->startOfDay(), now()->endOfDay()])
            ->groupBy('zona')->get()->keyBy('zona');

        $this->ventasPorZona = collect($zonaLabels)->map(fn($label, $slug) => [
            'slug'    => $slug,
            'label'   => $label,
            'ordenes' => (int) ($ventasRaw->get($slug)?->total_ordenes ?? 0),
            'ingresos'=> (float) ($ventasRaw->get($slug)?->total_ingresos ?? 0),
        ])->values()->all();

        $this->ultimasConversaciones = Conversacion::whereNotNull('ultimo_mensaje_at')
            ->orderByDesc('ultimo_mensaje_at')
            ->limit(5)
            ->get()
            ->map(fn($c) => [
                'id'      => $c->id,
                'nombre'  => $c->nombre ?? $c->telefono,
                'zona'    => $c->zonaLabel(),
                'estado'  => $c->estado,
                'hace'    => $c->ultimo_mensaje_at->diffForHumans(),
                'msg'     => $c->ultimo_mensaje,
            ])->all();
    }

}; ?>

@push('styles')
<style>
/* ── Dashboard responsive layout ─────────────────────────────── */

/* 3-col body grid */
.vd-db-body {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    align-items: start;
}

/* Calendario: 7 columnas horizontales */
.vd-db-week {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 3px;
}

/* Split screen (~2 ventanas lado a lado): ≤1150 px */
@media (max-width: 1150px) {
    .vd-db-body {
        grid-template-columns: repeat(2, 1fr);
    }
    /* Tarjeta Zonas ocupa ancho completo */
    .vd-db-zones {
        grid-column: 1 / -1;
    }
    /* Dentro de Zonas: Zonas activas + Sistema en 2 columnas */
    .vd-db-zones-inner {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        align-items: start;
    }
    .vd-db-zones-divider {
        display: none;  /* ocultar el hr vertical entre secciones */
    }
}

/* Pantalla estrecha (<768 px) */
@media (max-width: 768px) {
    .vd-db-body {
        grid-template-columns: 1fr;
    }
    .vd-db-zones {
        grid-column: auto;
    }
    .vd-db-zones-inner {
        display: block;
    }
    .vd-db-zones-sistema {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid var(--vd-bdr-soft);
    }
}

/* Header: comprimir pills en pantalla media */
@media (max-width: 900px) {
    .vd-db-header-right {
        gap: 6px;
    }
}

/* Ventas 30d: 4 cols en pantalla ancha, 2 cols en split/narrow */
@media (max-width: 900px) {
    .vd-db-ventas-grid {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}
</style>
@endpush

<div class="space-y-4">

    {{-- ── Header compacto ── --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="font-condensed font-bold text-xl leading-tight" style="color: var(--vd-text);">
                Hola, {{ auth()->user()->name }}
            </h2>
            <p style="font-size: 11px; color: var(--vd-muted);">{{ now()->translatedFormat('l j \d\e F, Y') }}</p>
        </div>
        <div class="vd-db-header-right flex flex-wrap items-center gap-2">
            {{-- Stat pills --}}
            <a href="{{ route('conversaciones') }}" wire:navigate
               class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold"
               style="background: var(--vd-bg-2); border: 1px solid var(--vd-bdr); color: var(--vd-text-soft); text-decoration: none;"
               onmouseover="this.style.borderColor='rgba(78,158,90,0.4)'" onmouseout="this.style.borderColor='var(--vd-bdr)'">
                <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                </svg>
                <span style="color: var(--vd-text);">{{ $conversacionesHoy }}</span>
                <span style="color: var(--vd-muted);">chats hoy</span>
            </a>
            <a href="{{ route('conversaciones') }}" wire:navigate
               class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold"
               style="background: rgba(78,158,90,0.08); border: 1px solid rgba(78,158,90,0.2); color: #4e9e5a; text-decoration: none;"
               onmouseover="this.style.background='rgba(78,158,90,0.15)'" onmouseout="this.style.background='rgba(78,158,90,0.08)'">
                <span class="w-1.5 h-1.5 rounded-full animate-pulse inline-block" style="background:#4e9e5a;"></span>
                <span>{{ $totalConversaciones }}</span>
                <span style="color: rgba(78,158,90,0.7);">activas</span>
            </a>
            <a href="{{ route('ordenes') }}" wire:navigate
               class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold"
               style="{{ $ordenesPendientes > 0
                   ? 'background: rgba(200,160,48,0.1); border: 1px solid rgba(200,160,48,0.3); color: #c8a030;'
                   : 'background: var(--vd-bg-2); border: 1px solid var(--vd-bdr); color: var(--vd-text-soft);' }}
                   text-decoration: none;"
               onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
                <span>{{ $ordenesPendientes }}</span>
                <span>pend.</span>
            </a>
            {{-- Acciones --}}
            <a href="{{ route('conversaciones') }}" wire:navigate class="btn-secondary text-xs px-3 py-1.5">Chats</a>
            <a href="{{ route('ordenes') }}" wire:navigate class="btn-primary text-xs px-3 py-1.5">+ Orden</a>
        </div>
    </div>

    {{-- ── Body: 3 columnas → 2 en split-screen → 1 en mobile ── --}}
    <div class="vd-db-body">

        {{-- 1. Calendario semanal --}}
        <div class="card py-3 px-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-condensed font-bold uppercase tracking-widest"
                      style="color: var(--vd-muted); letter-spacing: 1px;">Esta semana</span>
                <span class="text-xs" style="color: var(--vd-muted-2);">
                    {{ array_sum(array_column($calendarioSemanal, 'ordenes')) }} total
                    · <span style="color: var(--vd-green-lt);">{{ $ordenesHoy }} hoy</span>
                </span>
            </div>
            <div class="vd-db-week">
                @foreach($calendarioSemanal as $dia)
                <div style="display:flex; flex-direction:column; align-items:center; padding:5px 2px; border-radius:8px; text-align:center;
                            {{ $dia['hoy'] ? 'background:rgba(78,158,90,0.15); border:1px solid rgba(78,158,90,0.35);' : '' }}">
                    <span style="font-size:9px; font-weight:700; line-height:1.2;
                                 color:{{ $dia['hoy'] ? '#4e9e5a' : 'var(--vd-muted-2)' }};">
                        {{ substr($dia['dia'], 0, 1) }}
                    </span>
                    <span style="font-size:13px; font-weight:700; line-height:1.3;
                                 color:{{ $dia['hoy'] ? 'var(--vd-text)' : ($dia['pasado'] ? 'var(--vd-muted-2)' : 'var(--vd-text-soft)') }};">
                        {{ $dia['numero'] }}
                    </span>
                    @if($dia['ordenes'] > 0)
                    <span style="font-size:10px; font-weight:700; color:#4e9e5a; line-height:1.3;">{{ $dia['ordenes'] }}</span>
                    @else
                    <span style="display:inline-block; width:4px; height:4px; border-radius:50%; background:var(--vd-bdr); margin-top:2px;"></span>
                    @endif
                </div>
                @endforeach
            </div>
            <a href="{{ route('estadisticas') }}" wire:navigate
               class="flex items-center justify-center text-xs transition-colors"
               style="margin-top:8px; padding-top:8px; border-top:1px solid var(--vd-bdr-soft); color:var(--vd-muted-2); text-decoration:none;"
               onmouseover="this.style.color='var(--vd-green-lt)'" onmouseout="this.style.color='var(--vd-muted-2)'">
                Estadísticas →
            </a>
        </div>

        {{-- 2. Widget de chats --}}
        <div class="card py-3 px-4">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full animate-pulse inline-block" style="background:#4e9e5a;"></span>
                    <span class="text-xs font-condensed font-bold uppercase tracking-widest"
                          style="color:var(--vd-muted); letter-spacing:1px;">Conversaciones</span>
                </div>
                <a href="{{ route('conversaciones') }}" wire:navigate
                   class="text-xs transition-colors" style="color:var(--vd-muted-2); text-decoration:none;"
                   onmouseover="this.style.color='var(--vd-green-lt)'" onmouseout="this.style.color='var(--vd-muted-2)'">
                    Ver todas →
                </a>
            </div>
            @if(empty($ultimasConversaciones))
                <p class="text-xs text-center py-4" style="color:var(--vd-muted);">Sin conversaciones.</p>
            @else
            <div>
                @foreach(array_slice($ultimasConversaciones, 0, 5) as $conv)
                <a href="{{ route('conversaciones.ver', $conv['id']) }}" wire:navigate
                   class="flex items-center gap-2 rounded-lg transition-colors"
                   style="padding:5px 4px; text-decoration:none;"
                   onmouseover="this.style.background='var(--vd-bg-2)'" onmouseout="this.style.background='transparent'">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center font-bold flex-shrink-0"
                         style="font-size:10px; background:rgba(58,125,68,0.15); color:#4e9e5a;">
                        {{ strtoupper(substr($conv['nombre'], 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold truncate" style="color:var(--vd-text);">{{ $conv['nombre'] }}</p>
                        <p class="truncate" style="font-size:10px; color:var(--vd-muted);">
                            {{ \Illuminate\Support\Str::limit($conv['msg'] ?? '…', 34) }}
                        </p>
                    </div>
                    <div class="flex-shrink-0 flex flex-col items-end" style="gap:2px;">
                        <div class="w-1.5 h-1.5 rounded-full"
                             style="background:{{ match($conv['estado']) { 'abierta' => '#4e9e5a', 'esperando' => '#c8a030', default => 'var(--vd-muted-2)' } }};"></div>
                        <span style="font-size:9px; color:var(--vd-muted-2); white-space:nowrap;">{{ $conv['hace'] }}</span>
                    </div>
                </a>
                @endforeach
            </div>
            @endif
        </div>

        {{-- 3. Zonas + Sistema --}}
        <div class="card py-3 px-4 vd-db-zones">
            <div class="vd-db-zones-inner">
                {{-- Zonas activas --}}
                <div>
                    <span class="text-xs font-condensed font-bold uppercase tracking-widest"
                          style="color:var(--vd-muted); letter-spacing:1px;">Zonas activas</span>
                    <div style="margin-top:8px;">
                        @forelse($porZona as $z)
                        <div class="flex items-center justify-between" style="padding:3px 0;">
                            <div class="flex items-center gap-2">
                                <div class="zone-dot"></div>
                                <span class="text-xs font-medium" style="color:var(--vd-text-soft);">{{ $z['zona'] }}</span>
                                <span class="font-mono" style="font-size:10px; color:var(--vd-muted-2);">·{{ $z['numero'] }}</span>
                            </div>
                            <span class="font-condensed font-bold text-sm"
                                  style="color:{{ $z['activas'] > 0 ? 'var(--vd-green-lt)' : 'var(--vd-muted-2)' }};">
                                {{ $z['activas'] }}
                            </span>
                        </div>
                        @empty
                        <p class="text-xs" style="color:var(--vd-muted);">Sin zonas.</p>
                        @endforelse
                    </div>
                </div>

                {{-- Sistema --}}
                <div class="vd-db-zones-sistema" style="border-top:1px solid var(--vd-bdr-soft); margin-top:10px; padding-top:8px;">
                    <span class="text-xs font-condensed font-bold uppercase tracking-widest"
                          style="color:var(--vd-muted); letter-spacing:1px;">Sistema</span>
                    <div class="flex flex-wrap" style="gap:6px; margin-top:6px;">
                        <span class="text-xs px-2 py-0.5 rounded-full"
                              style="{{ $mensajesEnCola === 0
                                  ? 'background:rgba(78,158,90,0.1); border:1px solid rgba(78,158,90,0.2); color:#4e9e5a;'
                                  : 'background:rgba(200,160,48,0.1); border:1px solid rgba(200,160,48,0.3); color:#c8a030;' }}">
                            Horizon {{ $mensajesEnCola === 0 ? '✓' : $mensajesEnCola }}
                        </span>
                        <span class="text-xs px-2 py-0.5 rounded-full"
                              style="background:rgba(78,158,90,0.1); border:1px solid rgba(78,158,90,0.2); color:#4e9e5a;">
                            Evolution ✓
                        </span>
                        <span class="text-xs px-2 py-0.5 rounded-full"
                              style="background:rgba(78,158,90,0.1); border:1px solid rgba(78,158,90,0.2); color:#4e9e5a;">
                            n8n ✓
                        </span>
                        <span class="text-xs px-2 py-0.5 rounded-full"
                              style="background:rgba(96,165,250,0.08); border:1px solid rgba(96,165,250,0.2); color:#60a5fa;">
                            Ollama ✓
                        </span>
                    </div>
                    <div class="flex gap-2 mt-2">
                        <a href="/horizon" target="_blank" class="btn-secondary text-xs flex-1 text-center py-1">Horizon</a>
                        <a href="{{ route('n8n') }}" target="_blank" class="btn-secondary text-xs flex-1 text-center py-1">n8n</a>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- ── Ventas 30d — 4 cards inline ── --}}
    <div class="card py-3 px-4">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-condensed font-bold uppercase tracking-widest"
                  style="color: var(--vd-muted); letter-spacing: 1px;">Ventas últimos 30 días</span>
            <a href="{{ route('estadisticas') }}" wire:navigate
               class="text-xs transition-colors" style="color: var(--vd-muted-2); text-decoration: none;"
               onmouseover="this.style.color='var(--vd-green-lt)'" onmouseout="this.style.color='var(--vd-muted-2)'">
                Estadísticas →
            </a>
        </div>
        <div class="vd-db-ventas-grid" style="display:grid; grid-template-columns:repeat(4,1fr); gap:12px;">
            @foreach($ventasPorZona as $vz)
            <div class="rounded-xl px-3 py-2.5" style="background: var(--vd-bg-2); border: 1px solid var(--vd-bdr-soft);">
                <div class="flex items-center gap-1.5 mb-1">
                    <div class="w-1.5 h-1.5 rounded-full flex-shrink-0"
                         style="background: #4e9e5a; opacity: {{ $vz['ordenes'] > 0 ? '1' : '0.25' }};"></div>
                    <span class="text-xs font-semibold truncate" style="color: var(--vd-text-soft);">{{ $vz['label'] }}</span>
                </div>
                <p class="font-condensed font-bold text-xl leading-none" style="color: var(--vd-text);">
                    {{ $vz['ordenes'] ?: '—' }}
                </p>
                <p class="text-xs mt-0.5"
                   style="color: {{ $vz['ingresos'] > 0 ? '#4e9e5a' : 'var(--vd-muted-2)' }};">
                    {{ $vz['ingresos'] > 0 ? '$'.number_format($vz['ingresos'],0,',','.') : 'sin ingresos' }}
                </p>
            </div>
            @endforeach
        </div>
        @php
            $totOrd = array_sum(array_column($ventasPorZona, 'ordenes'));
            $totIng = array_sum(array_column($ventasPorZona, 'ingresos'));
        @endphp
        @if($totOrd > 0)
        <div class="flex items-center justify-between mt-2 pt-2" style="border-top: 1px solid var(--vd-bdr-soft);">
            <span class="text-xs" style="color: var(--vd-muted);">Total</span>
            <span class="text-xs font-mono font-semibold" style="color: var(--vd-text);">
                {{ $totOrd }} órdenes
                @if($totIng > 0)
                · <span style="color: #4e9e5a;">${{ number_format($totIng, 0, ',', '.') }}</span>
                @endif
            </span>
        </div>
        @endif
    </div>

</div>
