<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Conversacion;
use App\Models\User;

new #[Layout('layouts.app', ['title' => 'Detalle conversación'])] class extends Component {

    public Conversacion $conversacion;

    public string $nombre = '';
    public string $zona   = '';
    public string $estado = '';

    public bool $confirmandoBorrado = false;
    public bool $guardado           = false;

    public function mount(Conversacion $conversacion): void
    {
        if (! auth()->user()->isAdmin() && ! auth()->user()->isResponsableZona()) {
            abort(403);
        }
        $this->conversacion = $conversacion;
        $this->nombre = $conversacion->nombre ?? '';
        $this->zona   = $conversacion->zona;
        $this->estado = $conversacion->estado;
    }

    public function guardar(): void
    {
        $this->validate([
            'nombre' => 'nullable|max:120',
            'zona'   => 'required|in:' . implode(',', array_keys(Conversacion::zonas())),
            'estado' => 'required|in:' . implode(',', array_keys(Conversacion::estadosConv())),
        ]);

        $this->conversacion->update([
            'nombre' => $this->nombre ?: null,
            'zona'   => $this->zona,
            'estado' => $this->estado,
        ]);

        $this->conversacion->refresh();
        $this->guardado = true;
        $this->dispatch('guardado');
    }

    public function eliminar(): void
    {
        $this->conversacion->delete();
        $this->redirect(route('conversaciones'), navigate: true);
    }

    public function with(): array
    {
        $activeStates = ['pendiente', 'aprobada', 'lista_para_entrega'];

        $usuario = User::where('whatsapp', $this->conversacion->telefono)
            ->with(['ordenes' => fn($q) => $q->latest()])
            ->first();

        $ordenes = $usuario?->ordenes ?? collect();

        if ($usuario && $ordenes->whereIn('estado', $activeStates)->isNotEmpty()) {
            $etiqueta = ['label' => 'Pedido activo', 'class' => 'badge-yellow', 'color' => '#c8a030'];
        } elseif ($usuario && $ordenes->where('estado', 'entregada')->isNotEmpty()) {
            $etiqueta = ['label' => 'Entregado',    'class' => 'badge-green',  'color' => '#4e9e5a'];
        } else {
            $etiqueta = ['label' => 'Consulta',     'class' => 'badge-gray',   'color' => 'var(--vd-muted)'];
        }

        return compact('usuario', 'ordenes', 'etiqueta');
    }

}; ?>

<div x-data="{ flash: false }" @guardado.window="flash = true; setTimeout(() => flash = false, 3000)">

    {{-- Flash --}}
    <div x-show="flash" x-transition
         class="mb-4 badge-green px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
        Datos actualizados correctamente.
    </div>

    {{-- Back + actions bar --}}
    <div class="flex items-center justify-between mb-6">
        <a href="{{ route('conversaciones') }}" wire:navigate
           class="flex items-center gap-1.5 text-sm transition-colors"
           style="color: var(--vd-muted);"
           onmouseover="this.style.color='var(--vd-text)'" onmouseout="this.style.color='var(--vd-muted)'">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Conversaciones
        </a>
        <a href="https://wa.me/{{ $conversacion->telefono }}" target="_blank"
           class="btn-secondary text-sm flex items-center gap-2">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" style="color: #25d366;">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
            Abrir WhatsApp
        </a>
    </div>

    {{-- Header card --}}
    <div class="card mb-5">
        <div class="flex items-start gap-4">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center flex-shrink-0 font-bold text-xl"
                 style="background: rgba(58,125,68,0.18); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.3);">
                {{ strtoupper(substr($conversacion->nombre ?? '?', 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-3 flex-wrap mb-1">
                    <h2 class="font-condensed font-bold text-2xl" style="color: var(--vd-text); letter-spacing: 0.3px;">
                        {{ $conversacion->nombre ?? 'Sin nombre' }}
                    </h2>
                    <span class="{{ $etiqueta['class'] }}">{{ $etiqueta['label'] }}</span>
                    <span class="{{ match($conversacion->estado) {
                        'abierta'   => 'badge-green',
                        'esperando' => 'badge-yellow',
                        default     => 'badge-gray',
                    } }}">{{ ucfirst($conversacion->estado) }}</span>
                </div>
                <div class="flex items-center gap-2 mb-2" x-data="{ copied: false }">
                    <span class="font-mono text-sm" style="color: var(--vd-text-soft);">+{{ $conversacion->telefono }}</span>
                    <button type="button"
                            @click="navigator.clipboard.writeText('+{{ $conversacion->telefono }}').then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                            class="transition-colors" style="color: var(--vd-muted-2);"
                            :style="copied ? 'color: #4e9e5a' : ''">
                        <svg x-show="!copied" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        <svg x-show="copied" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                        </svg>
                    </button>
                </div>
                <p class="text-xs" style="color: var(--vd-muted);">
                    {{ $conversacion->zonaLabel() }} &nbsp;·&nbsp;
                    Primer contacto {{ $conversacion->created_at->diffForHumans() }}
                    @if($conversacion->ultimo_mensaje_at)
                    &nbsp;·&nbsp; Último mensaje {{ $conversacion->ultimo_mensaje_at->diffForHumans() }}
                    @endif
                </p>
            </div>
        </div>

        @if($conversacion->ultimo_mensaje)
        <div class="mt-4 px-4 py-3 rounded-xl" style="background: var(--vd-input-bg); border: 1px solid var(--vd-bdr-soft);">
            <p class="text-xs font-condensed uppercase tracking-wide mb-1" style="color: var(--vd-muted-2);">Último mensaje</p>
            <p class="text-sm leading-relaxed" style="color: var(--vd-text-soft);">{{ $conversacion->ultimo_mensaje }}</p>
        </div>
        @endif
    </div>

    {{-- Conversación (chat bubbles) --}}
    @if($conversacion->mensajes && count($conversacion->mensajes) > 0)
    <div class="card mb-5">
        <h3 class="font-condensed font-bold tracking-wide mb-4 flex items-center justify-between"
            style="color: var(--vd-text); letter-spacing: 1px; text-transform: uppercase; font-size: 12px;
                   border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 12px;">
            <span>Conversación</span>
            <span style="color: var(--vd-muted-2); font-weight: 400; font-size: 11px; text-transform: none; letter-spacing: 0;">
                {{ count($conversacion->mensajes) }} mensajes
            </span>
        </h3>

        <div class="overflow-y-auto space-y-2 pr-1"
             style="max-height: 500px;"
             x-data x-init="$el.scrollTop = $el.scrollHeight">

            @php $prevDate = null; @endphp
            @foreach($conversacion->mensajes as $msg)
                @php
                    $at = \Carbon\Carbon::parse($msg['at']);
                    $dateKey = $at->format('Y-m-d');
                    $esCliente = $msg['from'] === 'cliente';
                @endphp

                @if($prevDate !== $dateKey)
                    @php $prevDate = $dateKey; @endphp
                    <div class="flex justify-center py-1">
                        <span class="px-3 py-1 rounded-full text-xs"
                              style="background: rgba(255,255,255,0.04); border: 1px solid var(--vd-bdr-soft);
                                     color: var(--vd-muted-2); font-size: 10px; letter-spacing: 0.8px;">
                            {{ $at->format('d/m/Y') }}
                        </span>
                    </div>
                @endif

                <div class="flex {{ $esCliente ? 'justify-start' : 'justify-end' }}">
                    <div style="max-width: 72%;">
                        <div class="px-4 py-2.5 rounded-2xl text-sm leading-relaxed"
                             style="{{ $esCliente
                                 ? 'background: rgba(255,255,255,0.05); border: 1px solid var(--vd-bdr); color: var(--vd-text-soft); border-bottom-left-radius: 6px;'
                                 : 'background: rgba(58,125,68,0.2); border: 1px solid rgba(78,158,90,0.25); color: var(--vd-text); border-bottom-right-radius: 6px;' }}">
                            {{ $msg['texto'] }}
                        </div>
                        <p class="text-[10px] mt-0.5 {{ $esCliente ? 'text-left pl-1' : 'text-right pr-1' }}"
                           style="color: var(--vd-muted-2);">
                            {{ $esCliente ? ($conversacion->nombre ?? 'Cliente') : 'Verdeo' }}
                            &middot; {{ $at->format('H:i') }}
                        </p>
                    </div>
                </div>
            @endforeach

        </div>
    </div>
    @endif

    {{-- Edit form --}}
    <div class="card mb-5">
        <h3 class="font-condensed font-bold tracking-wide mb-4"
            style="color: var(--vd-text); letter-spacing: 1px; text-transform: uppercase; font-size: 12px;
                   border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 12px;">
            Editar datos
        </h3>
        <form wire:submit="guardar" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="label">Nombre / Alias</label>
                    <input type="text" wire:model="nombre" class="input" placeholder="Nombre del contacto">
                    @error('nombre') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Zona</label>
                    <select wire:model="zona" class="input">
                        @foreach(Conversacion::zonas() as $slug => $label)
                        <option value="{{ $slug }}" style="background:var(--vd-bg-2);color:var(--vd-text);">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('zona') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Estado de conversación</label>
                    <select wire:model="estado" class="input">
                        @foreach(Conversacion::estadosConv() as $val => $label)
                        <option value="{{ $val }}" style="background:var(--vd-bg-2);color:var(--vd-text);">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('estado') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>Guardar cambios</span>
                    <span wire:loading class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Guardando…
                    </span>
                </button>
            </div>
        </form>
    </div>

    {{-- Usuario vinculado --}}
    <div class="card mb-5">
        <h3 class="font-condensed font-bold tracking-wide mb-4"
            style="color: var(--vd-text); letter-spacing: 1px; text-transform: uppercase; font-size: 12px;
                   border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 12px;">
            Usuario registrado vinculado
        </h3>
        @if($usuario)
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 font-bold"
                 style="background: linear-gradient(135deg, #3a7d44, #4e9e5a); color: #fff;">
                {{ $usuario->iniciales() }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-semibold" style="color: var(--vd-text);">{{ $usuario->nombreCompleto() }}</p>
                <p class="text-xs" style="color: var(--vd-muted);">
                    {{ $usuario->email }}
                    @if($usuario->numero_cliente) &nbsp;·&nbsp; Cliente #{{ $usuario->numero_cliente }} @endif
                    &nbsp;·&nbsp; <span class="{{ $usuario->isCliente() ? 'text-green-400' : '' }}">{{ $usuario->rolLabel() }}</span>
                </p>
            </div>
            <a href="{{ route('usuarios.ver', $usuario) }}" wire:navigate
               class="btn-secondary text-xs px-3 py-1.5 flex items-center gap-1 flex-shrink-0">
                Ver perfil
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
        @else
        <div class="flex items-center gap-3 py-2">
            <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                 style="background: rgba(255,255,255,0.04); border: 1px solid var(--vd-bdr);">
                <svg width="18" height="18" fill="none" stroke="var(--vd-muted-2)" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-medium" style="color: var(--vd-text);">Sin usuario registrado</p>
                <p class="text-xs" style="color: var(--vd-muted);">
                    Ningún usuario tiene el número <span class="font-mono">{{ $conversacion->telefono }}</span> en su perfil.
                    Para vincularlo, editá el campo WhatsApp del usuario correspondiente.
                </p>
            </div>
        </div>
        @endif
    </div>

    {{-- Historial de pedidos --}}
    @if($usuario && $ordenes->isNotEmpty())
    <div class="card mb-5">
        <h3 class="font-condensed font-bold tracking-wide mb-4"
            style="color: var(--vd-text); letter-spacing: 1px; text-transform: uppercase; font-size: 12px;
                   border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 12px;">
            Historial de pedidos ({{ $ordenes->count() }})
        </h3>
        <div class="space-y-2">
            @foreach($ordenes as $orden)
            <div class="flex items-center gap-3 px-4 py-3 rounded-xl"
                 style="background: var(--vd-input-bg); border: 1px solid var(--vd-bdr-soft);">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-sm font-semibold" style="color: var(--vd-text);">{{ $orden->numero }}</span>
                        <span class="{{ $orden->estadoBadgeClass() }} text-xs">{{ $orden->estadoLabel() }}</span>
                    </div>
                    <p class="text-xs mt-0.5" style="color: var(--vd-muted);">
                        {{ $orden->created_at->format('d/m/Y') }}
                        @if($orden->zona) &nbsp;·&nbsp; {{ $orden->zona }} @endif
                    </p>
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="font-condensed font-bold text-sm" style="color: var(--vd-text);">
                        $&nbsp;{{ number_format($orden->total, 0, ',', '.') }}
                    </p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Zona peligrosa --}}
    @if(auth()->user()->isAdmin())
    <div class="card" style="border-color: rgba(239,68,68,0.2);"
         x-data="{ confirmar: false }">
        <h3 class="font-condensed font-bold tracking-wide mb-1"
            style="color: #ef4444; letter-spacing: 1px; text-transform: uppercase; font-size: 12px;">
            Zona peligrosa
        </h3>
        <p class="text-sm mb-4" style="color: var(--vd-muted);">
            Eliminar esta conversación borra el registro del sistema. No afecta al usuario registrado ni a sus pedidos.
        </p>

        <div x-show="!confirmar">
            <button type="button" @click="confirmar = true"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-all"
                    style="border: 1px solid rgba(239,68,68,0.35); color: #ef4444; background: rgba(239,68,68,0.06);"
                    onmouseover="this.style.background='rgba(239,68,68,0.12)'"
                    onmouseout="this.style.background='rgba(239,68,68,0.06)'">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                </svg>
                Eliminar conversación
            </button>
        </div>

        <div x-show="confirmar" x-transition
             class="rounded-xl p-4" style="background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.25);">
            <p class="text-sm font-semibold mb-3" style="color: #ef4444;">
                ¿Estás seguro? Esta acción no se puede deshacer.
            </p>
            <p class="text-xs mb-4" style="color: var(--vd-muted);">
                Se eliminará el registro de conversación de
                <strong style="color: var(--vd-text);">{{ $conversacion->nombre ?? $conversacion->telefono }}</strong>.
            </p>
            <div class="flex gap-3">
                <button type="button" @click="confirmar = false" class="btn-secondary text-sm">
                    Cancelar
                </button>
                <button wire:click="eliminar" wire:loading.attr="disabled"
                        class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition-all"
                        style="background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.4); color: #ef4444;"
                        onmouseover="this.style.background='rgba(239,68,68,0.25)'"
                        onmouseout="this.style.background='rgba(239,68,68,0.15)'">
                    <span wire:loading.remove>Sí, eliminar</span>
                    <span wire:loading class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Eliminando…
                    </span>
                </button>
            </div>
        </div>
    </div>
    @endif

</div>
