<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\Conversacion;
use App\Services\OllamaService;

new #[Layout('layouts.app', ['title' => 'Conversaciones'])] class extends Component {

    use WithPagination;

    public string $buscar    = '';
    public string $zona      = '';
    public string $estado    = '';
    public string $categoria = 'todos';

    public ?int   $sugerenciaId       = null;
    public string $sugerenciaTexto    = '';
    public string $sugerenciaMensaje  = '';
    public bool   $sugerenciaCargando = false;

    public function updatingBuscar(): void    { $this->resetPage(); }
    public function updatingZona(): void      { $this->resetPage(); }
    public function updatingEstado(): void    { $this->resetPage(); }
    public function updatingCategoria(): void { $this->resetPage(); }

    public function with(): array
    {
        $activeStates = ['pendiente', 'aprobada', 'lista_para_entrega'];

        $base = Conversacion::query()
            ->when($this->buscar, fn($q) =>
                $q->where(fn($q) =>
                    $q->where('telefono', 'like', "%{$this->buscar}%")
                      ->orWhere('nombre',   'like', "%{$this->buscar}%")
                )
            )
            ->when($this->zona,   fn($q) => $q->zona($this->zona))
            ->when($this->estado, fn($q) => $q->where('estado', $this->estado));

        $hasPendiente = fn($q) => $q->whereHas('usuarioVinculado', fn($u) =>
            $u->whereHas('ordenes', fn($o) => $o->whereIn('estado', $activeStates))
        );
        $hasEntregado = fn($q) => $q->whereHas('usuarioVinculado', fn($u) =>
            $u->whereHas('ordenes', fn($o) => $o->where('estado', 'entregada'))
        );
        $noActivePedido = fn($q) => $q->whereDoesntHave('usuarioVinculado', fn($u) =>
            $u->whereHas('ordenes', fn($o) => $o->whereIn('estado', $activeStates))
        );
        $esConsulta = fn($q) => $q->where(fn($q) =>
            $q->whereDoesntHave('usuarioVinculado')
              ->orWhereHas('usuarioVinculado', fn($u) => $u->whereDoesntHave('ordenes'))
        );

        $counts = [
            'todos'     => (clone $base)->count(),
            'consulta'  => (clone $base)->tap($esConsulta)->count(),
            'pendiente' => (clone $base)->tap($hasPendiente)->count(),
            'entregado' => (clone $base)->tap($hasEntregado)->tap($noActivePedido)->count(),
        ];

        $conversaciones = (clone $base)
            ->when($this->categoria === 'consulta',  $esConsulta)
            ->when($this->categoria === 'pendiente', $hasPendiente)
            ->when($this->categoria === 'entregado', fn($q) => $q->tap($hasEntregado)->tap($noActivePedido))
            ->orderByDesc('ultimo_mensaje_at')
            ->paginate(20);

        return compact('conversaciones', 'counts');
    }

    public function sugerirRespuesta(int $id, OllamaService $ollama): void
    {
        if (! $ollama->isAvailable()) {
            $this->sugerenciaTexto   = 'Ollama no está disponible en este momento.';
            $this->sugerenciaId      = $id;
            $this->sugerenciaMensaje = '';
            return;
        }

        $conv = Conversacion::findOrFail($id);
        $this->sugerenciaId       = $id;
        $this->sugerenciaMensaje  = $conv->ultimo_mensaje ?? '';
        $this->sugerenciaTexto    = '';
        $this->sugerenciaCargando = true;

        try {
            $context = "Cliente: {$conv->nombre}, Teléfono: {$conv->telefono}, Zona: {$conv->zona}, Estado: {$conv->estado}";
            $this->sugerenciaTexto = $ollama->suggestReply($conv->ultimo_mensaje ?? '', $context);
        } catch (\Throwable $e) {
            $this->sugerenciaTexto = 'Error al generar sugerencia: ' . $e->getMessage();
        } finally {
            $this->sugerenciaCargando = false;
        }
    }

    public function cerrarSugerencia(): void
    {
        $this->sugerenciaId       = null;
        $this->sugerenciaTexto    = '';
        $this->sugerenciaMensaje  = '';
        $this->sugerenciaCargando = false;
    }

}; ?>

<div>

    {{-- Sugerencia modal --}}
    @if($sugerenciaId)
    <div class="fixed inset-0 z-50 flex items-center justify-center"
         style="background: rgba(0,0,0,0.55); backdrop-filter: blur(4px);">
        <div class="w-full max-w-lg rounded-2xl p-6"
             style="background: var(--vd-surface-2); border: 1px solid rgba(78,158,90,0.3); box-shadow: 0 24px 60px rgba(0,0,0,0.4);">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <svg width="16" height="16" fill="none" stroke="#4e9e5a" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                    </svg>
                    <h3 class="font-condensed font-bold text-base" style="color: var(--vd-text);">Sugerencia de respuesta</h3>
                </div>
                <button wire:click="cerrarSugerencia" style="color: var(--vd-muted-2);"
                        onmouseover="this.style.color='var(--vd-text)'" onmouseout="this.style.color='var(--vd-muted-2)'">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            @if($sugerenciaMensaje)
            <div class="mb-4 rounded-xl px-4 py-3" style="background: var(--vd-input-bg); border: 1px solid var(--vd-bdr-soft);">
                <p class="text-xs font-condensed uppercase tracking-wide mb-1" style="color: var(--vd-muted-2);">Último mensaje del cliente</p>
                <p class="text-sm" style="color: var(--vd-text-soft);">{{ $sugerenciaMensaje }}</p>
            </div>
            @endif
            <div class="rounded-xl px-4 py-3 mb-4 min-h-[80px]"
                 style="background: rgba(58,125,68,0.08); border: 1px solid rgba(78,158,90,0.2);">
                <p class="text-xs font-condensed uppercase tracking-wide mb-1" style="color: #4e9e5a;">Respuesta sugerida</p>
                @if($sugerenciaCargando)
                <div class="flex items-center gap-2 mt-2">
                    <span class="w-2 h-2 rounded-full animate-bounce" style="background:#4e9e5a;animation-delay:0ms;"></span>
                    <span class="w-2 h-2 rounded-full animate-bounce" style="background:#4e9e5a;animation-delay:150ms;"></span>
                    <span class="w-2 h-2 rounded-full animate-bounce" style="background:#4e9e5a;animation-delay:300ms;"></span>
                    <span class="text-xs" style="color: var(--vd-muted);">Generando...</span>
                </div>
                @else
                <p class="text-sm leading-relaxed" style="color: var(--vd-text);">{{ $sugerenciaTexto }}</p>
                @endif
            </div>
            <div class="flex justify-end gap-3">
                <button wire:click="cerrarSugerencia" class="btn-secondary text-sm">Cerrar</button>
                @if($sugerenciaTexto && !$sugerenciaCargando)
                <button onclick="navigator.clipboard.writeText({{ json_encode($sugerenciaTexto) }}).then(() => { this.textContent = '¡Copiado!'; setTimeout(() => this.textContent = 'Copiar texto', 2000); })"
                        class="btn-primary text-sm">Copiar texto</button>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- Filtros secundarios --}}
    <div class="flex flex-wrap gap-3 mb-4">
        <input type="text" wire:model.live.debounce.300ms="buscar"
               placeholder="Buscar por número o nombre…" class="input w-64">
        <select wire:model.live="zona" class="input w-48">
            <option value="">Todas las zonas</option>
            @foreach(Conversacion::zonas() as $slug => $label)
            <option value="{{ $slug }}">{{ $label }}</option>
            @endforeach
        </select>
        <select wire:model.live="estado" class="input w-40">
            <option value="">Cualquier estado</option>
            @foreach(Conversacion::estadosConv() as $val => $label)
            <option value="{{ $val }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    {{-- Tabs de categoría --}}
    <div class="flex gap-1 mb-5 p-1 rounded-xl" style="background: rgba(0,0,0,0.2); border: 1px solid var(--vd-bdr-soft);">
        @foreach([
            'todos'     => ['Todos',     null],
            'consulta'  => ['Consulta',  'rgba(120,120,130,0.5)'],
            'pendiente' => ['Pendiente', 'rgba(200,160,48,0.7)'],
            'entregado' => ['Entregado', 'rgba(78,158,90,0.7)'],
        ] as $tab => [$label, $badgeColor])
        <button type="button" wire:click="$set('categoria', '{{ $tab }}')"
                class="flex-1 flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-sm font-semibold transition-all"
                style="{{ $categoria === $tab
                    ? 'background: rgba(78,158,90,0.18); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.35);'
                    : 'color: var(--vd-muted); border: 1px solid transparent;' }}">
            {{ $label }}
            <span class="rounded-full min-w-[20px] h-5 px-1.5 flex items-center justify-center font-bold"
                  style="font-size: 10px; background: {{ $categoria === $tab ? 'rgba(78,158,90,0.25)' : 'rgba(255,255,255,0.06)' }}; color: {{ $categoria === $tab ? '#4e9e5a' : 'var(--vd-muted-2)' }};">
                {{ $counts[$tab] }}
            </span>
        </button>
        @endforeach
    </div>

    {{-- Tabla --}}
    <div class="card p-0 overflow-hidden">
        <table class="w-full text-sm">
            <thead style="background: var(--vd-bg-2); border-bottom: 1px solid var(--vd-bdr);">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-semibold uppercase tracking-wider" style="color: var(--vd-muted-2);">Contacto</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold uppercase tracking-wider" style="color: var(--vd-muted-2);">Zona</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold uppercase tracking-wider" style="color: var(--vd-muted-2);">Último mensaje</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold uppercase tracking-wider" style="color: var(--vd-muted-2);">Estado</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($conversaciones as $conv)
                <tr wire:key="{{ $conv->id }}"
                    style="border-bottom: 1px solid var(--vd-bdr-soft); cursor: pointer; transition: background .12s;"
                    onmouseover="this.style.background='var(--vd-nav-hover)'"
                    onmouseout="this.style.background=''"
                    onclick="window.location='{{ route('conversaciones.ver', $conv) }}'">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm"
                                 style="background: rgba(58,125,68,0.18); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.25);">
                                {{ strtoupper(substr($conv->nombre ?? '?', 0, 1)) }}
                            </div>
                            <div>
                                <p class="font-medium" style="color: var(--vd-text);">{{ $conv->nombre ?? 'Sin nombre' }}</p>
                                <p class="text-xs font-mono" style="color: var(--vd-muted-2);">+{{ $conv->telefono }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4" style="color: var(--vd-muted);">{{ $conv->zonaLabel() }}</td>
                    <td class="px-6 py-4 max-w-xs">
                        <p class="truncate" style="color: var(--vd-text);">{{ $conv->ultimo_mensaje ?? '—' }}</p>
                        @if($conv->ultimo_mensaje_at)
                        <p class="text-xs mt-0.5" style="color: var(--vd-muted-2);">{{ $conv->ultimo_mensaje_at->diffForHumans() }}</p>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <span class="{{ match($conv->estado) {
                            'abierta'   => 'badge-green',
                            'esperando' => 'badge-yellow',
                            default     => 'badge-gray',
                        } }}">{{ ucfirst($conv->estado) }}</span>
                    </td>
                    <td class="px-6 py-4 text-right" onclick="event.stopPropagation()">
                        <div class="flex items-center justify-end gap-2">
                            @if($conv->ultimo_mensaje && (auth()->user()->isAdmin() || auth()->user()->isResponsableZona()))
                            <button wire:click.stop="sugerirRespuesta({{ $conv->id }})"
                                    class="btn-secondary text-xs px-3 py-1.5 flex items-center gap-1.5"
                                    style="color: #4e9e5a; border-color: rgba(78,158,90,0.3);"
                                    onmouseover="this.style.background='rgba(58,125,68,0.1)'"
                                    onmouseout="this.style.background=''">
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                                </svg>
                                IA
                            </button>
                            @endif
                            <a href="{{ route('conversaciones.ver', $conv) }}" wire:navigate
                               class="btn-secondary text-xs px-3 py-1.5 flex items-center gap-1"
                               onclick="event.stopPropagation()">
                                Ver
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-16 text-center">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3"
                             style="background: rgba(58,125,68,0.08);">
                            <svg width="22" height="22" fill="none" stroke="#4e9e5a" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>
                            </svg>
                        </div>
                        <p class="font-semibold mb-1" style="color: var(--vd-muted);">Sin conversaciones en esta categoría</p>
                        <p class="text-sm" style="color: var(--vd-muted-2);">
                            @if($categoria === 'pendiente') Ningún contacto tiene un pedido activo en este momento.
                            @elseif($categoria === 'entregado') Ningún contacto tiene pedidos entregados aún.
                            @elseif($categoria === 'consulta') No hay contactos sin pedidos registrados.
                            @else Conectá una instancia de WhatsApp en <a href="{{ route('zonas') }}" style="color: var(--vd-green-lt);" class="underline">Zonas</a> para comenzar.
                            @endif
                        </p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        @if($conversaciones->hasPages())
        <div class="px-6 py-4" style="border-top: 1px solid var(--vd-bdr);">
            {{ $conversaciones->links() }}
        </div>
        @endif
    </div>
</div>
