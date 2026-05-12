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
        <input type="text" wire:model.live.debounce.300ms="buscar"
               placeholder="Buscar por número o nombre…" class="input w-64">
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
            <thead style="background: var(--vd-bg-2); border-bottom: 1px solid var(--vd-bdr);">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-semibold uppercase tracking-wider"
                        style="color: var(--vd-muted-2);">Contacto</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold uppercase tracking-wider"
                        style="color: var(--vd-muted-2);">Zona</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold uppercase tracking-wider"
                        style="color: var(--vd-muted-2);">Último mensaje</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold uppercase tracking-wider"
                        style="color: var(--vd-muted-2);">Estado</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($conversaciones as $conv)
                <tr style="border-bottom: 1px solid var(--vd-bdr-soft);">
                    <td class="px-6 py-4">
                        <p class="font-medium" style="color: var(--vd-text);">{{ $conv->nombre ?? 'Sin nombre' }}</p>
                        <p class="text-xs" style="color: var(--vd-muted-2);">+{{ $conv->telefono }}</p>
                    </td>
                    <td class="px-6 py-4" style="color: var(--vd-muted);">
                        {{ match($conv->zona) {
                            'bsas'      => 'BSAS',
                            'valle_nqn' => 'Valle NQN / Roca',
                            'cordoba'   => 'Córdoba',
                            'mendoza'   => 'Mendoza',
                            default     => $conv->zona,
                        } }}
                    </td>
                    <td class="px-6 py-4 max-w-xs">
                        <p class="truncate" style="color: var(--vd-text);">{{ $conv->ultimo_mensaje ?? '—' }}</p>
                        @if($conv->ultimo_mensaje_at)
                        <p class="text-xs mt-0.5" style="color: var(--vd-muted-2);">{{ $conv->ultimo_mensaje_at->diffForHumans() }}</p>
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
                        <a href="https://wa.me/{{ $conv->telefono }}" target="_blank"
                           class="btn-secondary text-xs px-3 py-1.5">Abrir WA</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-16 text-center">
                        <p class="text-lg mb-1" style="color: var(--vd-muted);">Sin conversaciones</p>
                        <p class="text-sm" style="color: var(--vd-muted-2);">
                            Conectá una instancia de WhatsApp en
                            <a href="{{ route('zonas') }}" style="color: var(--vd-green-lt);" class="underline">Zonas</a>
                            para comenzar.
                        </p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        @if($conversaciones->hasPages())
        <div class="px-6 py-4" style="border-top: 1px solid var(--vd-bdr);">
            {{ $conversaciones->links() }}
        </div>
        @endif
    </div>
</div>
