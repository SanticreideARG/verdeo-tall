<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Conversacion;
use App\Models\Orden;
use App\Models\User;
use Illuminate\Support\Facades\Redis;

new #[Layout('layouts.app', ['title' => 'Dashboard'])] class extends Component {

    public int   $totalConversaciones = 0;
    public int   $conversacionesHoy   = 0;
    public int   $mensajesEnCola      = 0;
    public int   $totalClientes       = 0;
    public int   $ordenesPendientes   = 0;
    public array $porZona             = [];

    public function mount(): void
    {
        $this->totalConversaciones = Conversacion::activas()->count();
        $this->conversacionesHoy   = Conversacion::hoy()->count();
        $this->totalClientes       = User::where('role', 'cliente')->count();
        $this->ordenesPendientes   = Orden::where('estado', 'pendiente')->count();

        try {
            $this->mensajesEnCola = Redis::llen('queues:default') + Redis::llen('queues:whatsapp');
        } catch (\Exception) {
            $this->mensajesEnCola = 0;
        }

        $zonas = [
            'bsas'      => ['nombre' => 'Buenos Aires',   'numero' => '5491158393179'],
            'valle_nqn' => ['nombre' => 'Valle NQN/Roca', 'numero' => '5492995493102'],
            'cordoba'   => ['nombre' => 'Córdoba',        'numero' => '5493513007925'],
            'mendoza'   => ['nombre' => 'Mendoza',        'numero' => '5492615117163'],
        ];

        $activas = Conversacion::activas()
            ->selectRaw('zona, count(*) as total')
            ->groupBy('zona')
            ->pluck('total', 'zona');

        $this->porZona = collect($zonas)->map(fn($z, $id) => [
            'zona'    => $z['nombre'],
            'numero'  => $z['numero'],
            'activas' => $activas->get($id, 0),
        ])->values()->all();
    }

}; ?>

<div>

    {{-- Greeting --}}
    <div class="flex items-start justify-between mb-8 flex-wrap gap-4">
        <div>
            <h2 class="font-condensed font-bold text-2xl" style="color: var(--vd-text); letter-spacing: 0.5px;">
                Buenos días, {{ auth()->user()->name }} 🌿
            </h2>
            <p class="text-sm mt-1" style="color: var(--vd-muted);">
                Resumen de operaciones · {{ now()->translatedFormat('j \d\e F, Y') }}
            </p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('conversaciones') }}" wire:navigate class="btn-secondary text-xs px-4">Ver conversaciones</a>
            <a href="{{ route('ordenes') }}" wire:navigate class="btn-primary text-xs px-4">+ Nueva orden</a>
        </div>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">

        <div class="stat-card">
            <div class="flex items-start justify-between mb-3">
                <span class="stat-label">Chats hoy</span>
                <div class="stat-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value">{{ $conversacionesHoy }}</div>
            <div class="text-xs mt-2" style="color: var(--vd-muted);">conversaciones iniciadas</div>
        </div>

        <div class="stat-card">
            <div class="flex items-start justify-between mb-3">
                <span class="stat-label">Total activas</span>
                <div class="stat-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value" style="color: var(--vd-green-lt);">{{ $totalConversaciones }}</div>
            <div class="flex items-center gap-1 mt-2">
                <span class="trend-up">▲ activo</span>
                <span class="text-xs" style="color: var(--vd-muted);">en curso</span>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-start justify-between mb-3">
                <span class="stat-label">Clientes</span>
                <div class="stat-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-7 8-7s8 3 8 7"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value">{{ $totalClientes }}</div>
            <div class="text-xs mt-2" style="color: var(--vd-muted);">registrados en sistema</div>
        </div>

        <div class="stat-card">
            <div class="flex items-start justify-between mb-3">
                <span class="stat-label">Órdenes pend.</span>
                <div class="stat-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 2l1 4h11l1-4M5 6l2 14h10l2-14"/><circle cx="9" cy="20" r="1.5"/><circle cx="15" cy="20" r="1.5"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value" style="{{ $ordenesPendientes > 0 ? 'color: var(--vd-gold)' : '' }}">
                {{ $ordenesPendientes }}
            </div>
            <div class="flex items-center gap-1 mt-2">
                @if($ordenesPendientes > 0)
                    <span class="trend-down">● pendientes</span>
                @else
                    <span class="trend-up">✓ al día</span>
                @endif
            </div>
        </div>

    </div>

    {{-- Zones + Queue --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        {{-- Zones (2/3 width) --}}
        <div class="md:col-span-2 card">
            <div class="flex items-end justify-between mb-5" style="border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 14px;">
                <div>
                    <h3 class="font-condensed font-bold text-lg" style="color: var(--vd-text); letter-spacing: 0.5px;">Zonas activas</h3>
                    <p class="text-xs mt-0.5" style="color: var(--vd-muted);">Conversaciones por cobertura WhatsApp</p>
                </div>
                <a href="{{ route('zonas') }}" wire:navigate
                   class="text-xs font-condensed font-bold tracking-wide uppercase transition-colors"
                   style="color: var(--vd-green-lt);"
                   onmouseover="this.style.color='var(--vd-text)'" onmouseout="this.style.color='var(--vd-green-lt)'">
                    Ver todas →
                </a>
            </div>
            <div class="flex flex-col gap-3">
                @forelse($porZona as $z)
                <div class="zone-row">
                    <div class="flex items-center gap-3" style="color: var(--vd-text); font-size: 14px;">
                        <div class="zone-dot"></div>
                        <div>
                            <span class="font-semibold">{{ $z['zona'] }}</span>
                            <span class="ml-2 font-mono text-xs" style="color: var(--vd-muted);">+{{ $z['numero'] }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="stat-value text-base">{{ $z['activas'] }}</span>
                        <span class="badge-green">Activo</span>
                    </div>
                </div>
                @empty
                <p class="text-sm text-center py-6" style="color: var(--vd-muted);">Sin zonas configuradas.</p>
                @endforelse
            </div>
        </div>

        {{-- System status (1/3 width) --}}
        <div class="card flex flex-col gap-4">
            <div style="border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 14px;">
                <h3 class="font-condensed font-bold text-lg" style="color: var(--vd-text); letter-spacing: 0.5px;">Sistema</h3>
                <p class="text-xs mt-0.5" style="color: var(--vd-muted);">Estado en tiempo real</p>
            </div>

            <div class="flex flex-col gap-3">
                <div class="zone-row">
                    <span class="text-sm font-medium" style="color: var(--vd-text);">Cola Horizon</span>
                    <div class="flex items-center gap-2">
                        <span class="font-condensed font-bold text-base" style="color: var(--vd-text);">{{ $mensajesEnCola }}</span>
                        @if($mensajesEnCola === 0)
                            <span class="badge-green">OK</span>
                        @else
                            <span class="badge-yellow">Activa</span>
                        @endif
                    </div>
                </div>

                <div class="zone-row">
                    <span class="text-sm font-medium" style="color: var(--vd-text);">Evolution API</span>
                    <span class="badge-green">Activo</span>
                </div>

                <div class="zone-row">
                    <span class="text-sm font-medium" style="color: var(--vd-text);">n8n Workflows</span>
                    <span class="badge-green">Activo</span>
                </div>

                <div class="zone-row">
                    <span class="text-sm font-medium" style="color: var(--vd-text);">Ollama / IA</span>
                    <span class="badge-blue">Listo</span>
                </div>
            </div>

            <div class="mt-auto flex gap-2">
                <a href="/horizon" target="_blank" class="btn-secondary text-xs flex-1 justify-center">Horizon</a>
                <a href="{{ route('n8n') }}" target="_blank" class="btn-secondary text-xs flex-1 justify-center">n8n</a>
            </div>
        </div>

    </div>

</div>
