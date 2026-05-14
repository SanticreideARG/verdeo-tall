<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.app', ['title' => 'Clientes'])] class extends Component {

    use WithPagination;

    public string $buscar = '';
    public string $zona   = '';
    public string $orden  = 'numero_cliente';

    public function updatingBuscar(): void { $this->resetPage(); }
    public function updatingZona(): void   { $this->resetPage(); }

    public function with(): array
    {
        return [
            'clientes' => User::where('role', 'cliente')
                ->when($this->buscar, fn($q) =>
                    $q->where(fn($q) =>
                        $q->where('name',     'like', "%{$this->buscar}%")
                          ->orWhere('apellido','like', "%{$this->buscar}%")
                          ->orWhere('whatsapp','like', "%{$this->buscar}%")
                          ->orWhereRaw('LPAD(numero_cliente,4,0) like ?', ["%{$this->buscar}%"])
                    )
                )
                ->when($this->zona, fn($q) => $q->where('zona', $this->zona))
                ->orderBy($this->orden)
                ->paginate(25),
            'total'    => User::where('role', 'cliente')->count(),
        ];
    }

}; ?>

<div>

    @if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         class="mb-6 badge-green px-4 py-3 rounded-xl text-sm">
        {{ session('success') }}
    </div>
    @endif

    {{-- Filtros + acción --}}
    <div class="flex flex-wrap items-center gap-3 mb-6">
        <div class="relative flex-1 min-w-48">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"
                 class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" style="color: var(--vd-muted-2);">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
            </svg>
            <input type="text" wire:model.live.debounce.300ms="buscar"
                   placeholder="Buscar por nombre, número o WhatsApp…"
                   class="input pl-9 w-full">
        </div>
        <select wire:model.live="zona" class="input w-48">
            <option value="">Todas las zonas</option>
            <option value="bsas">BSAS</option>
            <option value="valle_nqn">Valle NQN / Roca</option>
            <option value="cordoba">Córdoba</option>
            <option value="mendoza">Mendoza</option>
        </select>
        <select wire:model.live="orden" class="input w-44">
            <option value="numero_cliente">Ordenar por #</option>
            <option value="name">Nombre A–Z</option>
            <option value="created_at">Más recientes</option>
        </select>
        <div class="flex-1"></div>
        @if(auth()->user()->isAdmin())
            <span class="text-sm" style="color: var(--vd-muted);">{{ $total }} clientes</span>
            <a href="{{ route('usuarios.crear-cliente') }}" wire:navigate
               class="btn-primary text-sm flex items-center gap-2">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Registrar cliente
            </a>
        @endif
    </div>

    {{-- Tabla --}}
    <div class="card p-0 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr style="border-bottom: 1px solid var(--vd-bdr-soft);">
                    <th class="text-left px-5 py-3 font-condensed text-xs uppercase tracking-wide" style="color: var(--vd-muted-2);">#</th>
                    <th class="text-left px-5 py-3 font-condensed text-xs uppercase tracking-wide" style="color: var(--vd-muted-2);">Cliente</th>
                    <th class="text-left px-5 py-3 font-condensed text-xs uppercase tracking-wide" style="color: var(--vd-muted-2);">WhatsApp</th>
                    <th class="text-left px-5 py-3 font-condensed text-xs uppercase tracking-wide" style="color: var(--vd-muted-2);">Zona</th>
                    <th class="text-left px-5 py-3 font-condensed text-xs uppercase tracking-wide" style="color: var(--vd-muted-2);">Ciudad</th>
                    <th class="text-left px-5 py-3 font-condensed text-xs uppercase tracking-wide" style="color: var(--vd-muted-2);">Registro</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($clientes as $c)
                <tr style="border-bottom: 1px solid var(--vd-bdr-soft); cursor: pointer;"
                    onclick="window.location.href='{{ route('usuarios.ver', $c) }}'"
                    onmouseover="this.style.background='var(--vd-nav-hover)'"
                    onmouseout="this.style.background=''">
                    <td class="px-5 py-3">
                        @if($c->numero_cliente)
                            <span class="font-mono text-xs font-bold" style="color: #c8a030;">
                                #{{ str_pad($c->numero_cliente, 4, '0', STR_PAD_LEFT) }}
                            </span>
                        @else
                            <span style="color: var(--vd-muted-2);">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            @if($c->foto)
                                <img src="{{ Storage::url($c->foto) }}" alt="{{ $c->nombreCompleto() }}"
                                     class="w-8 h-8 rounded-full object-cover flex-shrink-0"
                                     style="border: 1px solid rgba(200,160,48,0.3);">
                            @else
                                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-white flex-shrink-0"
                                     style="background: linear-gradient(135deg, #a07820, #c8a030); font-size: 13px;">
                                    {{ strtoupper(substr($c->name, 0, 1)) }}
                                </div>
                            @endif
                            <div>
                                <p class="font-semibold" style="color: var(--vd-text);">{{ $c->nombreCompleto() }}</p>
                                @if($c->email)
                                    <p class="text-xs" style="color: var(--vd-muted);">{{ $c->email }}</p>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3">
                        <span class="text-xs font-mono" style="color: var(--vd-text-soft);">{{ $c->whatsapp ?: '—' }}</span>
                    </td>
                    <td class="px-5 py-3">
                        @if($c->zona)
                            <span class="text-xs px-2 py-0.5 rounded-full font-condensed font-bold uppercase"
                                  style="background: rgba(78,158,90,0.12); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.25);">
                                {{ match($c->zona) {
                                    'bsas'      => 'BSAS',
                                    'valle_nqn' => 'Valle NQN',
                                    'cordoba'   => 'Córdoba',
                                    'mendoza'   => 'Mendoza',
                                    default     => $c->zona,
                                } }}
                            </span>
                        @else
                            <span style="color: var(--vd-muted-2);">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-sm" style="color: var(--vd-text-soft);">{{ $c->ciudad ?: '—' }}</td>
                    <td class="px-5 py-3 text-xs" style="color: var(--vd-muted);">{{ $c->created_at->format('d/m/Y') }}</td>
                    <td class="px-5 py-3">
                        <span class="btn-secondary text-xs px-3 py-1" style="pointer-events:none;">Ver ficha</span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-16 text-center" style="color: var(--vd-muted);">
                        @if($buscar || $zona)
                            No se encontraron clientes con esos filtros.
                        @else
                            No hay clientes registrados.
                            @if(auth()->user()->isAdmin())
                                <a href="{{ route('usuarios.crear-cliente') }}" wire:navigate
                                   style="color: var(--vd-green-lt);" class="underline ml-1">Registrar el primero</a>
                            @endif
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        @if($clientes->hasPages())
        <div class="px-6 py-4" style="border-top: 1px solid var(--vd-bdr);">
            {{ $clientes->links() }}
        </div>
        @endif
    </div>
</div>
