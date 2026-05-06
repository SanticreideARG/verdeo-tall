<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Enlace;

new #[Layout('layouts.app', ['title' => 'Enlaces'])] class extends Component {

    public string $titulo      = '';
    public string $url         = '';
    public string $descripcion = '';

    public ?int   $editingId       = null;
    public string $editTitulo      = '';
    public string $editUrl         = '';
    public string $editDescripcion = '';

    public ?int $deletingId = null;

    public function with(): array
    {
        return ['enlaces' => Enlace::orderBy('orden')->orderBy('titulo')->get()];
    }

    /* ── Create ──────────────────────────────────────────── */

    public function guardar(): void
    {
        $this->validate([
            'titulo'      => 'required|min:2|max:100',
            'url'         => 'required|url|max:255',
            'descripcion' => 'nullable|max:200',
        ], [
            'titulo.required' => 'El título es obligatorio.',
            'titulo.min'      => 'Mínimo 2 caracteres.',
            'url.required'    => 'La URL es obligatoria.',
            'url.url'         => 'Ingresá una URL válida (con https://).',
        ]);

        Enlace::create([
            'titulo'      => trim($this->titulo),
            'url'         => trim($this->url),
            'descripcion' => trim($this->descripcion) ?: null,
            'orden'       => (int) Enlace::max('orden') + 1,
            'activo'      => true,
        ]);

        $this->titulo      = '';
        $this->url         = '';
        $this->descripcion = '';
        $this->dispatch('enlaceGuardado');
    }

    /* ── Edit ────────────────────────────────────────────── */

    public function startEdit(int $id): void
    {
        $e                   = Enlace::findOrFail($id);
        $this->editingId     = $id;
        $this->editTitulo    = $e->titulo;
        $this->editUrl       = $e->url;
        $this->editDescripcion = (string) $e->descripcion;
    }

    public function guardarEdicion(): void
    {
        $this->validate([
            'editTitulo'      => 'required|min:2|max:100',
            'editUrl'         => 'required|url|max:255',
            'editDescripcion' => 'nullable|max:200',
        ], [
            'editTitulo.required' => 'El título es obligatorio.',
            'editTitulo.min'      => 'Mínimo 2 caracteres.',
            'editUrl.required'    => 'La URL es obligatoria.',
            'editUrl.url'         => 'URL inválida (debe incluir https://).',
        ]);

        Enlace::findOrFail($this->editingId)->update([
            'titulo'      => trim($this->editTitulo),
            'url'         => trim($this->editUrl),
            'descripcion' => trim($this->editDescripcion) ?: null,
        ]);

        $this->editingId = null;
    }

    public function cancelarEdicion(): void { $this->editingId = null; }

    /* ── Toggle / delete ─────────────────────────────────── */

    public function toggleActivo(int $id): void
    {
        $e = Enlace::findOrFail($id);
        $e->update(['activo' => !$e->activo]);
    }

    public function confirmarEliminar(int $id): void { $this->deletingId = $id; }

    public function eliminar(): void
    {
        if ($this->deletingId) Enlace::findOrFail($this->deletingId)->delete();
        $this->deletingId = null;
    }

    public function cancelarEliminar(): void { $this->deletingId = null; }

}; ?>

{{-- Alpine handles form visibility (no server round-trip needed) --}}
<div x-data="{ showForm: false }"
     @enlaceguardado.window="showForm = false">

    {{-- Delete modal --}}
    @if($deletingId)
    <div class="fixed inset-0 z-50 flex items-center justify-center"
         style="background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);">
        <div class="w-full max-w-sm rounded-2xl p-6"
             style="background: var(--vd-surface-2); border: 1px solid rgba(220,68,68,0.35);
                    box-shadow: 0 24px 60px rgba(0,0,0,0.4);">
            <h3 class="font-condensed font-bold text-lg mb-2" style="color: var(--vd-text);">¿Eliminar enlace?</h3>
            <p class="text-sm mb-6" style="color: var(--vd-muted);">Esta acción no se puede deshacer.</p>
            <div class="flex justify-end gap-3">
                <button wire:click="cancelarEliminar" class="btn-secondary">Cancelar</button>
                <button wire:click="eliminar" class="btn-danger">Eliminar</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Header --}}
    <div class="flex items-end justify-between mb-6">
        <div>
            <h2 class="font-condensed font-bold text-2xl" style="color: var(--vd-text); letter-spacing: 0.5px;">
                Enlaces
            </h2>
            <p class="text-sm mt-1" style="color: var(--vd-muted);">Accesos directos a herramientas y recursos externos.</p>
        </div>
        <button @click="showForm = !showForm" class="btn-primary">
            <span x-show="!showForm">+ Nuevo enlace</span>
            <span x-show="showForm">✕ Cancelar</span>
        </button>
    </div>

    {{-- Create form --}}
    <div x-show="showForm" x-collapse class="mb-6">
        <div class="card">
            <h3 class="font-condensed font-bold tracking-wide mb-4"
                style="color: var(--vd-text); font-size: 12px; letter-spacing: 1px; text-transform: uppercase;
                       border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 12px;">
                Nuevo enlace
            </h3>
            <form wire:submit="guardar" novalidate>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="label">Título <span style="color:#fca5a5">*</span></label>
                        <input type="text" wire:model="titulo"
                               class="input @error('titulo') border-red-400 @enderror"
                               placeholder="Google Drive">
                        @error('titulo') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">URL <span style="color:#fca5a5">*</span></label>
                        <input type="text" wire:model="url"
                               class="input @error('url') border-red-400 @enderror"
                               placeholder="https://drive.google.com/...">
                        @error('url') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Descripción <span style="color: var(--vd-muted-2);">(opcional)</span></label>
                        <input type="text" wire:model="descripcion"
                               class="input @error('descripcion') border-red-400 @enderror"
                               placeholder="Archivos del equipo Verdeo">
                        @error('descripcion') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="showForm = false" class="btn-secondary">Cancelar</button>
                    <button type="submit" class="btn-primary px-6"
                            wire:loading.attr="disabled" wire:target="guardar">
                        <span wire:loading.remove wire:target="guardar">Guardar enlace</span>
                        <span wire:loading wire:target="guardar">Guardando…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Links list --}}
    @if($enlaces->isEmpty())
    <div class="card text-center py-16">
        <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4"
             style="background: rgba(78,158,90,0.1); border: 1px solid rgba(78,158,90,0.2);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                 style="color: var(--vd-green-lt);">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/>
            </svg>
        </div>
        <p class="font-condensed font-bold text-base" style="color: var(--vd-text);">Sin enlaces todavía</p>
        <p class="text-sm mt-1 mb-5" style="color: var(--vd-muted);">Agregá accesos rápidos a herramientas y recursos.</p>
        <button @click="showForm = true" class="btn-primary text-xs px-5">+ Agregar el primero</button>
    </div>

    @else

    <div class="card p-0 overflow-hidden">
        @foreach($enlaces as $enlace)

        {{-- Inline edit row --}}
        @if($editingId === $enlace->id)
        <div class="px-5 py-4" style="background: rgba(58,125,68,0.05); {{ !$loop->last ? 'border-bottom: 1px solid var(--vd-bdr-soft);' : '' }}">
            <form wire:submit="guardarEdicion" novalidate>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="label">Título *</label>
                        <input type="text" wire:model="editTitulo"
                               class="input text-sm @error('editTitulo') border-red-400 @enderror"
                               wire:keydown.escape="cancelarEdicion">
                        @error('editTitulo') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">URL *</label>
                        <input type="text" wire:model="editUrl"
                               class="input text-sm @error('editUrl') border-red-400 @enderror"
                               wire:keydown.escape="cancelarEdicion">
                        @error('editUrl') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Descripción</label>
                        <input type="text" wire:model="editDescripcion"
                               class="input text-sm @error('editDescripcion') border-red-400 @enderror"
                               wire:keydown.escape="cancelarEdicion">
                        @error('editDescripcion') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="cancelarEdicion" class="btn-secondary text-xs">Cancelar</button>
                    <button type="submit" class="btn-primary text-xs px-5"
                            wire:loading.attr="disabled" wire:target="guardarEdicion">
                        <span wire:loading.remove wire:target="guardarEdicion">Guardar</span>
                        <span wire:loading wire:target="guardarEdicion">Guardando…</span>
                    </button>
                </div>
            </form>
        </div>

        {{-- Normal row --}}
        @else
        <div class="group flex items-center gap-4 px-5 transition-colors duration-150"
             style="{{ !$loop->last ? 'border-bottom: 1px solid var(--vd-bdr-soft);' : '' }} {{ !$enlace->activo ? 'opacity: 0.45;' : '' }}"
             onmouseover="this.style.background='var(--vd-row-hover)'"
             onmouseout="this.style.background=''">

            {{-- Link icon --}}
            <a href="{{ $enlace->url }}" target="_blank" rel="noopener"
               class="flex-shrink-0 w-9 h-9 rounded-xl flex items-center justify-center transition-all duration-150"
               style="background: rgba(78,158,90,0.10); border: 1px solid rgba(78,158,90,0.22);"
               onmouseover="this.style.background='rgba(78,158,90,0.22)'; this.style.borderColor='rgba(78,158,90,0.5)'"
               onmouseout="this.style.background='rgba(78,158,90,0.10)'; this.style.borderColor='rgba(78,158,90,0.22)'"
               title="Abrir {{ $enlace->titulo }}">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                     style="color: var(--vd-green-lt);">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/>
                </svg>
            </a>

            {{-- Title + URL reveal on hover --}}
            <div class="flex-1 min-w-0 py-3.5">
                <a href="{{ $enlace->url }}" target="_blank" rel="noopener"
                   class="block text-sm font-semibold leading-snug transition-colors duration-150"
                   style="color: var(--vd-text);"
                   onmouseover="this.style.color='var(--vd-green-lt)'"
                   onmouseout="this.style.color='var(--vd-text)'">
                    {{ $enlace->titulo }}
                </a>
                <span class="block text-xs font-mono mt-0.5 truncate opacity-0 group-hover:opacity-100 transition-opacity duration-200"
                      style="color: var(--vd-muted);">
                    {{ $enlace->url }}
                </span>
                @if($enlace->descripcion)
                <span class="block text-xs mt-0.5 opacity-0 group-hover:opacity-100 transition-opacity duration-200"
                      style="color: var(--vd-muted-2);">
                    {{ $enlace->descripcion }}
                </span>
                @endif
            </div>

            {{-- Action buttons --}}
            <div class="flex items-center gap-1 flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity duration-150">
                <button wire:click="startEdit({{ $enlace->id }})"
                        class="btn-secondary text-xs px-2.5 py-1.5" title="Editar">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                </button>
                <button wire:click="toggleActivo({{ $enlace->id }})"
                        class="btn-secondary text-xs px-2.5 py-1.5"
                        title="{{ $enlace->activo ? 'Desactivar' : 'Activar' }}">
                    @if($enlace->activo)
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                    @else
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/>
                        <line x1="1" y1="1" x2="23" y2="23"/>
                    </svg>
                    @endif
                </button>
                <button wire:click="confirmarEliminar({{ $enlace->id }})"
                        class="btn-secondary text-xs px-2.5 py-1.5"
                        style="color: #fca5a5;"
                        onmouseover="this.style.background='rgba(220,68,68,0.12)'"
                        onmouseout="this.style.background=''"
                        title="Eliminar">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
                        <path d="M10 11v6m4-6v6M9 6V4h6v2"/>
                    </svg>
                </button>
            </div>

        </div>
        @endif

        @endforeach
    </div>
    @endif

</div>
