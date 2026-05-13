<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\Orden;
use App\Models\OrdenItem;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app', ['title' => 'Órdenes'])] class extends Component {

    use WithPagination;

    public string $buscar = '';
    public string $estado = '';
    public string $zona   = '';

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

    public function agregarItem(): void
    {
        $this->items[] = [
            'producto_id'     => '',
            'tamano'          => '250g',
            'forma_pago'      => 'no_definido',
            'precio_unitario' => 0,
            'subtotal'        => 0,
        ];
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

        if (in_array($field, ['producto_id', 'tamano'])) {
            $this->recalcularPrecio($idx);
        }
    }

    private function recalcularPrecio(int $idx): void
    {
        $productoId = $this->items[$idx]['producto_id'] ?? '';
        $tamano     = $this->items[$idx]['tamano'] ?? '250g';

        if ($productoId) {
            $producto = Producto::find($productoId);
            if ($producto) {
                $precio = $tamano === '400g'
                    ? (float) $producto->precio_400g
                    : (float) $producto->precio_250g;
                $this->items[$idx]['precio_unitario'] = $precio;
                $this->items[$idx]['subtotal']        = $precio;
            }
        } else {
            $this->items[$idx]['precio_unitario'] = 0;
            $this->items[$idx]['subtotal']        = 0;
        }
    }

    public function guardarOrden(): void
    {
        $this->validate([
            'clienteId'               => 'required|exists:users,id',
            'zonaNueva'               => 'required',
            'items'                   => 'required|array|min:1',
            'items.*.producto_id'     => 'required|exists:productos,id',
            'items.*.tamano'          => 'required|in:250g,400g',
            'items.*.forma_pago'      => 'required|in:no_definido,en_destino,transferencia',
        ], [
            'clienteId.required'          => 'Seleccioná un cliente.',
            'zonaNueva.required'          => 'Seleccioná una zona.',
            'items.required'              => 'Agregá al menos un menú.',
            'items.*.producto_id.required' => 'Seleccioná un menú.',
            'items.*.tamano.required'      => 'Seleccioná un tamaño.',
            'items.*.forma_pago.required'  => 'Seleccioná la forma de pago.',
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
                $precio = (float) $item['precio_unitario'];
                $orden->items()->create([
                    'producto_id'     => $item['producto_id'],
                    'tamano'          => $item['tamano'],
                    'forma_pago'      => $item['forma_pago'],
                    'cantidad'        => 1,
                    'precio_unitario' => $precio,
                    'subtotal'        => $precio,
                ]);
            }

            $orden->recalcularTotal();
            return $orden;
        });

        $this->reset(['clienteId', 'zonaNueva', 'notas', 'items']);
        $this->clienteId = 0;
        session()->flash('success', "Orden {$orden->numero} creada.");
        $this->dispatch('orden-guardada');
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

    public function cambiarEstado(int $id, string $estado): void
    {
        if (!$this->puedeModificar()) return;
        if (!array_key_exists($estado, Orden::$estados)) return;

        Orden::findOrFail($id)->update(['estado' => $estado]);
    }

    public function updatedBuscar(): void { $this->resetPage(); }
    public function updatedEstado(): void  { $this->resetPage(); }
    public function updatedZona(): void    { $this->resetPage(); }

}; ?>

<div x-data="{ showForm: false }" @orden-guardada.window="showForm = false">

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
        <button @click="showForm = !showForm" class="btn-primary"
                x-text="showForm ? 'Cancelar' : '+ Nueva orden'">+ Nueva orden</button>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="mb-4 badge-green px-3 py-2 text-sm font-medium">
            {{ session('success') }}
        </div>
    @endif

    {{-- New order form --}}
    <div x-show="showForm" x-collapse class="card mb-6" style="display:none;">
        <h3 class="font-condensed font-bold text-lg mb-5" style="color: var(--vd-text);">Nueva orden</h3>
        <form wire:submit="guardarOrden" class="space-y-5">

            {{-- Cliente + Zona --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label class="label">Cliente</label>
                    <select wire:model.live="clienteId" class="input">
                        <option value="0">Seleccionar cliente…</option>
                        @foreach($clientes as $c)
                            <option value="{{ $c->id }}">{{ $c->nombreCompleto() }}@if($c->whatsapp) · {{ $c->whatsapp }}@endif</option>
                        @endforeach
                    </select>
                    @error('clienteId') <p class="text-xs mt-1" style="color: #fca5a5;">{{ $message }}</p> @enderror
                    @if($clientes->isEmpty())
                        <p class="text-xs mt-1" style="color: #f59e0b;">
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
                    @error('zonaNueva') <p class="text-xs mt-1" style="color: #fca5a5;">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Notas --}}
            <div>
                <label class="label">Notas <span class="font-normal" style="color: var(--vd-muted-2);">(opcional)</span></label>
                <textarea wire:model="notas" class="input" rows="2"
                          placeholder="Instrucciones de entrega, aclaraciones…"></textarea>
            </div>

            {{-- Items --}}
            <div>
                <div class="flex items-center justify-between mb-3">
                    <label class="label mb-0">Menús</label>
                    <button type="button" wire:click="agregarItem"
                            class="text-sm font-medium transition-colors"
                            style="color: var(--vd-green-lt);">+ Agregar menú</button>
                </div>
                @error('items') <p class="text-xs mb-2" style="color: #fca5a5;">{{ $message }}</p> @enderror

                @if(count($items))
                <div class="rounded-xl overflow-hidden" style="border: 1px solid var(--vd-bdr);">
                    <table class="w-full text-sm">
                        <thead style="background: var(--vd-bg-2);">
                            <tr>
                                <th class="text-left px-4 py-2 text-xs font-condensed font-bold uppercase tracking-wide"
                                    style="color: var(--vd-muted-2);">Menú</th>
                                <th class="text-left px-4 py-2 w-36 text-xs font-condensed font-bold uppercase tracking-wide"
                                    style="color: var(--vd-muted-2);">Tamaño</th>
                                <th class="text-left px-4 py-2 w-44 text-xs font-condensed font-bold uppercase tracking-wide"
                                    style="color: var(--vd-muted-2);">Forma de pago</th>
                                <th class="text-right px-4 py-2 w-32 text-xs font-condensed font-bold uppercase tracking-wide"
                                    style="color: var(--vd-muted-2);">Precio</th>
                                <th class="px-4 py-2 w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $idx => $item)
                            <tr style="border-top: 1px solid var(--vd-bdr-soft);">

                                {{-- Menú --}}
                                <td class="px-4 py-2">
                                    <select wire:model.live="items.{{ $idx }}.producto_id" class="input">
                                        <option value="">Seleccionar…</option>
                                        @foreach($productos as $prod)
                                            <option value="{{ $prod->id }}">{{ $prod->nombre }}</option>
                                        @endforeach
                                    </select>
                                    @error("items.{$idx}.producto_id")
                                        <p class="text-xs mt-1" style="color: #fca5a5;">{{ $message }}</p>
                                    @enderror
                                </td>

                                {{-- Tamaño --}}
                                <td class="px-4 py-2">
                                    <select wire:model.live="items.{{ $idx }}.tamano" class="input">
                                        @foreach(\App\Models\OrdenItem::TAMANOS as $val => $label)
                                            <option value="{{ $val }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error("items.{$idx}.tamano")
                                        <p class="text-xs mt-1" style="color: #fca5a5;">{{ $message }}</p>
                                    @enderror
                                </td>

                                {{-- Forma de pago --}}
                                <td class="px-4 py-2">
                                    <select wire:model="items.{{ $idx }}.forma_pago" class="input">
                                        @foreach(\App\Models\OrdenItem::FORMAS_PAGO as $val => $label)
                                            <option value="{{ $val }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error("items.{$idx}.forma_pago")
                                        <p class="text-xs mt-1" style="color: #fca5a5;">{{ $message }}</p>
                                    @enderror
                                </td>

                                {{-- Precio (auto) --}}
                                <td class="px-4 py-2 text-right font-mono font-semibold"
                                    style="color: var(--vd-text);">
                                    @if(($item['precio_unitario'] ?? 0) > 0)
                                        ${{ number_format($item['precio_unitario'], 2, ',', '.') }}
                                    @else
                                        <span style="color: var(--vd-muted-2);">—</span>
                                    @endif
                                </td>

                                {{-- Quitar --}}
                                <td class="px-4 py-2 text-center">
                                    <button type="button" wire:click="removerItem({{ $idx }})"
                                            class="transition-colors" style="color: #fca5a5;">✕</button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot style="background: var(--vd-bg-2); border-top: 1px solid var(--vd-bdr);">
                            <tr>
                                <td colspan="3" class="px-4 py-2 text-right font-semibold text-sm"
                                    style="color: var(--vd-muted);">Total:</td>
                                <td class="px-4 py-2 text-right font-mono font-bold" style="color: var(--vd-text);">
                                    ${{ number_format(array_sum(array_column($items, 'subtotal')), 2, ',', '.') }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @else
                <div class="rounded-xl p-6 text-center text-sm"
                     style="border: 2px dashed var(--vd-bdr); color: var(--vd-muted-2);">
                    Usá "+ Agregar menú" para incluir ítems en la orden.
                </div>
                @endif
            </div>

            <div class="flex justify-end gap-3 pt-2" style="border-top: 1px solid var(--vd-bdr-soft);">
                <button type="button" @click="showForm = false" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>Crear orden</span>
                    <span wire:loading>Guardando…</span>
                </button>
            </div>
        </form>
    </div>

    {{-- Orders table --}}
    <div class="card p-0 overflow-hidden">
        <table class="w-full text-sm">
            <thead style="background: var(--vd-bg-2); border-bottom: 1px solid var(--vd-bdr);">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-condensed font-bold uppercase tracking-wide"
                        style="color: var(--vd-muted-2);">Nº</th>
                    <th class="text-left px-6 py-3 text-xs font-condensed font-bold uppercase tracking-wide"
                        style="color: var(--vd-muted-2);">Cliente</th>
                    <th class="text-left px-6 py-3 text-xs font-condensed font-bold uppercase tracking-wide"
                        style="color: var(--vd-muted-2);">Zona</th>
                    <th class="text-left px-6 py-3 text-xs font-condensed font-bold uppercase tracking-wide"
                        style="color: var(--vd-muted-2);">Detalle</th>
                    <th class="text-right px-6 py-3 text-xs font-condensed font-bold uppercase tracking-wide"
                        style="color: var(--vd-muted-2);">Total</th>
                    <th class="text-center px-6 py-3 text-xs font-condensed font-bold uppercase tracking-wide"
                        style="color: var(--vd-muted-2);">Estado</th>
                    <th class="text-left px-6 py-3 text-xs font-condensed font-bold uppercase tracking-wide"
                        style="color: var(--vd-muted-2);">Fecha</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ordenes as $o)
                <tr style="border-bottom: 1px solid var(--vd-bdr-soft);">
                    <td class="px-6 py-4 font-mono text-xs whitespace-nowrap"
                        style="color: var(--vd-muted);">{{ $o->numero }}</td>
                    <td class="px-6 py-4">
                        <p class="font-semibold" style="color: var(--vd-text);">{{ $o->cliente->nombreCompleto() }}</p>
                        @if($o->cliente->whatsapp)
                            <p class="text-xs" style="color: var(--vd-muted-2);">{{ $o->cliente->whatsapp }}</p>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-xs" style="color: var(--vd-muted);">
                        {{ match($o->zona) {
                            'bsas'      => 'Buenos Aires',
                            'valle_nqn' => 'Valle NQN',
                            'cordoba'   => 'Córdoba',
                            'mendoza'   => 'Mendoza',
                            default     => $o->zona,
                        } }}
                    </td>
                    <td class="px-6 py-4">
                        @foreach($o->items->take(2) as $it)
                            <p class="text-xs leading-5" style="color: var(--vd-text-soft);">
                                {{ $it->producto?->nombre ?? '—' }}
                                <span class="font-mono" style="color: var(--vd-muted-2);">· {{ $it->tamano }}</span>
                            </p>
                        @endforeach
                        @if($o->items->count() > 2)
                            <p class="text-xs" style="color: var(--vd-muted-2);">+{{ $o->items->count() - 2 }} más</p>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right font-mono font-semibold whitespace-nowrap"
                        style="color: var(--vd-text);">
                        ${{ number_format($o->total, 2, ',', '.') }}
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if($this->puedeModificar() && !in_array($o->estado, ['entregada', 'cancelada']))
                            <select wire:change="cambiarEstado({{ $o->id }}, $event.target.value)"
                                    class="text-xs rounded-full border-0 font-medium cursor-pointer py-0.5 pl-2 pr-6
                                           {{ \App\Models\Orden::$estadoBadge[$o->estado] ?? 'badge-gray' }}">
                                @foreach(\App\Models\Orden::$estados as $val => $label)
                                    <option value="{{ $val }}" @selected($o->estado === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                        @else
                            <span class="{{ $o->estadoBadgeClass() }}">{{ $o->estadoLabel() }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap" style="color: var(--vd-muted);">
                        {{ $o->created_at->format('d/m/Y') }}
                        <span class="block text-xs" style="color: var(--vd-muted-2);">{{ $o->created_at->format('H:i') }}</span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center" style="color: var(--vd-muted-2);">
                        No hay órdenes que coincidan con los filtros.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @if($ordenes->hasPages())
            <div class="px-6 py-4" style="border-top: 1px solid var(--vd-bdr);">
                {{ $ordenes->links() }}
            </div>
        @endif
    </div>
</div>
