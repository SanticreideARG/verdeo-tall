<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\ActividadLog;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.app', ['title' => 'Sistema · Log'])] class extends Component {
    use WithPagination;

    public string $filtroAccion  = '';
    public string $filtroModelo  = '';
    public string $filtroUsuario = '';
    public string $buscar        = '';

    public function mount(): void
    {
        if (! auth()->user()->isAdmin()) {
            $this->redirect(route('dashboard'), navigate: true);
        }
    }

    public function with(): array
    {
        $q = ActividadLog::with('user:id,name,apellido,foto')
            ->when($this->filtroAccion,  fn($q) => $q->where('accion',  $this->filtroAccion))
            ->when($this->filtroModelo,  fn($q) => $q->where('modelo',  $this->filtroModelo))
            ->when($this->filtroUsuario, fn($q) => $q->where('user_id', $this->filtroUsuario))
            ->when($this->buscar, fn($q) => $q->where('descripcion', 'like', '%'.$this->buscar.'%'))
            ->orderByDesc('created_at')
            ->paginate(40);

        $usuarios = User::orderBy('name')->get(['id', 'name', 'apellido']);
        $modelos  = ActividadLog::distinct()->orderBy('modelo')->pluck('modelo');

        return compact('q', 'usuarios', 'modelos');
    }

    public function resetFiltros(): void
    {
        $this->filtroAccion = $this->filtroModelo = $this->filtroUsuario = $this->buscar = '';
        $this->resetPage();
    }
}; ?>

<div>
    {{-- Header --}}
    <div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
        <div>
            <h1 class="font-condensed font-bold text-2xl" style="color: var(--vd-text);">Log de actividad</h1>
            <p class="text-sm mt-0.5" style="color: var(--vd-muted);">
                {{ $q->total() }} {{ $q->total() === 1 ? 'entrada' : 'entradas' }} registradas
            </p>
        </div>
        <button wire:click="resetFiltros"
                class="btn-secondary text-xs flex items-center gap-1.5"
                style="padding: 0.35rem 0.85rem;">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M4.05 9A9 9 0 0 1 19.94 15M19.95 15A9 9 0 0 1 4.06 9"/>
            </svg>
            Resetear filtros
        </button>
    </div>

    {{-- Filter bar --}}
    <div class="flex flex-wrap gap-2 mb-5">
        <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-2.5 pointer-events-none" style="color: var(--vd-muted-2);">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/>
                </svg>
            </span>
            <input wire:model.live.debounce.250ms="buscar"
                   type="text"
                   placeholder="Buscar descripción…"
                   class="pl-8 pr-3 py-1.5 text-sm rounded-lg"
                   style="background: var(--vd-surface-2); border: 1px solid var(--vd-bdr-soft); color: var(--vd-text); width: 200px; outline: none;">
        </div>

        <select wire:model.live="filtroAccion"
                class="text-sm rounded-lg px-2.5 py-1.5"
                style="background: var(--vd-surface-2); border: 1px solid var(--vd-bdr-soft); color: var(--vd-text); outline: none;">
            <option value="">Toda acción</option>
            <option value="crear">Crear</option>
            <option value="actualizar">Actualizar</option>
            <option value="eliminar">Eliminar</option>
        </select>

        <select wire:model.live="filtroModelo"
                class="text-sm rounded-lg px-2.5 py-1.5"
                style="background: var(--vd-surface-2); border: 1px solid var(--vd-bdr-soft); color: var(--vd-text); outline: none;">
            <option value="">Toda sección</option>
            @foreach($modelos as $modelo)
                <option value="{{ $modelo }}">{{ $modelo }}</option>
            @endforeach
        </select>

        <select wire:model.live="filtroUsuario"
                class="text-sm rounded-lg px-2.5 py-1.5"
                style="background: var(--vd-surface-2); border: 1px solid var(--vd-bdr-soft); color: var(--vd-text); outline: none;">
            <option value="">Todo usuario</option>
            @foreach($usuarios as $u)
                <option value="{{ $u->id }}">{{ trim($u->name . ' ' . $u->apellido) }}</option>
            @endforeach
        </select>
    </div>

    @if($q->isEmpty())
        <div class="text-center py-20" style="color: var(--vd-muted);">
            <svg class="mx-auto mb-3 opacity-40" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 5.25 6h.008c1.135 0 2.098.845 2.192 1.976"/>
            </svg>
            <p class="text-sm">No hay registros para los filtros seleccionados.</p>
        </div>
    @else
        <div class="rounded-2xl overflow-hidden" style="border: 1px solid var(--vd-bdr);">
            @php
                $prevDay = null;
                $today = now()->toDateString();
            @endphp

            @foreach($q as $log)
                @php
                    $day = $log->created_at->toDateString();
                    $accionConfig = match($log->accion) {
                        'crear'      => ['bg' => 'rgba(78,158,90,0.15)',   'text' => '#6dbf7a', 'label' => 'Creó'],
                        'actualizar' => ['bg' => 'rgba(96,165,250,0.15)',  'text' => '#93c5fd', 'label' => 'Actualizó'],
                        'eliminar'   => ['bg' => 'rgba(248,113,113,0.15)', 'text' => '#fca5a5', 'label' => 'Eliminó'],
                        default      => ['bg' => 'rgba(255,255,255,0.08)', 'text' => 'var(--vd-muted)', 'label' => ucfirst($log->accion)],
                    };
                    $iconSvg = match($log->accion) {
                        'crear'      => '<svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 4v16m8-8H4"/></svg>',
                        'actualizar' => '<svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Z"/></svg>',
                        'eliminar'   => '<svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>',
                        default      => '',
                    };
                    $ts = $log->created_at;
                    $tsLabel = ($ts->toDateString() === $today) ? $ts->format('H:i') : $ts->format('d/m H:i');
                @endphp

                @if($day !== $prevDay)
                    @php $prevDay = $day; @endphp
                    <div class="flex items-center gap-3 px-4 py-2" style="background: var(--vd-surface);">
                        <div class="flex-1 h-px" style="background: var(--vd-bdr-soft);"></div>
                        <span class="text-xs font-medium px-3 py-0.5 rounded-full"
                              style="background: var(--vd-surface-2); color: var(--vd-muted); border: 1px solid var(--vd-bdr-soft);">
                            @if($day === $today)
                                Hoy
                            @elseif($day === now()->subDay()->toDateString())
                                Ayer
                            @else
                                {{ $log->created_at->isoFormat('dddd D [de] MMMM') }}
                            @endif
                        </span>
                        <div class="flex-1 h-px" style="background: var(--vd-bdr-soft);"></div>
                    </div>
                @endif

                <div wire:key="{{ $log->id }}"
                     class="flex items-start gap-3 px-4 py-3 transition-colors"
                     style="border-bottom: 1px solid var(--vd-bdr-soft); background: var(--vd-surface);"
                     onmouseover="this.style.background='var(--vd-row-hover)'"
                     onmouseout="this.style.background='var(--vd-surface)'">

                    <div class="flex-shrink-0 mt-0.5">
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full"
                              style="background: {{ $accionConfig['bg'] }}; color: {{ $accionConfig['text'] }};">
                            {!! $iconSvg !!}
                        </span>
                    </div>

                    <div class="flex-1 min-w-0" x-data="{ expanded: false }">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            @if($log->user)
                                @if($log->user->foto)
                                    <img src="{{ Storage::url($log->user->foto) }}"
                                         alt="{{ $log->user->name }}"
                                         class="w-6 h-6 rounded-full object-cover flex-shrink-0"
                                         style="border: 1px solid var(--vd-bdr-soft);">
                                @else
                                    @php
                                        $initials = strtoupper(substr($log->user->name ?? '?', 0, 1) . substr($log->user->apellido ?? '', 0, 1));
                                    @endphp
                                    <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold"
                                         style="background: var(--vd-green-lt); color: var(--vd-text); border: 1px solid var(--vd-bdr-soft);">
                                        {{ $initials }}
                                    </div>
                                @endif
                                <span class="text-xs font-semibold" style="color: var(--vd-text);">
                                    {{ trim($log->user->name . ' ' . $log->user->apellido) }}
                                </span>
                                <span class="text-xs px-1.5 py-0.5 rounded-md font-medium"
                                      style="background: {{ $accionConfig['bg'] }}; color: {{ $accionConfig['text'] }};">
                                    {{ $accionConfig['label'] }}
                                </span>
                            @else
                                <span class="text-xs italic" style="color: var(--vd-muted-2);">Sistema</span>
                            @endif
                            <span class="text-xs" style="color: var(--vd-muted);">{{ $log->descripcion }}</span>
                        </div>

                        @if(! empty($log->cambios))
                            <div class="mt-1">
                                <button @click="expanded = !expanded"
                                        class="text-xs flex items-center gap-1 transition-opacity hover:opacity-80"
                                        style="color: var(--vd-muted-2);">
                                    <svg class="transition-transform" :class="expanded ? 'rotate-90' : ''"
                                         width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/>
                                    </svg>
                                    <span x-text="expanded ? 'Ocultar cambios' : '{{ count($log->cambios) }} campo(s) modificado(s)'"></span>
                                </button>
                                <div x-show="expanded" x-collapse class="mt-1.5 space-y-0.5 pl-3"
                                     style="border-left: 2px solid var(--vd-bdr-soft);">
                                    @foreach($log->cambios as $cambio)
                                        <div class="text-xs" style="color: var(--vd-muted);">
                                            <span class="font-medium" style="color: var(--vd-muted-2);">{{ $cambio['campo'] }}:</span>
                                            <code class="px-1 rounded text-xs"
                                                  style="background: rgba(248,113,113,0.12); color: #fca5a5;">{{ $cambio['de'] ?? '(vacío)' }}</code>
                                            <span style="color: var(--vd-muted-2);">&#8594;</span>
                                            <code class="px-1 rounded text-xs"
                                                  style="background: rgba(78,158,90,0.12); color: #6dbf7a;">{{ $cambio['a'] ?? '(vacío)' }}</code>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="flex-shrink-0 text-right">
                        <span class="text-xs" style="color: var(--vd-muted);">{{ $tsLabel }}</span>
                        @if($log->ip)
                            <div class="mt-0.5" style="color: var(--vd-muted-2); font-size: 0.65rem;">
                                IP: {{ $log->ip }}
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if($q->hasPages())
            <div class="mt-5 flex justify-center">
                {{ $q->links() }}
            </div>
        @endif
    @endif
</div>