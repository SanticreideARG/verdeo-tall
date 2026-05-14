<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Enlace;

new #[Layout('layouts.app', ['title' => 'Enlaces'])] class extends Component {

    public string $titulo      = '';
    public string $enlaceUrl   = '';
    public string $descripcion = '';
    public string $categoria   = '';

    public ?int   $editingId       = null;
    public string $editTitulo      = '';
    public string $editUrl         = '';
    public string $editDescripcion = '';
    public string $editCategoria   = '';

    public ?int $deletingId = null;
    public string $buscar    = '';
    public string $filtroCat = '';

    public function with(): array
    {
        $q = Enlace::orderBy('orden')->orderBy('titulo');
        if ($this->buscar !== '') {
            $q->where(fn($q2) =>
                $q2->where('titulo', 'like', '%'.$this->buscar.'%')
                   ->orWhere('descripcion', 'like', '%'.$this->buscar.'%')
                   ->orWhere('url', 'like', '%'.$this->buscar.'%')
            );
        }
        if ($this->filtroCat !== '') {
            $q->where('categoria', $this->filtroCat);
        }
        $categorias = Enlace::select('categoria')->distinct()->whereNotNull('categoria')->orderBy('categoria')->pluck('categoria');
        return ['enlaces' => $q->get(), 'categorias' => $categorias];
    }

    public function guardar(): void
    {
        $this->validate([
            'titulo'      => 'required|min:2|max:100',
            'enlaceUrl'   => 'required|url|max:255',
            'descripcion' => 'nullable|max:200',
            'categoria'   => 'nullable|max:60',
        ], [
            'titulo.required'    => 'El título es obligatorio.',
            'titulo.min'         => 'Mínimo 2 caracteres.',
            'enlaceUrl.required' => 'La URL es obligatoria.',
            'enlaceUrl.url'      => 'Ingresá una URL válida (con https://).',
        ]);
        Enlace::create([
            'titulo'      => trim($this->titulo),
            'url'         => trim($this->enlaceUrl),
            'descripcion' => trim($this->descripcion) ?: null,
            'categoria'   => trim($this->categoria) ?: null,
            'orden'       => (int) Enlace::max('orden') + 1,
            'activo'      => true,
        ]);
        $this->titulo = $this->enlaceUrl = $this->descripcion = $this->categoria = '';
        $this->dispatch('enlaceguardado');
    }

    public function startEdit(int $id): void
    {
        $e = Enlace::findOrFail($id);
        $this->editingId       = $id;
        $this->editTitulo      = $e->titulo;
        $this->editUrl         = $e->url;
        $this->editDescripcion = (string) $e->descripcion;
        $this->editCategoria   = (string) $e->categoria;
    }

    public function guardarEdicion(): void
    {
        $this->validate([
            'editTitulo'      => 'required|min:2|max:100',
            'editUrl'         => 'required|url|max:255',
            'editDescripcion' => 'nullable|max:200',
            'editCategoria'   => 'nullable|max:60',
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
            'categoria'   => trim($this->editCategoria) ?: null,
        ]);
        $this->editingId = null;
    }

    public function cancelarEdicion(): void { $this->editingId = null; }

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

<div x-data="{
        showForm: false,
        vista: localStorage.getItem('enlaces_vista') ?? 'lista',
        setVista(v) { this.vista = v; localStorage.setItem('enlaces_vista', v); },
        copied: null,
        copyUrl(id, url) {
            navigator.clipboard.writeText(url).then(() => {
                this.copied = id;
                setTimeout(() => this.copied = null, 1800);
            });
        }
     }"
     @enlaceguardado.window="showForm = false">

    {{-- Delete modal --}}
    @if($deletingId)
    <div class="fixed inset-0 z-50 flex items-center justify-center"
         style="background: rgba(0,0,0,0.55); backdrop-filter: blur(4px);">
        <div class="w-full max-w-sm rounded-2xl p-6"
             style="background: var(--vd-surface-2); border: 1px solid rgba(220,68,68,0.35); box-shadow: 0 24px 60px rgba(0,0,0,0.4);">
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
    <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
        <div>
            <h2 class="font-condensed font-bold text-2xl" style="color: var(--vd-text); letter-spacing: 0.5px;">
                Enlaces
            </h2>
            <p class="text-sm mt-1" style="color: var(--vd-muted);">Accesos directos a herramientas y recursos externos.</p>
        </div>
        <div class="flex items-center gap-2">
            {{-- Vista toggle --}}
            <div class="flex rounded-xl overflow-hidden" style="border: 1px solid var(--vd-bdr-soft);">
                <button @click="setVista('lista')"
                        :style="vista === 'lista' ? 'background: rgba(58,125,68,0.25); color: var(--vd-green-lt);' : 'color: var(--vd-muted);'"
                        class="px-3 py-2 transition-all" title="Vista lista">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
                        <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
                    </svg>
                </button>
                <button @click="setVista('tarjetas')"
                        :style="vista === 'tarjetas' ? 'background: rgba(58,125,68,0.25); color: var(--vd-green-lt);' : 'color: var(--vd-muted);'"
                        class="px-3 py-2 transition-all" title="Vista tarjetas">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                        <rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>
                    </svg>
                </button>
            </div>
            <button @click="showForm = !showForm" class="btn-primary">
                <span x-show="!showForm">+ Nuevo enlace</span>
                <span x-show="showForm">✕ Cancelar</span>
            </button>
        </div>
    </div>

    {{-- Search + category chips --}}
    <div class="flex flex-wrap gap-3 mb-5">
        <div class="relative flex-1 min-w-48">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 class="absolute left-3 top-1/2 -translate-y-1/2" style="color: var(--vd-muted);">
                <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
            </svg>
            <input type="text" wire:model.live.debounce.200ms="buscar"
                   placeholder="Buscar enlaces…"
                   class="input pl-9 text-sm" style="padding-top: 9px; padding-bottom: 9px;">
        </div>
        @if($categorias->isNotEmpty())
        <div class="flex flex-wrap gap-2 items-center">
            <button wire:click="$set('filtroCat', '')"
                    class="text-xs px-3 py-1.5 rounded-full transition-all"
                    style="{{ $filtroCat === '' ? 'background: rgba(58,125,68,0.25); color: var(--vd-green-lt); border: 1px solid rgba(78,158,90,0.4);' : 'background: var(--vd-surface); color: var(--vd-muted); border: 1px solid var(--vd-bdr-soft);' }}">
                Todos
            </button>
            @foreach($categorias as $cat)
            <button wire:click="$set('filtroCat', '{{ $cat }}')"
                    class="text-xs px-3 py-1.5 rounded-full transition-all"
                    style="{{ $filtroCat === $cat ? 'background: rgba(58,125,68,0.25); color: var(--vd-green-lt); border: 1px solid rgba(78,158,90,0.4);' : 'background: var(--vd-surface); color: var(--vd-muted); border: 1px solid var(--vd-bdr-soft);' }}">
                {{ $cat }}
            </button>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Create form --}}
    <div x-show="showForm" x-collapse class="mb-5">
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
                        <input type="text" wire:model="enlaceUrl"
                               class="input @error('enlaceUrl') border-red-400 @enderror"
                               placeholder="https://drive.google.com/...">
                        @error('enlaceUrl') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Descripción <span style="color: var(--vd-muted-2);">(opcional)</span></label>
                        <input type="text" wire:model="descripcion"
                               class="input @error('descripcion') border-red-400 @enderror"
                               placeholder="Archivos del equipo Verdeo">
                    </div>
                    <div>
                        <label class="label">Categoría <span style="color: var(--vd-muted-2);">(opcional)</span></label>
                        <input type="text" wire:model="categoria"
                               class="input @error('categoria') border-red-400 @enderror"
                               placeholder="Herramientas, Docs, Clientes…"
                               list="cats-list">
                        <datalist id="cats-list">
                            @foreach($categorias as $cat)<option value="{{ $cat }}">@endforeach
                        </datalist>
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

    {{-- Empty state --}}
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
        <p class="font-condensed font-bold text-base" style="color: var(--vd-text);">
            {{ $buscar || $filtroCat ? 'Sin resultados' : 'Sin enlaces todavía' }}
        </p>
        <p class="text-sm mt-1 mb-5" style="color: var(--vd-muted);">
            {{ $buscar || $filtroCat ? 'Probá con otro término o categoría.' : 'Agregá accesos rápidos a herramientas y recursos.' }}
        </p>
        @if(!$buscar && !$filtroCat)
        <button @click="showForm = true" class="btn-primary text-xs px-5">+ Agregar el primero</button>
        @endif
    </div>

    @else

    {{-- ══ VISTA LISTA ══ --}}
    <div x-show="vista === 'lista'" class="card p-0 overflow-hidden">
        @foreach($enlaces as $enlace)

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
                    <div>
                        <label class="label">Descripción</label>
                        <input type="text" wire:model="editDescripcion"
                               class="input text-sm"
                               wire:keydown.escape="cancelarEdicion">
                    </div>
                    <div>
                        <label class="label">Categoría</label>
                        <input type="text" wire:model="editCategoria"
                               class="input text-sm"
                               wire:keydown.escape="cancelarEdicion"
                               list="cats-list-edit">
                        <datalist id="cats-list-edit">
                            @foreach($categorias as $cat)<option value="{{ $cat }}">@endforeach
                        </datalist>
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

        @else
        <div class="group flex items-center gap-3 px-5 transition-colors duration-150"
             style="{{ !$loop->last ? 'border-bottom: 1px solid var(--vd-bdr-soft);' : '' }} {{ !$enlace->activo ? 'opacity: 0.45;' : '' }}"
             onmouseover="this.style.background='var(--vd-row-hover)'"
             onmouseout="this.style.background=''">

            {{-- Favicon --}}
            <a href="{{ route('enlaces.ir', $enlace) }}" target="_blank" rel="noopener"
               class="flex-shrink-0 w-9 h-9 rounded-xl flex items-center justify-center overflow-hidden"
               style="background: rgba(255,255,255,0.06); border: 1px solid var(--vd-bdr-soft);"
               title="Abrir {{ $enlace->titulo }}">
                <img src="https://www.google.com/s2/favicons?domain={{ $enlace->faviconDomain() }}&sz=32"
                     width="20" height="20" alt=""
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                <span style="display:none; align-items:center; justify-content:center; color: var(--vd-green-lt);">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/>
                    </svg>
                </span>
            </a>

            {{-- Info --}}
            <div class="flex-1 min-w-0 py-3.5">
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="{{ route('enlaces.ir', $enlace) }}" target="_blank" rel="noopener"
                       class="text-sm font-semibold leading-snug transition-colors duration-150"
                       style="color: var(--vd-text);"
                       onmouseover="this.style.color='var(--vd-green-lt)'"
                       onmouseout="this.style.color='var(--vd-text)'">
                        {{ $enlace->titulo }}
                    </a>
                    @if($enlace->categoria)
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                          style="background: rgba(58,125,68,0.15); color: var(--vd-green-lt); border: 1px solid rgba(78,158,90,0.25);">
                        {{ $enlace->categoria }}
                    </span>
                    @endif
                </div>
                <span class="block text-xs font-mono mt-0.5 truncate opacity-0 group-hover:opacity-100 transition-opacity duration-200"
                      style="color: var(--vd-muted);">{{ $enlace->url }}</span>
                @if($enlace->descripcion)
                <span class="block text-xs mt-0.5 opacity-0 group-hover:opacity-100 transition-opacity duration-200"
                      style="color: var(--vd-muted-2);">{{ $enlace->descripcion }}</span>
                @endif
            </div>

            {{-- Clicks badge --}}
            @if($enlace->clicks > 0)
            <span class="flex-shrink-0 text-xs font-semibold tabular-nums hidden sm:flex items-center gap-1"
                  style="color: var(--vd-muted-2);" title="{{ $enlace->clicks }} clics">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 15l6 6m-11-4a7 7 0 110-14 7 7 0 010 14z"/>
                </svg>
                {{ $enlace->clicks }}
            </span>
            @endif

            {{-- Actions (show on hover) --}}
            <div class="flex items-center gap-1 flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity duration-150">
                <button @click="copyUrl({{ $enlace->id }}, '{{ $enlace->url }}')"
                        class="btn-secondary text-xs px-2.5 py-1.5 transition-all" title="Copiar URL"
                        :style="copied === {{ $enlace->id }} ? 'color: var(--vd-green-lt);' : ''">
                    <svg x-show="copied !== {{ $enlace->id }}" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>
                    </svg>
                    <svg x-show="copied === {{ $enlace->id }}" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12l5 5L20 7"/>
                    </svg>
                </button>
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

    {{-- ══ VISTA TARJETAS ══ --}}
    <div x-show="vista === 'tarjetas'"
         class="grid gap-4"
         style="grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));">
        @foreach($enlaces as $enlace)
        <div class="card flex flex-col gap-3 p-4"
             style="{{ !$enlace->activo ? 'opacity: 0.5;' : '' }}">

            <div class="flex items-start justify-between gap-2">
                <a href="{{ route('enlaces.ir', $enlace) }}" target="_blank" rel="noopener"
                   class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 overflow-hidden"
                   style="background: rgba(255,255,255,0.07); border: 1px solid var(--vd-bdr-soft);">
                    <img src="https://www.google.com/s2/favicons?domain={{ $enlace->faviconDomain() }}&sz=32"
                         width="22" height="22" alt=""
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                    <span style="display:none; align-items:center; justify-content:center; color: var(--vd-green-lt);">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/>
                        </svg>
                    </span>
                </a>
                <div class="flex gap-1">
                    <button @click="copyUrl({{ $enlace->id }}, '{{ $enlace->url }}')"
                            class="btn-secondary text-xs px-2 py-1.5 transition-all" title="Copiar URL"
                            :style="copied === {{ $enlace->id }} ? 'color: var(--vd-green-lt);' : ''">
                        <svg x-show="copied !== {{ $enlace->id }}" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>
                        </svg>
                        <svg x-show="copied === {{ $enlace->id }}" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12l5 5L20 7"/>
                        </svg>
                    </button>
                    <button wire:click="startEdit({{ $enlace->id }})" class="btn-secondary text-xs px-2 py-1.5" title="Editar">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                    </button>
                    <button wire:click="confirmarEliminar({{ $enlace->id }})" class="btn-secondary text-xs px-2 py-1.5" title="Eliminar" style="color: #fca5a5;">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
                            <path d="M10 11v6m4-6v6M9 6V4h6v2"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div>
                <a href="{{ route('enlaces.ir', $enlace) }}" target="_blank" rel="noopener"
                   class="block text-sm font-semibold leading-snug transition-colors"
                   style="color: var(--vd-text);"
                   onmouseover="this.style.color='var(--vd-green-lt)'"
                   onmouseout="this.style.color='var(--vd-text)'">
                    {{ $enlace->titulo }}
                </a>
                @if($enlace->descripcion)
                <p class="text-xs mt-1" style="color: var(--vd-muted);">{{ $enlace->descripcion }}</p>
                @endif
            </div>

            <div class="flex items-center justify-between mt-auto pt-2" style="border-top: 1px solid var(--vd-bdr-soft);">
                @if($enlace->categoria)
                <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                      style="background: rgba(58,125,68,0.15); color: var(--vd-green-lt); border: 1px solid rgba(78,158,90,0.25);">
                    {{ $enlace->categoria }}
                </span>
                @else<span></span>@endif
                @if($enlace->clicks > 0)
                <span class="text-xs flex items-center gap-1" style="color: var(--vd-muted-2);" title="{{ $enlace->clicks }} clics">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 15l6 6m-11-4a7 7 0 110-14 7 7 0 010 14z"/>
                    </svg>
                    {{ $enlace->clicks }}
                </span>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    @endif

</div>
