<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app', ['title' => 'Zonas'])] class extends Component {

    public array $zonas = [];

    public function mount(): void
    {
        $this->zonas = [
            ['id' => 'bsas',      'nombre' => 'Buenos Aires',    'numero' => '5491158393179', 'modelo' => 'mistral', 'activa' => true],
            ['id' => 'valle_nqn', 'nombre' => 'Valle NQN / Roca','numero' => '5492995493102', 'modelo' => 'mistral', 'activa' => true],
            ['id' => 'cordoba',   'nombre' => 'Córdoba',         'numero' => '5493513007925', 'modelo' => 'mistral', 'activa' => true],
            ['id' => 'mendoza',   'nombre' => 'Mendoza',         'numero' => '5492615117163', 'modelo' => 'mistral', 'activa' => true],
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
                    <h3 class="font-semibold text-gray-900">{{ $zona['nombre'] }}</h3>
                    <p class="text-sm text-gray-500 mt-0.5">+{{ $zona['numero'] }}</p>
                </div>
                <button wire:click="toggleZona('{{ $zona['id'] }}')"
                        class="{{ $zona['activa'] ? 'badge-green' : 'badge-gray' }} cursor-pointer">
                    {{ $zona['activa'] ? 'Activa' : 'Inactiva' }}
                </button>
            </div>

            <div class="space-y-2 text-sm">
                <div class="flex justify-between text-gray-600">
                    <span>Modelo IA</span>
                    <span class="font-medium text-gray-800">{{ $zona['modelo'] }}</span>
                </div>
                <div class="flex justify-between text-gray-600">
                    <span>WhatsApp</span>
                    <span class="badge-gray">Sin conectar</span>
                </div>
                <div class="flex justify-between text-gray-600">
                    <span>Conversaciones activas</span>
                    <span class="font-medium text-gray-800">0</span>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-gray-100 flex gap-2">
                <button class="btn-secondary text-xs px-3 py-1.5">Conectar WhatsApp</button>
                <button class="btn-secondary text-xs px-3 py-1.5">Ver logs</button>
            </div>
        </div>
        @endforeach
    </div>
</div>
