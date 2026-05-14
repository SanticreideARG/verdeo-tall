<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\User;
use App\Models\Orden;

new #[Layout('layouts.app', ['title' => 'CRM — Clientes'])] class extends Component {

    public function with(): array
    {
        $clientes = User::where('role', 'cliente');

        $porZona = User::where('role', 'cliente')
            ->whereNotNull('zona')
            ->selectRaw('zona, COUNT(*) as total')
            ->groupBy('zona')
            ->orderByDesc('total')
            ->get();

        $recientes = User::where('role', 'cliente')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        $sinZona = User::where('role', 'cliente')->whereNull('zona')->count();

        return [
            'totalClientes'    => (clone $clientes)->count(),
            'conWhatsapp'      => (clone $clientes)->whereNotNull('whatsapp')->count(),
            'conEmail'         => (clone $clientes)->whereNotNull('email')->count(),
            'esteMes'          => (clone $clientes)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
            'porZona'          => $porZona,
            'sinZona'          => $sinZona,
            'recientes'        => $recientes,
        ];
    }

}; ?>

<div class="space-y-6">

    {{-- KPI cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card">
            <p class="text-xs font-condensed uppercase tracking-wide mb-2" style="color: var(--vd-muted-2);">Total clientes</p>
            <p class="font-condensed font-bold text-3xl" style="color: var(--vd-text);">{{ $totalClientes }}</p>
        </div>
        <div class="card">
            <p class="text-xs font-condensed uppercase tracking-wide mb-2" style="color: var(--vd-muted-2);">Nuevos este mes</p>
            <p class="font-condensed font-bold text-3xl" style="color: #4e9e5a;">{{ $esteMes }}</p>
        </div>
        <div class="card">
            <p class="text-xs font-condensed uppercase tracking-wide mb-2" style="color: var(--vd-muted-2);">Con WhatsApp</p>
            <p class="font-condensed font-bold text-3xl" style="color: var(--vd-text);">{{ $conWhatsapp }}</p>
            @if($totalClientes > 0)
            <p class="text-xs mt-1" style="color: var(--vd-muted);">{{ round($conWhatsapp / $totalClientes * 100) }}% del total</p>
            @endif
        </div>
        <div class="card">
            <p class="text-xs font-condensed uppercase tracking-wide mb-2" style="color: var(--vd-muted-2);">Con cuenta web</p>
            <p class="font-condensed font-bold text-3xl" style="color: #a78bfa;">{{ $conEmail }}</p>
            @if($totalClientes > 0)
            <p class="text-xs mt-1" style="color: var(--vd-muted);">{{ round($conEmail / $totalClientes * 100) }}% del total</p>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Distribución por zona --}}
        <div class="card">
            <h3 class="font-condensed font-bold text-sm uppercase tracking-wide mb-5"
                style="color: var(--vd-muted-2); letter-spacing: 1px; border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 10px;">
                Distribución por zona
            </h3>
            @forelse($porZona as $z)
            @php
                $label = match($z->zona) {
                    'bsas'      => 'BSAS',
                    'valle_nqn' => 'Valle NQN / Roca',
                    'cordoba'   => 'Córdoba',
                    'mendoza'   => 'Mendoza',
                    default     => $z->zona,
                };
                $pct = $totalClientes > 0 ? round($z->total / $totalClientes * 100) : 0;
            @endphp
            <div class="mb-4">
                <div class="flex justify-between items-center mb-1">
                    <span class="text-sm" style="color: var(--vd-text-soft);">{{ $label }}</span>
                    <span class="text-sm font-bold font-mono" style="color: var(--vd-text);">{{ $z->total }}</span>
                </div>
                <div class="h-1.5 rounded-full" style="background: var(--vd-input-bg);">
                    <div class="h-1.5 rounded-full" style="width: {{ $pct }}%; background: linear-gradient(90deg, #3a7d44, #4e9e5a);"></div>
                </div>
            </div>
            @empty
            <p class="text-sm" style="color: var(--vd-muted);">Sin datos de zona aún.</p>
            @endforelse
            @if($sinZona > 0)
            <div class="mt-3 pt-3" style="border-top: 1px solid var(--vd-bdr-soft);">
                <div class="flex justify-between items-center">
                    <span class="text-xs" style="color: var(--vd-muted-2);">Sin zona asignada</span>
                    <span class="text-xs font-mono" style="color: var(--vd-muted-2);">{{ $sinZona }}</span>
                </div>
            </div>
            @endif
        </div>

        {{-- Registros recientes --}}
        <div class="card lg:col-span-2">
            <div class="flex items-center justify-between mb-5" style="border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 10px;">
                <h3 class="font-condensed font-bold text-sm uppercase tracking-wide" style="color: var(--vd-muted-2); letter-spacing: 1px;">
                    Últimos registros
                </h3>
                <a href="{{ route('clientes') }}" wire:navigate
                   class="text-xs" style="color: var(--vd-green-lt);">Ver todos →</a>
            </div>
            <div class="space-y-3">
                @forelse($recientes as $c)
                <div class="flex items-center gap-3 cursor-pointer py-1 px-2 rounded-lg -mx-2 transition-colors"
                     onclick="window.location.href='{{ route('usuarios.ver', $c) }}'"
                     onmouseover="this.style.background='var(--vd-nav-hover)'"
                     onmouseout="this.style.background=''">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-white flex-shrink-0"
                         style="background: linear-gradient(135deg, #a07820, #c8a030); font-size: 13px;">
                        {{ strtoupper(substr($c->name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold truncate" style="color: var(--vd-text);">{{ $c->nombreCompleto() }}</p>
                        <p class="text-xs" style="color: var(--vd-muted);">{{ $c->whatsapp ?: 'Sin WA' }} · {{ $c->ciudad ?: 'Sin ciudad' }}</p>
                    </div>
                    @if($c->numero_cliente)
                    <span class="font-mono text-xs font-bold flex-shrink-0" style="color: #c8a030;">
                        #{{ str_pad($c->numero_cliente, 4, '0', STR_PAD_LEFT) }}
                    </span>
                    @endif
                    <span class="text-xs flex-shrink-0" style="color: var(--vd-muted-2);">{{ $c->created_at->diffForHumans() }}</span>
                </div>
                @empty
                <p class="text-sm" style="color: var(--vd-muted);">Sin clientes registrados aún.</p>
                @endforelse
            </div>
        </div>

    </div>

    {{-- Próximamente --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        @foreach([
            ['Historial de pedidos', 'Frecuencia de compra y últimos pedidos por cliente.'],
            ['Valor de cliente (LTV)', 'Ticket promedio y acumulado por cliente.'],
            ['Retención y abandono', 'Clientes activos, en riesgo y perdidos.'],
        ] as [$titulo, $desc])
        <div class="card flex flex-col items-center justify-center text-center py-10 gap-2"
             style="border-style: dashed; opacity: 0.6;">
            <p class="font-condensed font-bold text-sm" style="color: var(--vd-text);">{{ $titulo }}</p>
            <p class="text-xs" style="color: var(--vd-muted);">{{ $desc }}</p>
            <span class="text-xs mt-1 px-2 py-0.5 rounded-full" style="background: var(--vd-input-bg); color: var(--vd-muted-2);">Próximamente</span>
        </div>
        @endforeach
    </div>

</div>
