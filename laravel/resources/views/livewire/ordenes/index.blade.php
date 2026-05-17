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

    public int    $clienteId  = 0;
    public string $zonaNueva  = '';
    public string $notas      = '';
    public string $ingresaPor = '';
    public string $direccion  = '';
    public string $latitud    = '';
    public string $longitud   = '';
    public string $fechaLista = '';
    public array  $items        = [];
    public array  $seleccionados = [];

    public string $buscarCliente         = '';
    public string $nuevoClienteNombre    = '';
    public string $nuevoClienteTelefono  = '';

    public function mount(): void
    {
        $this->fechaLista = now()->toDateString();
    }

    public function with(): array
    {
        $zonaLabels = [
            'bsas'      => 'Buenos Aires',
            'valle_nqn' => 'Valle NQN / Roca',
            'cordoba'   => 'Córdoba',
            'mendoza'   => 'Mendoza',
        ];

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
            'resultadosBusqueda' => strlen($this->buscarCliente) >= 2
                ? User::where('role', 'cliente')
                    ->where(fn($q) => $q
                        ->where('name', 'like', "%{$this->buscarCliente}%")
                        ->orWhere('whatsapp', 'like', "%{$this->buscarCliente}%"))
                    ->orderBy('name')->limit(10)->get()
                : collect(),
            'clienteNombreDisplay' => $this->clienteId
                ? (User::find($this->clienteId)?->name ?? '')
                : '',
            'productos' => Producto::activos()->orderBy('nombre')->get(),
            'listaVentas' => ($this->zona && $this->puedeModificar())
                ? Orden::whereDate('created_at', $this->fechaLista ?: now()->toDateString())
                    ->where('zona', $this->zona)
                    ->with(['items.producto', 'cliente'])
                    ->orderBy('numero')
                    ->get()
                : collect(),
            'zonaLabel'  => $zonaLabels[$this->zona] ?? $this->zona,
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
            'ingresaPor'              => 'nullable|in:whatsapp,mail',
            'direccion'               => 'nullable|string|max:255',
            'latitud'                 => 'nullable|numeric|between:-90,90',
            'longitud'                => 'nullable|numeric|between:-180,180',
            'items'                   => 'required|array|min:1',
            'items.*.producto_id'     => 'required|exists:productos,id',
            'items.*.tamano'          => 'required|in:250g,400g',
            'items.*.forma_pago'      => 'required|in:no_definido,en_destino,transferencia',
        ], [
            'clienteId.required'           => 'Seleccioná un cliente.',
            'zonaNueva.required'           => 'Seleccioná una zona.',
            'items.required'               => 'Agregá al menos un menú.',
            'items.*.producto_id.required' => 'Seleccioná un menú.',
            'items.*.tamano.required'      => 'Seleccioná un tamaño.',
            'items.*.forma_pago.required'  => 'Seleccioná la forma de pago.',
        ]);

        $orden = DB::transaction(function () {
            $orden = Orden::create([
                'numero'     => Orden::generarNumero($this->clienteId),
                'user_id'    => $this->clienteId,
                'estado'     => 'pendiente',
                'zona'       => $this->zonaNueva,
                'notas'      => $this->notas ?: null,
                'ingresa_por'=> $this->ingresaPor ?: null,
                'direccion'  => $this->direccion ?: null,
                'latitud'    => $this->latitud !== '' ? (float) $this->latitud : null,
                'longitud'   => $this->longitud !== '' ? (float) $this->longitud : null,
                'total'      => 0,
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

        $this->reset(['clienteId', 'zonaNueva', 'notas', 'ingresaPor', 'direccion', 'latitud', 'longitud', 'items', 'buscarCliente', 'nuevoClienteNombre', 'nuevoClienteTelefono']);
        $this->clienteId = 0;
        session()->flash('success', "Orden {$orden->numero} creada.");
        $this->dispatch('orden-guardada');
    }

    public function seleccionarCliente(int $id): void
    {
        $this->clienteId    = $id;
        $this->buscarCliente = '';
        $cliente = User::find($id);
        if ($cliente?->zona) {
            $this->zonaNueva = $cliente->zona;
        }
    }

    public function crearYSeleccionarCliente(): void
    {
        $this->validate([
            'nuevoClienteNombre'   => 'required|string|max:100',
            'nuevoClienteTelefono' => 'required|string|max:30',
        ], [
            'nuevoClienteNombre.required'   => 'Ingresá el nombre.',
            'nuevoClienteTelefono.required' => 'Ingresá el número de WhatsApp.',
        ]);

        $user = User::create([
            'name'           => $this->nuevoClienteNombre,
            'email'          => preg_replace('/\D/', '', $this->nuevoClienteTelefono) . '.' . \Illuminate\Support\Str::random(6) . '@verdeo.local',
            'password'       => bcrypt(\Illuminate\Support\Str::random(16)),
            'role'           => 'cliente',
            'whatsapp'       => $this->nuevoClienteTelefono,
            'numero_cliente' => (User::where('role', 'cliente')->max('numero_cliente') ?? 0) + 1,
        ]);

        $this->clienteId             = $user->id;
        $this->nuevoClienteNombre    = '';
        $this->nuevoClienteTelefono  = '';
        $this->dispatch('cliente-creado');
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

    public function confirmarBloque(): void
    {
        if (!$this->puedeModificar() || empty($this->seleccionados)) return;
        Orden::whereIn('id', $this->seleccionados)
            ->where('estado', 'pendiente')
            ->update(['estado' => 'aprobada']);
        $count = count($this->seleccionados);
        $this->seleccionados = [];
        session()->flash('success', "{$count} órdenes aprobadas para cocina.");
    }

    public function confirmarTodosPendientes(): void
    {
        if (!$this->puedeModificar()) return;
        $user = auth()->user();
        $zona = $user->isAdmin() ? ($this->zona ?: null) : $user->zona;
        $query = Orden::where('estado', 'pendiente');
        if ($zona) $query->where('zona', $zona);
        $count = $query->count();
        $query->update(['estado' => 'aprobada']);
        $this->seleccionados = [];
        session()->flash('success', "{$count} órdenes pendientes aprobadas para cocina.");
    }

}; ?>

<div x-data="{ showForm: false, showListaVentas: false }" @orden-guardada.window="showForm = false" @cliente-creado.window="$dispatch('close-new-client')">

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-3 mb-3">
        <input type="text" wire:model.live.debounce.300ms="buscar"
               placeholder="Buscar por número o cliente…" class="input w-60">
        <select wire:model.live="estado" class="input w-44">
            <option value="">Todos los estados</option>
            @foreach(\App\Models\Orden::$estados as $val => $label)
                <option value="{{ $val }}">{{ $label }}</option>
            @endforeach
        </select>
        <div class="flex-1"></div>
        @if($this->puedeModificar() && $zona)
        <button @click="showListaVentas = true"
                class="btn-secondary text-sm flex items-center gap-1.5">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/>
            </svg>
            Lista de ventas
        </button>
        @endif
        <button @click="showForm = !showForm" class="btn-primary"
                x-text="showForm ? 'Cancelar' : '+ Nueva orden'">+ Nueva orden</button>
    </div>

    {{-- Zone filter pills --}}
    <div class="flex flex-wrap gap-2 mb-5">
        @php
        $zonasFiltro = [
            ''          => 'Todas',
            'bsas'      => 'Buenos Aires',
            'valle_nqn' => 'Valle NQN',
            'cordoba'   => 'Córdoba',
            'mendoza'   => 'Mendoza',
        ];
        @endphp
        @foreach($zonasFiltro as $val => $label)
        <button wire:click="$set('zona', '{{ $val }}')"
                class="px-3 py-1.5 rounded-full text-xs font-semibold transition-all duration-150"
                style="{{ $zona === $val
                    ? 'background: var(--vd-green); color: #fff; box-shadow: 0 2px 8px rgba(58,125,68,0.3);'
                    : 'background: var(--vd-bg-2); color: var(--vd-text-soft); border: 1px solid var(--vd-bdr);' }}">
            {{ $label }}
        </button>
        @endforeach
    </div>

    {{-- Block confirmation bar (admin / responsable_zona only) --}}
    @if($this->puedeModificar())
    <div class="flex flex-wrap items-center gap-3 mb-4 p-3 rounded-xl"
         style="background: rgba(58,125,68,0.08); border: 1px solid rgba(58,125,68,0.2);">
        <span class="text-xs font-condensed font-bold uppercase tracking-wide" style="color: var(--vd-muted);">
            Confirmación para cocina
        </span>
        <div class="flex-1"></div>
        @if(count($seleccionados) > 0)
        <span class="text-xs" style="color: var(--vd-text-soft);">
            {{ count($seleccionados) }} seleccionadas
        </span>
        <button wire:click="confirmarBloque" class="btn-primary text-xs px-4 py-1.5"
                wire:loading.attr="disabled">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="inline mr-1">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Confirmar bloque ({{ count($seleccionados) }})
        </button>
        @endif
        <button wire:click="confirmarTodosPendientes" class="btn-secondary text-xs px-4 py-1.5"
                wire:loading.attr="disabled"
                title="Confirmar todos los pendientes{{ auth()->user()->isAdmin() && $zona ? ' de la zona filtrada' : (auth()->user()->zona ? ' de ' . auth()->user()->zona : '') }}">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="inline mr-1">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
            </svg>
            Confirmar todos los pendientes
        </button>
    </div>
    @endif

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
                <div class="md:col-span-2"
                     x-data="{ openSearch: false, showNewClient: false }"
                     @close-new-client.window="showNewClient = false"
                     @click.outside="openSearch = false">
                    <label class="label">Cliente</label>

                    {{-- Selected state --}}
                    @if($clienteId && $clienteNombreDisplay)
                    <div class="flex items-center gap-2.5 px-3 py-2 rounded-xl"
                         style="background: rgba(78,158,90,0.08); border: 1px solid rgba(78,158,90,0.3);">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                             style="background: rgba(78,158,90,0.2); color: #4e9e5a;">
                            {{ strtoupper(substr($clienteNombreDisplay, 0, 1)) }}
                        </div>
                        <span class="flex-1 text-sm font-medium" style="color: var(--vd-text);">{{ $clienteNombreDisplay }}</span>
                        <button type="button" wire:click="$set('clienteId', 0)"
                                class="text-xs transition-colors" style="color: var(--vd-muted-2);"
                                onmouseover="this.style.color='#fca5a5'" onmouseout="this.style.color='var(--vd-muted-2)'">✕</button>
                    </div>

                    @else
                    {{-- Search input --}}
                    <div class="relative">
                        <input type="text" wire:model.live.debounce.300ms="buscarCliente"
                               class="input w-full"
                               placeholder="Buscar por nombre o WhatsApp…"
                               autocomplete="off"
                               @focus="openSearch = true">

                        {{-- Dropdown results --}}
                        @if($resultadosBusqueda->isNotEmpty())
                        <div x-show="openSearch"
                             class="absolute top-full left-0 right-0 mt-1 rounded-xl overflow-hidden z-50"
                             style="background: var(--vd-card-bg); border: 1px solid var(--vd-bdr); box-shadow: 0 8px 24px rgba(0,0,0,0.35);">
                            @foreach($resultadosBusqueda as $c)
                            <button type="button"
                                    wire:click="seleccionarCliente({{ $c->id }})"
                                    @click="openSearch = false"
                                    class="w-full text-left px-3 py-2.5 flex items-center gap-2.5 transition-colors"
                                    style="border-bottom: 1px solid var(--vd-bdr-soft);"
                                    onmouseover="this.style.background='var(--vd-bg-2)'"
                                    onmouseout="this.style.background='transparent'">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                                     style="background: rgba(78,158,90,0.15); color: #4e9e5a;">
                                    {{ strtoupper(substr($c->name, 0, 1)) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium truncate" style="color: var(--vd-text);">{{ $c->name }}</p>
                                    @if($c->whatsapp)
                                    <p class="text-xs truncate font-mono" style="color: var(--vd-muted-2);">{{ $c->whatsapp }}</p>
                                    @endif
                                </div>
                            </button>
                            @endforeach
                        </div>
                        @endif
                    </div>

                    {{-- New client toggle --}}
                    <button type="button" @click="showNewClient = !showNewClient"
                            class="mt-2 text-xs font-semibold transition-colors flex items-center gap-1"
                            style="color: var(--vd-green-lt);">
                        <span x-text="showNewClient ? '↑ Cancelar' : '+ Nuevo cliente'">+ Nuevo cliente</span>
                    </button>

                    {{-- Inline new-client form --}}
                    <div x-show="showNewClient" x-collapse
                         class="mt-2 p-3 rounded-xl space-y-2"
                         style="background: var(--vd-bg-2); border: 1px solid var(--vd-bdr); display:none;">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="label" style="font-size:11px;">Nombre</label>
                                <input type="text" wire:model="nuevoClienteNombre" class="input"
                                       placeholder="Juan García">
                                @error('nuevoClienteNombre') <p class="text-xs mt-0.5" style="color:#fca5a5;">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label" style="font-size:11px;">WhatsApp</label>
                                <input type="text" wire:model="nuevoClienteTelefono" class="input"
                                       placeholder="5491158393179">
                                @error('nuevoClienteTelefono') <p class="text-xs mt-0.5" style="color:#fca5a5;">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <button type="button" wire:click="crearYSeleccionarCliente"
                                class="btn-primary text-xs px-4 py-1.5 w-full"
                                wire:loading.attr="disabled" wire:target="crearYSeleccionarCliente">
                            <span wire:loading.remove wire:target="crearYSeleccionarCliente">Crear y seleccionar</span>
                            <span wire:loading wire:target="crearYSeleccionarCliente">Creando…</span>
                        </button>
                    </div>
                    @endif

                    @error('clienteId') <p class="text-xs mt-1" style="color: #fca5a5;">{{ $message }}</p> @enderror
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

            {{-- Ingresa por + Dirección --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="label">Ingresa por</label>
                    <select wire:model="ingresaPor" class="input">
                        <option value="">— Sin especificar —</option>
                        @foreach(\App\Models\Orden::INGRESA_POR as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-3">
                    <label class="label">Dirección de entrega</label>
                    <input type="text" wire:model="direccion" class="input"
                           placeholder="Ej: Roca 574, Neuquén">
                    @error('direccion') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Coordenadas --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">
                        Latitud
                        <span class="font-normal" style="color: var(--vd-muted-2);">(opcional)</span>
                    </label>
                    <input type="number" wire:model="latitud" class="input" step="0.0000001"
                           placeholder="Ej: -38.9516">
                    @error('latitud') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">
                        Longitud
                        <span class="font-normal" style="color: var(--vd-muted-2);">(opcional)</span>
                    </label>
                    <input type="number" wire:model="longitud" class="input" step="0.0000001"
                           placeholder="Ej: -68.0591">
                    @error('longitud') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
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
                    @if($this->puedeModificar())
                    <th class="px-4 py-3 w-10"></th>
                    @endif
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
                    @if($this->puedeModificar())
                    <td class="px-4 py-4 w-10">
                        @if($o->estado === 'pendiente')
                        <input type="checkbox" wire:model.live="seleccionados" value="{{ $o->id }}"
                               class="rounded cursor-pointer"
                               style="accent-color: #4e9e5a; width: 16px; height: 16px;">
                        @endif
                    </td>
                    @endif
                    <td class="px-6 py-4 font-mono text-xs whitespace-nowrap"
                        style="color: var(--vd-muted);">{{ $o->numero }}</td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <p class="font-semibold" style="color: var(--vd-text);">{{ $o->cliente->nombreCompleto() }}</p>
                            @if($o->ingresa_por === 'whatsapp')
                                <span title="Ingresó por WhatsApp">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="#25d366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                </span>
                            @elseif($o->ingresa_por === 'mail')
                                <span title="Ingresó por Mail">
                                    <svg width="13" height="13" fill="none" stroke="#6b7280" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                                </span>
                            @endif
                        </div>
                        @if($o->cliente->whatsapp)
                            <p class="text-xs" style="color: var(--vd-muted-2);">{{ $o->cliente->whatsapp }}</p>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-xs">
                        <span style="color: var(--vd-muted);">{{ match($o->zona) {
                            'bsas'      => 'Buenos Aires',
                            'valle_nqn' => 'Valle NQN',
                            'cordoba'   => 'Córdoba',
                            'mendoza'   => 'Mendoza',
                            default     => $o->zona,
                        } }}</span>
                        @if($o->direccion)
                            <p class="mt-0.5 truncate max-w-[160px]" style="color: var(--vd-muted-2);" title="{{ $o->direccion }}">
                                {{ $o->direccion }}
                            </p>
                        @endif
                        @if($o->latitud && $o->longitud)
                            <a href="https://www.google.com/maps?q={{ $o->latitud }},{{ $o->longitud }}"
                               target="_blank" class="inline-flex items-center gap-1 mt-0.5 text-[10px]"
                               style="color: #4e9e5a;" onclick="event.stopPropagation()">
                                <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                                Ver en mapa
                            </a>
                        @endif
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

    {{-- Lista de Ventas Modal --}}
    @if($this->puedeModificar() && $zona)
    <div x-show="showListaVentas" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center no-print"
         style="background: rgba(0,0,0,0.6);">
        <div class="card w-full max-w-3xl max-h-[90vh] flex flex-col mx-4"
             @click.stop style="overflow: hidden;">

            {{-- Modal header --}}
            <div class="flex items-center justify-between p-5 shrink-0"
                 style="border-bottom: 1px solid var(--vd-bdr);">
                <div>
                    <h2 class="font-condensed font-bold text-lg" style="color: var(--vd-text);">
                        Lista de Ventas
                    </h2>
                    <p class="text-xs mt-0.5" style="color: var(--vd-muted);">
                        {{ $zonaLabel }} — Filtrar por fecha:
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <input type="date" wire:model.live="fechaLista"
                           class="input text-sm py-1 px-3 w-40">
                    <button onclick="window.print()"
                            class="btn-primary text-sm flex items-center gap-1.5">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/>
                        </svg>
                        Imprimir
                    </button>
                    <button @click="showListaVentas = false"
                            style="color: var(--vd-muted-2);" class="hover:opacity-70 transition-opacity">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Printable content --}}
            <div id="lista-ventas-print" class="overflow-y-auto p-5 flex-1">

                {{-- Print header (hidden on screen, shown on print) --}}
                <div class="print-only mb-4" style="display:none;">
                    <h1 style="font-size: 18px; font-weight: bold; margin: 0;">Lista de Ventas</h1>
                    <p style="margin: 4px 0 0; font-size: 13px; color: #555;">
                        {{ $zonaLabel }} — {{ \Carbon\Carbon::parse($fechaLista)->format('d/m/Y') }}
                    </p>
                    <hr style="margin: 10px 0; border-color: #ccc;">
                </div>

                @if($listaVentas->isEmpty())
                <div class="py-12 text-center text-sm" style="color: var(--vd-muted-2);">
                    No hay órdenes para esta zona en la fecha seleccionada.
                </div>
                @else

                {{-- Summary row --}}
                <div class="flex flex-wrap gap-4 mb-4 no-print">
                    <div class="rounded-xl px-4 py-2 text-sm" style="background: rgba(58,125,68,0.08); border: 1px solid rgba(58,125,68,0.2);">
                        <span style="color: var(--vd-muted);">Órdenes:</span>
                        <strong style="color: var(--vd-text); margin-left: 6px;">{{ $listaVentas->count() }}</strong>
                    </div>
                    <div class="rounded-xl px-4 py-2 text-sm" style="background: rgba(58,125,68,0.08); border: 1px solid rgba(58,125,68,0.2);">
                        <span style="color: var(--vd-muted);">Total unidades:</span>
                        <strong style="color: var(--vd-text); margin-left: 6px;">
                            {{ $listaVentas->sum(fn($o) => $o->items->sum('cantidad')) }}
                        </strong>
                    </div>
                    <div class="rounded-xl px-4 py-2 text-sm" style="background: rgba(58,125,68,0.08); border: 1px solid rgba(58,125,68,0.2);">
                        <span style="color: var(--vd-muted);">Total $:</span>
                        <strong style="color: var(--vd-text); margin-left: 6px;">
                            ${{ number_format($listaVentas->sum('total'), 2, ',', '.') }}
                        </strong>
                    </div>
                </div>

                {{-- Table --}}
                <div class="rounded-xl overflow-hidden" style="border: 1px solid var(--vd-bdr);">
                    <table class="w-full text-sm" id="print-table">
                        <thead style="background: var(--vd-bg-2);">
                            <tr>
                                <th class="text-left px-4 py-2.5 text-xs font-condensed font-bold uppercase tracking-wide" style="color: var(--vd-muted-2); width: 90px;">N° Pedido</th>
                                <th class="text-left px-4 py-2.5 text-xs font-condensed font-bold uppercase tracking-wide" style="color: var(--vd-muted-2);">Cliente</th>
                                <th class="text-left px-4 py-2.5 text-xs font-condensed font-bold uppercase tracking-wide" style="color: var(--vd-muted-2);">Menús</th>
                                <th class="text-center px-4 py-2.5 text-xs font-condensed font-bold uppercase tracking-wide" style="color: var(--vd-muted-2); width: 60px;">Uds.</th>
                                <th class="text-right px-4 py-2.5 text-xs font-condensed font-bold uppercase tracking-wide" style="color: var(--vd-muted-2); width: 90px;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $totalUds = 0; @endphp
                            @foreach($listaVentas as $orden)
                            @php
                                $uds = $orden->items->sum('cantidad');
                                $totalUds += $uds;
                            @endphp
                            <tr style="border-top: 1px solid var(--vd-bdr-soft);">
                                <td class="px-4 py-3 font-mono text-xs" style="color: var(--vd-muted);">
                                    {{ $orden->numero }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-medium text-xs" style="color: var(--vd-text);">
                                        {{ $orden->cliente->nombreCompleto() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @foreach($orden->items as $item)
                                    <div class="text-xs leading-5" style="color: var(--vd-text-soft);">
                                        {{ $item->producto?->nombre ?? '—' }}
                                        <span class="font-mono" style="color: var(--vd-muted-2);">· {{ $item->tamano }}</span>
                                        @if($item->cantidad > 1)
                                            <span style="color: var(--vd-muted);">× {{ $item->cantidad }}</span>
                                        @endif
                                    </div>
                                    @endforeach
                                </td>
                                <td class="px-4 py-3 text-center font-mono font-semibold text-xs" style="color: var(--vd-text);">
                                    {{ $uds }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-xs" style="color: var(--vd-text);">
                                    ${{ number_format($orden->total, 0, ',', '.') }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot style="background: var(--vd-bg-2); border-top: 2px solid var(--vd-bdr);">
                            <tr>
                                <td colspan="3" class="px-4 py-3 text-right font-bold text-xs uppercase tracking-wide" style="color: var(--vd-muted);">
                                    Total
                                </td>
                                <td class="px-4 py-3 text-center font-mono font-bold text-sm" style="color: var(--vd-text);">
                                    {{ $totalUds }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-sm" style="color: var(--vd-text);">
                                    ${{ number_format($listaVentas->sum('total'), 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- Print styles --}}
    <style>
        @media print {
            body > * { display: none !important; }
            #lista-ventas-print { display: block !important; }
            #lista-ventas-print .no-print { display: none !important; }
            #lista-ventas-print .print-only { display: block !important; }
            #print-table { border-collapse: collapse; width: 100%; font-size: 11px; }
            #print-table th, #print-table td { border: 1px solid #ccc; padding: 5px 8px; }
            #print-table thead { background: #f3f4f6 !important; -webkit-print-color-adjust: exact; }
            #print-table tfoot { background: #f3f4f6 !important; -webkit-print-color-adjust: exact; }
        }
    </style>
</div>
