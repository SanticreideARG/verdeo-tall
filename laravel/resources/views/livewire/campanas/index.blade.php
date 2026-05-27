<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\Campana;
use App\Models\Conversacion;
use App\Jobs\EnviarMensajeCampana;

new #[Layout('layouts.app', ['title' => 'Campañas'])] class extends Component {

    /* ── Formulario ──────────────────────────────────────────────────────── */
    public bool   $showForm     = false;
    public string $nombre       = '';
    public string $mensaje      = '';
    public string $filtroZona   = '';
    public string $filtroEstado = '';

    /* ── UI ───────────────────────────────────────────────────────────────── */
    public ?int $cancelandoId = null;

    public function mount(): void
    {
        if (! (auth()->user()->isAdmin() || auth()->user()->isResponsableZona())) {
            $this->redirect(route('dashboard'), navigate: true);
        }
    }

    /* ── Query de destinatarios (reutilizada en preview y al lanzar) ──────── */
    private function queryDestinatarios()
    {
        $q = Conversacion::whereNotNull('telefono')
                         ->where('telefono', '!=  ', '');

        if ($this->filtroZona) {
            $q->where('zona', $this->filtroZona);
        }
        if ($this->filtroEstado) {
            $q->where('estado', $this->filtroEstado);
        }

        return $q;
    }

    /* ── Sustituir variables en el mensaje ───────────────────────────────── */
    private function sustituir(string $mensaje, Conversacion $c): string
    {
        $ultimoPedido = 'sin pedidos';
        if ($c->telefono) {
            $user = \App\Models\User::where('whatsapp', $c->telefono)->first();
            if ($user) {
                $ultimoPedido = $user->ordenes()->latest()->first()?->numero ?? 'sin pedidos';
            }
        }

        $zonaLabel = match($c->zona ?? '') {
            'bsas'      => 'Buenos Aires',
            'valle_nqn' => 'Valle NQN / Roca',
            'cordoba'   => 'Córdoba',
            'mendoza'   => 'Mendoza',
            default     => $c->zona ?? '',
        };

        return str_replace(
            ['{{nombre}}', '{{zona}}', '{{ultimo_pedido}}'],
            [$c->nombre ?? 'Cliente', $zonaLabel, $ultimoPedido],
            $mensaje
        );
    }

    /* ── Acciones de formulario ──────────────────────────────────────────── */
    public function abrirFormulario(): void
    {
        $this->showForm     = true;
        $this->nombre       = '';
        $this->mensaje      = '';
        $this->filtroZona   = '';
        $this->filtroEstado = '';
    }

    public function cerrarFormulario(): void
    {
        $this->showForm = false;
    }

    public function lanzar(): void
    {
        $this->validate([
            'nombre'  => 'required|min:3|max:150',
            'mensaje' => 'required|min:10|max:1000',
        ]);

        $contactos = $this->queryDestinatarios()
                         ->orderBy('updated_at', 'desc')
                         ->get(['id', 'telefono', 'nombre', 'zona']);

        if ($contactos->isEmpty()) {
            session()->flash('error', 'No hay contactos que coincidan con los filtros seleccionados.');
            return;
        }

        $campana = Campana::create([
            'numero'              => Campana::generarNumero(),
            'nombre'              => $this->nombre,
            'mensaje'             => $this->mensaje,
            'filtro_zona'         => $this->filtroZona  ?: null,
            'filtro_estado'       => $this->filtroEstado ?: null,
            'estado'              => 'enviando',
            'total_destinatarios' => $contactos->count(),
            'creado_por'          => auth()->id(),
            'lanzada_at'          => now(),
        ]);

        // Despachar un job por contacto, espaciados 2 segundos entre sí
        foreach ($contactos as $idx => $c) {
            EnviarMensajeCampana::dispatch(
                campanaId:    $campana->id,
                telefono:     preg_replace('/\D/', '', $c->telefono),
                mensajeFinal: $this->sustituir($this->mensaje, $c),
                zona:         $c->zona ?? '',
            )->delay(now()->addSeconds($idx * 2));
        }

        $this->showForm = false;
        session()->flash('success', "Campaña \"{$campana->nombre}\" lanzada — {$contactos->count()} mensajes en cola.");
    }

    /* ── Cancelar campaña en curso ───────────────────────────────────────── */
    public function pedirCancelar(int $id): void
    {
        $this->cancelandoId = $id;
    }

    public function cancelar(): void
    {
        if (! $this->cancelandoId) return;
        $campana = Campana::find($this->cancelandoId);
        if ($campana && $campana->estado === 'enviando') {
            // Los jobs pendientes en la queue seguirán procesándose
            // pero marcamos la campaña como cancelada para que la UI lo refleje.
            $campana->update(['estado' => 'cancelada']);
            session()->flash('success', "Campaña \"{$campana->nombre}\" marcada como cancelada.");
        }
        $this->cancelandoId = null;
    }

    /* ── Data ────────────────────────────────────────────────────────────── */
    public function with(): array
    {
        $activas = Campana::whereIn('estado', ['enviando'])
            ->orderByDesc('lanzada_at')
            ->get();

        $historial = Campana::whereIn('estado', ['completada', 'cancelada', 'borrador'])
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        // Preview (solo cuando el formulario está abierto)
        $previewCount   = 0;
        $previewMensaje = '';

        if ($this->showForm && $this->mensaje) {
            $q            = $this->queryDestinatarios();
            $previewCount = $q->count();
            $primero      = $q->orderBy('updated_at', 'desc')->first();
            if ($primero) {
                $previewMensaje = $this->sustituir($this->mensaje, $primero);
            } else {
                $previewMensaje = $this->mensaje;
            }
        } elseif ($this->showForm) {
            $previewCount = $this->queryDestinatarios()->count();
        }

        $zonas = [
            ''          => 'Todas las zonas',
            'bsas'      => 'Buenos Aires',
            'valle_nqn' => 'Valle NQN / Roca',
            'cordoba'   => 'Córdoba',
            'mendoza'   => 'Mendoza',
        ];

        $estados = [
            ''          => 'Todos los estados',
            'abierta'   => 'Abierta',
            'esperando' => 'Esperando',
            'cerrada'   => 'Cerrada',
        ];

        return compact('activas', 'historial', 'previewCount', 'previewMensaje', 'zonas', 'estados');
    }

}; ?>

<div>

{{-- ── Flash ──────────────────────────────────────────────────────────────── --}}
@if(session('success'))
<div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
     class="mb-4 badge-green px-3 py-2 text-sm rounded-xl">{{ session('success') }}</div>
@endif
@if(session('error'))
<div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
     class="mb-4 px-3 py-2 text-sm rounded-xl"
     style="background:rgba(239,68,68,0.1);color:#fca5a5;border:1px solid rgba(239,68,68,0.3);">
    {{ session('error') }}
</div>
@endif

{{-- ══════════════════════════════════════════════════════
     FORMULARIO — Nueva Campaña
══════════════════════════════════════════════════════ --}}
@if(! $showForm)

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold" style="color:var(--vd-text);">Campañas</h1>
        <p class="text-sm mt-0.5" style="color:var(--vd-muted-2);">Envíos masivos por WhatsApp con seguimiento en tiempo real.</p>
    </div>
    <button wire:click="abrirFormulario"
            class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold transition-all"
            style="background:rgba(78,158,90,0.2);color:#4ade80;border:1px solid rgba(78,158,90,0.4);">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Nueva campaña
    </button>
</div>

@else

{{-- ── Form abierto ──────────────────────────────────────────────────────── --}}
<div class="mb-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="font-bold text-base" style="color:var(--vd-text);">Nueva campaña</h2>
        <button wire:click="cerrarFormulario" style="color:var(--vd-muted-2);" class="text-xl leading-none">×</button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Columna izquierda — formulario --}}
        <div class="card flex flex-col gap-5">

            {{-- Nombre --}}
            <div>
                <label class="label">Nombre interno *</label>
                <input wire:model.live="nombre"
                       type="text"
                       placeholder="Ej: Promo mayo — Buenos Aires"
                       class="input w-full">
                @error('nombre')
                <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Filtros --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label">Zona</label>
                    <select wire:model.live="filtroZona" class="input w-full">
                        @foreach($zonas as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Estado conversación</label>
                    <select wire:model.live="filtroEstado" class="input w-full">
                        @foreach($estados as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Destinatarios --}}
            <div class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm"
                 style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--vd-muted-2);flex-shrink:0;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span style="color:var(--vd-muted);">
                    <span class="font-bold" style="color:{{ $previewCount > 0 ? '#4ade80' : '#fca5a5' }};">
                        {{ $previewCount }}
                    </span>
                    contacto{{ $previewCount !== 1 ? 's' : '' }} recibirán este mensaje
                </span>
            </div>

            {{-- Mensaje --}}
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="label">Mensaje *</label>
                    <span class="text-xs" style="color:var(--vd-muted-2);">{{ strlen($mensaje) }}/1000</span>
                </div>
                <textarea wire:model.live="mensaje"
                          rows="6"
                          maxlength="1000"
                          placeholder="Escribí el mensaje aquí…"
                          class="input w-full resize-none font-mono text-sm"></textarea>
                @error('mensaje')
                <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p>
                @enderror

                {{-- Variables disponibles --}}
                <div class="flex flex-wrap gap-1.5 mt-2">
                    <span class="text-xs" style="color:var(--vd-muted-2);">Variables:</span>
                    @foreach(['{{nombre}}', '{{zona}}', '{{ultimo_pedido}}'] as $var)
                    <button type="button"
                            x-data
                            @click="
                                const ta = $el.closest('.card').querySelector('textarea');
                                const start = ta.selectionStart;
                                const end = ta.selectionEnd;
                                const val = ta.value;
                                ta.value = val.slice(0, start) + '{{ $var }}' + val.slice(end);
                                ta.dispatchEvent(new Event('input'));
                                ta.setSelectionRange(start + {{ strlen($var) }}, start + {{ strlen($var) }});
                                ta.focus();
                            "
                            class="text-xs px-2 py-0.5 rounded font-mono cursor-pointer transition-all"
                            style="background:rgba(168,85,247,0.12);color:#c084fc;border:1px solid rgba(168,85,247,0.25);">
                        {{ $var }}
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Botones --}}
            <div class="flex gap-3 pt-1">
                <button wire:click="cerrarFormulario"
                        class="flex-1 py-2.5 rounded-xl text-sm font-semibold"
                        style="background:rgba(255,255,255,0.05);color:var(--vd-muted);border:1px solid rgba(255,255,255,0.08);">
                    Cancelar
                </button>
                <button wire:click="lanzar"
                        wire:loading.attr="disabled"
                        wire:target="lanzar"
                        @disabled($previewCount === 0)
                        class="flex-1 py-2.5 rounded-xl text-sm font-bold transition-all"
                        style="{{ $previewCount > 0
                            ? 'background:rgba(78,158,90,0.25);color:#4ade80;border:1px solid rgba(78,158,90,0.5);cursor:pointer;'
                            : 'background:rgba(255,255,255,0.04);color:#475569;border:1px solid rgba(255,255,255,0.06);cursor:not-allowed;' }}">
                    <span wire:loading.remove wire:target="lanzar">
                        🚀 Lanzar {{ $previewCount > 0 ? "($previewCount)" : '' }}
                    </span>
                    <span wire:loading wire:target="lanzar">Encolando mensajes…</span>
                </button>
            </div>
        </div>

        {{-- Columna derecha — preview --}}
        <div>
            <div class="card h-full flex flex-col" style="min-height:320px;">
                <p class="text-xs font-bold uppercase tracking-wider mb-3" style="color:var(--vd-muted-2);">
                    Preview del mensaje
                </p>

                @if($previewMensaje)
                {{-- Burbuja estilo WhatsApp --}}
                <div class="flex-1">
                    <div class="inline-block max-w-xs px-3 py-2 rounded-2xl rounded-tl-sm text-sm leading-relaxed"
                         style="background:#1f2d1f;border:1px solid rgba(78,158,90,0.2);color:#e2e8f0;white-space:pre-wrap;">{{ $previewMensaje }}</div>
                    <p class="text-xs mt-1" style="color:var(--vd-muted-2);">
                        Basado en el primer contacto del filtro actual
                    </p>
                </div>
                @else
                <div class="flex-1 flex items-center justify-center text-center px-4">
                    <div>
                        <div class="text-4xl mb-3">💬</div>
                        <p class="text-sm" style="color:var(--vd-muted-2);">
                            Escribí el mensaje para ver la preview
                        </p>
                    </div>
                </div>
                @endif

                @if($previewCount === 0 && ($filtroZona || $filtroEstado))
                <div class="mt-auto pt-4">
                    <div class="px-3 py-2 rounded-xl text-xs text-center"
                         style="background:rgba(239,68,68,0.08);color:#fca5a5;border:1px solid rgba(239,68,68,0.2);">
                        ⚠️ Ningún contacto coincide con los filtros actuales
                    </div>
                </div>
                @endif
            </div>
        </div>

    </div>
</div>

@endif

{{-- ══════════════════════════════════════════════════════
     EN CURSO — con polling cada 3 segundos
══════════════════════════════════════════════════════ --}}
@if($activas->isNotEmpty())
<div class="mb-6" wire:poll.3s>
    <h2 class="font-bold text-base mb-3 flex items-center gap-2" style="color:var(--vd-text);">
        En curso
        <span class="w-2 h-2 rounded-full inline-block animate-pulse"
              style="background:#4ade80;box-shadow:0 0 6px rgba(74,222,128,0.6);"></span>
    </h2>

    <div class="flex flex-col gap-4">
    @foreach($activas as $c)
    @php $pct = $c->porcentaje(); @endphp
    <div class="card">
        <div class="flex items-start justify-between gap-4 mb-3">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-mono text-xs font-semibold" style="color:var(--vd-muted);">{{ $c->numero }}</span>
                    <span class="font-bold text-sm" style="color:var(--vd-text);">{{ $c->nombre }}</span>
                </div>
                <p class="text-xs mt-0.5" style="color:var(--vd-muted-2);">
                    Lanzada {{ $c->lanzada_at?->diffForHumans() }}
                    @if($c->filtro_zona || $c->filtro_estado)
                    ·
                    @if($c->filtro_zona)
                    <span>{{ match($c->filtro_zona) {
                        'bsas' => 'Buenos Aires',
                        'valle_nqn' => 'Valle NQN',
                        'cordoba' => 'Córdoba',
                        'mendoza' => 'Mendoza',
                        default => $c->filtro_zona,
                    } }}</span>
                    @endif
                    @if($c->filtro_estado)
                    <span>· {{ $c->filtro_estado }}</span>
                    @endif
                    @endif
                </p>
            </div>
            <button wire:click="pedirCancelar({{ $c->id }})"
                    class="flex-shrink-0 text-xs px-2.5 py-1 rounded-lg"
                    style="background:rgba(239,68,68,0.08);color:#fca5a5;border:1px solid rgba(239,68,68,0.2);">
                Cancelar
            </button>
        </div>

        {{-- Barra de progreso --}}
        <div class="mb-2">
            <div class="flex justify-between text-xs mb-1" style="color:var(--vd-muted-2);">
                <span>{{ $c->total_enviados + $c->total_fallidos }} / {{ $c->total_destinatarios }} procesados</span>
                <span>{{ $pct }}%</span>
            </div>
            <div class="rounded-full overflow-hidden" style="background:rgba(255,255,255,0.07);height:8px;">
                <div class="h-full rounded-full transition-all duration-500"
                     style="width:{{ $pct }}%;background:linear-gradient(90deg,#4ade80,#22c55e);"></div>
            </div>
        </div>

        {{-- Contadores --}}
        <div class="flex gap-4 text-xs">
            <span style="color:#4ade80;">✓ {{ $c->total_enviados }} enviados</span>
            @if($c->total_fallidos > 0)
            <span style="color:#fca5a5;">✗ {{ $c->total_fallidos }} fallidos</span>
            @endif
            <span style="color:var(--vd-muted-2);">⏳ {{ $c->total_destinatarios - $c->total_enviados - $c->total_fallidos }} pendientes</span>
        </div>
    </div>
    @endforeach
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════
     HISTORIAL
══════════════════════════════════════════════════════ --}}
@if($historial->isNotEmpty())
<div>
    <h2 class="font-bold text-base mb-3" style="color:var(--vd-text);">Historial</h2>

    <div class="card p-0 overflow-hidden">
        <table class="w-full text-sm">
            <thead style="background:var(--vd-bg-2);border-bottom:1px solid var(--vd-bdr);">
                <tr>
                    <th class="text-left px-4 py-3 text-xs font-bold uppercase tracking-wide" style="color:var(--vd-muted-2);">Campaña</th>
                    <th class="text-left px-4 py-3 text-xs font-bold uppercase tracking-wide hidden sm:table-cell" style="color:var(--vd-muted-2);">Filtros</th>
                    <th class="text-center px-4 py-3 text-xs font-bold uppercase tracking-wide" style="color:var(--vd-muted-2);">Resultado</th>
                    <th class="text-right px-4 py-3 text-xs font-bold uppercase tracking-wide" style="color:var(--vd-muted-2);">Fecha</th>
                </tr>
            </thead>
            <tbody>
            @foreach($historial as $c)
            <tr style="border-bottom:1px solid var(--vd-bdr-soft);">
                <td class="px-4 py-3">
                    <p class="font-semibold text-sm" style="color:var(--vd-text);">{{ $c->nombre }}</p>
                    <p class="font-mono text-xs" style="color:var(--vd-muted-2);">{{ $c->numero }}</p>
                </td>
                <td class="px-4 py-3 hidden sm:table-cell">
                    <div class="flex flex-wrap gap-1">
                        @if($c->filtro_zona)
                        <span class="text-xs px-1.5 py-0.5 rounded"
                              style="background:rgba(59,130,246,0.1);color:#93c5fd;">
                            {{ match($c->filtro_zona) {
                                'bsas' => 'Buenos Aires',
                                'valle_nqn' => 'Valle NQN',
                                'cordoba' => 'Córdoba',
                                'mendoza' => 'Mendoza',
                                default => $c->filtro_zona,
                            } }}
                        </span>
                        @else
                        <span class="text-xs" style="color:var(--vd-muted-2);">Todas las zonas</span>
                        @endif
                        @if($c->filtro_estado)
                        <span class="text-xs px-1.5 py-0.5 rounded"
                              style="background:rgba(255,255,255,0.06);color:var(--vd-muted);">
                            {{ $c->filtro_estado }}
                        </span>
                        @endif
                    </div>
                </td>
                <td class="px-4 py-3 text-center">
                    @if($c->estado === 'completada')
                        <div class="flex items-center justify-center gap-3 text-xs">
                            <span style="color:#4ade80;">✓ {{ $c->total_enviados }}</span>
                            @if($c->total_fallidos > 0)
                            <span style="color:#fca5a5;">✗ {{ $c->total_fallidos }}</span>
                            @endif
                        </div>
                    @elseif($c->estado === 'cancelada')
                        <span class="text-xs px-2 py-0.5 rounded"
                              style="background:rgba(239,68,68,0.1);color:#fca5a5;">Cancelada</span>
                    @else
                        <span class="text-xs" style="color:var(--vd-muted-2);">—</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right text-xs" style="color:var(--vd-muted-2);">
                    {{ ($c->lanzada_at ?? $c->created_at)?->format('d/m H:i') }}
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@if($activas->isEmpty() && $historial->isEmpty() && ! $showForm)
<div class="card text-center py-20">
    <div class="text-5xl mb-4">📣</div>
    <p class="font-bold text-base mb-1" style="color:var(--vd-text-soft);">Sin campañas todavía</p>
    <p class="text-sm" style="color:var(--vd-muted-2);">Creá tu primera campaña para enviar mensajes masivos por WhatsApp.</p>
</div>
@endif

{{-- ── Modal cancelar ───────────────────────────────────────────────────────── --}}
@if($cancelandoId)
<div class="fixed inset-0 z-50 flex items-center justify-center p-4"
     style="background:rgba(0,0,0,0.65);backdrop-filter:blur(4px);">
    <div class="w-full max-w-sm rounded-2xl p-6 text-center"
         style="background:#1e293b;border:1px solid rgba(239,68,68,0.3);box-shadow:0 24px 80px rgba(0,0,0,0.5);">
        <div class="text-4xl mb-3">⚠️</div>
        <h3 class="font-bold text-base mb-2" style="color:#fca5a5;">Cancelar campaña</h3>
        <p class="text-sm mb-6" style="color:var(--vd-muted-2);">
            Los mensajes ya encolados que aún no se enviaron serán descartados progresivamente.
            Los que ya se enviaron no se pueden revertir.
        </p>
        <div class="flex gap-3">
            <button wire:click="$set('cancelandoId', null)"
                    class="flex-1 py-2.5 rounded-xl text-sm font-semibold"
                    style="background:rgba(255,255,255,0.05);color:var(--vd-muted);border:1px solid rgba(255,255,255,0.08);">
                Volver
            </button>
            <button wire:click="cancelar"
                    wire:loading.attr="disabled"
                    wire:target="cancelar"
                    class="flex-1 py-2.5 rounded-xl text-sm font-bold"
                    style="background:rgba(239,68,68,0.2);color:#fca5a5;border:1px solid rgba(239,68,68,0.4);">
                <span wire:loading.remove wire:target="cancelar">Sí, cancelar</span>
                <span wire:loading wire:target="cancelar">…</span>
            </button>
        </div>
    </div>
</div>
@endif

</div>
