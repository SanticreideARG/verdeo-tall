<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\Conversacion;

new #[Layout('layouts.app', ['title' => 'Conversaciones'])] class extends Component {

    use WithPagination;

    public string $buscar = '';
    public string $zona   = '';
    public string $estado = '';

    public function updatingBuscar(): void { $this->resetPage(); }
    public function updatingZona(): void   { $this->resetPage(); }
    public function updatingEstado(): void { $this->resetPage(); }

    public function with(): array
    {
        return [
            'conversaciones' => Conversacion::query()
                ->when($this->buscar, fn($q) =>
                    $q->where(fn($q) =>
                        $q->where('telefono', 'like', "%{$this->buscar}%")
                          ->orWhere('nombre', 'like', "%{$this->buscar}%")
                    )
                )
                ->when($this->zona,   fn($q) => $q->zona($this->zona))
                ->when($this->estado, fn($q) => $q->where('estado', $this->estado))
                ->orderByDesc('ultimo_mensaje_at')
                ->paginate(20),
        ];
    }

}; ?>

<div>
    <div class="flex flex-wrap gap-3 mb-6">
        <input
            type="text"
            wire:model.live.debounce.300ms="buscar"
            placeholder="Buscar por número o nombre…"
            class="input w-64"
        >
        <select wire:model.live="zona" class="input w-48">
            <option value="">Todas las zonas</option>
            <option value="bsas">BSAS</option>
            <option value="valle_nqn">Valle NQN / Roca</option>
            <option value="cordoba">Córdoba</option>
            <option value="mendoza">Mendoza</option>
        </select>
        <select wire:model.live="estado" class="input w-36">
            <option value="">Todos</option>
            <option value="abierta">Abierta</option>
            <option value="cerrada">Cerrada</option>
            <option value="esperando">Esperando</option>
        </select>
    </div>

    <div class="card p-0 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Contacto</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Zona</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Último mensaje</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($conversaciones as $conv)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4">
                        <p class="font-medium text-gray-900">{{ $conv->nombre ?? 'Sin nombre' }}</p>
                        <p class="text-xs text-gray-400">+{{ $conv->telefono }}</p>
                    </td>
                    <td class="px-6 py-4 text-gray-600">
                        {{ match($conv->zona) {
                            'bsas'      => 'BSAS',
                            'valle_nqn' => 'Valle NQN / Roca',
                            'cordoba'   => 'Córdoba',
                            'mendoza'   => 'Mendoza',
                            default     => $conv->zona,
                        } }}
                    </td>
                    <td class="px-6 py-4 max-w-xs">
                        <p class="text-gray-700 truncate">{{ $conv->ultimo_mensaje ?? '—' }}</p>
                        @if($conv->ultimo_mensaje_at)
                        <p class="text-xs text-gray-400 mt-0.5">{{ $conv->ultimo_mensaje_at->diffForHumans() }}</p>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <span class="{{ match($conv->estado) {
                            'abierta'   => 'badge-green',
                            'esperando' => 'badge-gray',
                            'cerrada'   => 'badge-gray',
                            default     => 'badge-gray',
                        } }}">
                            {{ ucfirst($conv->estado) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <button class="btn-secondary text-xs px-3 py-1.5">Ver</button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-16 text-center text-gray-400">
                        <p class="text-lg mb-1">Sin conversaciones</p>
                        <p class="text-sm">Conectá una instancia de WhatsApp en <a href="{{ route('zonas') }}" class="text-verdeo-600 underline">Zonas</a> para comenzar.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        @if($conversaciones->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $conversaciones->links() }}
        </div>
        @endif
    </div>
</div>
