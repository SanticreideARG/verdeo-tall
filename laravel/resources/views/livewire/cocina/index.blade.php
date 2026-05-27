<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Orden;
use App\Models\OrdenCocina;
use App\Models\Zona;
use App\Models\User;

new #[Layout('layouts.app', ['title' => 'Cocina'])] class extends Component {

    public string $ciudadActiva  = '';
    public array  $ciudades      = [];
    public array  $seleccionados = [];   // IDs de pedidos pendientes a incluir en el batch

    // Estado de asignación por batch (clave: ordenes_cocina.id)
    public ?int $asignandoBatchId    = null;
    public int  $colaboradorBatchId  = 0;

    // Estado de reasignación individual (clave: orden.id)
    public ?int $reasignandoOrdenId  = null;
    public int  $colaboradorOrdenId  = 0;

    /* ── Ciclo de vida ──────────────────────────────────────────────────────── */

    public function mount(): void
    {
        $user = auth()->user();

        // Rol cocina o transporte: solo ve su ciudad asignada
        if (in_array($user->role, ['cocina', 'transporte'])) {
            $ciudad = $user->ciudad ?? '';
            $this->ciudades     = $ciudad ? [$ciudad] : [];
            $this->ciudadActiva = $ciudad;
        } else {
            $this->ciudades = Zona::whereNotNull('ciudad')
                ->where('ciudad', '!=', '')
                ->distinct()
                ->orderBy('ciudad')
                ->pluck('ciudad')
                ->toArray();
            $this->ciudadActiva = $this->ciudades[0] ?? '';
        }

        $this->cargarSeleccionados();
    }

    public function with(): array
    {
        if (! $this->ciudadActiva) {
            return [
                'pendientes'          => collect(),
                'batches'             => collect(),
                'resumenPendientes'   => collect(),
                'colaboradoresCocina' => collect(),
                'totalPendientes'     => 0,
                'totalAprobados'      => 0,
            ];
        }

        $zonaSlugs = Zona::where('ciudad', $this->ciudadActiva)->pluck('slug')->toArray();

        // Pendientes sin batch asignado aún
        $pendientes = Orden::with(['items.producto', 'cliente'])
            ->whereIn('zona', $zonaSlugs)
            ->where('estado', 'pendiente')
            ->orderBy('created_at')
            ->get();

        // Batches activos de esta ciudad (incluye sus órdenes aprobadas y listas)
        $batches = OrdenCocina::with([
                'creadoPor',
                'asignado',
                'ordenes.items.producto',
                'ordenes.cliente',
                'ordenes.asignadoCocina',
            ])
            ->where('ciudad', $this->ciudadActiva)
            ->where('estado', 'activa')
            ->orderBy('created_at')
            ->get();

        // Resumen de menús de los pedidos seleccionados (para previsualización antes de crear batch)
        $pendientesSeleccionados = $pendientes->filter(
            fn($o) => in_array($o->id, $this->seleccionados)
        );

        $resumenPendientes = $pendientesSeleccionados->flatMap->items
            ->groupBy(fn($i) => ($i->producto?->nombre ?? '—') . '|||' . $i->tamano)
            ->map(fn($grupo) => [
                'nombre' => $grupo->first()->producto?->nombre ?? '—',
                'tamano' => $grupo->first()->tamano,
                'total'  => (int) $grupo->sum('cantidad'),
            ])
            ->sortByDesc('total')
            ->values();

        // Colaboradores disponibles para asignación en esta ciudad
        $colaboradoresCocina = User::whereIn('role', ['cocina', 'colaborador'])
            ->where(fn($q) => $q
                ->where('ciudad', $this->ciudadActiva)
                ->orWhereNull('ciudad')
            )
            ->orderBy('name')
            ->get(['id', 'name', 'apellido', 'role']);

        return [
            'pendientes'          => $pendientes,
            'batches'             => $batches,
            'resumenPendientes'   => $resumenPendientes,
            'colaboradoresCocina' => $colaboradoresCocina,
            'totalPendientes'     => $pendientes->count(),
            'totalAprobados'      => $batches->sum(fn($b) => $b->ordenes->count()),
        ];
    }

    /* ── Navegación ─────────────────────────────────────────────────────────── */

    public function cambiarCiudad(string $ciudad): void
    {
        $this->ciudadActiva    = $ciudad;
        $this->seleccionados   = [];
        $this->asignandoBatchId   = null;
        $this->reasignandoOrdenId = null;
        $this->cargarSeleccionados();
    }

    /* ── Selección de pendientes ─────────────────────────────────────────────── */

    public function cargarSeleccionados(): void
    {
        if (! $this->ciudadActiva) {
            $this->seleccionados = [];
            return;
        }
        $zonaSlugs = Zona::where('ciudad', $this->ciudadActiva)->pluck('slug')->toArray();
        $this->seleccionados = Orden::whereIn('zona', $zonaSlugs)
            ->where('estado', 'pendiente')
            ->pluck('id')
            ->toArray();
    }

    public function toggleSeleccionado(int $id): void
    {
        if (in_array($id, $this->seleccionados)) {
            $this->seleccionados = array_values(array_filter(
                $this->seleccionados, fn($i) => $i !== $id
            ));
        } else {
            $this->seleccionados[] = $id;
        }
    }

    public function seleccionarTodos(): void
    {
        $this->cargarSeleccionados();
    }

    public function deseleccionarTodos(): void
    {
        $this->seleccionados = [];
    }

    /* ── Crear Orden de Cocina ───────────────────────────────────────────────── */

    public function crearOrdenCocina(): void
    {
        $user = auth()->user();
        if (! ($user->isAdmin() || $user->isResponsableZona() || $user->isColaborador())) return;

        if (empty($this->seleccionados)) {
            $this->dispatch('notify', type: 'error', msg: 'Seleccioná al menos un pedido.');
            return;
        }

        $batch = OrdenCocina::create([
            'numero'     => OrdenCocina::generarNumero(),
            'ciudad'     => $this->ciudadActiva,
            'creado_por' => $user->id,
            'estado'     => 'activa',
        ]);

        Orden::whereIn('id', $this->seleccionados)
            ->where('estado', 'pendiente')
            ->update([
                'estado'         => 'aprobada',
                'orden_cocina_id' => $batch->id,
            ]);

        $this->cargarSeleccionados();
        $this->dispatch('notify', type: 'ok', msg: "Orden {$batch->numero} creada.");
    }

    /* ── Marcar listo para entrega ───────────────────────────────────────────── */

    public function marcarListo(int $ordenId): void
    {
        $user  = auth()->user();
        $orden = Orden::findOrFail($ordenId);

        // Solo puede marcar quien está asignado, o admin/responsable
        $puedeMarcar = $user->isAdmin()
            || $user->isResponsableZona()
            || $user->isColaborador()
            || ($orden->asignado_cocina_id === $user->id)
            || ($orden->ordenCocina?->asignado_a === $user->id);

        if (! $puedeMarcar) return;
        if ($orden->estado !== 'aprobada') return;

        $orden->update(['estado' => 'lista_para_entrega']);

        // Verificar si el batch queda completado
        $orden->ordenCocina?->checkCompletada();

        $this->dispatch('notify', type: 'ok', msg: "Pedido #{$orden->numero} listo para entrega.");
    }

    /* ── Asignación por batch ────────────────────────────────────────────────── */

    public function abrirAsignacionBatch(int $batchId): void
    {
        $this->asignandoBatchId  = $batchId;
        $this->colaboradorBatchId = 0;
    }

    public function cerrarAsignacionBatch(): void
    {
        $this->asignandoBatchId  = null;
        $this->colaboradorBatchId = 0;
    }

    public function confirmarAsignacionBatch(): void
    {
        $user = auth()->user();
        if (! ($user->isAdmin() || $user->isResponsableZona())) return;
        if (! $this->asignandoBatchId) return;

        $batch = OrdenCocina::findOrFail($this->asignandoBatchId);
        $batch->update(['asignado_a' => $this->colaboradorBatchId ?: null]);

        // Propagar asignación a órdenes del batch que no tienen asignación individual
        if ($this->colaboradorBatchId) {
            $batch->ordenes()
                ->whereNull('asignado_cocina_id')
                ->whereIn('estado', ['aprobada'])
                ->update(['asignado_cocina_id' => $this->colaboradorBatchId]);
        }

        $this->cerrarAsignacionBatch();
        $this->dispatch('notify', type: 'ok', msg: 'Colaborador asignado al batch.');
    }

    /* ── Reasignación individual de orden ───────────────────────────────────── */

    public function abrirReasignacion(int $ordenId): void
    {
        $this->reasignandoOrdenId = $ordenId;
        $this->colaboradorOrdenId = 0;
    }

    public function cerrarReasignacion(): void
    {
        $this->reasignandoOrdenId = null;
        $this->colaboradorOrdenId = 0;
    }

    public function confirmarReasignacion(): void
    {
        $user = auth()->user();
        if (! ($user->isAdmin() || $user->isResponsableZona() || $user->isColaborador())) return;
        if (! $this->reasignandoOrdenId) return;

        Orden::where('id', $this->reasignandoOrdenId)->update([
            'asignado_cocina_id' => $this->colaboradorOrdenId ?: null,
        ]);

        $this->cerrarReasignacion();
        $this->dispatch('notify', type: 'ok', msg: 'Pedido reasignado.');
    }

}; ?>

<div
    wire:poll.15s
    x-data="{
        prevPend: {{ $totalPendientes }},
        showBanner: false,
        init() {
            document.addEventListener('livewire:morph', () => {
                const el = document.querySelector('[data-pend-count]');
                const newCount = parseInt(el?.dataset?.pendCount ?? 0);
                if (newCount > this.prevPend) {
                    this.showBanner = true;
                    this.playBeep();
                    setTimeout(() => this.showBanner = false, 6000);
                }
                this.prevPend = newCount;
            });
        },
        playBeep() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                [660, 880].forEach((freq, i) => {
                    const osc = ctx.createOscillator(), gain = ctx.createGain();
                    osc.connect(gain); gain.connect(ctx.destination);
                    osc.frequency.value = freq;
                    const t = ctx.currentTime + i * 0.18;
                    gain.gain.setValueAtTime(0.35, t);
                    gain.gain.exponentialRampToValueAtTime(0.001, t + 0.15);
                    osc.start(t); osc.stop(t + 0.15);
                });
            } catch(e) {}
        }
    }"
    data-pend-count="{{ $totalPendientes }}">

    {{-- ── Banner nuevo pedido ──────────────────────────────────────────────── --}}
    <div x-show="showBanner" x-transition
         class="mb-4 px-4 py-3 rounded-xl flex items-center gap-3 text-sm font-semibold"
         style="background: rgba(234,179,8,0.15); border: 1px solid rgba(234,179,8,0.4); color: #facc15;">
        <svg class="animate-bounce" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
        </svg>
        Nuevo pedido entrante — revisá la lista de pendientes.
    </div>

    {{-- ── Notificación flash ───────────────────────────────────────────────── --}}
    <div
        x-data="{ show: false, msg: '', type: 'ok' }"
        @notify.window="show = true; msg = $event.detail.msg; type = $event.detail.type; setTimeout(() => show = false, 3500)"
        x-show="show" x-transition
        class="mb-4 px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2"
        :style="type === 'ok'
            ? 'background: rgba(78,158,90,0.12); border: 1px solid rgba(78,158,90,0.3); color: #4e9e5a;'
            : 'background: rgba(239,68,68,0.10); border: 1px solid rgba(239,68,68,0.25); color: #ef4444;'">
        <svg x-show="type==='ok'" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
        <svg x-show="type!=='ok'" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
        <span x-text="msg"></span>
    </div>

    {{-- ── Sin ciudades configuradas ───────────────────────────────────────── --}}
    @if(empty($ciudades))
    <div class="card text-center py-12">
        <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4"
             style="background: rgba(234,179,8,0.12); border: 1px solid rgba(234,179,8,0.25);">
            <svg width="28" height="28" fill="none" stroke="#facc15" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
            </svg>
        </div>
        <p class="font-semibold mb-1" style="color: var(--vd-text);">No hay ciudades configuradas</p>
        <p class="text-sm" style="color: var(--vd-muted);">
            Asigná una ciudad a cada zona en
            <a href="{{ route('zonas') }}" wire:navigate class="underline" style="color: #4e9e5a;">Zonas</a>
            para organizar la cocina por ciudad.
        </p>
    </div>

    @else

    {{-- ── Pestañas de ciudad ──────────────────────────────────────────────── --}}
    <div class="flex gap-1.5 p-1.5 rounded-xl mb-6"
         style="background: rgba(0,0,0,0.2); border: 1px solid var(--vd-bdr-soft);">
        @foreach($ciudades as $ciudad)
        <button type="button"
                wire:click="cambiarCiudad('{{ $ciudad }}')"
                class="flex-1 flex items-center justify-center gap-2 py-2.5 px-3 rounded-lg text-sm font-semibold transition-all"
                style="{{ $ciudadActiva === $ciudad
                    ? 'background: rgba(78,158,90,0.18); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.35);'
                    : 'color: var(--vd-muted); border: 1px solid transparent;' }}">
            {{ $ciudad }}
        </button>
        @endforeach
    </div>

    <div class="space-y-6">

        {{-- ══ SECCIÓN PENDIENTES ══════════════════════════════════════════════ --}}
        <div class="card">

            <div class="flex items-center justify-between mb-4" style="border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 14px;">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                         style="background: rgba(234,179,8,0.12); border: 1px solid rgba(234,179,8,0.3);">
                        <svg width="16" height="16" fill="none" stroke="#facc15" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-condensed font-bold tracking-wide text-xs uppercase"
                            style="color: var(--vd-text); letter-spacing: 1px;">
                            Pendientes — {{ $ciudadActiva }}
                        </h3>
                        <p class="text-xs" style="color: var(--vd-muted);">
                            {{ $totalPendientes }} {{ $totalPendientes === 1 ? 'pedido sin procesar' : 'pedidos sin procesar' }}
                        </p>
                    </div>
                </div>

                @if($totalPendientes > 0)
                <span class="text-sm font-bold px-3 py-1 rounded-full"
                      style="background: rgba(234,179,8,0.15); color: #facc15; border: 1px solid rgba(234,179,8,0.3);">
                    {{ count($seleccionados) }}/{{ $totalPendientes }} sel.
                </span>
                @endif
            </div>

            @if($pendientes->isEmpty())
            <div class="flex items-center gap-3 py-6 justify-center">
                <svg width="20" height="20" fill="none" stroke="var(--vd-muted-2)" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm" style="color: var(--vd-muted);">Sin pedidos pendientes en {{ $ciudadActiva }} 🎉</p>
            </div>

            @else

            {{-- Resumen de menús seleccionados --}}
            @if($resumenPendientes->isNotEmpty())
            <div class="mb-4 p-3 rounded-xl" style="background: rgba(0,0,0,0.12); border: 1px solid var(--vd-bdr-soft);">
                <p class="text-xs font-bold uppercase tracking-widest mb-2" style="color: var(--vd-muted); letter-spacing: 1px;">
                    Menús a preparar (seleccionados)
                </p>
                <div class="flex flex-wrap gap-2">
                    @foreach($resumenPendientes as $r)
                    <span class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-lg font-semibold"
                          style="{{ $r['tamano'] === '400kcal'
                              ? 'background: rgba(168,85,247,0.12); color: #c084fc; border: 1px solid rgba(168,85,247,0.25);'
                              : 'background: rgba(78,158,90,0.12); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.25);' }}">
                        {{ $r['total'] }}× {{ $r['nombre'] }}
                        <span class="opacity-70 font-normal">{{ $r['tamano'] }}</span>
                    </span>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Controles de selección + botón crear batch --}}
            <div class="flex items-center gap-3 mb-4">
                <button type="button" wire:click="seleccionarTodos"
                        class="text-xs px-3 py-1.5 rounded-lg transition-colors"
                        style="color: var(--vd-muted); border: 1px solid var(--vd-bdr-soft);">
                    ✓ Todos
                </button>
                <button type="button" wire:click="deseleccionarTodos"
                        class="text-xs px-3 py-1.5 rounded-lg transition-colors"
                        style="color: var(--vd-muted); border: 1px solid var(--vd-bdr-soft);">
                    ✗ Ninguno
                </button>
                <div class="flex-1"></div>
                @if(auth()->user()->isAdmin() || auth()->user()->isResponsableZona() || auth()->user()->isColaborador())
                <button type="button"
                        wire:click="crearOrdenCocina"
                        wire:loading.attr="disabled"
                        @disabled(empty($seleccionados))
                        class="flex items-center gap-2 py-2 px-4 rounded-xl text-sm font-bold transition-all"
                        style="{{ empty($seleccionados)
                            ? 'background: rgba(0,0,0,0.1); color: var(--vd-muted); border: 1px solid var(--vd-bdr-soft); cursor: not-allowed;'
                            : 'background: rgba(78,158,90,0.18); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.4); cursor: pointer;' }}">
                    <svg wire:loading.remove wire:target="crearOrdenCocina"
                         width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <svg wire:loading wire:target="crearOrdenCocina" class="animate-spin"
                         width="14" height="14" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Enviar a Cocina ({{ count($seleccionados) }})
                </button>
                @endif
            </div>

            {{-- Lista de pedidos pendientes --}}
            <div class="space-y-2">
                @foreach($pendientes as $orden)
                <div class="flex items-start gap-3 p-3 rounded-xl transition-all"
                     style="{{ in_array($orden->id, $seleccionados)
                         ? 'background: rgba(234,179,8,0.07); border: 1px solid rgba(234,179,8,0.25);'
                         : 'background: rgba(0,0,0,0.05); border: 1px solid var(--vd-bdr-soft); opacity: 0.65;' }}">

                    {{-- Checkbox --}}
                    <div class="flex-shrink-0 mt-0.5">
                        <button type="button"
                                wire:click="toggleSeleccionado({{ $orden->id }})"
                                class="w-5 h-5 rounded flex items-center justify-center transition-all"
                                style="{{ in_array($orden->id, $seleccionados)
                                    ? 'background: rgba(234,179,8,0.25); border: 2px solid #facc15;'
                                    : 'background: transparent; border: 2px solid var(--vd-bdr);' }}">
                            @if(in_array($orden->id, $seleccionados))
                            <svg width="10" height="10" fill="none" stroke="#facc15" stroke-width="3" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                            </svg>
                            @endif
                        </button>
                    </div>

                    {{-- Info principal --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <span class="font-mono text-xs font-bold" style="color: var(--vd-text-soft);">
                                #{{ $orden->numero }}
                            </span>
                            <span class="text-sm font-semibold truncate" style="color: var(--vd-text);">
                                {{ $orden->cliente?->nombreCompleto() ?? '—' }}
                            </span>
                            <span class="text-xs" style="color: var(--vd-muted);">· {{ $orden->zona }}</span>
                        </div>

                        {{-- Items --}}
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($orden->items as $item)
                            <span class="text-xs px-2 py-0.5 rounded"
                                  style="{{ $item->tamano === '400kcal'
                                      ? 'background: rgba(168,85,247,0.1); color: #c084fc; border: 1px solid rgba(168,85,247,0.2);'
                                      : 'background: rgba(78,158,90,0.1); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.2);' }}">
                                {{ $item->cantidad }}× {{ $item->producto?->nombre ?? '—' }} {{ $item->tamano }}
                            </span>
                            @endforeach
                        </div>

                        @if($orden->direccion)
                        <p class="text-xs mt-1 truncate" style="color: var(--vd-muted-2);">
                            📍 {{ $orden->direccion }}
                        </p>
                        @endif
                    </div>

                    {{-- Total + forma de pago --}}
                    <div class="text-right flex-shrink-0">
                        <p class="text-sm font-bold" style="color: var(--vd-text);">
                            $ {{ number_format($orden->total, 0, ',', '.') }}
                        </p>
                        @php $fp = $orden->items->first()?->forma_pago; @endphp
                        @if($fp === 'en_destino')
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded"
                              style="background: rgba(239,68,68,0.1); color: #f87171; border: 1px solid rgba(239,68,68,0.2);">
                            Cobrar en destino
                        </span>
                        @elseif($fp === 'transferencia')
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded"
                              style="background: rgba(59,130,246,0.1); color: #93c5fd; border: 1px solid rgba(59,130,246,0.2);">
                            Transferencia
                        </span>
                        @endif
                        <p class="text-[10px] mt-1" style="color: var(--vd-muted-2);">
                            {{ $orden->created_at?->format('H:i') }}
                        </p>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- ══ SECCIÓN EN COCINA (aprobados) ══════════════════════════════════ --}}
        <div class="card">

            <div class="flex items-center gap-3 mb-4" style="border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 14px;">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                     style="background: rgba(59,130,246,0.12); border: 1px solid rgba(59,130,246,0.3);">
                    <svg width="16" height="16" fill="none" stroke="#60a5fa" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.6a8.983 8.983 0 013.361-6.867 8.21 8.21 0 003 2.48z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 00.495-7.467 5.99 5.99 0 00-1.925 3.546 5.974 5.974 0 01-2.133-1A3.75 3.75 0 0012 18z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-condensed font-bold tracking-wide text-xs uppercase"
                        style="color: var(--vd-text); letter-spacing: 1px;">
                        En Cocina — {{ $ciudadActiva }}
                    </h3>
                    <p class="text-xs" style="color: var(--vd-muted);">
                        {{ $batches->count() }} {{ $batches->count() === 1 ? 'batch activo' : 'batches activos' }}
                        · {{ $totalAprobados }} {{ $totalAprobados === 1 ? 'pedido' : 'pedidos' }}
                    </p>
                </div>
            </div>

            @if($batches->isEmpty())
            <div class="flex items-center gap-3 py-6 justify-center">
                <svg width="20" height="20" fill="none" stroke="var(--vd-muted-2)" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
                </svg>
                <p class="text-sm" style="color: var(--vd-muted);">No hay batches activos. Enviá pedidos desde la sección de arriba.</p>
            </div>

            @else

            <div class="space-y-5">
            @foreach($batches as $batch)

            <div class="rounded-xl overflow-hidden" style="border: 1px solid var(--vd-bdr);">

                {{-- Cabecera del batch --}}
                <div class="flex items-center justify-between gap-3 px-4 py-3"
                     style="background: rgba(59,130,246,0.08); border-bottom: 1px solid var(--vd-bdr-soft);">
                    <div class="flex items-center gap-3">
                        <span class="font-mono text-sm font-bold" style="color: #60a5fa;">{{ $batch->numero }}</span>
                        <span class="text-xs" style="color: var(--vd-muted);">
                            {{ $batch->created_at->format('d/m H:i') }}
                            · por {{ $batch->creadoPor?->name }}
                        </span>
                        <span class="text-xs px-2 py-0.5 rounded-full"
                              style="background: rgba(59,130,246,0.12); color: #60a5fa; border: 1px solid rgba(59,130,246,0.25);">
                            {{ $batch->ordenes->count() }} pedidos
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        {{-- Asignación del batch --}}
                        @if($asignandoBatchId === $batch->id)
                        <div class="flex items-center gap-2">
                            <select wire:model="colaboradorBatchId"
                                    class="input py-1 text-xs"
                                    style="min-width: 160px;">
                                <option value="0" style="background:var(--vd-bg-2);">Sin asignar</option>
                                @foreach($colaboradoresCocina as $col)
                                <option value="{{ $col->id }}" style="background:var(--vd-bg-2);">
                                    {{ $col->name }} {{ $col->apellido }} ({{ $col->role }})
                                </option>
                                @endforeach
                            </select>
                            <button type="button" wire:click="confirmarAsignacionBatch"
                                    class="text-xs px-3 py-1.5 rounded-lg font-bold"
                                    style="background: rgba(78,158,90,0.15); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.35);">
                                ✓
                            </button>
                            <button type="button" wire:click="cerrarAsignacionBatch"
                                    class="text-xs px-3 py-1.5 rounded-lg"
                                    style="color: var(--vd-muted); border: 1px solid var(--vd-bdr-soft);">
                                ✕
                            </button>
                        </div>
                        @else
                        @if($batch->asignado)
                        <span class="text-xs flex items-center gap-1.5"
                              style="color: var(--vd-muted);">
                            <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                            </svg>
                            {{ $batch->asignado->name }}
                        </span>
                        @endif

                        @if(auth()->user()->isAdmin() || auth()->user()->isResponsableZona())
                        <button type="button" wire:click="abrirAsignacionBatch({{ $batch->id }})"
                                class="text-xs px-3 py-1.5 rounded-lg transition-colors"
                                style="color: var(--vd-muted); border: 1px solid var(--vd-bdr-soft);">
                            {{ $batch->asignado ? 'Reasignar' : 'Asignar' }}
                        </button>
                        @endif
                        @endif
                    </div>
                </div>

                {{-- Resumen de menús del batch --}}
                @php
                    $resumenBatch = $batch->ordenes->flatMap->items
                        ->groupBy(fn($i) => ($i->producto?->nombre ?? '—') . '|||' . $i->tamano)
                        ->map(fn($g) => [
                            'nombre' => $g->first()->producto?->nombre ?? '—',
                            'tamano' => $g->first()->tamano,
                            'total'  => (int) $g->sum('cantidad'),
                            'listos' => (int) $g->filter(fn($i) => $i->orden?->estado === 'lista_para_entrega')->sum('cantidad'),
                        ])
                        ->sortByDesc('total')
                        ->values();
                @endphp
                <div class="px-4 py-2.5 flex flex-wrap gap-2" style="background: rgba(0,0,0,0.06); border-bottom: 1px solid var(--vd-bdr-soft);">
                    @foreach($resumenBatch as $r)
                    <span class="text-xs px-2.5 py-1 rounded-lg font-semibold flex items-center gap-1.5"
                          style="{{ $r['tamano'] === '400kcal'
                              ? 'background: rgba(168,85,247,0.1); color: #c084fc; border: 1px solid rgba(168,85,247,0.2);'
                              : 'background: rgba(78,158,90,0.1); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.2);' }}">
                        {{ $r['listos'] }}/{{ $r['total'] }}× {{ $r['nombre'] }} {{ $r['tamano'] }}
                    </span>
                    @endforeach
                </div>

                {{-- Lista de órdenes del batch --}}
                <div class="divide-y" style="divide-color: var(--vd-bdr-soft);">
                @foreach($batch->ordenes->sortBy('created_at') as $orden)
                <div class="flex items-center gap-3 px-4 py-3 transition-all"
                     style="{{ $orden->estado === 'lista_para_entrega'
                         ? 'background: rgba(78,158,90,0.05);'
                         : '' }}">

                    {{-- Estado visual --}}
                    <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0"
                         style="{{ $orden->estado === 'lista_para_entrega'
                             ? 'background: rgba(78,158,90,0.15); border: 1px solid rgba(78,158,90,0.35);'
                             : 'background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.25);' }}">
                        @if($orden->estado === 'lista_para_entrega')
                        <svg width="13" height="13" fill="none" stroke="#4e9e5a" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                        </svg>
                        @else
                        <svg width="13" height="13" fill="none" stroke="#60a5fa" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        @endif
                    </div>

                    {{-- Info --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-mono text-xs" style="color: var(--vd-muted);">#{{ $orden->numero }}</span>
                            <span class="text-sm font-semibold" style="color: var(--vd-text);">
                                {{ $orden->cliente?->nombreCompleto() ?? '—' }}
                            </span>
                            @if($orden->direccion)
                            <span class="text-xs truncate max-w-[200px]" style="color: var(--vd-muted-2);">
                                📍 {{ $orden->direccion }}
                            </span>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-1 mt-0.5">
                            @foreach($orden->items as $item)
                            <span class="text-[10px] px-1.5 py-0.5 rounded"
                                  style="{{ $item->tamano === '400kcal'
                                      ? 'background: rgba(168,85,247,0.08); color: #c084fc;'
                                      : 'background: rgba(78,158,90,0.08); color: #4e9e5a;' }}">
                                {{ $item->cantidad }}× {{ $item->producto?->nombre ?? '—' }} {{ $item->tamano }}
                            </span>
                            @endforeach

                            {{-- Cobro en destino --}}
                            @if($orden->items->first()?->forma_pago === 'en_destino')
                            <span class="text-[10px] px-1.5 py-0.5 rounded font-bold"
                                  style="background: rgba(239,68,68,0.08); color: #f87171; border: 1px solid rgba(239,68,68,0.15);">
                                💳 $ {{ number_format($orden->total, 0, ',', '.') }} en destino
                            </span>
                            @endif
                        </div>

                        {{-- Asignado individualmente --}}
                        @if($orden->asignadoCocina)
                        <p class="text-[10px] mt-0.5" style="color: var(--vd-muted-2);">
                            👤 {{ $orden->asignadoCocina->name }} (asignado)
                        </p>
                        @endif
                    </div>

                    {{-- Acciones --}}
                    <div class="flex items-center gap-2 flex-shrink-0">

                        {{-- Reasignación individual --}}
                        @if($reasignandoOrdenId === $orden->id)
                        <div class="flex items-center gap-1.5">
                            <select wire:model="colaboradorOrdenId"
                                    class="input py-1 text-xs"
                                    style="min-width: 130px;">
                                <option value="0" style="background:var(--vd-bg-2);">Sin asignar</option>
                                @foreach($colaboradoresCocina as $col)
                                <option value="{{ $col->id }}" style="background:var(--vd-bg-2);">
                                    {{ $col->name }}
                                </option>
                                @endforeach
                            </select>
                            <button type="button" wire:click="confirmarReasignacion"
                                    class="text-xs px-2 py-1 rounded font-bold"
                                    style="background: rgba(78,158,90,0.15); color: #4e9e5a;">✓</button>
                            <button type="button" wire:click="cerrarReasignacion"
                                    class="text-xs px-2 py-1 rounded"
                                    style="color: var(--vd-muted);">✕</button>
                        </div>
                        @else
                        @if(auth()->user()->isAdmin() || auth()->user()->isResponsableZona() || auth()->user()->isColaborador())
                        <button type="button"
                                wire:click="abrirReasignacion({{ $orden->id }})"
                                class="text-xs px-2.5 py-1.5 rounded-lg transition-colors"
                                style="color: var(--vd-muted); border: 1px solid var(--vd-bdr-soft);">
                            ↻
                        </button>
                        @endif
                        @endif

                        {{-- Marcar listo --}}
                        @if($orden->estado === 'aprobada')
                        <button type="button"
                                wire:click="marcarListo({{ $orden->id }})"
                                wire:loading.attr="disabled"
                                wire:target="marcarListo({{ $orden->id }})"
                                class="flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-lg font-semibold transition-all"
                                style="background: rgba(78,158,90,0.15); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.35); cursor: pointer;">
                            <svg wire:loading.remove wire:target="marcarListo({{ $orden->id }})"
                                 width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                            </svg>
                            <svg wire:loading wire:target="marcarListo({{ $orden->id }})" class="animate-spin"
                                 width="12" height="12" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Listo
                        </button>
                        @else
                        <span class="text-xs px-3 py-1.5 rounded-lg font-semibold"
                              style="background: rgba(78,158,90,0.08); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.2);">
                            ✓ Listo
                        </span>
                        @endif

                    </div>
                </div>
                @endforeach
                </div>

            </div>{{-- /batch --}}
            @endforeach
            </div>

            @endif

        </div>{{-- /card en cocina --}}

    </div>{{-- /space-y-6 --}}

    @endif {{-- /if ciudades --}}

</div>
