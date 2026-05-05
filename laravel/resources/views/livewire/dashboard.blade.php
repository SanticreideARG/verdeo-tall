<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Conversacion;
use Illuminate\Support\Facades\Redis;

new #[Layout('layouts.app', ['title' => 'Dashboard'])] class extends Component {

    public int $totalConversaciones = 0;
    public int $conversacionesHoy   = 0;
    public int $mensajesEnCola      = 0;
    public array $porZona           = [];

    public function mount(): void
    {
        $this->totalConversaciones = Conversacion::activas()->count();
        $this->conversacionesHoy   = Conversacion::hoy()->count();

        try {
            $this->mensajesEnCola = Redis::llen('queues:default') + Redis::llen('queues:whatsapp');
        } catch (\Exception) {
            $this->mensajesEnCola = 0;
        }

        $zonas = [
            'bsas'      => ['nombre' => 'BSAS',          'numero' => '5491158393179'],
            'valle_nqn' => ['nombre' => 'Valle NQN/Roca', 'numero' => '5492995493102'],
            'cordoba'   => ['nombre' => 'Córdoba',        'numero' => '5493513007925'],
            'mendoza'   => ['nombre' => 'Mendoza',        'numero' => '5492615117163'],
        ];

        $activas = Conversacion::activas()
            ->selectRaw('zona, count(*) as total')
            ->groupBy('zona')
            ->pluck('total', 'zona');

        $this->porZona = collect($zonas)->map(fn($z, $id) => [
            'zona'   => $z['nombre'],
            'numero' => $z['numero'],
            'activas'=> $activas->get($id, 0),
            'estado' => 'Activo',
        ])->values()->all();
    }

}; ?>

<div>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="card">
            <p class="text-sm text-gray-500">Conversaciones hoy</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ $conversacionesHoy }}</p>
        </div>
        <div class="card">
            <p class="text-sm text-gray-500">Total activas</p>
            <p class="text-3xl font-bold text-verdeo-600 mt-1">{{ $totalConversaciones }}</p>
        </div>
        <div class="card">
            <p class="text-sm text-gray-500">Zonas</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ count($porZona) }}</p>
        </div>
        <div class="card">
            <p class="text-sm text-gray-500">Cola Horizon</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ $mensajesEnCola }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($porZona as $z)
        <div class="card flex items-center justify-between">
            <div>
                <p class="font-semibold text-gray-800">{{ $z['zona'] }}</p>
                <p class="text-xs text-gray-400 mt-0.5">+{{ $z['numero'] }}</p>
                <p class="text-sm text-gray-600 mt-1">{{ $z['activas'] }} conv. activas</p>
            </div>
            <span class="badge-green">{{ $z['estado'] }}</span>
        </div>
        @endforeach
    </div>
</div>
