<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Orden;
use App\Models\OrdenItem;
use App\Models\Producto;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app', ['title' => 'Mi Verdeo'])] class extends Component {

    // Order form state
    public array  $items       = [];
    public string $notas       = '';
    public string $direccion   = '';
    public string $formaPago   = 'en_destino';

    public function mount(): void
    {
        if (! auth()->check() || ! auth()->user()->isCliente()) {
            $this->redirect(route('dashboard'), navigate: true);
            return;
        }
        $this->direccion = auth()->user()->direccion ?? '';
    }

    public function with(): array
    {
        return [
            'menus'   => Producto::activos()->orderBy('orden')->get(),
            'pedidos' => Orden::where('user_id', auth()->id())
                            ->with('items.producto')
                            ->latest()
                            ->take(8)
                            ->get(),
        ];
    }

    public function agregarItem(int $productoId, string $tamano): void
    {
        $producto = Producto::find($productoId);
        if (!$producto) return;

        $precio = $tamano === '400kcal' ? (float)$producto->precio_400kcal : (float)$producto->precio_250kcal;

        // Merge if same product+size exists
        foreach ($this->items as $k => $it) {
            if ($it['producto_id'] === $productoId && $it['tamano'] === $tamano) {
                $this->items[$k]['cantidad']++;
                $this->items[$k]['subtotal'] = $this->items[$k]['precio_unitario'] * $this->items[$k]['cantidad'];
                return;
            }
        }

        $this->items[] = [
            'producto_id'     => $productoId,
            'nombre'          => $producto->nombre,
            'tamano'          => $tamano,
            'forma_pago'      => 'en_destino',
            'precio_unitario' => $precio,
            'cantidad'        => 1,
            'subtotal'        => $precio,
        ];
    }

    public function quitarItem(int $idx): void
    {
        unset($this->items[$idx]);
        $this->items = array_values($this->items);
    }

    public function totalPedido(): float
    {
        return array_sum(array_column($this->items, 'subtotal'));
    }

    public function hacerPedido(): void
    {
        $this->validate([
            'items'       => 'required|array|min:1',
            'direccion'   => 'required|string|min:5|max:255',
        ], [
            'items.required'    => 'Agregá al menos un menú al pedido.',
            'direccion.required'=> 'Indicá tu dirección de entrega.',
        ]);

        $user = auth()->user();

        $orden = DB::transaction(function () use ($user) {
            $orden = Orden::create([
                'numero'    => Orden::generarNumero($user->id),
                'user_id'   => $user->id,
                'estado'    => 'pendiente',
                'zona'      => $user->zona,
                'direccion' => $this->direccion,
                'notas'     => $this->notas ?: null,
                'total'     => 0,
            ]);

            foreach ($this->items as $item) {
                $orden->items()->create([
                    'producto_id'     => $item['producto_id'],
                    'tamano'          => $item['tamano'],
                    'forma_pago'      => $this->formaPago,
                    'cantidad'        => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'subtotal'        => $item['subtotal'],
                ]);
            }

            $orden->recalcularTotal();
            return $orden;
        });

        // Notificar al responsable de zona por WhatsApp (best-effort, no bloquea)
        try {
            $responsable = User::where('role', 'responsable_zona')
                ->where('zona', $orden->zona)
                ->whereNotNull('whatsapp')
                ->first();

            if ($responsable) {
                $zonaLabel = match($orden->zona) {
                    'bsas'      => 'Buenos Aires',
                    'valle_nqn' => 'Valle NQN',
                    'cordoba'   => 'Córdoba',
                    'mendoza'   => 'Mendoza',
                    default     => $orden->zona,
                };
                $itemsTexto = $orden->load('items.producto')
                    ->items
                    ->map(fn($it) => "• {$it->producto?->nombre} ({$it->tamano}) × {$it->cantidad}")
                    ->join("\n");

                $msg = "🛒 *Nuevo pedido #{$orden->numero}*\n"
                     . "👤 {$user->name}\n"
                     . "📍 {$zonaLabel} — {$orden->direccion}\n"
                     . "─────────────\n"
                     . $itemsTexto . "\n"
                     . "─────────────\n"
                     . "💰 Total: \${$orden->total}\n"
                     . "💳 Forma de pago: {$this->formaPago}";

                app(WhatsAppService::class)->enviarMensajeZona(
                    $orden->zona,
                    $responsable->whatsapp,
                    $msg
                );
            }
        } catch (\Throwable) {
            // Silent fail — no bloquear el flujo del cliente
        }

        $this->reset(['items', 'notas']);
        $this->dispatch('pedido-enviado', numero: $orden->numero);
    }

}; ?>

<div
    x-data="{
        showForm: false,
        showPedidoBanner: false,
        pedidoNumero: '',
        init() {
            $wire.on('pedido-enviado', ({ numero }) => {
                this.pedidoNumero = numero;
                this.showPedidoBanner = true;
                this.showForm = false;
                setTimeout(() => this.showPedidoBanner = false, 8000);
            });
        }
    }"
    class="max-w-2xl mx-auto">

    {{-- Success banner --}}
    <div x-show="showPedidoBanner"
         x-transition:enter="transition ease-out duration-400"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         style="display:none;"
         class="mb-6 rounded-2xl p-5 flex items-start gap-4"
         style="background: rgba(34,197,94,0.1); border: 1.5px solid rgba(34,197,94,0.35);">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
             style="background: rgba(34,197,94,0.2);">
            <svg width="20" height="20" fill="none" stroke="#22c55e" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div class="flex-1">
            <p class="font-bold text-sm" style="color: #22c55e;">¡Pedido recibido!</p>
            <p class="text-sm mt-0.5" style="color: var(--vd-text-soft);">
                Tu pedido <span x-text="pedidoNumero" class="font-mono font-bold"></span> fue enviado con éxito.
                Te contactaremos para coordinar la entrega.
            </p>
        </div>
        <button @click="showPedidoBanner=false" style="color: var(--vd-muted-2);">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- Welcome card --}}
    <div class="card mb-6 flex flex-col sm:flex-row items-start sm:items-center gap-4">
        <div class="w-14 h-14 rounded-2xl flex items-center justify-center flex-shrink-0 text-2xl font-black"
             style="background: linear-gradient(135deg,#3a7d44,#4e9e5a); color:#fff;">
            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
        </div>
        <div class="flex-1">
            <h2 class="font-condensed font-bold text-xl" style="color: var(--vd-text);">
                ¡Hola, {{ auth()->user()->name }}!
            </h2>
            <div class="flex flex-wrap items-center gap-2 mt-1">
                <span class="text-xs px-2.5 py-0.5 rounded-full font-medium"
                      style="background: rgba(78,158,90,0.12); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.25);">
                    Cliente #{{ str_pad(auth()->user()->numero_cliente ?? 0, 4, '0', STR_PAD_LEFT) }}
                </span>
                @if(auth()->user()->zona)
                <span class="text-xs px-2.5 py-0.5 rounded-full"
                      style="background: var(--vd-bg-2); color: var(--vd-muted); border: 1px solid var(--vd-bdr-soft);">
                    {{ match(auth()->user()->zona) {
                        'bsas'      => 'Buenos Aires',
                        'valle_nqn' => 'Valle NQN / Roca',
                        'cordoba'   => 'Córdoba',
                        'mendoza'   => 'Mendoza',
                        default     => auth()->user()->zona,
                    } }}
                </span>
                @endif
            </div>
        </div>
        <a href="{{ route('ai.chat') }}" wire:navigate
           class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150 flex-shrink-0"
           style="background: rgba(167,139,250,0.1); color: #a78bfa; border: 1px solid rgba(167,139,250,0.25);">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
            </svg>
            Asistente IA
        </a>
    </div>

    {{-- Active orders alert --}}
    @php $activos = $pedidos->whereIn('estado', ['pendiente','aprobada','lista_para_entrega']); @endphp
    @if($activos->isNotEmpty())
    <div class="mb-5 rounded-xl p-4 flex items-center gap-3"
         style="background: rgba(200,160,48,0.08); border: 1.5px solid rgba(200,160,48,0.25);">
        <div class="w-2 h-2 rounded-full animate-pulse flex-shrink-0" style="background: #c8a030;"></div>
        <p class="text-sm font-semibold" style="color: #c8a030;">
            Tenés {{ $activos->count() }} {{ $activos->count() === 1 ? 'pedido activo' : 'pedidos activos' }}
            en preparación.
        </p>
    </div>
    @endif

    {{-- Menu catalog --}}
    <div class="mb-2 flex items-center justify-between">
        <h3 class="font-condensed font-bold text-lg" style="color: var(--vd-text);">Nuestro menú</h3>
        @if(count($items) > 0)
        <button @click="showForm = !showForm"
                class="text-xs font-semibold flex items-center gap-1.5 px-3 py-1.5 rounded-full transition-all"
                style="background: rgba(78,158,90,0.12); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.3);">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z"/>
            </svg>
            {{ count($items) }} en pedido
        </button>
        @endif
    </div>

    @if($menus->isEmpty())
    <div class="card text-center py-10 mb-6" style="color: var(--vd-muted-2);">No hay menús disponibles por el momento.</div>
    @else
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        @foreach($menus as $m)
        <div class="card p-0 overflow-hidden"
             x-data="{ size: '250kcal', open: false }">
            {{-- Card top --}}
            <div class="px-5 pt-5 pb-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <span class="inline-block text-xs font-bold uppercase tracking-wider mb-1 px-2 py-0.5 rounded-full"
                              style="background: rgba(58,125,68,0.1); color: #4e9e5a; border: 1px solid rgba(58,125,68,0.2);">
                            {{ $m->tipo }}
                        </span>
                        <h4 class="font-condensed font-bold text-lg leading-tight" style="color: var(--vd-text);">{{ $m->nombre }}</h4>
                        @if($m->descripcion)
                        <p class="text-xs mt-1 leading-relaxed" style="color: var(--vd-muted-2);">{{ Str::limit($m->descripcion, 80) }}</p>
                        @endif
                    </div>
                    @if($m->foto)
                    <img src="{{ Storage::url($m->foto) }}" alt="{{ $m->nombre }}"
                         class="w-16 h-16 rounded-xl object-cover flex-shrink-0"
                         onerror="this.style.display='none'">
                    @else
                    <div class="w-16 h-16 rounded-xl flex items-center justify-center flex-shrink-0"
                         style="background: linear-gradient(135deg,rgba(58,125,68,0.15),rgba(78,158,90,0.08));">
                        <svg width="24" height="24" fill="none" stroke="#4e9e5a" stroke-width="1.4" viewBox="0 0 24 24" style="opacity:.6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8.25v-1.5m0 1.5c-1.355 0-2.697.056-4.024.166C6.845 8.51 6 9.473 6 10.608v2.513m6-4.87c1.355 0 2.697.055 4.024.165C17.155 8.51 18 9.473 18 10.608v2.513m-3-4.87v-1.5m-6 1.5v-1.5m12 9.75l-1.5.75a3.354 3.354 0 01-3 0 3.354 3.354 0 00-3 0 3.354 3.354 0 01-3 0L3 16.5m15-3.38a48.474 48.474 0 00-6-.37c-2.032 0-4.034.125-6 .37m12 0c.39.049.777.102 1.163.16 1.07.16 1.837 1.084 1.837 2.165V19a2 2 0 01-2 2H5a2 2 0 01-2-2v-2.354c0-1.08.768-2.004 1.837-2.165A48.33 48.33 0 016 13.12M12 10.5a.75.75 0 110-1.5.75.75 0 010 1.5z"/>
                        </svg>
                    </div>
                    @endif
                </div>

                {{-- Prices --}}
                <div class="flex items-center gap-3 mt-3">
                    @if($m->precio_250kcal)
                    <button type="button" @click="size = '250kcal'"
                            class="flex-1 py-2 rounded-xl text-sm font-semibold transition-all duration-150"
                            :style="size === '250kcal'
                                ? 'background: linear-gradient(135deg,#3a7d44,#4e9e5a); color:#fff; border:none;'
                                : 'background: var(--vd-bg-2); color: var(--vd-muted); border: 1px solid var(--vd-bdr);'">
                        250 Kcal<br>
                        <span style="font-size:11px; font-weight:400;">${{ number_format($m->precio_250kcal, 0, ',', '.') }}</span>
                    </button>
                    @endif
                    @if($m->precio_400kcal)
                    <button type="button" @click="size = '400kcal'"
                            class="flex-1 py-2 rounded-xl text-sm font-semibold transition-all duration-150"
                            :style="size === '400kcal'
                                ? 'background: linear-gradient(135deg,#3a7d44,#4e9e5a); color:#fff; border:none;'
                                : 'background: var(--vd-bg-2); color: var(--vd-muted); border: 1px solid var(--vd-bdr);'">
                        400 Kcal<br>
                        <span style="font-size:11px; font-weight:400;">${{ number_format($m->precio_400kcal, 0, ',', '.') }}</span>
                    </button>
                    @endif
                </div>
            </div>

            {{-- Add button --}}
            <div class="px-5 pb-5">
                <button type="button"
                        @click="$wire.agregarItem({{ $m->id }}, size); showForm = true;"
                        class="w-full py-3 rounded-xl font-semibold text-sm transition-all duration-150 flex items-center justify-center gap-2"
                        style="background: rgba(78,158,90,0.1); color: #4e9e5a; border: 1.5px solid rgba(78,158,90,0.3); min-height:48px;"
                        onmouseover="this.style.background='rgba(78,158,90,0.18)'"
                        onmouseout="this.style.background='rgba(78,158,90,0.1)'">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Agregar al pedido
                </button>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Order form --}}
    <div x-show="showForm && {{ count($items) > 0 ? 'true' : 'false' }}"
         x-collapse
         class="card mb-6"
         style="display:none;">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-condensed font-bold text-lg" style="color: var(--vd-text);">Mi pedido</h3>
            <button @click="showForm = false" style="color: var(--vd-muted-2);">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        @error('items') <p class="text-xs mb-3" style="color:#fca5a5;">{{ $message }}</p> @enderror

        {{-- Items --}}
        <div class="space-y-2 mb-5">
            @foreach($items as $idx => $it)
            <div class="flex items-center gap-3 px-4 py-3 rounded-xl"
                 style="background: var(--vd-bg-2); border: 1px solid var(--vd-bdr-soft);">
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-sm truncate" style="color: var(--vd-text);">
                        {{ $it['nombre'] ?? 'Menú' }}
                    </p>
                    <p class="text-xs" style="color: var(--vd-muted-2);">
                        {{ $it['tamano'] }} · x{{ $it['cantidad'] }}
                    </p>
                </div>
                <span class="font-mono text-sm font-semibold" style="color: var(--vd-text);">
                    ${{ number_format($it['subtotal'], 0, ',', '.') }}
                </span>
                <button wire:click="quitarItem({{ $idx }})"
                        class="flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center transition-colors"
                        style="color: var(--vd-muted-2);"
                        onmouseover="this.style.color='#ef4444'"
                        onmouseout="this.style.color='var(--vd-muted-2)'">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            @endforeach

            {{-- Total --}}
            <div class="flex items-center justify-between px-4 py-3 rounded-xl font-semibold"
                 style="background: rgba(78,158,90,0.08); border: 1px solid rgba(78,158,90,0.2);">
                <span style="color: var(--vd-text-soft);">Total estimado</span>
                <span class="font-mono text-lg" style="color: #4e9e5a;">
                    ${{ number_format($this->totalPedido(), 0, ',', '.') }}
                </span>
            </div>
        </div>

        {{-- Delivery details --}}
        <div class="space-y-4">
            <div>
                <label class="label">Dirección de entrega <span style="color:#fca5a5">*</span></label>
                <input type="text" wire:model="direccion"
                       class="input @error('direccion') border-red-400 @enderror"
                       placeholder="Calle 123, Piso 2, Depto A">
                @error('direccion') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Forma de pago</label>
                <select wire:model="formaPago" class="input">
                    <option value="en_destino">Efectivo en destino</option>
                    <option value="transferencia">Transferencia</option>
                </select>
            </div>
            <div>
                <label class="label">Notas o aclaraciones</label>
                <textarea wire:model="notas" rows="2" class="input"
                          placeholder="Preferencias, alergias, instrucciones…"></textarea>
            </div>
        </div>

        <div class="mt-5">
            <button wire:click="hacerPedido"
                    wire:loading.attr="disabled"
                    class="w-full flex items-center justify-center gap-2 font-bold py-4 rounded-xl text-base transition-all"
                    style="background: linear-gradient(135deg,#3a7d44,#4e9e5a); color:#fff; min-height:56px;">
                <span wire:loading.remove wire:target="hacerPedido">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" class="inline mr-1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/>
                    </svg>
                    Enviar pedido
                </span>
                <span wire:loading wire:target="hacerPedido">Enviando…</span>
            </button>
        </div>
    </div>

    {{-- My orders --}}
    @if($pedidos->isNotEmpty())
    @php $pedidosActivos = $pedidos->whereNotIn('estado', ['entregada', 'cancelada']); @endphp
    <h3 class="font-condensed font-bold text-lg mb-4" style="color: var(--vd-text);">Mis pedidos</h3>
    <div class="space-y-3 mb-24" @if($pedidosActivos->isNotEmpty()) wire:poll.30s @endif>
        @foreach($pedidos as $p)
        @php
            $estadoBadge = match($p->estado) {
                'pendiente'          => ['Pendiente',       'rgba(200,160,48,.15)',  '#c8a030', 'rgba(200,160,48,.3)'],
                'aprobada'           => ['Aprobada',        'rgba(59,130,246,.12)',  '#60a5fa', 'rgba(59,130,246,.3)'],
                'lista_para_entrega' => ['Lista p/entregar','rgba(168,85,247,.12)',  '#c084fc', 'rgba(168,85,247,.3)'],
                'entregada'          => ['Entregada',       'rgba(78,158,90,.12)',   '#4e9e5a', 'rgba(78,158,90,.3)'],
                'cancelada'          => ['Cancelada',       'rgba(239,68,68,.1)',    '#ef4444', 'rgba(239,68,68,.25)'],
                default              => [$p->estado,        'rgba(107,114,128,.1)', '#9ca3af', 'rgba(107,114,128,.2)'],
            };
        @endphp
        <div class="card py-3 px-4 flex items-start gap-3">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-mono text-xs font-bold" style="color: var(--vd-muted);">{{ $p->numero }}</span>
                    <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full"
                          style="background:{{ $estadoBadge[1] }}; color:{{ $estadoBadge[2] }}; border:1px solid {{ $estadoBadge[3] }};">
                        {{ $estadoBadge[0] }}
                    </span>
                </div>
                <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-1">
                    @foreach($p->items->take(3) as $it)
                    <p class="text-xs" style="color: var(--vd-text-soft);">{{ $it->producto?->nombre ?? '—' }} · {{ $it->tamano }}</p>
                    @endforeach
                    @if($p->items->count() > 3)
                    <p class="text-xs" style="color: var(--vd-muted-2);">+{{ $p->items->count() - 3 }} más</p>
                    @endif
                </div>
            </div>
            <div class="text-right flex-shrink-0">
                <p class="font-mono font-semibold text-sm" style="color: var(--vd-text);">${{ number_format($p->total, 0, ',', '.') }}</p>
                <p class="text-xs" style="color: var(--vd-muted-2);">{{ $p->created_at->format('d/m') }}</p>
            </div>
        </div>
        @endforeach
    </div>
    @endif

</div>
