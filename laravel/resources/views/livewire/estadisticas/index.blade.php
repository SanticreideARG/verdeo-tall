<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Conversacion;
use App\Models\Orden;
use App\Models\OrdenItem;
use App\Models\User;

new #[Layout('layouts.app', ['title' => 'Estadísticas'])] class extends Component {

    public function with(): array
    {
        $zonaLabels = [
            'bsas'      => 'Buenos Aires',
            'valle_nqn' => 'Valle NQN / Roca',
            'cordoba'   => 'Córdoba',
            'mendoza'   => 'Mendoza',
        ];

        // ── Ventas ────────────────────────────────────────────────
        $totalOrdenes      = Orden::count();
        $ingresos          = Orden::where('estado', 'entregada')->sum('total');
        $ordenesPendientes = Orden::where('estado', 'pendiente')->count();
        $ordenesHoy        = Orden::whereDate('created_at', today())->count();

        $estadosOrdenes = Orden::selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $ordenesPorZona = Orden::selectRaw('zona, count(*) as total, sum(total) as ingresos')
            ->groupBy('zona')
            ->orderByDesc('total')
            ->get()
            ->map(fn($r) => [
                'zona'     => $zonaLabels[$r->zona] ?? $r->zona,
                'total'    => $r->total,
                'ingresos' => (float) $r->ingresos,
            ]);

        $topProductos = OrdenItem::selectRaw('producto_id, sum(cantidad) as total_cantidad, sum(subtotal) as total_ingresos')
            ->with('producto:id,nombre')
            ->groupBy('producto_id')
            ->orderByDesc('total_ingresos')
            ->limit(5)
            ->get();

        $ordenesUltimos7 = collect(range(6, 0))->map(function ($days) {
            $date  = now()->subDays($days)->format('Y-m-d');
            $label = now()->subDays($days)->isoFormat('ddd D/M');
            return [
                'fecha'    => $label,
                'ordenes'  => Orden::whereDate('created_at', $date)->count(),
                'ingresos' => (float) Orden::whereDate('created_at', $date)->sum('total'),
            ];
        });

        // ── Sitio ─────────────────────────────────────────────────
        $totalConv   = Conversacion::count();
        $activas     = Conversacion::activas()->count();
        $esperando   = Conversacion::where('estado', 'esperando')->count();
        $convHoy     = Conversacion::hoy()->count();
        $totalUsuarios = User::count();

        $convPorZona = Conversacion::selectRaw('zona, count(*) as total, SUM(estado = "abierta") as activas, SUM(estado = "cerrada") as cerradas')
            ->groupBy('zona')
            ->orderByDesc('total')
            ->get()
            ->map(fn($r) => [
                'zona'     => $zonaLabels[$r->zona] ?? $r->zona,
                'total'    => $r->total,
                'activas'  => (int) $r->activas,
                'cerradas' => (int) $r->cerradas,
            ]);

        $convUltimos7 = collect(range(6, 0))->map(function ($days) {
            $date  = now()->subDays($days)->format('Y-m-d');
            $label = now()->subDays($days)->isoFormat('ddd D/M');
            return ['fecha' => $label, 'total' => Conversacion::whereDate('created_at', $date)->count()];
        });

        $porRol = User::selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->orderByDesc('total')
            ->get()
            ->map(fn($r) => [
                'rol'   => User::rolesLabels()[$r->role] ?? $r->role,
                'total' => $r->total,
            ]);

        return compact(
            'totalOrdenes', 'ingresos', 'ordenesPendientes', 'ordenesHoy',
            'estadosOrdenes', 'ordenesPorZona', 'topProductos', 'ordenesUltimos7',
            'totalConv', 'activas', 'esperando', 'convHoy',
            'totalUsuarios', 'convPorZona', 'convUltimos7', 'porRol'
        );
    }

}; ?>

<div x-data="{ tab: 'ventas' }">

    {{-- Tab selector --}}
    <div class="flex gap-2 mb-8 pb-0" style="border-bottom: 1px solid var(--vd-bdr);">
        <button @click="tab = 'ventas'"
                :style="tab === 'ventas'
                    ? 'border-color: var(--vd-green-lt); color: var(--vd-green-lt); font-weight: 600;'
                    : 'border-color: transparent; color: var(--vd-muted-2);'"
                class="pb-3 px-1 border-b-2 text-sm transition-colors -mb-px">
            Ventas
        </button>
        <button @click="tab = 'sitio'"
                :style="tab === 'sitio'
                    ? 'border-color: var(--vd-green-lt); color: var(--vd-green-lt); font-weight: 600;'
                    : 'border-color: transparent; color: var(--vd-muted-2);'"
                class="pb-3 px-1 border-b-2 text-sm transition-colors -mb-px">
            Sitio y Operaciones
        </button>
    </div>

    {{-- ════════════════ VENTAS ════════════════ --}}
    <div x-show="tab === 'ventas'" x-transition class="space-y-8">

        {{-- Top cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="card text-center">
                <p class="text-3xl font-bold" style="color: var(--vd-text);">{{ $totalOrdenes }}</p>
                <p class="text-sm mt-1" style="color: var(--vd-muted-2);">Órdenes totales</p>
            </div>
            <div class="card text-center">
                <p class="text-3xl font-bold" style="color: #f59e0b;">{{ $ordenesPendientes }}</p>
                <p class="text-sm mt-1" style="color: var(--vd-muted-2);">Pendientes</p>
            </div>
            <div class="card text-center">
                <p class="text-3xl font-bold" style="color: var(--vd-muted);">{{ $ordenesHoy }}</p>
                <p class="text-sm mt-1" style="color: var(--vd-muted-2);">Órdenes hoy</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold" style="color: var(--vd-green-lt);">${{ number_format($ingresos, 0, ',', '.') }}</p>
                <p class="text-sm mt-1" style="color: var(--vd-muted-2);">Ingresos (entregadas)</p>
            </div>
        </div>

        {{-- Estado breakdown --}}
        <div class="card">
            <h3 class="font-semibold mb-4" style="color: var(--vd-text);">Órdenes por estado</h3>
            <div class="flex flex-wrap gap-4">
                @foreach(\App\Models\Orden::$estados as $val => $label)
                    @php $count = $estadosOrdenes[$val] ?? 0; @endphp
                    <div class="flex items-center gap-2">
                        <span class="{{ \App\Models\Orden::$estadoBadge[$val] }}">{{ $label }}</span>
                        <span class="font-bold" style="color: var(--vd-text);">{{ $count }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            {{-- Órdenes por zona --}}
            <div class="card p-0 overflow-hidden">
                <div class="px-6 py-4" style="border-bottom: 1px solid var(--vd-bdr);">
                    <h3 class="font-semibold" style="color: var(--vd-text);">Órdenes por zona</h3>
                </div>
                <table class="w-full text-sm">
                    <thead style="background: var(--vd-bg-2);">
                        <tr>
                            <th class="text-left px-6 py-3 text-xs font-semibold uppercase"
                                style="color: var(--vd-muted-2);">Zona</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold uppercase"
                                style="color: var(--vd-muted-2);">Órdenes</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold uppercase"
                                style="color: var(--vd-muted-2);">Ingresos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ordenesPorZona as $z)
                        <tr style="border-bottom: 1px solid var(--vd-bdr-soft);">
                            <td class="px-6 py-3 font-medium" style="color: var(--vd-muted);">{{ $z['zona'] }}</td>
                            <td class="px-6 py-3 text-right font-semibold" style="color: var(--vd-text);">{{ $z['total'] }}</td>
                            <td class="px-6 py-3 text-right font-mono" style="color: var(--vd-green-lt);">${{ number_format($z['ingresos'], 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="px-6 py-6 text-center" style="color: var(--vd-muted-2);">Sin órdenes</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Top productos --}}
            <div class="card p-0 overflow-hidden">
                <div class="px-6 py-4" style="border-bottom: 1px solid var(--vd-bdr);">
                    <h3 class="font-semibold" style="color: var(--vd-text);">Top 5 menús</h3>
                </div>
                <table class="w-full text-sm">
                    <thead style="background: var(--vd-bg-2);">
                        <tr>
                            <th class="text-left px-6 py-3 text-xs font-semibold uppercase"
                                style="color: var(--vd-muted-2);">Menú</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold uppercase"
                                style="color: var(--vd-muted-2);">Vendido</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold uppercase"
                                style="color: var(--vd-muted-2);">Ingresos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topProductos as $tp)
                        <tr style="border-bottom: 1px solid var(--vd-bdr-soft);">
                            <td class="px-6 py-3 font-medium" style="color: var(--vd-muted);">{{ $tp->producto?->nombre ?? '—' }}</td>
                            <td class="px-6 py-3 text-right" style="color: var(--vd-muted);">{{ number_format($tp->total_cantidad, 2, ',', '.') }}</td>
                            <td class="px-6 py-3 text-right font-mono" style="color: var(--vd-green-lt);">${{ number_format($tp->total_ingresos, 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="px-6 py-6 text-center" style="color: var(--vd-muted-2);">Sin ventas registradas</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>

        {{-- Actividad últimos 7 días --}}
        <div class="card p-0 overflow-hidden">
            <div class="px-6 py-4" style="border-bottom: 1px solid var(--vd-bdr);">
                <h3 class="font-semibold" style="color: var(--vd-text);">Actividad — últimos 7 días</h3>
            </div>
            <table class="w-full text-sm">
                <thead style="background: var(--vd-bg-2);">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-semibold uppercase"
                            style="color: var(--vd-muted-2);">Día</th>
                        <th class="text-right px-6 py-3 text-xs font-semibold uppercase"
                            style="color: var(--vd-muted-2);">Órdenes</th>
                        <th class="text-right px-6 py-3 text-xs font-semibold uppercase"
                            style="color: var(--vd-muted-2);">Ingresos</th>
                        <th class="px-6 py-3 w-40"></th>
                    </tr>
                </thead>
                <tbody>
                    @php $maxOrd = $ordenesUltimos7->max('ordenes') ?: 1; @endphp
                    @foreach($ordenesUltimos7 as $d)
                    <tr style="border-bottom: 1px solid var(--vd-bdr-soft);">
                        <td class="px-6 py-3" style="color: var(--vd-muted);">{{ $d['fecha'] }}</td>
                        <td class="px-6 py-3 text-right font-semibold" style="color: var(--vd-text);">{{ $d['ordenes'] }}</td>
                        <td class="px-6 py-3 text-right font-mono" style="color: var(--vd-green-lt);">${{ number_format($d['ingresos'], 0, ',', '.') }}</td>
                        <td class="px-6 py-3">
                            <div class="h-2 rounded-full overflow-hidden" style="background: var(--vd-bg-2);">
                                <div class="h-full rounded-full" style="width: {{ $maxOrd > 0 ? round($d['ordenes'] / $maxOrd * 100) : 0 }}%; background: var(--vd-green-lt);"></div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>

    {{-- ════════════════ SITIO ════════════════ --}}
    <div x-show="tab === 'sitio'" x-transition x-cloak class="space-y-8">

        {{-- Top cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="card text-center">
                <p class="text-3xl font-bold" style="color: var(--vd-text);">{{ $totalConv }}</p>
                <p class="text-sm mt-1" style="color: var(--vd-muted-2);">Conversaciones totales</p>
            </div>
            <div class="card text-center">
                <p class="text-3xl font-bold" style="color: var(--vd-green-lt);">{{ $activas }}</p>
                <p class="text-sm mt-1" style="color: var(--vd-muted-2);">Abiertas</p>
            </div>
            <div class="card text-center">
                <p class="text-3xl font-bold" style="color: #f59e0b;">{{ $esperando }}</p>
                <p class="text-sm mt-1" style="color: var(--vd-muted-2);">En espera</p>
            </div>
            <div class="card text-center">
                <p class="text-3xl font-bold" style="color: var(--vd-muted);">{{ $convHoy }}</p>
                <p class="text-sm mt-1" style="color: var(--vd-muted-2);">Nuevas hoy</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            {{-- Conversaciones por zona --}}
            <div class="card p-0 overflow-hidden">
                <div class="px-6 py-4" style="border-bottom: 1px solid var(--vd-bdr);">
                    <h3 class="font-semibold" style="color: var(--vd-text);">Conversaciones por zona</h3>
                </div>
                <table class="w-full text-sm">
                    <thead style="background: var(--vd-bg-2);">
                        <tr>
                            <th class="text-left px-6 py-3 text-xs font-semibold uppercase"
                                style="color: var(--vd-muted-2);">Zona</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold uppercase"
                                style="color: var(--vd-muted-2);">Total</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold uppercase"
                                style="color: var(--vd-muted-2);">Abiertas</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold uppercase"
                                style="color: var(--vd-muted-2);">Cerradas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($convPorZona as $z)
                        <tr style="border-bottom: 1px solid var(--vd-bdr-soft);">
                            <td class="px-6 py-3 font-medium" style="color: var(--vd-muted);">{{ $z['zona'] }}</td>
                            <td class="px-6 py-3 text-right font-semibold" style="color: var(--vd-text);">{{ $z['total'] }}</td>
                            <td class="px-6 py-3 text-right"><span class="badge-green">{{ $z['activas'] }}</span></td>
                            <td class="px-6 py-3 text-right"><span class="badge-gray">{{ $z['cerradas'] }}</span></td>
                        </tr>
                        @endforeach
                        @if($convPorZona->isEmpty())
                        <tr><td colspan="4" class="px-6 py-6 text-center" style="color: var(--vd-muted-2);">Sin datos</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>

            {{-- Conversaciones últimos 7 días --}}
            <div class="card p-0 overflow-hidden">
                <div class="px-6 py-4" style="border-bottom: 1px solid var(--vd-bdr);">
                    <h3 class="font-semibold" style="color: var(--vd-text);">Conversaciones — últimos 7 días</h3>
                </div>
                <table class="w-full text-sm">
                    <thead style="background: var(--vd-bg-2);">
                        <tr>
                            <th class="text-left px-6 py-3 text-xs font-semibold uppercase"
                                style="color: var(--vd-muted-2);">Día</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold uppercase"
                                style="color: var(--vd-muted-2);">Nuevas</th>
                            <th class="px-6 py-3 w-32"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $maxConv = $convUltimos7->max('total') ?: 1; @endphp
                        @foreach($convUltimos7 as $d)
                        <tr style="border-bottom: 1px solid var(--vd-bdr-soft);">
                            <td class="px-6 py-3" style="color: var(--vd-muted);">{{ $d['fecha'] }}</td>
                            <td class="px-6 py-3 text-right font-semibold" style="color: var(--vd-text);">{{ $d['total'] }}</td>
                            <td class="px-6 py-3">
                                <div class="h-2 rounded-full overflow-hidden" style="background: var(--vd-bg-2);">
                                    <div class="h-full rounded-full"
                                         style="width: {{ $maxConv > 0 ? round($d['total'] / $maxConv * 100) : 0 }}%; background: var(--vd-green-lt);"></div>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        </div>

        {{-- Usuarios por tipo --}}
        <div class="card p-0 overflow-hidden">
            <div class="px-6 py-4 flex items-center justify-between" style="border-bottom: 1px solid var(--vd-bdr);">
                <h3 class="font-semibold" style="color: var(--vd-text);">Usuarios por tipo</h3>
                <span class="text-sm" style="color: var(--vd-muted-2);">{{ $totalUsuarios }} en total</span>
            </div>
            @php
                $rolColors = [
                    'Administrador' => 'var(--vd-green-lt)',
                    'Resp. de Zona' => '#60a5fa',
                    'Colaborador'   => 'var(--vd-muted)',
                    'Cliente'       => '#f59e0b',
                ];
            @endphp
            <div class="grid grid-cols-2 md:grid-cols-4" style="border-top: 1px solid var(--vd-bdr-soft);">
                @foreach(\App\Models\User::rolesLabels() as $val => $label)
                    @php $count = $porRol->firstWhere('rol', $label)['total'] ?? 0; @endphp
                    <div class="px-6 py-5 text-center" style="border-right: 1px solid var(--vd-bdr-soft);">
                        <p class="text-2xl font-bold" style="color: {{ $rolColors[$label] ?? 'var(--vd-text)' }};">{{ $count }}</p>
                        <p class="text-sm mt-1" style="color: var(--vd-muted-2);">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
        </div>

    </div>
</div>
