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
        $totalOrdenes = Orden::count();
        $ingresos     = Orden::where('estado', 'entregada')->sum('total');
        $ordenesPendientes = Orden::where('estado', 'pendiente')->count();
        $ordenesHoy   = Orden::whereDate('created_at', today())->count();

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
            ->with('producto:id,nombre,unidad')
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
            // ventas
            'totalOrdenes', 'ingresos', 'ordenesPendientes', 'ordenesHoy',
            'estadosOrdenes', 'ordenesPorZona', 'topProductos', 'ordenesUltimos7',
            // sitio
            'totalConv', 'activas', 'esperando', 'convHoy',
            'totalUsuarios', 'convPorZona', 'convUltimos7', 'porRol'
        );
    }

}; ?>

<div x-data="{ tab: 'ventas' }">

    {{-- Tab selector --}}
    <div class="flex gap-2 mb-8 border-b border-gray-200 pb-0">
        <button @click="tab = 'ventas'"
                :class="tab === 'ventas'
                    ? 'border-verdeo-600 text-verdeo-700 font-semibold'
                    : 'border-transparent text-gray-500 hover:text-gray-700'"
                class="pb-3 px-1 border-b-2 text-sm transition-colors -mb-px">
            Ventas
        </button>
        <button @click="tab = 'sitio'"
                :class="tab === 'sitio'
                    ? 'border-verdeo-600 text-verdeo-700 font-semibold'
                    : 'border-transparent text-gray-500 hover:text-gray-700'"
                class="pb-3 px-1 border-b-2 text-sm transition-colors -mb-px">
            Sitio y Operaciones
        </button>
    </div>

    {{-- ════════════════ VENTAS ════════════════ --}}
    <div x-show="tab === 'ventas'" x-transition class="space-y-8">

        {{-- Top cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="card text-center">
                <p class="text-3xl font-bold text-gray-900">{{ $totalOrdenes }}</p>
                <p class="text-sm text-gray-500 mt-1">Órdenes totales</p>
            </div>
            <div class="card text-center">
                <p class="text-3xl font-bold text-yellow-600">{{ $ordenesPendientes }}</p>
                <p class="text-sm text-gray-500 mt-1">Pendientes</p>
            </div>
            <div class="card text-center">
                <p class="text-3xl font-bold text-gray-700">{{ $ordenesHoy }}</p>
                <p class="text-sm text-gray-500 mt-1">Órdenes hoy</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-verdeo-700">${{ number_format($ingresos, 0, ',', '.') }}</p>
                <p class="text-sm text-gray-500 mt-1">Ingresos (entregadas)</p>
            </div>
        </div>

        {{-- Estado breakdown --}}
        <div class="card">
            <h3 class="font-semibold text-gray-800 mb-4">Órdenes por estado</h3>
            <div class="flex flex-wrap gap-4">
                @foreach(\App\Models\Orden::$estados as $val => $label)
                    @php $count = $estadosOrdenes[$val] ?? 0; @endphp
                    <div class="flex items-center gap-2">
                        <span class="{{ \App\Models\Orden::$estadoBadge[$val] }}">{{ $label }}</span>
                        <span class="font-bold text-gray-900">{{ $count }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            {{-- Órdenes por zona --}}
            <div class="card p-0 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="font-semibold text-gray-800">Órdenes por zona</h3>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Zona</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Órdenes</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Ingresos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($ordenesPorZona as $z)
                        <tr>
                            <td class="px-6 py-3 font-medium text-gray-700">{{ $z['zona'] }}</td>
                            <td class="px-6 py-3 text-right font-semibold text-gray-900">{{ $z['total'] }}</td>
                            <td class="px-6 py-3 text-right font-mono text-verdeo-700">${{ number_format($z['ingresos'], 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="px-6 py-6 text-center text-gray-400">Sin órdenes</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Top productos --}}
            <div class="card p-0 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="font-semibold text-gray-800">Top 5 productos</h3>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Producto</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Vendido</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Ingresos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($topProductos as $tp)
                        <tr>
                            <td class="px-6 py-3 font-medium text-gray-700">{{ $tp->producto?->nombre ?? '—' }}</td>
                            <td class="px-6 py-3 text-right text-gray-700">{{ number_format($tp->total_cantidad, 2, ',', '.') }} {{ $tp->producto?->unidad }}</td>
                            <td class="px-6 py-3 text-right font-mono text-verdeo-700">${{ number_format($tp->total_ingresos, 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="px-6 py-6 text-center text-gray-400">Sin ventas registradas</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>

        {{-- Actividad últimos 7 días --}}
        <div class="card p-0 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="font-semibold text-gray-800">Actividad — últimos 7 días</h3>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Día</th>
                        <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Órdenes</th>
                        <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Ingresos</th>
                        <th class="px-6 py-3 w-40"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php $maxOrd = $ordenesUltimos7->max('ordenes') ?: 1; @endphp
                    @foreach($ordenesUltimos7 as $d)
                    <tr>
                        <td class="px-6 py-3 text-gray-600">{{ $d['fecha'] }}</td>
                        <td class="px-6 py-3 text-right font-semibold text-gray-900">{{ $d['ordenes'] }}</td>
                        <td class="px-6 py-3 text-right font-mono text-verdeo-700">${{ number_format($d['ingresos'], 0, ',', '.') }}</td>
                        <td class="px-6 py-3">
                            <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full bg-verdeo-400 rounded-full"
                                     style="width: {{ $maxOrd > 0 ? round($d['ordenes'] / $maxOrd * 100) : 0 }}%">
                                </div>
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
                <p class="text-3xl font-bold text-gray-900">{{ $totalConv }}</p>
                <p class="text-sm text-gray-500 mt-1">Conversaciones totales</p>
            </div>
            <div class="card text-center">
                <p class="text-3xl font-bold text-verdeo-600">{{ $activas }}</p>
                <p class="text-sm text-gray-500 mt-1">Abiertas</p>
            </div>
            <div class="card text-center">
                <p class="text-3xl font-bold text-yellow-600">{{ $esperando }}</p>
                <p class="text-sm text-gray-500 mt-1">En espera</p>
            </div>
            <div class="card text-center">
                <p class="text-3xl font-bold text-gray-700">{{ $convHoy }}</p>
                <p class="text-sm text-gray-500 mt-1">Nuevas hoy</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            {{-- Conversaciones por zona --}}
            <div class="card p-0 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="font-semibold text-gray-800">Conversaciones por zona</h3>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Zona</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Total</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Abiertas</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Cerradas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($convPorZona as $z)
                        <tr>
                            <td class="px-6 py-3 font-medium text-gray-700">{{ $z['zona'] }}</td>
                            <td class="px-6 py-3 text-right font-semibold text-gray-900">{{ $z['total'] }}</td>
                            <td class="px-6 py-3 text-right"><span class="badge-green">{{ $z['activas'] }}</span></td>
                            <td class="px-6 py-3 text-right"><span class="badge-gray">{{ $z['cerradas'] }}</span></td>
                        </tr>
                        @endforeach
                        @if($convPorZona->isEmpty())
                        <tr><td colspan="4" class="px-6 py-6 text-center text-gray-400">Sin datos</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>

            {{-- Conversaciones últimos 7 días --}}
            <div class="card p-0 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="font-semibold text-gray-800">Conversaciones — últimos 7 días</h3>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Día</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Nuevas</th>
                            <th class="px-6 py-3 w-32"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @php $maxConv = $convUltimos7->max('total') ?: 1; @endphp
                        @foreach($convUltimos7 as $d)
                        <tr>
                            <td class="px-6 py-3 text-gray-600">{{ $d['fecha'] }}</td>
                            <td class="px-6 py-3 text-right font-semibold text-gray-900">{{ $d['total'] }}</td>
                            <td class="px-6 py-3">
                                <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                                    <div class="h-full bg-verdeo-400 rounded-full"
                                         style="width: {{ $maxConv > 0 ? round($d['total'] / $maxConv * 100) : 0 }}%">
                                    </div>
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
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800">Usuarios por tipo</h3>
                <span class="text-sm text-gray-500">{{ $totalUsuarios }} en total</span>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 divide-x divide-gray-100">
                @php
                    $rolColors = [
                        'Administrador' => 'text-verdeo-700',
                        'Resp. de Zona' => 'text-blue-700',
                        'Colaborador'   => 'text-gray-700',
                        'Cliente'       => 'text-yellow-700',
                    ];
                @endphp
                @foreach(\App\Models\User::rolesLabels() as $val => $label)
                    @php $count = $porRol->firstWhere('rol', $label)['total'] ?? 0; @endphp
                    <div class="px-6 py-5 text-center">
                        <p class="text-2xl font-bold {{ $rolColors[$label] ?? 'text-gray-700' }}">{{ $count }}</p>
                        <p class="text-sm text-gray-500 mt-1">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
        </div>

    </div>
</div>
