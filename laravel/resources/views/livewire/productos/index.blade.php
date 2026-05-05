<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Producto;

new #[Layout('layouts.app', ['title' => 'Productos'])] class extends Component {

    public string  $buscar    = '';
    public string  $categoria = '';

    public bool    $showForm  = false;
    public string  $nombre      = '';
    public string  $descripcion = '';
    public string  $precio      = '';
    public string  $unidad      = 'kg';
    public string  $categoriaForm = '';

    public ?int    $editingId       = null;
    public string  $editNombre      = '';
    public string  $editDescripcion = '';
    public string  $editPrecio      = '';
    public string  $editUnidad      = 'kg';
    public string  $editCategoria   = '';

    public ?int    $deletingId = null;

    private array $reglasCrear = [
        'nombre'      => 'required|min:2|max:100',
        'precio'      => 'required|numeric|min:0',
        'unidad'      => 'required',
        'descripcion' => 'nullable|max:1000',
        'categoriaForm' => 'nullable|max:60',
    ];

    private array $mensajesCrear = [
        'nombre.required' => 'El nombre del producto es obligatorio.',
        'nombre.min'      => 'El nombre debe tener al menos 2 caracteres.',
        'precio.required' => 'El precio es obligatorio.',
        'precio.numeric'  => 'El precio debe ser un número.',
        'precio.min'      => 'El precio no puede ser negativo.',
        'unidad.required' => 'Seleccioná una unidad de medida.',
    ];

    public function with(): array
    {
        return [
            'productos' => Producto::query()
                ->when($this->buscar, fn($q) => $q->where('nombre', 'like', "%{$this->buscar}%"))
                ->when($this->categoria, fn($q) => $q->where('categoria', $this->categoria))
                ->orderBy('nombre')
                ->get(),
        ];
    }

    public function guardar(): void
    {
        $this->validate($this->reglasCrear, $this->mensajesCrear);

        Producto::create([
            'nombre'      => trim($this->nombre),
            'descripcion' => trim($this->descripcion) ?: null,
            'precio'      => (float) $this->precio,
            'unidad'      => $this->unidad,
            'categoria'   => trim($this->categoriaForm) ?: null,
            'activo'      => true,
        ]);

        $this->nombre       = '';
        $this->descripcion  = '';
        $this->precio       = '';
        $this->unidad       = 'kg';
        $this->categoriaForm = '';
        $this->showForm     = false;
    }

    public function startEdit(int $id): void
    {
        $p = Producto::findOrFail($id);
        $this->editingId       = $id;
        $this->editNombre      = $p->nombre;
        $this->editDescripcion = (string) $p->descripcion;
        $this->editPrecio      = (string) $p->precio;
        $this->editUnidad      = $p->unidad;
        $this->editCategoria   = (string) $p->categoria;
        $this->showForm        = false;
    }

    public function guardarEdicion(): void
    {
        $this->validate([
            'editNombre'      => 'required|min:2|max:100',
            'editPrecio'      => 'required|numeric|min:0',
            'editUnidad'      => 'required',
            'editDescripcion' => 'nullable|max:1000',
            'editCategoria'   => 'nullable|max:60',
        ], [
            'editNombre.required' => 'El nombre es obligatorio.',
            'editNombre.min'      => 'El nombre debe tener al menos 2 caracteres.',
            'editPrecio.required' => 'El precio es obligatorio.',
            'editPrecio.numeric'  => 'El precio debe ser un número.',
            'editPrecio.min'      => 'El precio no puede ser negativo.',
        ]);

        Producto::findOrFail($this->editingId)->update([
            'nombre'      => trim($this->editNombre),
            'descripcion' => trim($this->editDescripcion) ?: null,
            'precio'      => (float) $this->editPrecio,
            'unidad'      => $this->editUnidad,
            'categoria'   => trim($this->editCategoria) ?: null,
        ]);

        $this->editingId = null;
    }

    public function cancelarEdicion(): void
    {
        $this->editingId = null;
    }

    public function toggleActivo(int $id): void
    {
        $p = Producto::findOrFail($id);
        $p->update(['activo' => !$p->activo]);
    }

    public function confirmarEliminar(int $id): void
    {
        $this->deletingId = $id;
    }

    public function eliminar(): void
    {
        if (!$this->deletingId) return;

        $p = Producto::findOrFail($this->deletingId);

        if ($p->tieneOrdenes()) {
            session()->flash('error', 'No se puede eliminar: el producto tiene órdenes asociadas.');
            $this->deletingId = null;
            return;
        }

        $p->delete();
        $this->deletingId = null;
    }

    public function cancelarEliminar(): void
    {
        $this->deletingId = null;
    }

}; ?>

<div>

    {{-- Flash messages --}}
    @if(session('error'))
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">{{ session('error') }}</div>
    @endif

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-3 mb-6">
        <input type="text" wire:model.live.debounce.300ms="buscar"
               placeholder="Buscar producto…" class="input w-56">
        <select wire:model.live="categoria" class="input w-44">
            <option value="">Todas las categorías</option>
            @foreach(\App\Models\Producto::$categorias as $cat)
                <option value="{{ $cat }}">{{ $cat }}</option>
            @endforeach
        </select>
        <div class="flex-1"></div>
        <button wire:click="$toggle('showForm')" class="btn-primary">
            {{ $showForm ? 'Cancelar' : '+ Nuevo producto' }}
        </button>
    </div>

    {{-- Create form --}}
    @if($showForm)
    <div class="card mb-6">
        <h3 class="font-semibold text-gray-800 mb-1">Nuevo producto</h3>
        <p class="text-sm text-gray-500 mb-5">Los campos marcados con <span class="text-red-500">*</span> son obligatorios.</p>

        <form wire:submit="guardar" novalidate>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">

                <div class="md:col-span-2">
                    <label class="label">Nombre <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="nombre"
                           class="input @error('nombre') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                           placeholder="ej: Tomate perita">
                    @error('nombre')
                        <p class="text-red-500 text-xs mt-1 flex items-center gap-1">
                            <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label class="label">Categoría</label>
                    <select wire:model="categoriaForm" class="input">
                        <option value="">Sin categoría</option>
                        @foreach(\App\Models\Producto::$categorias as $cat)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-3">
                    <label class="label">Descripción <span class="text-gray-400 font-normal text-xs">(opcional)</span></label>
                    <textarea wire:model="descripcion"
                              class="input @error('descripcion') border-red-400 @enderror"
                              rows="2"
                              placeholder="Variedad, origen, características…"></textarea>
                    @error('descripcion')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="label">Precio <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                        <input type="number" step="0.01" min="0" wire:model="precio"
                               class="input pl-7 @error('precio') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                               placeholder="0,00">
                    </div>
                    @error('precio')
                        <p class="text-red-500 text-xs mt-1 flex items-center gap-1">
                            <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label class="label">Unidad <span class="text-red-500">*</span></label>
                    <select wire:model="unidad"
                            class="input @error('unidad') border-red-400 @enderror">
                        @foreach(\App\Models\Producto::$unidades as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('unidad')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" wire:click="$toggle('showForm')" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary"
                        wire:loading.attr="disabled" wire:target="guardar">
                    <span wire:loading.remove wire:target="guardar">Guardar producto</span>
                    <span wire:loading wire:target="guardar">Guardando…</span>
                </button>
            </div>
        </form>
    </div>
    @endif

    {{-- Delete modal --}}
    @if($deletingId)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-sm">
            <h3 class="font-semibold text-gray-900 mb-2">¿Eliminar producto?</h3>
            <p class="text-sm text-gray-600 mb-6">Esta acción no se puede deshacer.</p>
            <div class="flex justify-end gap-3">
                <button wire:click="cancelarEliminar" class="btn-secondary">Cancelar</button>
                <button wire:click="eliminar" class="btn-danger">Eliminar</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Products table --}}
    <div class="card p-0 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Producto</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Categoría</th>
                    <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Precio</th>
                    <th class="text-center px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($productos as $p)

                {{-- Inline edit row --}}
                @if($editingId === $p->id)
                <tr class="bg-verdeo-50">
                    <td class="px-4 py-3" colspan="5">
                        <form wire:submit="guardarEdicion" novalidate>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-3">
                                <div class="md:col-span-2">
                                    <label class="label text-xs">Nombre *</label>
                                    <input wire:model="editNombre" type="text"
                                           class="input text-sm @error('editNombre') border-red-400 @enderror">
                                    @error('editNombre') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="label text-xs">Categoría</label>
                                    <select wire:model="editCategoria" class="input text-sm">
                                        <option value="">Sin categoría</option>
                                        @foreach(\App\Models\Producto::$categorias as $cat)
                                            <option value="{{ $cat }}">{{ $cat }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="label text-xs">Precio *</label>
                                        <div class="relative">
                                            <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-xs">$</span>
                                            <input wire:model="editPrecio" type="number" step="0.01" min="0"
                                                   class="input text-sm pl-5 @error('editPrecio') border-red-400 @enderror">
                                        </div>
                                        @error('editPrecio') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="label text-xs">Unidad *</label>
                                        <select wire:model="editUnidad" class="input text-sm">
                                            @foreach(\App\Models\Producto::$unidades as $val => $label)
                                                <option value="{{ $val }}">{{ $val }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="md:col-span-4">
                                    <label class="label text-xs">Descripción</label>
                                    <textarea wire:model="editDescripcion" class="input text-sm" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" wire:click="cancelarEdicion" class="btn-secondary text-xs">Cancelar</button>
                                <button type="submit" class="btn-primary text-xs"
                                        wire:loading.attr="disabled" wire:target="guardarEdicion">
                                    <span wire:loading.remove wire:target="guardarEdicion">Guardar cambios</span>
                                    <span wire:loading wire:target="guardarEdicion">Guardando…</span>
                                </button>
                            </div>
                        </form>
                    </td>
                </tr>

                {{-- Normal row --}}
                @else
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4">
                        <p class="font-medium text-gray-900">{{ $p->nombre }}</p>
                        @if($p->descripcion)
                            <p class="text-xs text-gray-400 mt-0.5 truncate max-w-xs">{{ $p->descripcion }}</p>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-gray-500">{{ $p->categoria ?? '—' }}</td>
                    <td class="px-6 py-4 text-right font-mono font-semibold text-gray-900">
                        ${{ number_format($p->precio, 2, ',', '.') }}
                        <span class="text-xs text-gray-400 font-sans">/{{ $p->unidad }}</span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <button wire:click="toggleActivo({{ $p->id }})"
                                class="{{ $p->activo ? 'badge-green' : 'badge-gray' }} cursor-pointer hover:opacity-75 transition-opacity">
                            {{ $p->activo ? 'Activo' : 'Inactivo' }}
                        </button>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex justify-end gap-2">
                            <button wire:click="startEdit({{ $p->id }})" class="btn-secondary text-xs px-3 py-1.5">
                                Editar
                            </button>
                            <button wire:click="confirmarEliminar({{ $p->id }})"
                                    class="btn-secondary text-xs px-3 py-1.5 text-red-600 hover:bg-red-50">
                                Eliminar
                            </button>
                        </div>
                    </td>
                </tr>
                @endif

                @empty
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-gray-400">
                        No hay productos. Agregá el primero con el botón de arriba.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
