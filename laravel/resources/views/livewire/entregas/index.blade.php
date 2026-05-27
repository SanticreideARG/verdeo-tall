<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Orden;
use App\Models\HojaRuta;
use App\Models\Zona;

new #[Layout('layouts.app', ['title' => 'Entregas'])] class extends Component {

    public string $ciudadActiva         = '';
    public array  $ciudades             = [];
    public array  $seleccionados        = [];
    public bool   $showCrearHR          = false;
    public string $transportistaNombre  = '';
    public string $transportistaTel     = '';
    public string $hrNotas              = '';
    public ?int   $cancelandoHR         = null;
    public array  $hrExpandidas         = [];

    public function mount(): void
    {
        $user = auth()->user();
        if (! ($user->isAdmin() || $user->isResponsableZona())) {
            $this->redirect(route('dashboard'), navigate: true);
            return;
        }

        $this->ciudades     = Zona::whereNotNull('ciudad')
                                  ->orderBy('ciudad')
                                  ->distinct()
                                  ->pluck('ciudad')
                                  ->toArray();
        $this->ciudadActiva = $this->ciudades[0] ?? '';
        $this->preseleccionar();
    }

    private function preseleccionar(): void
    {
        if (! $this->ciudadActiva) return;
        $slugs = Zona::where('ciudad', $this->ciudadActiva)->pluck('slug')->toArray();
        $this->seleccionados = Orden::whereIn('zona', $slugs)
            ->where('estado', 'lista_para_entrega')
            ->whereNull('hoja_ruta_id')
            ->pluck('id')
            ->toArray();
    }

    public function setCiudad(string $c): void
    {
        $this->ciudadActiva = $c;
        $this->showCrearHR  = false;
        $this->cancelandoHR = null;
        $this->preseleccionar();
    }

    public function toggleOrden(int $id): void
    {
        $this->seleccionados = in_array($id, $this->seleccionados)
            ? array_values(array_filter($this->seleccionados, fn($x) => $x !== $id))
            : [...$this->seleccionados, $id];
    }

    public function abrirCrearHR(): void
    {
        if (empty($this->seleccionados)) return;
        $this->showCrearHR        = true;
        $this->transportistaNombre = '';
        $this->transportistaTel   = '';
        $this->hrNotas            = '';
    }

    public function cerrarCrearHR(): void
    {
        $this->showCrearHR = false;
    }

    public function crearHojaRuta(): void
    {
        $this->validate([
            'transportistaNombre' => 'required|min:2|max:100',
            'transportistaTel'    => 'nullable|max:20',
            'hrNotas'             => 'nullable|max:500',
        ]);

        $slugs   = Zona::where('ciudad', $this->ciudadActiva)->pluck('slug')->toArray();
        $ordenes = Orden::whereIn('id', $this->seleccionados)
            ->whereIn('zona', $slugs)
            ->where('estado', 'lista_para_entrega')
            ->whereNull('hoja_ruta_id')
            ->get();

        if ($ordenes->isEmpty()) {
            session()->flash('error', 'No hay órdenes válidas disponibles.');
            $this->showCrearHR = false;
            return;
        }

        $hr = HojaRuta::create([
            'numero'                 => HojaRuta::generarNumero(),
            'ciudad'                 => $this->ciudadActiva,
            'token'                  => HojaRuta::generarToken(),
            'expires_at'             => now()->addHours(24),
            'creado_por'             => auth()->id(),
            'transportista_nombre'   => $this->transportistaNombre,
            'transportista_telefono' => $this->transportistaTel ?: null,
            'estado'                 => 'activa',
            'notas'                  => $this->hrNotas ?: null,
        ]);

        $ordenes->each(fn($o) => $o->update(['hoja_ruta_id' => $hr->id]));

        $this->seleccionados     = [];
        $this->showCrearHR       = false;
        $exp = $this->hrExpandidas;
        $exp[$hr->id] = true;
        $this->hrExpandidas = $exp;

        session()->flash('success', "HR {$hr->numero} creada con {$ordenes->count()} pedidos.");
    }

    public function confirmarEntrega(int $ordenId): void
    {
        $orden = Orden::findOrFail($ordenId);
        if (! $orden->transportista_confirma_at) {
            session()->flash('error', 'El transportista aún no confirmó esta entrega.');
            return;
        }
        $orden->update(['estado' => 'entregada']);
        session()->flash('success', 'Entrega confirmada.');
    }

    public function pedirCancelar(int $hrId): void
    {
        $this->cancelandoHR = $hrId;
    }

    public function cancelarHR(): void
    {
        if (! $this->cancelandoHR) return;
        $hr = HojaRuta::find($this->cancelandoHR);
        if ($hr) {
            $hr->cancelar();
            session()->flash('success', "HR {$hr->numero} cancelada. Pedidos liberados.");
        }
        $this->cancelandoHR = null;
    }

    public function toggleHR(int $hrId): void
    {
        $exp = $this->hrExpandidas;
        if (isset($exp[$hrId])) {
            unset($exp[$hrId]);
        } else {
            $exp[$hrId] = true;
        }
        $this->hrExpandidas = $exp;
    }

    public function with(): array
    {
        if (! $this->ciudadActiva) {
            return [
                'disponibles'     => collect(),
                'hojasEnReparto'  => collect(),
                'hojasPendConf'   => collect(),
                'hojasHoy'        => collect(),
            ];
        }

        $slugs = Zona::where('ciudad', $this->ciudadActiva)->pluck('slug')->toArray();

        // Órdenes listas para despachar (sin HR asignada)
        $disponibles = Orden::whereIn('zona', $slugs)
            ->where('estado', 'lista_para_entrega')
            ->whereNull('hoja_ruta_id')
            ->with(['items.producto', 'cliente'])
            ->orderBy('created_at')
            ->get();

        // HRs activas o en reparto
        $hojasEnReparto = HojaRuta::where('ciudad', $this->ciudadActiva)
            ->whereIn('estado', ['activa', 'en_reparto'])
            ->with(['ordenes' => fn($q) => $q->with(['items.producto', 'cliente'])->orderBy('id'), 'creadoPor'])
            ->orderByDesc('created_at')
            ->get();

        // HRs completadas con pedidos pendientes de confirmación admin
        $hojasPendConf = HojaRuta::where('ciudad', $this->ciudadActiva)
            ->where('estado', 'completada')
            ->whereHas('ordenes', fn($q) => $q->whereNotIn('estado', ['entregada', 'cancelada']))
            ->with(['ordenes' => fn($q) => $q->with(['items.producto', 'cliente'])->orderBy('id'), 'creadoPor'])
            ->orderByDesc('created_at')
            ->get();

        // HRs finalizadas hoy (historial)
        $hojasHoy = HojaRuta::where('ciudad', $this->ciudadActiva)
            ->whereIn('estado', ['completada', 'cancelada'])
            ->whereDoesntHave('ordenes', fn($q) => $q->whereNotIn('estado', ['entregada', 'cancelada']))
            ->whereDate('created_at', today())
            ->with(['ordenes'])
            ->orderByDesc('created_at')
            ->get();

        return compact('disponibles', 'hojasEnReparto', 'hojasPendConf', 'hojasHoy');
    }

}; ?>

<div>

{{-- ── Flash ──────────────────────────────────────────────────────────────────── --}}
@if(session('success'))
<div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
     class="mb-4 badge-green px-3 py-2 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
<div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
     class="mb-4 px-3 py-2 text-sm rounded-xl"
     style="background:rgba(239,68,68,0.1);color:#fca5a5;border:1px solid rgba(239,68,68,0.3);">
    {{ session('error') }}
</div>
@endif

{{-- ── Tabs de ciudad ──────────────────────────────────────────────────────────── --}}
@if(count($ciudades) > 1)
<div class="flex gap-2 mb-6 flex-wrap">
    @foreach($ciudades as $c)
    <button wire:click="setCiudad('{{ $c }}')"
            class="px-4 py-2 rounded-xl text-sm font-semibold transition-all"
            style="{{ $ciudadActiva === $c
                ? 'background:rgba(78,158,90,0.2);color:#4ade80;border:1px solid rgba(78,158,90,0.4);'
                : 'background:rgba(255,255,255,0.04);color:#94a3b8;border:1px solid rgba(255,255,255,0.08);' }}">
        {{ $c }}
    </button>
    @endforeach
</div>
@elseif(count($ciudades) === 1)
<div class="mb-5 flex items-center gap-2">
    <span class="text-sm font-semibold" style="color:#4ade80;">📍 {{ $ciudades[0] }}</span>
</div>
@endif

@if(! $ciudadActiva)
<div class="card text-center py-16">
    <p class="text-base font-semibold mb-1" style="color:var(--vd-text-soft);">Sin ciudades configuradas</p>
    <p class="text-sm" style="color:var(--vd-muted-2);">Asigná una ciudad a las zonas para usar esta sección.</p>
</div>
@else

{{-- ══════════════════════════════════════════════════════
     SECCIÓN 1 — Listos para despachar
══════════════════════════════════════════════════════ --}}
<div class="card mb-6">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <h2 class="font-bold text-base" style="color:var(--vd-text);">Listos para despachar</h2>
            @if($disponibles->isNotEmpty())
            <span class="text-xs font-bold px-2 py-0.5 rounded-full"
                  style="background:rgba(78,158,90,0.15);color:#4ade80;border:1px solid rgba(78,158,90,0.3);">
                {{ $disponibles->count() }}
            </span>
            @endif
        </div>
        @if($disponibles->isNotEmpty())
        <button wire:click="abrirCrearHR"
                @disabled(empty($seleccionados))
                class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition-all"
                style="{{ empty($seleccionados)
                    ? 'background:rgba(255,255,255,0.04);color:#475569;border:1px solid rgba(255,255,255,0.06);cursor:not-allowed;'
                    : 'background:rgba(78,158,90,0.2);color:#4ade80;border:1px solid rgba(78,158,90,0.4);cursor:pointer;' }}">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            Crear HR
            @if(! empty($seleccionados))
            <span class="text-xs font-bold px-1.5 py-0.5 rounded"
                  style="background:rgba(78,158,90,0.3);">{{ count($seleccionados) }}</span>
            @endif
        </button>
        @endif
    </div>

    @if($disponibles->isEmpty())
    <div class="text-center py-10">
        <div class="text-4xl mb-3">✅</div>
        <p class="text-sm font-semibold" style="color:var(--vd-text-soft);">Sin pedidos pendientes de despacho</p>
        <p class="text-xs mt-1" style="color:var(--vd-muted-2);">Los pedidos listos para entregar aparecerán aquí.</p>
    </div>
    @else

    {{-- Seleccionar/Deseleccionar todos --}}
    <div class="flex items-center gap-3 mb-3 pb-3" style="border-bottom:1px solid var(--vd-bdr-soft);">
        <label class="flex items-center gap-2 cursor-pointer select-none">
            <input type="checkbox"
                   @change="{{ count($seleccionados) === $disponibles->count() ? 'seleccionados=[]' : 'seleccionados='.json_encode($disponibles->pluck('id')->toArray()) }}"
                   :checked="{{ count($seleccionados) === $disponibles->count() ? 'true' : 'false' }}"
                   class="w-4 h-4 rounded accent-green-500">
            <span class="text-xs font-semibold" style="color:var(--vd-muted);">
                {{ count($seleccionados) === $disponibles->count() ? 'Deseleccionar todo' : 'Seleccionar todo' }}
            </span>
        </label>
        @if(! empty($seleccionados) && count($seleccionados) !== $disponibles->count())
        <span class="text-xs" style="color:var(--vd-muted-2);">
            {{ count($seleccionados) }} de {{ $disponibles->count() }} seleccionados
        </span>
        @endif
    </div>

    <div class="flex flex-col gap-2">
    @foreach($disponibles as $o)
    @php
        $sel = in_array($o->id, $seleccionados);
        $fp  = $o->items->first()?->forma_pago ?? '';
    @endphp
    <label class="flex items-start gap-3 p-3 rounded-xl cursor-pointer transition-all"
           style="{{ $sel
               ? 'background:rgba(78,158,90,0.08);border:1px solid rgba(78,158,90,0.2);'
               : 'background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);' }}">

        <input type="checkbox"
               wire:click="toggleOrden({{ $o->id }})"
               {{ $sel ? 'checked' : '' }}
               class="mt-0.5 w-4 h-4 rounded accent-green-500 flex-shrink-0">

        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="font-mono text-xs font-bold" style="color:var(--vd-text);">{{ $o->numero }}</span>
                @if($o->cliente?->nombreCompleto())
                <span class="text-xs" style="color:var(--vd-muted);">{{ $o->cliente->nombreCompleto() }}</span>
                @endif
                @if($fp === 'en_destino')
                <span class="text-xs px-1.5 py-0.5 rounded font-semibold"
                      style="background:rgba(234,179,8,0.15);color:#facc15;border:1px solid rgba(234,179,8,0.3);">
                    💵 Cobrar destino
                </span>
                @endif
            </div>
            @if($o->direccion)
            <p class="text-xs mt-0.5" style="color:var(--vd-muted-2);">📍 {{ $o->direccion }}</p>
            @endif
            <div class="flex flex-wrap gap-1 mt-1">
                @foreach($o->items as $it)
                <span class="text-xs px-1.5 py-0.5 rounded"
                      style="background:rgba(255,255,255,0.06);color:var(--vd-muted);">
                    {{ $it->cantidad }}× {{ $it->producto?->nombre ?? '—' }}
                </span>
                @endforeach
            </div>
        </div>

        <div class="text-right flex-shrink-0">
            <p class="text-sm font-bold font-mono" style="color:var(--vd-text);">
                ${{ number_format($o->total, 0, ',', '.') }}
            </p>
            <p class="text-xs" style="color:var(--vd-muted-2);">
                {{ $o->created_at->format('d/m') }}
            </p>
        </div>
    </label>
    @endforeach
    </div>

    @endif{{-- /isEmpty disponibles --}}
</div>

{{-- ══════════════════════════════════════════════════════
     SECCIÓN 2 — Hojas de Ruta en reparto
══════════════════════════════════════════════════════ --}}
@if($hojasEnReparto->isNotEmpty())
<div class="mb-6">
    <h2 class="font-bold text-base mb-3 flex items-center gap-2" style="color:var(--vd-text);">
        En reparto
        <span class="text-xs font-bold px-2 py-0.5 rounded-full"
              style="background:rgba(59,130,246,0.15);color:#93c5fd;border:1px solid rgba(59,130,246,0.3);">
            {{ $hojasEnReparto->count() }}
        </span>
    </h2>

    <div class="flex flex-col gap-4">
    @foreach($hojasEnReparto as $hr)
    @php
        $expanded  = isset($hrExpandidas[$hr->id]);
        $pendConf  = $hr->ordenes->filter(fn($o) => $o->transportista_confirma_at && $o->estado !== 'entregada' && $o->estado !== 'cancelada')->count();
        $entregadas = $hr->ordenes->where('estado', 'entregada')->count();
        $total     = $hr->ordenes->count();
    @endphp
    <div class="card p-0 overflow-hidden">
        {{-- Cabecera HR --}}
        <div class="flex items-center gap-3 px-4 py-3 cursor-pointer"
             wire:click="toggleHR({{ $hr->id }})"
             style="border-bottom:{{ $expanded ? '1px solid var(--vd-bdr-soft)' : 'none' }};">

            {{-- Estado indicador --}}
            <div class="w-2 h-2 rounded-full flex-shrink-0"
                 style="background:{{ $hr->estado === 'en_reparto' ? '#93c5fd' : '#facc15' }};
                         box-shadow:0 0 6px {{ $hr->estado === 'en_reparto' ? 'rgba(147,197,253,0.6)' : 'rgba(250,204,21,0.6)' }};"></div>

            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-mono font-bold text-sm" style="color:var(--vd-text);">{{ $hr->numero }}</span>
                    <span class="text-xs px-1.5 py-0.5 rounded font-semibold"
                          style="{{ $hr->estado === 'en_reparto'
                              ? 'background:rgba(59,130,246,0.15);color:#93c5fd;border:1px solid rgba(59,130,246,0.3);'
                              : 'background:rgba(234,179,8,0.15);color:#facc15;border:1px solid rgba(234,179,8,0.3);' }}">
                        {{ $hr->estado === 'en_reparto' ? 'En reparto' : 'Activa' }}
                    </span>
                    @if($pendConf > 0)
                    <span class="text-xs px-1.5 py-0.5 rounded font-semibold animate-pulse"
                          style="background:rgba(78,158,90,0.2);color:#4ade80;border:1px solid rgba(78,158,90,0.4);">
                        ✓ {{ $pendConf }} confirmar
                    </span>
                    @endif
                </div>
                <p class="text-xs mt-0.5" style="color:var(--vd-muted-2);">
                    {{ $hr->transportista_nombre }}
                    @if($hr->transportista_telefono) · {{ $hr->transportista_telefono }} @endif
                    · {{ $entregadas }}/{{ $total }} pedidos
                    · vence {{ $hr->expires_at->format('H:i') }} hs
                </p>
            </div>

            {{-- Botones de acción (no propagan toggle) --}}
            <div class="flex items-center gap-2" wire:click.stop>

                {{-- Copiar link --}}
                <div x-data="{ copied: false }">
                    <button @click="
                        navigator.clipboard.writeText('{{ $hr->publicUrl() }}');
                        copied = true;
                        setTimeout(() => copied = false, 2500);
                    "
                    class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-all"
                    style="background:rgba(59,130,246,0.12);color:#93c5fd;border:1px solid rgba(59,130,246,0.25);"
                    title="Copiar link para transportista">
                        <span x-show="!copied">
                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                        </span>
                        <span x-show="copied">✓</span>
                        <span x-show="!copied" class="hidden sm:inline">Link</span>
                        <span x-show="copied">Copiado</span>
                    </button>
                </div>

                {{-- Cancelar HR --}}
                <button wire:click="pedirCancelar({{ $hr->id }})"
                        class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-all"
                        style="background:rgba(239,68,68,0.1);color:#fca5a5;border:1px solid rgba(239,68,68,0.25);"
                        title="Cancelar HR">
                    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>

                {{-- Toggle arrow --}}
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                     wire:click="toggleHR({{ $hr->id }})"
                     style="color:var(--vd-muted-2);transition:transform 0.2s;{{ $expanded ? 'transform:rotate(180deg);' : '' }}">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </div>

        {{-- Órdenes de la HR (expandible) --}}
        @if($expanded)
        <div class="px-4 py-3">
            @if($hr->notas)
            <div class="mb-3 px-3 py-2 rounded-lg text-xs" style="background:rgba(255,255,255,0.04);color:var(--vd-muted);">
                📝 {{ $hr->notas }}
            </div>
            @endif

            <div class="flex flex-col gap-2">
            @foreach($hr->ordenes->sortBy('id') as $idx => $o)
            @php
                $transConf  = $o->transportista_confirma_at !== null;
                $adminConf  = $o->estado === 'entregada';
                $cancelada  = $o->estado === 'cancelada';
                $fp         = $o->items->first()?->forma_pago ?? '';
                $pendiente  = $transConf && ! $adminConf && ! $cancelada;
            @endphp
            <div class="flex items-start gap-3 p-3 rounded-xl"
                 style="{{ $adminConf ? 'background:rgba(78,158,90,0.06);border:1px solid rgba(78,158,90,0.15);'
                        : ($cancelada ? 'background:rgba(239,68,68,0.05);border:1px solid rgba(239,68,68,0.1);'
                        : ($pendiente ? 'background:rgba(78,158,90,0.1);border:1px solid rgba(78,158,90,0.25);'
                        : 'background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);')) }}">

                {{-- Índice --}}
                <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0 mt-0.5"
                     style="background:{{ $adminConf ? 'rgba(78,158,90,0.25)' : 'rgba(255,255,255,0.08)' }};
                             color:{{ $adminConf ? '#4ade80' : '#94a3b8' }};">
                    {{ $adminConf ? '✓' : ($idx + 1) }}
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-mono text-xs font-semibold" style="color:var(--vd-text);">#{{ $o->numero }}</span>
                        @if($cancelada)
                        <span class="text-xs" style="color:#fca5a5;">Cancelada</span>
                        @elseif($adminConf)
                        <span class="text-xs" style="color:#4ade80;">Entregada ✓</span>
                        @elseif($transConf)
                        <span class="text-xs font-semibold" style="color:#4ade80;">
                            Transportista confirmó {{ $o->transportista_confirma_at->format('H:i') }}
                        </span>
                        @else
                        <span class="text-xs" style="color:var(--vd-muted-2);">En camino</span>
                        @endif
                        @if($fp === 'en_destino' && ! $adminConf && ! $cancelada)
                        <span class="text-xs px-1 py-0.5 rounded font-semibold"
                              style="background:rgba(234,179,8,0.15);color:#facc15;">
                            ${{ number_format($o->total, 0, ',', '.') }} efectivo
                        </span>
                        @endif
                    </div>
                    @if($o->direccion)
                    <p class="text-xs mt-0.5" style="color:var(--vd-muted-2);">📍 {{ $o->direccion }}</p>
                    @endif
                    <div class="flex flex-wrap gap-1 mt-1">
                        @foreach($o->items as $it)
                        <span class="text-xs px-1.5 py-0.5 rounded" style="background:rgba(255,255,255,0.06);color:var(--vd-muted);">
                            {{ $it->cantidad }}× {{ $it->producto?->nombre ?? '—' }}
                        </span>
                        @endforeach
                    </div>
                </div>

                {{-- Confirmar entrega (admin side) --}}
                @if($pendiente)
                <button wire:click="confirmarEntrega({{ $o->id }})"
                        wire:loading.attr="disabled"
                        wire:target="confirmarEntrega({{ $o->id }})"
                        class="flex-shrink-0 flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all"
                        style="background:rgba(78,158,90,0.2);color:#4ade80;border:1px solid rgba(78,158,90,0.4);">
                    <span wire:loading.remove wire:target="confirmarEntrega({{ $o->id }})">✓ Confirmar</span>
                    <span wire:loading wire:target="confirmarEntrega({{ $o->id }})">…</span>
                </button>
                @endif

            </div>
            @endforeach
            </div>
        </div>
        @endif{{-- /expanded --}}
    </div>
    @endforeach
    </div>
</div>
@endif{{-- /hojasEnReparto --}}

{{-- ══════════════════════════════════════════════════════
     SECCIÓN 3 — Pendientes de confirmación admin
══════════════════════════════════════════════════════ --}}
@if($hojasPendConf->isNotEmpty())
<div class="mb-6">
    <h2 class="font-bold text-base mb-3 flex items-center gap-2" style="color:var(--vd-text);">
        Pendientes de confirmación
        <span class="text-xs font-bold px-2 py-0.5 rounded-full animate-pulse"
              style="background:rgba(78,158,90,0.2);color:#4ade80;border:1px solid rgba(78,158,90,0.4);">
            {{ $hojasPendConf->count() }}
        </span>
    </h2>

    <div class="flex flex-col gap-4">
    @foreach($hojasPendConf as $hr)
    @php
        $expanded   = isset($hrExpandidas[$hr->id]);
        $sinConf    = $hr->ordenes->filter(fn($o) => ! in_array($o->estado, ['entregada', 'cancelada']))->count();
        $entregadas = $hr->ordenes->where('estado', 'entregada')->count();
        $total      = $hr->ordenes->count();
    @endphp
    <div class="card p-0 overflow-hidden" style="border-color:rgba(78,158,90,0.25);">
        <div class="flex items-center gap-3 px-4 py-3 cursor-pointer"
             wire:click="toggleHR({{ $hr->id }})"
             style="background:rgba(78,158,90,0.05);border-bottom:{{ $expanded ? '1px solid rgba(78,158,90,0.2)' : 'none' }};">

            <div class="w-2 h-2 rounded-full flex-shrink-0 animate-pulse"
                 style="background:#4ade80;box-shadow:0 0 6px rgba(74,222,128,0.6);"></div>

            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-mono font-bold text-sm" style="color:var(--vd-text);">{{ $hr->numero }}</span>
                    <span class="text-xs px-1.5 py-0.5 rounded font-semibold"
                          style="background:rgba(78,158,90,0.2);color:#4ade80;border:1px solid rgba(78,158,90,0.35);">
                        Completada
                    </span>
                    <span class="text-xs font-semibold" style="color:#4ade80;">
                        {{ $sinConf }} por confirmar
                    </span>
                </div>
                <p class="text-xs mt-0.5" style="color:var(--vd-muted-2);">
                    {{ $hr->transportista_nombre }}
                    · {{ $entregadas }}/{{ $total }} confirmados
                </p>
            </div>

            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                 style="color:var(--vd-muted-2);transition:transform 0.2s;{{ $expanded ? 'transform:rotate(180deg);' : '' }}">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>

        @if($expanded)
        <div class="px-4 py-3">
            <div class="flex flex-col gap-2">
            @foreach($hr->ordenes->sortBy('id') as $idx => $o)
            @php
                $adminConf  = $o->estado === 'entregada';
                $cancelada  = $o->estado === 'cancelada';
                $fp         = $o->items->first()?->forma_pago ?? '';
            @endphp
            <div class="flex items-start gap-3 p-3 rounded-xl"
                 style="{{ $adminConf ? 'background:rgba(78,158,90,0.06);border:1px solid rgba(78,158,90,0.15);'
                        : ($cancelada ? 'background:rgba(239,68,68,0.05);border:1px solid rgba(239,68,68,0.1);'
                        : 'background:rgba(78,158,90,0.1);border:1px solid rgba(78,158,90,0.25);') }}">

                <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0 mt-0.5"
                     style="background:{{ $adminConf ? 'rgba(78,158,90,0.25)' : 'rgba(78,158,90,0.15)' }};
                             color:{{ $adminConf ? '#4ade80' : '#86efac' }};">
                    {{ $adminConf ? '✓' : ($idx + 1) }}
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-mono text-xs font-semibold" style="color:var(--vd-text);">#{{ $o->numero }}</span>
                        @if($cancelada)
                        <span class="text-xs" style="color:#fca5a5;">Cancelada</span>
                        @elseif($adminConf)
                        <span class="text-xs" style="color:#4ade80;">Entregada ✓</span>
                        @else
                        <span class="text-xs font-semibold" style="color:#4ade80;">
                            Transportista confirmó · esperando admin
                        </span>
                        @endif
                        @if($fp === 'en_destino' && ! $adminConf && ! $cancelada)
                        <span class="text-xs px-1 py-0.5 rounded font-semibold"
                              style="background:rgba(234,179,8,0.15);color:#facc15;">
                            ${{ number_format($o->total, 0, ',', '.') }} efectivo
                        </span>
                        @endif
                    </div>
                    @if($o->direccion)
                    <p class="text-xs mt-0.5" style="color:var(--vd-muted-2);">📍 {{ $o->direccion }}</p>
                    @endif
                    <div class="flex flex-wrap gap-1 mt-1">
                        @foreach($o->items as $it)
                        <span class="text-xs px-1.5 py-0.5 rounded" style="background:rgba(255,255,255,0.06);color:var(--vd-muted);">
                            {{ $it->cantidad }}× {{ $it->producto?->nombre ?? '—' }}
                        </span>
                        @endforeach
                    </div>
                </div>

                @if(! $adminConf && ! $cancelada)
                <button wire:click="confirmarEntrega({{ $o->id }})"
                        wire:loading.attr="disabled"
                        wire:target="confirmarEntrega({{ $o->id }})"
                        class="flex-shrink-0 flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold"
                        style="background:rgba(78,158,90,0.2);color:#4ade80;border:1px solid rgba(78,158,90,0.4);">
                    <span wire:loading.remove wire:target="confirmarEntrega({{ $o->id }})">✓ Confirmar</span>
                    <span wire:loading wire:target="confirmarEntrega({{ $o->id }})">…</span>
                </button>
                @endif
            </div>
            @endforeach
            </div>
        </div>
        @endif
    </div>
    @endforeach
    </div>
</div>
@endif{{-- /hojasPendConf --}}

{{-- ══════════════════════════════════════════════════════
     SECCIÓN 4 — Historial de hoy
══════════════════════════════════════════════════════ --}}
@if($hojasHoy->isNotEmpty())
<div class="mb-6">
    <h2 class="font-bold text-sm mb-3 flex items-center gap-2" style="color:var(--vd-muted);">
        Historial de hoy
    </h2>
    <div class="flex flex-col gap-2">
    @foreach($hojasHoy as $hr)
    <div class="flex items-center gap-3 px-4 py-3 rounded-xl"
         style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
        <div class="w-2 h-2 rounded-full flex-shrink-0"
             style="background:{{ $hr->estado === 'completada' ? '#4ade80' : '#fca5a5' }};opacity:0.6;"></div>
        <span class="font-mono text-xs font-semibold" style="color:var(--vd-muted);">{{ $hr->numero }}</span>
        <span class="text-xs" style="color:var(--vd-muted-2);">
            {{ $hr->transportista_nombre }}
        </span>
        <span class="text-xs px-1.5 py-0.5 rounded ml-auto"
              style="{{ $hr->estado === 'completada'
                  ? 'background:rgba(78,158,90,0.1);color:#4ade80;'
                  : 'background:rgba(239,68,68,0.1);color:#fca5a5;' }}">
            {{ $hr->estado === 'completada' ? 'Completada' : 'Cancelada' }}
        </span>
        <span class="text-xs" style="color:var(--vd-muted-2);">
            {{ $hr->ordenes->count() }} pedidos
        </span>
    </div>
    @endforeach
    </div>
</div>
@endif

@endif{{-- /ciudadActiva --}}

{{-- ══════════════════════════════════════════════════════
     MODAL — Crear Hoja de Ruta
══════════════════════════════════════════════════════ --}}
@if($showCrearHR)
<div class="fixed inset-0 z-50 flex items-center justify-center p-4"
     style="background:rgba(0,0,0,0.65);backdrop-filter:blur(4px);">
    <div class="w-full max-w-md rounded-2xl p-6"
         style="background:#1e293b;border:1px solid rgba(255,255,255,0.1);box-shadow:0 24px 80px rgba(0,0,0,0.5);">

        <div class="flex items-center justify-between mb-5">
            <h3 class="font-bold text-base" style="color:var(--vd-text);">
                Nueva Hoja de Ruta
            </h3>
            <button wire:click="cerrarCrearHR" class="text-xl leading-none" style="color:var(--vd-muted-2);">×</button>
        </div>

        <div class="mb-3 px-3 py-2 rounded-xl text-sm"
             style="background:rgba(78,158,90,0.1);border:1px solid rgba(78,158,90,0.2);color:#4ade80;">
            📦 {{ count($seleccionados) }} pedidos seleccionados · {{ $ciudadActiva }}
        </div>

        <div class="flex flex-col gap-4">
            <div>
                <label class="label">Nombre del transportista *</label>
                <input wire:model="transportistaNombre"
                       type="text"
                       placeholder="Ej: Juan Pérez"
                       class="input w-full"
                       autofocus>
                @error('transportistaNombre')
                <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="label">Teléfono del transportista</label>
                <input wire:model="transportistaTel"
                       type="tel"
                       placeholder="Ej: 2994123456"
                       class="input w-full">
                @error('transportistaTel')
                <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="label">Notas internas</label>
                <textarea wire:model="hrNotas"
                          placeholder="Instrucciones, observaciones…"
                          rows="2"
                          class="input w-full resize-none"></textarea>
                @error('hrNotas')
                <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="flex gap-3 mt-6">
            <button wire:click="cerrarCrearHR"
                    class="flex-1 py-2.5 rounded-xl text-sm font-semibold transition-all"
                    style="background:rgba(255,255,255,0.05);color:var(--vd-muted);border:1px solid rgba(255,255,255,0.08);">
                Cancelar
            </button>
            <button wire:click="crearHojaRuta"
                    wire:loading.attr="disabled"
                    wire:target="crearHojaRuta"
                    class="flex-1 py-2.5 rounded-xl text-sm font-bold transition-all"
                    style="background:rgba(78,158,90,0.25);color:#4ade80;border:1px solid rgba(78,158,90,0.5);">
                <span wire:loading.remove wire:target="crearHojaRuta">🚀 Crear y compartir</span>
                <span wire:loading wire:target="crearHojaRuta">Creando…</span>
            </button>
        </div>
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════
     MODAL — Confirmar cancelar HR
══════════════════════════════════════════════════════ --}}
@if($cancelandoHR)
<div class="fixed inset-0 z-50 flex items-center justify-center p-4"
     style="background:rgba(0,0,0,0.65);backdrop-filter:blur(4px);">
    <div class="w-full max-w-sm rounded-2xl p-6 text-center"
         style="background:#1e293b;border:1px solid rgba(239,68,68,0.3);box-shadow:0 24px 80px rgba(0,0,0,0.5);">

        <div class="text-4xl mb-3">⚠️</div>
        <h3 class="font-bold text-base mb-2" style="color:#fca5a5;">Cancelar Hoja de Ruta</h3>
        <p class="text-sm mb-6" style="color:var(--vd-muted-2);">
            Los pedidos sin confirmar serán liberados y vuelven al estado "Lista para entregar".
            Esta acción no se puede deshacer.
        </p>

        <div class="flex gap-3">
            <button wire:click="$set('cancelandoHR', null)"
                    class="flex-1 py-2.5 rounded-xl text-sm font-semibold"
                    style="background:rgba(255,255,255,0.05);color:var(--vd-muted);border:1px solid rgba(255,255,255,0.08);">
                Volver
            </button>
            <button wire:click="cancelarHR"
                    wire:loading.attr="disabled"
                    wire:target="cancelarHR"
                    class="flex-1 py-2.5 rounded-xl text-sm font-bold"
                    style="background:rgba(239,68,68,0.2);color:#fca5a5;border:1px solid rgba(239,68,68,0.4);">
                <span wire:loading.remove wire:target="cancelarHR">Sí, cancelar HR</span>
                <span wire:loading wire:target="cancelarHR">Cancelando…</span>
            </button>
        </div>
    </div>
</div>
@endif

</div>
