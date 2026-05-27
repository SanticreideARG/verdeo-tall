<?php

use App\Models\UserLink;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    /* ── Estado del formulario ──────────────────────────────────────────── */
    public ?int   $editandoId  = null;
    public string $titulo      = '';
    public string $url         = '';
    public string $descripcion = '';
    public bool   $mostrarForm = false;

    /* ── Guardia de acceso ──────────────────────────────────────────────── */
    public function mount(): void
    {
        $user = Auth::user();
        if ($user->isColaborador() || $user->isCocina() || $user->isCliente()) {
            $this->redirect(route('portal'), navigate: true);
        }
    }

    /* ── Datos para la vista ────────────────────────────────────────────── */
    public function with(): array
    {
        return [
            'links' => Auth::user()->links()->get(),
        ];
    }

    /* ── Formulario ─────────────────────────────────────────────────────── */
    public function abrirNuevo(): void
    {
        $this->resetForm();
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $link = $this->linkDelUsuario($id);
        if (! $link) return;

        $this->editandoId  = $link->id;
        $this->titulo      = $link->titulo;
        $this->url         = $link->url;
        $this->descripcion = $link->descripcion ?? '';
        $this->mostrarForm = true;
    }

    public function guardar(): void
    {
        $data = $this->validate([
            'titulo'      => 'required|string|max:120',
            'url'         => 'required|url|max:2048',
            'descripcion' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();

        if ($this->editandoId) {
            $link = $this->linkDelUsuario($this->editandoId);
            $link?->update($data);
        } else {
            $maxOrden = $user->links()->max('orden') ?? -1;
            $user->links()->create([...$data, 'orden' => $maxOrden + 1]);
        }

        $this->resetForm();
    }

    public function cancelar(): void
    {
        $this->resetForm();
    }

    public function eliminar(int $id): void
    {
        $link = $this->linkDelUsuario($id);
        $link?->delete();
        $this->reordenar();
    }

    /* ── Reordenar ──────────────────────────────────────────────────────── */
    public function subir(int $id): void
    {
        $links = Auth::user()->links()->get();
        $idx   = $links->search(fn ($l) => $l->id === $id);
        if ($idx < 1) return;

        $links[$idx]->update(['orden' => $links[$idx - 1]->orden]);
        $links[$idx - 1]->update(['orden' => $links[$idx]->orden + 1]);
        $this->reordenar();
    }

    public function bajar(int $id): void
    {
        $links = Auth::user()->links()->get();
        $idx   = $links->search(fn ($l) => $l->id === $id);
        if ($idx === false || $idx >= $links->count() - 1) return;

        $links[$idx]->update(['orden' => $links[$idx + 1]->orden]);
        $links[$idx + 1]->update(['orden' => $links[$idx]->orden - 1]);
        $this->reordenar();
    }

    /* ── Helpers privados ───────────────────────────────────────────────── */
    private function linkDelUsuario(int $id): ?UserLink
    {
        return Auth::user()->links()->find($id);
    }

    private function reordenar(): void
    {
        Auth::user()->links()->get()->each(function (UserLink $link, int $i) {
            $link->update(['orden' => $i]);
        });
    }

    private function resetForm(): void
    {
        $this->editandoId  = null;
        $this->titulo      = '';
        $this->url         = '';
        $this->descripcion = '';
        $this->mostrarForm = false;
    }
}; ?>

<div>
    {{-- ── Encabezado ──────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Mis enlaces</h1>
            <p class="text-sm text-gray-500 mt-1">Links personales de acceso rápido</p>
        </div>
        @if (! $mostrarForm)
            <button wire:click="abrirNuevo"
                    class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Agregar enlace
            </button>
        @endif
    </div>

    {{-- ── Formulario inline ────────────────────────────────────────────── --}}
    @if ($mostrarForm)
        <div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-5 shadow-sm">
            <h2 class="text-base font-semibold text-green-800 mb-4">
                {{ $editandoId ? 'Editar enlace' : 'Nuevo enlace' }}
            </h2>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                {{-- Título --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Título <span class="text-red-500">*</span></label>
                    <input wire:model="titulo" type="text" maxlength="120" placeholder="Ej: Panel Google Ads"
                           class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-green-500 focus:ring-green-500">
                    @error('titulo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- URL --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">URL <span class="text-red-500">*</span></label>
                    <input wire:model="url" type="url" maxlength="2048" placeholder="https://..."
                           class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-green-500 focus:ring-green-500">
                    @error('url') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Descripción --}}
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Descripción <span class="text-gray-400">(opcional)</span></label>
                    <input wire:model="descripcion" type="text" maxlength="255" placeholder="Para qué sirve este enlace..."
                           class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-green-500 focus:ring-green-500">
                    @error('descripcion') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-4 flex gap-3">
                <button wire:click="guardar"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    {{ $editandoId ? 'Guardar cambios' : 'Agregar' }}
                </button>
                <button wire:click="cancelar"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition">
                    Cancelar
                </button>
            </div>
        </div>
    @endif

    {{-- ── Lista vacía ─────────────────────────────────────────────────── --}}
    @if ($links->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 bg-gray-50 py-16 text-center">
            <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
            </svg>
            <p class="text-sm font-medium text-gray-500">No tenés enlaces guardados</p>
            <p class="text-xs text-gray-400 mt-1">Usá el botón "Agregar enlace" para empezar</p>
        </div>

    {{-- ── Lista de enlaces ─────────────────────────────────────────────── --}}
    @else
        <ul class="divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            @foreach ($links as $i => $link)
                <li class="flex items-center gap-4 px-5 py-4 hover:bg-gray-50 transition group">

                    {{-- Favicon --}}
                    <img src="{{ $link->faviconUrl() }}"
                         alt=""
                         class="w-6 h-6 rounded flex-shrink-0"
                         onerror="this.style.display='none'">

                    {{-- Info --}}
                    <div class="flex-1 min-w-0">
                        <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer"
                           class="text-sm font-semibold text-gray-900 hover:text-green-700 hover:underline truncate block">
                            {{ $link->titulo }}
                        </a>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-xs text-gray-400 truncate">{{ $link->dominio() }}</span>
                            @if ($link->descripcion)
                                <span class="text-gray-300">·</span>
                                <span class="text-xs text-gray-500 truncate">{{ $link->descripcion }}</span>
                            @endif
                        </div>
                    </div>

                    {{-- Acciones --}}
                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition">

                        {{-- Subir --}}
                        @if ($i > 0)
                            <button wire:click="subir({{ $link->id }})"
                                    title="Mover arriba"
                                    class="p-1.5 rounded-lg text-gray-400 hover:text-green-600 hover:bg-green-50 transition">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
                                </svg>
                            </button>
                        @else
                            <span class="w-7"></span>
                        @endif

                        {{-- Bajar --}}
                        @if ($i < $links->count() - 1)
                            <button wire:click="bajar({{ $link->id }})"
                                    title="Mover abajo"
                                    class="p-1.5 rounded-lg text-gray-400 hover:text-green-600 hover:bg-green-50 transition">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>
                        @else
                            <span class="w-7"></span>
                        @endif

                        {{-- Editar --}}
                        <button wire:click="editar({{ $link->id }})"
                                title="Editar"
                                class="p-1.5 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                            </svg>
                        </button>

                        {{-- Eliminar --}}
                        <button wire:click="eliminar({{ $link->id }})"
                                wire:confirm="¿Eliminar '{{ $link->titulo }}'?"
                                title="Eliminar"
                                class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>
                    </div>
                </li>
            @endforeach
        </ul>

        <p class="mt-3 text-xs text-gray-400 text-right">{{ $links->count() }} {{ Str::plural('enlace', $links->count()) }}</p>
    @endif
</div>
