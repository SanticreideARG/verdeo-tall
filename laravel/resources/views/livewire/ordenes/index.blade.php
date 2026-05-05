<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use App\Models\Orden;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app', ['title' => 'Órdenes'])] class extends Component {

    use WithPagination;

    // — Filters
    public string $buscar = '';
    public string $estado = '';
    public string $zona   = '';

    // — New order form
    public bool   $showForm  = false;
    public int    $clienteId = 0;
    public string $zonaNueva = '';
    public string $notas     = '';
    public array  $items     = [];

    public function with(): array
    {
        return [
            'ordenes'   => Orden::with(['cliente', 'items'])
                ->when($this->buscar, fn($q) => $q->where(fn($q) => $q
                    ->where('numero', 'like', "%{$this->buscar}%")
                    ->orWhereHas('cliente', fn($q) => $q->where('name', 'like', "%{$this->buscar}%"))
                ))
                ->when($this->estado, fn($q) => $q->where('estado', $this->estado))
                ->when($this->zona,   fn($q) => $q->where('zona', $this->zona))
                ->orderByDesc('created_at')
                ->paginate(20),
            'clientes'  => User::where('role', 'cliente')->orderBy('name')->get(),
            'productos' => Producto::activos()->orderBy('nombre')->get(),
        ];
    }

    private function puedeModificar(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'responsable_zona']);
    }

    // — Items management

    public function agregarItem(): void
    {
        $this->items[] = ['producto_id' => '', 'cantidad' => 1, 'precio_unitario' => 0, 'subtotal' => 0];
    }

    public function removerItem(int $idx): void
    {
        unset($this->items[$idx]);
        $this->items = array_values($this->items);
    }

    public function updatedItems($value, $key): void
    {
        [$idx, $field] = array_pad(explode('.', $key, 2), 2, '');
        $idx = (int) $idx;

        if ($field === 'producto_id' && $value) {
            $producto = Producto::find($value);
            if ($producto) {
                $this->items[$idx]['precio_unitario'] = (float) $producto->precio;
            }
        }

        if (in_array($field, ['producto_id', 'cantidad', 'precio_unitario'])) {
            $cant   = (float) ($this->items[$idx]['cantidad'] ?? 0);
            $precio = (float) ($this->items[$idx]['precio_unitario'] ?? 0);
            $this->items[$idx]['subtotal'] = round($cant * $precio, 2);
        }
    }

    // — Create order

    public function guardarOrden(): void
    {
        $this->validate([
            'clienteId' => 'required|exists:users,id',
            'zonaNueva' => 'required',
            'items'     => 'required|array|min:1',
            'items.*.producto_id' => 'required|exists:productos,id',
            'items.*.cantidad'    => 'required|numeric|min:0.001',
            'items.*.precio_unitario' => 'required|numeric|min:0',
        ]);

        $orden = DB::transaction(function () {
            $orden = Orden::create([
                'numero'  => Orden::generarNumero(),
                'user_id' => $this->clienteId,
                'estado'  => 'pendiente',
                'zona'    => $this->zonaNueva,
                'notas'   => $this->notas ?: null,
                'total'   => 0,
            ]);

            foreach ($this->items as $item) {
                $cant   = (float) $item['cantidad'];
                $precio = (float) $item['precio_unitario'];
                $orden->items()->create([
                    'producto_id'     => $item['producto_id'],
                    'cantidad'        => $cant,
                    'precio_unitario' => $precio,
                    'subtotal'        => round($cant * $precio, 2),
                ]);
            }

            $orden->recalcularTotal();
            return $orden;
        });

        $this->reset(['showForm', 'clienteId', 'zonaNueva', 'notas', 'items']);
        $this->clienteId = 0;
        session()->flash('success', "Orden {$orden->numero} creada.");
    }

    public function updatedClienteId($value): void
    {
        if ($value) {
            $cliente = User::find($value);
            if ($cliente?->zona) {
                $this->zonaNueva = $cliente->zona;
            }
        }
    }

    // — Change estado

    public function cambiarEstado(int $id, string $estado): void
    {
        if (!$this->puedeModificar()) return;
        if (!array_key_exists($estado, Orden::$estados)) return;

        Orden::findOrFail($id)->update(['estado' => $estado]);
    }

    // — Filters reset pagination
    public function updatedBuscar(): void { $this->resetPage(); }
    public function updatedEstado(): void  { $this->resetPage(); }
    public function updatedZona(): void    { $this->resetPage(); }

}; ?>

<div>

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-3 mb-6">
        <input type="text" wire:model.live.debounce.300ms="buscar"
               placeholder="Buscar por número o cliente…" class="input w-60">
        <select wire:model.live="estado" class="input w-44">
            <option value="">Todos los estados</option>
            @foreach(\App\Models\Orden::$estados as $val => $label)
                <option value="{{ $val }}">{{ $label }}</option>
            @endforeach
        </select>
        <select wire:model.live="zona" class="input w-44">
            <option value="">Todas las zonas</option>
            <option value="bsas">Buenos Aires</option>
            <option value="valle_nqn">Valle NQN / Roca</option>
            <option value="cordoba">Córdoba</option>
            <option value="mendoza">Mendoza</option>
        </select>
        <div class="flex-1"></div>
        <button wire:click="$toggle('showForm')" class="btn-primary">
            {{ $showForm ? 'Cancelar' : '+ Nueva orden' }}
        </button>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="mb-4 p-3 bg-verdeo-50 border border-verdeo-200 rounded-lg text-verdeo-700 text-sm font-medium">
            {{ session('success') }}
        </div>
    @endif

    {{-- New order form --}}
    @if($showForm)
    <div class="card mb-6">
        <h3 class="font-semibold text-gray-800 mb-4">Nueva orden</h3>
        <form wire:submit="guardarOrden" class="space-y-4">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label class="label">Cliente</label>
                    <select wire:model.live="clienteId" class="input">
                        <option value="0">Seleccionar cliente…</option>
                        @foreach($clientes as $c)
                            <option value="{{ $c->id }}">
                                {{ $c->name }}
                                @if($c->whatsapp) · {{ $c->whatsapp }} @endif
                            </option>
                        @endforeach
                    </select>
                    @error('clienteId') <p class="text-red-500 text-xs mt-1">Seleccioná un cliente.</p> @enderror
                    @if($clientes->isEmpty())
                        <p class="text-xs text-amber-600 mt-1">
                            No hay clientes registrados. Creá uno en <a href="{{ route('usuarios') }}" class="underline">Usuarios</a>.
                        </p>
                    @endif
                </div>
                <div>
                    <label class="label">Zona de entrega</label>
                    <select wire:model="zonaNueva" class="input">
                        <option value="">Seleccionar…</option>
                        <option value="bsas">Buenos Aires</option>
                        <option value="valle_nqn">Valle NQN / Roca</option>
                        <option value="cordoba">Córdoba</option>
                        <option value="mendoza">Mendoza</option>
                    </select>
                    @error('zonaNueva') <p class="text-red-500 text-xs mt-1">Seleccioná una zona.</p> @enderror
                </div>
            </div>

            <div>
                <label class="label">Notas <span class="text-gray-400 font-normal">(opcional)</span></label>
                <textarea wire:model="notas" class="input" rows="2" placeholder="Instrucciones de entrega, aclaraciones…"></textarea>
            </div>

            {{-- Items --}}
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="label mb-0">Productos</label>
                    <button type="button" wire:click="agregarItem" class="text-verdeo-600 hover:text-verdeo-800 text-sm font-medium">
                        + Agregar producto
                    </button>
                </div>
                @error('items') <p class="text-red-500 text-xs mb-2">Agregá al menos un producto.</p> @enderror

                @if(count($items))
                <div class="border border-gray-200 rounded-lg overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                            <tr>
                                <th class="text-left px-4 py-2">Producto</th>
                                <th class="text-right px-4 py-2 w-28">Cantidad</th>
                                <th class="text-right px-4 py-2 w-32">Precio unit.</th>
                                <th class="text-right px-4 py-2 w-32">Subtotal</th>
                                <th class="px-4 py-2 w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($items as $idx => $item)
                            <tr>
                                <td class="px-4 py-2">
                                    <select wire:model.change="items.{{ $idx }}.producto_id" class="input">
                                        <option value="">Seleccionar…</option>
                                        @foreach($productos as $prod)
                                            <option value="{{ $prod->id }}">{{ $prod->nombre }} ({{ $prod->unidad }})</option>
                                        @endforeach
                                    </select>
                                    @error("items.{$idx}.producto_id") <p class="text-red-500 text-xs mt-1">Requerido.</p> @enderror
                                </td>
                                <td class="px-4 py-2">
                                    <input wire:model.blur="items.{{ $idx }}.cantidad"
                                           type="number" step="0.001" min="0.001"
                                           class="input text-right">
                                </td>
                                <td class="px-4 py-2">
                                    <div class="relative">
                                        <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-xs">$</span>
                                        <input wire:model.blur="items.{{ $idx }}.precio_unitario"
                                               type="number" step="0.01" min="0"
                                               class="input text-right pl-5">
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-right font-mono font-semibold text-gray-800">
                                    ${{ number_format($item['subtotal'] ?? 0, 2, ',', '.') }}
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <button type="button" wire:click="removerItem({{ $idx }})"
                                            class="text-red-400 hover:text-red-600 transition-colors">✕</button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 border-t border-gray-200">
                            <tr>
                                <td colspan="3" class="px-4 py-2 text-right font-semibold text-gray-700 text-sm">Total:</td>
                                <td class="px-4 py-2 text-right font-mono font-bold text-gray-900">
                                    ${{ number_format(array_sum(array_column($items, 'subtotal')), 2, ',', '.') }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @else
                <div class="border-2 border-dashed border-gray-200 rounded-lg p-6 text-center text-gray-400 text-sm">
                    Usá "+ Agregar producto" para incluir ítems en la orden.
                </div>
                @endif
            </div>

            <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                <button type="button" wire:click="$toggle('showForm')" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>Crear orden</span>
                    <span wire:loading>Guardando…</span>
                </button>
            </div>
        </form>
    </div>
    @endif

    {{-- Orders table --}}
    <div class="card p-0 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Nº</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Cliente</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Zona</th>
                    <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Total</th>
                    <th class="text-center px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Fecha</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($ordenes as $o)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 font-mono text-xs text-gray-600 whitespace-nowrap">{{ $o->numero }}</td>
                    <td class="px-6 py-4">
                        <p class="font-medium text-gray-900">{{ $o->cliente->name }}</p>
                        @if($o->cliente->whatsapp)
                            <p class="text-xs text-gray-400">{{ $o->cliente->whatsapp }}</p>
                        @endif
                        @if($o->cliente->direccion)
                            <p class="text-xs text-gray-400 truncate max-w-[180px]">{{ $o->cliente->direccion }}</p>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-gray-500 text-xs">
                        {{ match($o->zona) {
                            'bsas'      => 'Buenos Aires',
                            'valle_nqn' => 'Valle NQN',
                            'cordoba'   => 'Córdoba',
                            'mendoza'   => 'Mendoza',
                            default     => $o->zona,
                        } }}
                    </td>
                    <td class="px-6 py-4 text-right font-mono font-semibold text-gray-900 whitespace-nowrap">
                        ${{ number_format($o->total, 2, ',', '.') }}
                        <span class="block text-xs text-gray-400 font-sans">{{ $o->items->count() }} ítems</span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if($this->puedeModificar() && !in_array($o->estado, ['entregada', 'cancelada']))
                            <select wire:change="cambiarEstado({{ $o->id }}, $event.target.value)"
                                    class="text-xs rounded-full border-0 font-medium cursor-pointer focus:ring-1 focus:ring-verdeo-400 py-0.5 pl-2 pr-6
                                        {{ \App\Models\Orden::$estadoBadge[$o->estado] ?? 'badge-gray' }}">
                                @foreach(\App\Models\Orden::$estados as $val => $label)
                                    <option value="{{ $val }}" @selected($o->estado === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                        @else
                            <span class="{{ $o->estadoBadgeClass() }}">{{ $o->estadoLabel() }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-gray-500 whitespace-nowrap">
                        {{ $o->created_at->format('d/m/Y') }}
                        <span class="block text-xs text-gray-400">{{ $o->created_at->format('H:i') }}</span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                        No hay órdenes que coincidan con los filtros.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @if($ordenes->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $ordenes->links() }}
            </div>
        @endif
    </div>
</div>
