<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Conversacion;

new #[Layout('layouts.app', ['title' => 'Zonas'])] class extends Component {

    public array $zonas = [];

    public function mount(): void
    {
        $this->zonas = [
            ['id' => 'bsas',      'nombre' => 'Buenos Aires',    'numero' => '5491158393179', 'modelo' => 'Mistral', 'activa' => true],
            ['id' => 'valle_nqn', 'nombre' => 'Valle NQN / Roca','numero' => '5492995493102', 'modelo' => 'Mistral', 'activa' => true],
            ['id' => 'cordoba',   'nombre' => 'Córdoba',         'numero' => '5493513007925', 'modelo' => 'Mistral', 'activa' => true],
            ['id' => 'mendoza',   'nombre' => 'Mendoza',         'numero' => '5492615117163', 'modelo' => 'Mistral', 'activa' => true],
        ];
    }

    public function with(): array
    {
        return [
            'convActivas' => Conversacion::activas()
                ->selectRaw('zona, count(*) as total')
                ->groupBy('zona')
                ->pluck('total', 'zona'),
        ];
    }

    public function toggleZona(string $id): void
    {
        foreach ($this->zonas as &$zona) {
            if ($zona['id'] === $id) {
                $zona['activa'] = ! $zona['activa'];
            }
        }
    }

}; ?>

<div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @foreach($zonas as $zona)
        <div class="card">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h3 class="font-semibold" style="color: var(--vd-text);">{{ $zona['nombre'] }}</h3>
                    <p class="text-sm mt-0.5" style="color: var(--vd-muted);">+{{ $zona['numero'] }}</p>
                </div>
                <button wire:click="toggleZona('{{ $zona['id'] }}')"
                        class="{{ $zona['activa'] ? 'badge-green' : 'badge-gray' }} cursor-pointer select-none">
                    {{ $zona['activa'] ? 'Activa' : 'Inactiva' }}
                </button>
            </div>

            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span style="color: var(--vd-muted);">Modelo IA</span>
                    <span class="font-medium" style="color: var(--vd-text);">{{ $zona['modelo'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span style="color: var(--vd-muted);">WhatsApp</span>
                    <span class="badge-gray">Sin conectar</span>
                </div>
                <div class="flex justify-between">
                    <span style="color: var(--vd-muted);">Conversaciones activas</span>
                    <span class="font-medium" style="color: var(--vd-text);">{{ $convActivas[$zona['id']] ?? 0 }}</span>
                </div>
            </div>

            <div class="mt-4 pt-4 flex gap-2" style="border-top: 1px solid var(--vd-bdr-soft);">
                <a href="{{ route('n8n') }}" target="_blank"
                   class="btn-secondary text-xs px-3 py-1.5">Conectar WhatsApp</a>
                <a href="/horizon" target="_blank"
                   class="btn-secondary text-xs px-3 py-1.5">Ver logs</a>
            </div>
        </div>
        @endforeach
    </div>
</div>
