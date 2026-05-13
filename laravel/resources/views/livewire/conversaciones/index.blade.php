<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\Conversacion;
use App\Services\OllamaService;

new #[Layout('layouts.app', ['title' => 'Conversaciones'])] class extends Component {

    use WithPagination;

    public string  $buscar  = '';
    public string  $zona    = '';
    public string  $estado  = '';

    public ?int    $sugerenciaId      = null;
    public string  $sugerenciaTexto   = '';
    public string  $sugerenciaMensaje = '';
    public bool    $sugerenciaCargando = false;

    public function updatingBuscar(): void { $this->resetPage(); }
    public function updatingZona(): void   { $this->resetPage(); }
    public function updatingEstado(): void { $this->resetPage(); }

    public function with(): array
    {
        return [
            'conversaciones' => Conversacion::query()
                ->when($this->buscar, fn($q) =>
                    $q->where(fn($q) =>
                        $q->where('telefono', 'like', "%{$this->buscar}%")
                          ->orWhere('nombre', 'like', "%{$this->buscar}%")
                    )
                )
                ->when($this->zona,   fn($q) => $q->zona($this->zona))
                ->when($this->estado, fn($q) => $q->where('estado', $this->estado))
                ->orderByDesc('ultimo_mensaje_at')
                ->paginate(20),
        ];
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
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
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
            <div class="mb-4 rounded-xl px-4 py-3"
                 style="background: var(--vd-input-bg); border: 1px solid var(--vd-bdr-soft);">
                <p class="text-xs font-condensed uppercase tracking-wide mb-1" style="color: var(--vd-muted-2);">Último mensaje del cliente</p>
                <p class="text-sm" style="color: var(--vd-text-soft);">{{ $sugerenciaMensaje }}</p>
            </div>
            @endif

            <div class="rounded-xl px-4 py-3 mb-4 min-h-[80px]"
                 style="background: rgba(58,125,68,0.08); border: 1px solid rgba(78,158,90,0.2);">
                <p class="text-xs font-condensed uppercase tracking-wide mb-1" style="color: #4e9e5a;">Respuesta sugerida</p>
                @if($sugerenciaCargando)
                <div class="flex items-center gap-2 mt-2">
                    <span class="w-2 h-2 rounded-full animate-bounce" style="background: #4e9e5a; animation-delay: 0ms;"></span>
                    <span class="w-2 h-2 rounded-full animate-bounce" style="background: #4e9e5a; animation-delay: 150ms;"></span>
                    <span class="w-2 h-2 rounded-full animate-bounce" style="background: #4e9e5a; animation-delay: 300ms;"></span>
                    <span class="text-xs" style="color: var(--vd-muted);">Generando...</span>
                </div>
                @else
                <p class="text-sm leading-relaxed" style="color: var(--vd-text);">{{ $sugerenciaTexto }}</p>
                @endif
            </div>

            <div class="flex justify-end gap-3">
                <button wire:click="cerrarSugerencia" class="btn-secondary text-sm">Cerrar</button>
                @if($sugerenciaTexto && ! $sugerenciaCargando)
                <button onclick="navigator.clipboard.writeText({{ json_encode($sugerenciaTexto) }}).then(() => { this.textContent = '¡Copiado!'; setTimeout(() => this.textContent = 'Copiar texto', 2000); })"
                        class="btn-primary text-sm">Copiar texto</button>
                @endif
            </div>
        </div>
    </div>
    @endif

    <div class="flex flex-wrap gap-3 mb-6">
        <input type="text" wire:model.live.debounce.300ms="buscar"
               placeholder="Buscar por número o nombre…" class="input w-64">
        <select wire:model.live="zona" class="input w-48">
            <option value="">Todas las zonas</option>
            <option value="bsas">BSAS</option>
            <option value="valle_nqn">Valle NQN / Roca</option>
            <option value="cordoba">Córdoba</option>
            <option value="mendoza">Mendoza</option>
        </select>
        <select wire:model.live="estado" class="input w-36">
            <option value="">Todos</option>
            <option value="abierta">Abierta</option>
            <option value="cerrada">Cerrada</option>
            <option value="esperando">Esperando</option>
        </select>
    </div>

    <div class="card p-0 overflow-hidden">
        <table class="w-full text-sm">
            <thead style="background: var(--vd-bg-2); border-bottom: 1px solid var(--vd-bdr);">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-semibold uppercase tracking-wider"
                        style="color: var(--vd-muted-2);">Contacto</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold uppercase tracking-wider"
                        style="color: var(--vd-muted-2);">Zona</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold uppercase tracking-wider"
                        style="color: var(--vd-muted-2);">Último mensaje</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold uppercase tracking-wider"
                        style="color: var(--vd-muted-2);">Estado</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($conversaciones as $conv)
                <tr style="border-bottom: 1px solid var(--vd-bdr-soft);">
                    <td class="px-6 py-4">
                        <p class="font-medium" style="color: var(--vd-text);">{{ $conv->nombre ?? 'Sin nombre' }}</p>
                        <p class="text-xs" style="color: var(--vd-muted-2);">+{{ $conv->telefono }}</p>
                    </td>
                    <td class="px-6 py-4" style="color: var(--vd-muted);">
                        {{ match($conv->zona) {
                            'bsas'      => 'BSAS',
                            'valle_nqn' => 'Valle NQN / Roca',
                            'cordoba'   => 'Córdoba',
                            'mendoza'   => 'Mendoza',
                            default     => $conv->zona,
                        } }}
                    </td>
                    <td class="px-6 py-4 max-w-xs">
                        <p class="truncate" style="color: var(--vd-text);">{{ $conv->ultimo_mensaje ?? '—' }}</p>
                        @if($conv->ultimo_mensaje_at)
                        <p class="text-xs mt-0.5" style="color: var(--vd-muted-2);">{{ $conv->ultimo_mensaje_at->diffForHumans() }}</p>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <span class="{{ match($conv->estado) {
                            'abierta'   => 'badge-green',
                            'esperando' => 'badge-gray',
                            'cerrada'   => 'badge-gray',
                            default     => 'badge-gray',
                        } }}">
                            {{ ucfirst($conv->estado) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            @if($conv->ultimo_mensaje && (auth()->user()->isAdmin() || auth()->user()->isResponsableZona()))
                            <button wire:click="sugerirRespuesta({{ $conv->id }})"
                                    class="btn-secondary text-xs px-3 py-1.5 flex items-center gap-1.5"
                                    style="color: #4e9e5a; border-color: rgba(78,158,90,0.3);"
                                    onmouseover="this.style.background='rgba(58,125,68,0.1)'"
                                    onmouseout="this.style.background=''">
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                                </svg>
                                Sugerir
                            </button>
                            @endif
                            <a href="https://wa.me/{{ $conv->telefono }}" target="_blank"
                               class="btn-secondary text-xs px-3 py-1.5">Abrir WA</a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-16 text-center">
                        <p class="text-lg mb-1" style="color: var(--vd-muted);">Sin conversaciones</p>
                        <p class="text-sm" style="color: var(--vd-muted-2);">
                            Conectá una instancia de WhatsApp en
                            <a href="{{ route('zonas') }}" style="color: var(--vd-green-lt);" class="underline">Zonas</a>
                            para comenzar.
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
