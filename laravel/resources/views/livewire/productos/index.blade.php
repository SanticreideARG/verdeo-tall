<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Producto;
use App\Models\Plato;

new #[Layout('layouts.app', ['title' => 'Menús'])] class extends Component {

    // Edit menu description
    public ?int   $editMenuId          = null;
    public string $editMenuDescripcion = '';

    // Edit dish inline
    public ?int   $editPlatoId     = null;
    public string $editPlatoNombre = '';

    // Add dish to a menu
    public ?int   $addingToMenuId    = null;
    public string $nuevoPlatoNombre  = '';

    // CSV / bulk import
    public ?int   $importMenuId = null;
    public string $csvText      = '';

    // Delete dish
    public ?int $deletingPlatoId = null;

    public function with(): array
    {
        return [
            'menus' => Producto::orderBy('orden')->with(['platos' => fn($q) => $q->orderBy('orden')])->get(),
        ];
    }

    /* ── Menu description ─────────────────────────────────── */

    public function startEditMenu(int $id): void
    {
        $this->editMenuId          = $id;
        $this->editMenuDescripcion = (string) Producto::findOrFail($id)->descripcion;
        $this->addingToMenuId      = null;
        $this->importMenuId        = null;
    }

    public function guardarMenu(): void
    {
        $this->validate(
            ['editMenuDescripcion' => 'nullable|max:500'],
            ['editMenuDescripcion.max' => 'Máximo 500 caracteres.']
        );
        Producto::findOrFail($this->editMenuId)->update(['descripcion' => trim($this->editMenuDescripcion) ?: null]);
        $this->editMenuId = null;
    }

    public function cancelarEditMenu(): void { $this->editMenuId = null; }

    /* ── Dish edit ────────────────────────────────────────── */

    public function startEditPlato(int $id): void
    {
        $p                   = Plato::findOrFail($id);
        $this->editPlatoId   = $id;
        $this->editPlatoNombre = $p->nombre;
        $this->addingToMenuId  = null;
    }

    public function guardarPlato(): void
    {
        $this->validate(
            ['editPlatoNombre' => 'required|min:2|max:200'],
            ['editPlatoNombre.required' => 'El nombre es obligatorio.', 'editPlatoNombre.min' => 'Mínimo 2 caracteres.']
        );
        Plato::findOrFail($this->editPlatoId)->update(['nombre' => trim($this->editPlatoNombre)]);
        $this->editPlatoId = null;
    }

    public function cancelarEditPlato(): void { $this->editPlatoId = null; }

    /* ── Add dish ─────────────────────────────────────────── */

    public function startAddPlato(int $menuId): void
    {
        $this->addingToMenuId   = $menuId;
        $this->nuevoPlatoNombre = '';
        $this->editPlatoId      = null;
        $this->importMenuId     = null;
    }

    public function agregarPlato(): void
    {
        $this->validate(
            ['nuevoPlatoNombre' => 'required|min:2|max:200'],
            ['nuevoPlatoNombre.required' => 'El nombre es obligatorio.', 'nuevoPlatoNombre.min' => 'Mínimo 2 caracteres.']
        );
        $menu  = Producto::findOrFail($this->addingToMenuId);
        $orden = (int) $menu->platos()->max('orden') + 1;
        Plato::create([
            'producto_id' => $menu->id,
            'nombre'      => trim($this->nuevoPlatoNombre),
            'orden'       => $orden,
            'activo'      => true,
        ]);
        $this->nuevoPlatoNombre = '';
        $this->addingToMenuId   = null;
    }

    public function cancelarAgregarPlato(): void { $this->addingToMenuId = null; }

    /* ── Toggle / delete dish ─────────────────────────────── */

    public function togglePlato(int $id): void
    {
        $p = Plato::findOrFail($id);
        $p->update(['activo' => !$p->activo]);
    }

    public function confirmarEliminarPlato(int $id): void { $this->deletingPlatoId = $id; }

    public function eliminarPlato(): void
    {
        if ($this->deletingPlatoId) {
            Plato::findOrFail($this->deletingPlatoId)->delete();
        }
        $this->deletingPlatoId = null;
    }

    public function cancelarEliminarPlato(): void { $this->deletingPlatoId = null; }

    /* ── CSV / bulk import ────────────────────────────────── */

    public function openImport(int $menuId): void
    {
        $this->importMenuId   = $menuId;
        $this->csvText        = '';
        $this->addingToMenuId = null;
        $this->editPlatoId    = null;
    }

    public function importarCSV(): void
    {
        $this->validate(
            ['csvText' => 'required|string|max:5000'],
            ['csvText.required' => 'Pegá el listado de platos.']
        );
        $menu   = Producto::findOrFail($this->importMenuId);
        $lineas = array_filter(array_map('trim', explode("\n", $this->csvText)));
        $orden  = (int) $menu->platos()->max('orden') + 1;
        $count  = 0;
        foreach ($lineas as $linea) {
            $nombre = trim(preg_replace('/^[\*\-\•\·]\s*/', '', $linea));
            if (mb_strlen($nombre) >= 2) {
                Plato::create(['producto_id' => $menu->id, 'nombre' => $nombre, 'orden' => $orden++, 'activo' => true]);
                $count++;
            }
        }
        session()->flash('success', "{$count} plato(s) importados.");
        $this->importMenuId = null;
        $this->csvText      = '';
    }

    public function cancelarImport(): void { $this->importMenuId = null; }

}; ?>

<div>

    {{-- Flash --}}
    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="mb-6 badge-green px-4 py-3 rounded-xl text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-end justify-between mb-8">
        <div>
            <h2 class="font-condensed font-bold text-2xl" style="color: var(--vd-text); letter-spacing: 0.5px;">Menús</h2>
            <p class="text-sm mt-1" style="color: var(--vd-muted);">Administrá los platos de cada menú semanal.</p>
        </div>
    </div>

    {{-- Delete dish modal --}}
    @if($deletingPlatoId)
    <div class="fixed inset-0 z-50 flex items-center justify-center"
         style="background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);">
        <div class="w-full max-w-sm rounded-2xl p-6"
             style="background: var(--vd-surface-2); border: 1px solid rgba(220,68,68,0.35);
                    box-shadow: 0 24px 60px rgba(0,0,0,0.4);">
            <h3 class="font-condensed font-bold text-lg mb-2" style="color: var(--vd-text);">¿Eliminar plato?</h3>
            <p class="text-sm mb-6" style="color: var(--vd-muted);">Esta acción no se puede deshacer.</p>
            <div class="flex justify-end gap-3">
                <button wire:click="cancelarEliminarPlato" class="btn-secondary">Cancelar</button>
                <button wire:click="eliminarPlato" class="btn-danger">Eliminar</button>
            </div>
        </div>
    </div>
    @endif

    {{-- CSV Import modal --}}
    @if($importMenuId)
    @php $importMenu = $menus->firstWhere('id', $importMenuId); @endphp
    <div class="fixed inset-0 z-50 flex items-center justify-center"
         style="background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);">
        <div class="w-full max-w-lg rounded-2xl p-6"
             style="background: var(--vd-surface-2); border: 1px solid var(--vd-bdr);
                    box-shadow: 0 24px 60px rgba(0,0,0,0.4);">
            <h3 class="font-condensed font-bold text-lg mb-1" style="color: var(--vd-text);">
                Importar platos — {{ $importMenu?->tipoLabel() }}
            </h3>
            <p class="text-xs mb-4" style="color: var(--vd-muted);">
                Pegá un plato por línea. Los guiones o asteriscos iniciales se ignoran.
            </p>
            <textarea wire:model="csvText" rows="8" class="input mb-4 font-mono text-xs"
                      placeholder="* Canelones de pollo a la crema&#10;- Lomo braseado al romero&#10;Tagine de pollo con almendras"></textarea>
            @error('csvText') <p class="text-xs mb-3" style="color:#fca5a5;">{{ $message }}</p> @enderror
            <div class="flex justify-end gap-3">
                <button wire:click="cancelarImport" class="btn-secondary">Cancelar</button>
                <button wire:click="importarCSV" class="btn-primary"
                        wire:loading.attr="disabled" wire:target="importarCSV">
                    <span wire:loading.remove wire:target="importarCSV">Importar</span>
                    <span wire:loading wire:target="importarCSV">Importando…</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Menu cards --}}
    <div class="flex flex-col gap-5">

    @foreach($menus as $menu)
    @php
        $isIntuitivo = $menu->esIntuitivo();
        $badgeClass  = $menu->tipoBadge();
        $colorAccent = match($menu->tipo) {
            'keto'        => 'rgba(96,165,250,0.25)',
            'anti_age'    => 'rgba(78,158,90,0.25)',
            'vegetariano' => 'rgba(78,158,90,0.25)',
            'real'        => 'rgba(200,160,48,0.25)',
            'intuitivo'   => 'rgba(167,139,250,0.25)',
        };
    @endphp

    <div class="card p-0 overflow-visible"
         x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }"
         style="border-color: {{ $colorAccent }};">

        {{-- Card header --}}
        <div class="flex items-center justify-between px-6 py-4 cursor-pointer select-none"
             @click="open = !open"
             style="border-bottom: 1px solid {{ $colorAccent }};">

            <div class="flex items-center gap-3">
                <span class="{{ $badgeClass }} text-xs">{{ $menu->tipoLabel() }}</span>
                @if(!$isIntuitivo)
                    <span class="text-xs font-condensed font-bold tracking-wider"
                          style="color: var(--vd-muted);">
                        {{ $menu->platos->count() }} platos
                    </span>
                @endif
            </div>

            <div class="flex items-center gap-2">
                {{-- Edit description button --}}
                <button @click.stop="$wire.startEditMenu({{ $menu->id }})"
                        class="btn-secondary text-xs px-3 py-1.5"
                        title="Editar descripción">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mr-1">
                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    Descripción
                </button>
                {{-- Accordion toggle --}}
                <svg class="w-4 h-4 transition-transform duration-200" :class="open ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                     style="color: var(--vd-muted);">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </div>

        {{-- Edit description form --}}
        @if($editMenuId === $menu->id)
        <div class="px-6 py-4" style="border-bottom: 1px solid {{ $colorAccent }}; background: rgba(58,125,68,0.04);">
            <label class="label">Descripción del menú</label>
            <textarea wire:model="editMenuDescripcion" rows="3" class="input mb-3"></textarea>
            @error('editMenuDescripcion') <p class="text-xs mb-2" style="color:#fca5a5;">{{ $message }}</p> @enderror
            <div class="flex justify-end gap-2">
                <button wire:click="cancelarEditMenu" class="btn-secondary text-xs">Cancelar</button>
                <button wire:click="guardarMenu" class="btn-primary text-xs">Guardar</button>
            </div>
        </div>
        @endif

        {{-- Accordion body --}}
        <div x-show="open" x-collapse>
            <div class="px-6 py-5">

                {{-- Description --}}
                @if($menu->descripcion)
                <p class="text-sm mb-5 italic" style="color: var(--vd-muted); border-left: 3px solid {{ $colorAccent }}; padding-left: 12px;">
                    {{ $menu->descripcion }}
                </p>
                @endif

                @if($isIntuitivo)
                {{-- Intuitivo special content --}}
                <div class="rounded-xl p-4" style="background: rgba(167,139,250,0.08); border: 1px solid rgba(167,139,250,0.2);">
                    <p class="text-sm font-semibold mb-1" style="color: var(--vd-text);">Menú de selección libre</p>
                    <p class="text-xs" style="color: var(--vd-muted);">
                        Los clientes eligen sus propias combinaciones desde la página pública de ventas,
                        eligiendo de entre los platos disponibles en los demás menús.
                        La restricción mínima de 5 comidas se aplica en el proceso de compra.
                    </p>
                </div>

                @else
                {{-- Dish list --}}
                <div class="flex flex-col gap-1">
                    @foreach($menu->platos as $plato)
                    <div class="flex items-center gap-3 px-3 py-2 rounded-xl group transition-colors"
                         style="border: 1px solid transparent;"
                         onmouseover="this.style.borderColor='{{ $colorAccent }}'; this.style.background='rgba(58,125,68,0.04)'"
                         onmouseout="this.style.borderColor='transparent'; this.style.background=''">

                        @if($editPlatoId === $plato->id)
                        {{-- Inline edit --}}
                        <div class="flex-1 flex items-center gap-2">
                            <input wire:model="editPlatoNombre" type="text"
                                   class="input py-1.5 text-sm flex-1"
                                   wire:keydown.enter="guardarPlato"
                                   wire:keydown.escape="cancelarEditPlato">
                            <button wire:click="guardarPlato" class="btn-primary text-xs px-3 py-1.5">Guardar</button>
                            <button wire:click="cancelarEditPlato" class="btn-secondary text-xs px-3 py-1.5">✕</button>
                        </div>
                        @error('editPlatoNombre') <p class="text-xs" style="color:#fca5a5;">{{ $message }}</p> @enderror

                        @else
                        {{-- Dish number --}}
                        <span class="font-condensed font-bold text-sm w-5 text-center flex-shrink-0"
                              style="color: {{ $colorAccent }}; filter: brightness(1.6);">{{ $loop->iteration }}</span>

                        {{-- Dish name --}}
                        <span class="flex-1 text-sm {{ $plato->activo ? '' : 'opacity-40 line-through' }}"
                              style="color: var(--vd-text);">{{ $plato->nombre }}</span>

                        {{-- Actions (visible on hover) --}}
                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button wire:click="startEditPlato({{ $plato->id }})"
                                    class="btn-secondary text-xs px-2.5 py-1" title="Editar">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </button>
                            <button wire:click="togglePlato({{ $plato->id }})"
                                    class="btn-secondary text-xs px-2.5 py-1"
                                    title="{{ $plato->activo ? 'Desactivar' : 'Activar' }}">
                                @if($plato->activo)
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                    </svg>
                                @else
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/>
                                        <line x1="1" y1="1" x2="23" y2="23"/>
                                    </svg>
                                @endif
                            </button>
                            <button wire:click="confirmarEliminarPlato({{ $plato->id }})"
                                    class="btn-secondary text-xs px-2.5 py-1"
                                    style="color: #fca5a5;"
                                    onmouseover="this.style.background='rgba(220,68,68,0.12)'"
                                    onmouseout="this.style.background=''"
                                    title="Eliminar">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
                                    <path d="M10 11v6m4-6v6M9 6V4h6v2"/>
                                </svg>
                            </button>
                        </div>
                        @endif
                    </div>
                    @endforeach

                    {{-- Empty state --}}
                    @if($menu->platos->isEmpty())
                    <p class="text-xs text-center py-4" style="color: var(--vd-muted-2);">
                        Sin platos. Agregá el primero con el botón de abajo.
                    </p>
                    @endif
                </div>

                {{-- Add dish form --}}
                @if($addingToMenuId === $menu->id)
                <div class="mt-3 flex items-center gap-2">
                    <input wire:model="nuevoPlatoNombre" type="text"
                           class="input py-1.5 text-sm flex-1"
                           placeholder="Nombre del plato…"
                           wire:keydown.enter="agregarPlato"
                           wire:keydown.escape="cancelarAgregarPlato"
                           x-init="$el.focus()">
                    <button wire:click="agregarPlato" class="btn-primary text-xs px-4 py-1.5">Agregar</button>
                    <button wire:click="cancelarAgregarPlato" class="btn-secondary text-xs px-3 py-1.5">✕</button>
                </div>
                @error('nuevoPlatoNombre') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                @endif

                {{-- Action bar --}}
                <div class="flex items-center gap-2 mt-4 pt-4" style="border-top: 1px solid {{ $colorAccent }};">
                    <button wire:click="startAddPlato({{ $menu->id }})" class="btn-secondary text-xs px-3 py-1.5">
                        + Agregar plato
                    </button>
                    <button wire:click="openImport({{ $menu->id }})" class="btn-secondary text-xs px-3 py-1.5">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mr-1">
                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                            <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                        Importar listado
                    </button>
                </div>

                @endif {{-- /isIntuitivo --}}
            </div>
        </div>

    </div>
    @endforeach

    </div>

</div>
