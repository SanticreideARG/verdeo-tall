<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Orden;

new #[Layout('layouts.app', ['title' => 'Cocina'])] class extends Component {

    public function marcarListaParaEntrega(int $id): void
    {
        $orden = Orden::findOrFail($id);
        if ($orden->estado === 'aprobada') {
            $orden->update(['estado' => 'lista_para_entrega']);
            $this->dispatch('orden-lista', id: $id);
        }
    }

    public function with(): array
    {
        $ordenes = Orden::with(['items.producto'])
            ->where('estado', 'aprobada')
            ->orderBy('updated_at')
            ->get();

        // Resumen agregado: producto × tamaño → total unidades
        $resumen = $ordenes->flatMap->items
            ->groupBy(fn($i) => ($i->producto?->nombre ?? '—') . '|||' . $i->tamano)
            ->map(fn($grupo) => [
                'nombre' => $grupo->first()->producto?->nombre ?? '—',
                'tamano' => $grupo->first()->tamano,
                'total'  => $grupo->sum('cantidad'),
            ])
            ->sortByDesc('total')
            ->values();

        return [
            'ordenes'      => $ordenes,
            'totalOrdenes' => $ordenes->count(),
            'resumen'      => $resumen,
        ];
    }

}; ?>

<div
    wire:poll.10s
    data-orden-count="{{ $totalOrdenes }}"
    x-data="{
        prevCount: {{ $totalOrdenes }},
        showBanner: false,
        init() {
            document.addEventListener('livewire:morph', () => {
                const newCount = parseInt(this.$el.dataset.ordenCount) || 0;
                if (newCount > this.prevCount) {
                    this.showBanner = true;
                    this.playBeep();
                    setTimeout(() => this.showBanner = false, 5000);
                }
                this.prevCount = newCount;
            });
        },
        playBeep() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                [660, 880].forEach((freq, i) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain); gain.connect(ctx.destination);
                    osc.frequency.value = freq;
                    const t = ctx.currentTime + i * 0.18;
                    gain.gain.setValueAtTime(0.35, t);
                    gain.gain.exponentialRampToValueAtTime(0.001, t + 0.15);
                    osc.start(t); osc.stop(t + 0.15);
                });
            } catch(e) {}
        }
    }">

    {{-- New order banner --}}
    <div x-show="showBanner"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 -translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-end="opacity-0"
         style="display:none; position:fixed; top:72px; left:50%; transform:translateX(-50%); z-index:9999;
                background: linear-gradient(135deg,#16a34a,#22c55e); color:#fff; border-radius:16px;
                padding:14px 28px; font-weight:700; font-size:15px; box-shadow:0 8px 32px rgba(22,163,74,.5);
                display:flex; align-items:center; gap:10px;"
         @click="showBanner=false">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
        </svg>
        ¡Nuevo bloque recibido!
    </div>

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: rgba(78,158,90,0.15); border: 1px solid rgba(78,158,90,0.35);">
                <svg width="20" height="20" fill="none" stroke="#4e9e5a" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/>
                </svg>
            </div>
            <div>
                <h2 class="font-condensed font-bold text-xl" style="color: var(--vd-text);">Órdenes de Servicio</h2>
                <p class="text-xs" style="color: var(--vd-muted);">
                    Actualización automática cada 10 seg ·
                    <span style="color: {{ $totalOrdenes > 0 ? '#4e9e5a' : 'var(--vd-muted-2)' }}; font-weight:700;">
                        {{ $totalOrdenes }} {{ $totalOrdenes === 1 ? 'pedido pendiente' : 'pedidos pendientes' }}
                    </span>
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2 text-xs px-3 py-1.5 rounded-full"
             style="background: rgba(78,158,90,0.1); border: 1px solid rgba(78,158,90,0.25); color: var(--vd-muted);">
            <span class="w-2 h-2 rounded-full animate-pulse inline-block" style="background:#4e9e5a;"></span>
            En vivo
        </div>
    </div>

    @if($ordenes->isEmpty())
    {{-- Empty state --}}
    <div class="flex flex-col items-center justify-center py-24 text-center">
        <div class="w-20 h-20 rounded-3xl flex items-center justify-center mb-5"
             style="background: rgba(78,158,90,0.08); border: 2px dashed rgba(78,158,90,0.25);">
            <svg width="36" height="36" fill="none" stroke="#4e9e5a" stroke-width="1.3" viewBox="0 0 24 24" style="opacity:.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <h3 class="font-condensed font-bold text-xl mb-2" style="color: var(--vd-text-soft);">Todo al día</h3>
        <p class="text-sm" style="color: var(--vd-muted-2);">No hay órdenes aprobadas esperando preparación.</p>
    </div>
    @else

    {{-- ── Resumen de producción ── --}}
    <div class="card mb-6">
        <h3 class="font-condensed font-bold uppercase tracking-widest text-xs mb-3"
            style="color: var(--vd-muted); letter-spacing: 1.5px;">
            Resumen de producción
        </h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
            @foreach($resumen as $r)
            <div class="rounded-xl px-3 py-2.5 text-center"
                 style="background: var(--vd-bg-2); border: 1px solid var(--vd-bdr-soft);">
                <p class="text-xs font-semibold truncate mb-0.5" style="color: var(--vd-text-soft);">
                    {{ $r['nombre'] }}
                </p>
                <p class="font-mono text-xs px-2 py-0.5 rounded-full inline-block mb-1"
                   style="background: var(--vd-input-bg); color: var(--vd-muted);">
                    {{ $r['tamano'] }}
                </p>
                <p class="font-condensed font-bold text-2xl leading-tight" style="color: var(--vd-green-lt);">
                    {{ $r['total'] }}
                </p>
                <p class="text-[10px] uppercase tracking-wide" style="color: var(--vd-muted-2);">unidades</p>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ── Bloque de pedidos ── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($ordenes as $o)
        @php
            $dias    = (int) $o->updated_at->diffInDays(now());
            $urgente = $dias >= 1;
            $zonaLabel = match($o->zona) {
                'bsas'      => 'Bs As',
                'valle_nqn' => 'Valle NQN',
                'cordoba'   => 'Córdoba',
                'mendoza'   => 'Mendoza',
                default     => $o->zona,
            };
        @endphp
        <div class="card p-0 overflow-hidden flex flex-col"
             style="{{ $urgente ? 'border-color: rgba(239,68,68,0.4);' : '' }}">

            {{-- Ticket header --}}
            <div class="flex items-center justify-between px-4 pt-3 pb-3"
                 style="border-bottom: 1px solid var(--vd-bdr-soft);
                        {{ $urgente ? 'background: rgba(239,68,68,0.04);' : 'background: var(--vd-bg-2);' }}">
                <div class="flex items-center gap-2 flex-wrap">
                    {{-- Número de orden --}}
                    <span class="font-mono font-bold text-sm" style="color: var(--vd-text);">
                        #{{ $o->numero }}
                    </span>
                    {{-- Zona badge --}}
                    <span class="text-xs px-2 py-0.5 rounded-full font-semibold"
                          style="background: rgba(78,158,90,0.12); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.25);">
                        {{ $zonaLabel }}
                    </span>
                </div>
                {{-- Tiempo badge --}}
                @if($urgente)
                <span class="text-xs font-bold px-2 py-0.5 rounded-full animate-pulse"
                      style="background: rgba(239,68,68,0.15); color: #ef4444; border: 1px solid rgba(239,68,68,0.3);">
                    ⚠ {{ $dias }}d
                </span>
                @else
                <span class="text-xs px-2 py-0.5 rounded-full"
                      style="background: rgba(78,158,90,0.08); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.18);">
                    Hoy
                </span>
                @endif
            </div>

            {{-- Items --}}
            <div class="px-4 py-3 flex-1 space-y-2">
                @foreach($o->items as $item)
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center text-sm font-bold flex-shrink-0"
                              style="background: rgba(78,158,90,0.15); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.25);">
                            {{ $item->cantidad }}
                        </span>
                        <span class="text-sm font-medium truncate" style="color: var(--vd-text);">
                            {{ $item->producto?->nombre ?? '—' }}
                        </span>
                    </div>
                    <span class="text-xs font-mono flex-shrink-0 px-2 py-0.5 rounded-full"
                          style="background: var(--vd-bg-2); color: var(--vd-muted); border: 1px solid var(--vd-bdr-soft);">
                        {{ $item->tamano }}
                    </span>
                </div>
                @endforeach
            </div>

            {{-- Notes --}}
            @if($o->notas)
            <div class="mx-4 mb-3 px-3 py-2 rounded-lg text-xs"
                 style="background: rgba(200,160,48,0.08); border: 1px solid rgba(200,160,48,0.2); color: var(--vd-text-soft);">
                <span style="color: #c8a030; font-weight:700;">Nota: </span>{{ $o->notas }}
            </div>
            @endif

            {{-- Action --}}
            <div class="px-4 pb-4">
                <button wire:click="marcarListaParaEntrega({{ $o->id }})"
                        wire:loading.attr="disabled"
                        wire:target="marcarListaParaEntrega({{ $o->id }})"
                        class="w-full flex items-center justify-center gap-2 font-semibold py-3 rounded-xl text-sm transition-all"
                        style="background: linear-gradient(135deg,#3a7d44,#4e9e5a); color:#fff; border:none; min-height:48px;">
                    <span wire:loading.remove wire:target="marcarListaParaEntrega({{ $o->id }})">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" class="inline mr-1 -mt-0.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                        </svg>
                        Lista para entregar
                    </span>
                    <span wire:loading wire:target="marcarListaParaEntrega({{ $o->id }})">
                        Actualizando…
                    </span>
                </button>
            </div>

        </div>
        @endforeach
    </div>

    @endif
</div>
