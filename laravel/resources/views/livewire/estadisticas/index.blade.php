<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Conversacion;
use App\Models\Orden;
use App\Models\OrdenItem;
use App\Models\User;
use App\Models\Zona;
use Carbon\Carbon;

new #[Layout('layouts.app', ['title' => 'Estadísticas'])] class extends Component {

    public string $periodo = '7d';

    protected function rango(): array
    {
        return match($this->periodo) {
            '30d'        => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
            'mes_actual' => [now()->startOfMonth(),            now()->endOfDay()],
            default      => [now()->subDays(6)->startOfDay(),  now()->endOfDay()],
        };
    }

    protected function rangoAnterior(Carbon $desde, Carbon $hasta): array
    {
        $dias = (int) $desde->diffInDays($hasta) + 1;
        return [$desde->copy()->subDays($dias), $hasta->copy()->subDays($dias)];
    }

    protected function pct(int|float $now, int|float $prev): int
    {
        if ($prev == 0) return $now > 0 ? 100 : 0;
        return (int) round(($now - $prev) / $prev * 100);
    }

    public function with(): array
    {
        [$desde, $hasta]       = $this->rango();
        [$desdeAnt, $hastaAnt] = $this->rangoAnterior($desde, $hasta);

        $zonaLabel = Zona::orderBy('nombre')->get()->mapWithKeys(fn($z) => [$z->slug => $z->nombre]);
        $label     = fn($slug) => $zonaLabel[$slug] ?? $slug;

        /* ── Ventas ─────────────────────────────────────────────────── */
        $totalOrdenes    = Orden::whereBetween('created_at', [$desde, $hasta])->count();
        $totalOrdenesAnt = Orden::whereBetween('created_at', [$desdeAnt, $hastaAnt])->count();
        $pctOrdenes      = $this->pct($totalOrdenes, $totalOrdenesAnt);

        $ingresos    = (float) Orden::where('estado', 'entregada')->whereBetween('created_at', [$desde, $hasta])->sum('total');
        $ingresosAnt = (float) Orden::where('estado', 'entregada')->whereBetween('created_at', [$desdeAnt, $hastaAnt])->sum('total');
        $pctIngresos = $this->pct($ingresos, $ingresosAnt);

        $ticketPromedio    = $totalOrdenes > 0
            ? (float) Orden::whereBetween('created_at', [$desde, $hasta])->avg('total')
            : 0.0;
        $ticketPromedioAnt = (float) (Orden::whereBetween('created_at', [$desdeAnt, $hastaAnt])->avg('total') ?? 0);
        $pctTicket         = $this->pct($ticketPromedio, $ticketPromedioAnt);

        $ordenesPendientes = Orden::where('estado', 'pendiente')->count();
        $estadosOrdenes    = Orden::selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');

        $ordenesPorZona = Orden::selectRaw('zona, count(*) as total, sum(total) as ingresos')
            ->whereBetween('created_at', [$desde, $hasta])
            ->groupBy('zona')->orderByDesc('total')->get()
            ->map(fn($r) => ['zona' => $label($r->zona), 'total' => $r->total, 'ingresos' => (float) $r->ingresos]);

        $topProductos = OrdenItem::selectRaw('producto_id, sum(cantidad) as total_cantidad, sum(subtotal) as total_ingresos')
            ->with('producto:id,nombre')
            ->whereHas('orden', fn($q) => $q->whereBetween('created_at', [$desde, $hasta]))
            ->groupBy('producto_id')->orderByDesc('total_ingresos')->limit(5)->get();

        /* ── Chart data (one point per day in range) ────────────────── */
        $dias      = max(1, (int) $desde->diffInDays($hasta)) + 1;
        $chartData = collect(range(0, $dias - 1))->map(function ($i) use ($desde) {
            $d = $desde->copy()->addDays($i)->format('Y-m-d');
            return [
                'fecha'    => Carbon::parse($d)->isoFormat('D/M'),
                'ordenes'  => Orden::whereDate('created_at', $d)->count(),
                'ingresos' => (float) Orden::whereDate('created_at', $d)->sum('total'),
                'conv'     => Conversacion::whereDate('created_at', $d)->count(),
            ];
        })->values()->toArray();

        /* ── Sitio ──────────────────────────────────────────────────── */
        $totalConv    = Conversacion::whereBetween('created_at', [$desde, $hasta])->count();
        $totalConvAnt = Conversacion::whereBetween('created_at', [$desdeAnt, $hastaAnt])->count();
        $pctConv      = $this->pct($totalConv, $totalConvAnt);

        $activas       = Conversacion::activas()->count();
        $esperando     = Conversacion::where('estado', 'esperando')->count();
        $convHoy       = Conversacion::hoy()->count();
        $totalUsuarios = User::count();

        /* ── Embudo ─────────────────────────────────────────────────── */
        $clientesConOrden = Orden::whereBetween('created_at', [$desde, $hasta])
            ->distinct('user_id')->count('user_id');
        $tasaConv = $totalConv > 0 ? round($clientesConOrden / $totalConv * 100, 1) : 0;

        $convPorZona = Conversacion::selectRaw('zona, count(*) as total, SUM(estado = "abierta") as activas, SUM(estado = "cerrada") as cerradas')
            ->whereBetween('created_at', [$desde, $hasta])
            ->groupBy('zona')->orderByDesc('total')->get()
            ->map(fn($r) => ['zona' => $label($r->zona), 'total' => $r->total, 'activas' => (int) $r->activas, 'cerradas' => (int) $r->cerradas]);

        $porRol = User::selectRaw('role, count(*) as total')->groupBy('role')->orderByDesc('total')->get()
            ->map(fn($r) => ['rol' => User::rolesLabels()[$r->role] ?? $r->role, 'total' => $r->total]);

        $this->dispatch('charts-refresh', chartData: $chartData);

        return compact(
            'totalOrdenes', 'totalOrdenesAnt', 'pctOrdenes',
            'ingresos', 'ingresosAnt', 'pctIngresos',
            'ticketPromedio', 'ticketPromedioAnt', 'pctTicket',
            'ordenesPendientes', 'estadosOrdenes', 'ordenesPorZona', 'topProductos',
            'chartData',
            'totalConv', 'totalConvAnt', 'pctConv',
            'activas', 'esperando', 'convHoy', 'totalUsuarios',
            'clientesConOrden', 'tasaConv',
            'convPorZona', 'porRol',
            'desde', 'hasta'
        );
    }

    public function exportarCsv(): mixed
    {
        [$desde, $hasta] = $this->rango();
        $ordenes = Orden::whereBetween('created_at', [$desde, $hasta])
            ->with('cliente:id,name,apellido')
            ->orderByDesc('created_at')
            ->get();

        return response()->streamDownload(function () use ($ordenes) {
            $h = fopen('php://output', 'w');
            fputcsv($h, ['ID', 'Fecha', 'Cliente', 'Zona', 'Estado', 'Total']);
            foreach ($ordenes as $o) {
                fputcsv($h, [
                    $o->id,
                    $o->created_at->format('Y-m-d H:i'),
                    $o->cliente?->name ?? '—',
                    $o->zona,
                    $o->estado,
                    $o->total,
                ]);
            }
            fclose($h);
        }, 'ordenes-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

}; ?>

<div x-data="{ tab: 'ventas' }">

    {{-- ── Header ── --}}
    <div class="flex flex-wrap items-end justify-between gap-4 mb-8">
        <div>
            <h2 class="font-condensed font-bold text-2xl" style="color: var(--vd-text); letter-spacing: 0.5px;">Estadísticas</h2>
            <p class="text-sm mt-1" style="color: var(--vd-muted);">
                {{ $desde->isoFormat('D MMM') }} – {{ $hasta->isoFormat('D MMM YYYY') }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex rounded-xl overflow-hidden" style="border: 1px solid var(--vd-bdr-soft);">
                @foreach(['7d' => '7 días', '30d' => '30 días', 'mes_actual' => 'Este mes'] as $val => $lbl)
                <button wire:click="$set('periodo', '{{ $val }}')"
                        class="px-4 py-2 text-xs font-semibold transition-all"
                        style="{{ $periodo === $val
                            ? 'background: rgba(58,125,68,0.25); color: var(--vd-green-lt);'
                            : 'color: var(--vd-muted);' }}">
                    {{ $lbl }}
                </button>
                @endforeach
            </div>
            <button wire:click="exportarCsv" class="btn-secondary text-xs flex items-center gap-2"
                    wire:loading.attr="disabled" wire:target="exportarCsv">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                </svg>
                <span wire:loading.remove wire:target="exportarCsv">Exportar CSV</span>
                <span wire:loading wire:target="exportarCsv">Exportando…</span>
            </button>
        </div>
    </div>

    {{-- ── Tabs ── --}}
    <div class="flex gap-2 mb-8" style="border-bottom: 1px solid var(--vd-bdr);">
        <button @click="tab = 'ventas'"
                :style="tab === 'ventas' ? 'border-color: var(--vd-green-lt); color: var(--vd-green-lt); font-weight: 600;' : 'border-color: transparent; color: var(--vd-muted-2);'"
                class="pb-3 px-1 border-b-2 text-sm transition-colors -mb-px">Ventas</button>
        <button @click="tab = 'sitio'"
                :style="tab === 'sitio' ? 'border-color: var(--vd-green-lt); color: var(--vd-green-lt); font-weight: 600;' : 'border-color: transparent; color: var(--vd-muted-2);'"
                class="pb-3 px-1 border-b-2 text-sm transition-colors -mb-px">Sitio y Operaciones</button>
    </div>

    {{-- ════════════════ VENTAS ════════════════ --}}
    <div x-show="tab === 'ventas'" x-transition class="space-y-8">

        {{-- Stat cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @php
            $statCards = [
                ['label' => 'Órdenes',              'val' => $totalOrdenes,   'color' => 'var(--vd-text)',      'pct' => $pctOrdenes,  'fmt' => 'num'],
                ['label' => 'Ingresos (entregadas)','val' => $ingresos,       'color' => 'var(--vd-green-lt)',  'pct' => $pctIngresos, 'fmt' => 'money'],
                ['label' => 'Ticket promedio',       'val' => $ticketPromedio, 'color' => 'var(--vd-green-lt)', 'pct' => $pctTicket,   'fmt' => 'money'],
                ['label' => 'Pendientes (ahora)',    'val' => $ordenesPendientes, 'color' => '#f59e0b',         'pct' => null,         'fmt' => 'num'],
            ];
            @endphp
            @foreach($statCards as $c)
            <div class="card text-center">
                <p class="text-2xl font-bold font-condensed tabular-nums" style="color: {{ $c['color'] }};">
                    @if($c['fmt'] === 'money')${{ number_format($c['val'], 0, ',', '.') }}@else{{ $c['val'] }}@endif
                </p>
                <p class="text-xs mt-1" style="color: var(--vd-muted-2);">{{ $c['label'] }}</p>
                @if($c['pct'] !== null)
                <p class="text-xs mt-2 font-semibold"
                   style="color: {{ $c['pct'] >= 0 ? 'var(--vd-green-lt)' : '#f87171' }};">
                    {{ $c['pct'] >= 0 ? '↑' : '↓' }} {{ abs($c['pct']) }}%
                    <span style="color: var(--vd-muted-2); font-weight: 400;">vs ant.</span>
                </p>
                @endif
            </div>
            @endforeach
        </div>

        {{-- Chart: actividad del período --}}
        <div class="card p-0 overflow-hidden"
             x-data="{
                chart: null,
                mkChart(el, data) {
                    if (this.chart) this.chart.destroy();
                    this.chart = new Chart(el, {
                        type: 'line',
                        data: {
                            labels: data.map(d => d.fecha),
                            datasets: [
                                {
                                    label: 'Órdenes',
                                    data: data.map(d => d.ordenes),
                                    borderColor: '#4e9e5a',
                                    backgroundColor: 'rgba(78,158,90,0.12)',
                                    tension: 0.4, fill: true, pointRadius: 3,
                                    borderWidth: 2, yAxisID: 'y'
                                },
                                {
                                    label: 'Ingresos ($)',
                                    data: data.map(d => d.ingresos),
                                    borderColor: '#c8a030',
                                    backgroundColor: 'rgba(200,160,48,0.06)',
                                    tension: 0.4, fill: false, pointRadius: 3,
                                    borderWidth: 1.5, yAxisID: 'y2',
                                    borderDash: [4,3]
                                }
                            ]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { labels: { color: 'rgba(240,244,240,0.55)', font: { size: 11 }, boxWidth: 12 } },
                                tooltip: { backgroundColor: 'rgba(11,24,40,0.95)', borderColor: 'rgba(58,125,68,0.35)', borderWidth: 1 }
                            },
                            scales: {
                                x:  { ticks: { color: 'rgba(240,244,240,0.4)', font: { size: 10 } }, grid: { color: 'rgba(255,255,255,0.05)' } },
                                y:  { ticks: { color: 'rgba(240,244,240,0.4)', font: { size: 10 } }, grid: { color: 'rgba(255,255,255,0.05)' }, position: 'left' },
                                y2: { ticks: { color: 'rgba(200,160,48,0.55)', font: { size: 10 }, callback: v => '$'+v.toLocaleString('es-AR') }, grid: { display: false }, position: 'right' }
                            }
                        }
                    });
                }
             }"
             x-init="$nextTick(() => mkChart($refs.canvasV, {{ json_encode($chartData) }}))"
             @charts-refresh.window="mkChart($refs.canvasV, $event.detail.chartData)">
            <div class="px-6 py-4 flex items-center justify-between" style="border-bottom: 1px solid var(--vd-bdr);">
                <h3 class="font-semibold text-sm" style="color: var(--vd-text);">Actividad del período</h3>
                <span class="text-xs" style="color: var(--vd-muted);">
                    {{ $desde->isoFormat('D MMM') }} – {{ $hasta->isoFormat('D MMM') }}
                </span>
            </div>
            <div class="px-6 py-5" style="height: 230px;">
                <canvas wire:ignore x-ref="canvasV"></canvas>
            </div>
        </div>

        {{-- Estado breakdown --}}
        <div class="card">
            <h3 class="font-semibold text-sm mb-4" style="color: var(--vd-text);">
                Órdenes por estado
                <span class="font-normal text-xs ml-1" style="color: var(--vd-muted);">(todas)</span>
            </h3>
            <div class="flex flex-wrap gap-4">
                @foreach(\App\Models\Orden::$estados as $val => $lbl)
                @php $cnt = $estadosOrdenes[$val] ?? 0; @endphp
                <div class="flex items-center gap-2">
                    <span class="{{ \App\Models\Orden::$estadoBadge[$val] }}">{{ $lbl }}</span>
                    <span class="font-bold text-sm" style="color: var(--vd-text);">{{ $cnt }}</span>
                </div>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            {{-- Órdenes por zona --}}
            <div class="card p-0 overflow-hidden">
                <div class="px-6 py-4" style="border-bottom: 1px solid var(--vd-bdr);">
                    <h3 class="font-semibold text-sm" style="color: var(--vd-text);">Órdenes por zona</h3>
                </div>
                @php $maxZ = $ordenesPorZona->max('total') ?: 1; @endphp
                <table class="w-full text-sm">
                    <thead style="background: var(--vd-bg-2);">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold uppercase" style="color: var(--vd-muted-2);">Zona</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold uppercase" style="color: var(--vd-muted-2);">Órdenes</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold uppercase" style="color: var(--vd-muted-2);">Ingresos</th>
                            <th class="px-5 py-3 w-24"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ordenesPorZona as $z)
                        <tr style="border-bottom: 1px solid var(--vd-bdr-soft);">
                            <td class="px-5 py-3 font-medium text-xs" style="color: var(--vd-muted);">{{ $z['zona'] }}</td>
                            <td class="px-5 py-3 text-right font-semibold" style="color: var(--vd-text);">{{ $z['total'] }}</td>
                            <td class="px-5 py-3 text-right font-mono text-xs" style="color: var(--vd-green-lt);">${{ number_format($z['ingresos'], 0, ',', '.') }}</td>
                            <td class="px-5 py-3">
                                <div class="h-1.5 rounded-full overflow-hidden" style="background: var(--vd-bg-2);">
                                    <div class="h-full rounded-full" style="width: {{ round($z['total'] / $maxZ * 100) }}%; background: var(--vd-green-lt);"></div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="px-5 py-6 text-center text-sm" style="color: var(--vd-muted-2);">Sin órdenes en este período</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Top menús --}}
            <div class="card p-0 overflow-hidden">
                <div class="px-6 py-4" style="border-bottom: 1px solid var(--vd-bdr);">
                    <h3 class="font-semibold text-sm" style="color: var(--vd-text);">Top 5 menús</h3>
                </div>
                @php $maxP = $topProductos->max('total_ingresos') ?: 1; @endphp
                <table class="w-full text-sm">
                    <thead style="background: var(--vd-bg-2);">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold uppercase" style="color: var(--vd-muted-2);">Menú</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold uppercase" style="color: var(--vd-muted-2);">Uds.</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold uppercase" style="color: var(--vd-muted-2);">Ingresos</th>
                            <th class="px-5 py-3 w-24"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topProductos as $tp)
                        <tr style="border-bottom: 1px solid var(--vd-bdr-soft);">
                            <td class="px-5 py-3 font-medium text-xs" style="color: var(--vd-muted);">{{ $tp->producto?->nombre ?? '—' }}</td>
                            <td class="px-5 py-3 text-right" style="color: var(--vd-muted);">{{ number_format($tp->total_cantidad, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right font-mono text-xs" style="color: var(--vd-green-lt);">${{ number_format($tp->total_ingresos, 0, ',', '.') }}</td>
                            <td class="px-5 py-3">
                                <div class="h-1.5 rounded-full overflow-hidden" style="background: var(--vd-bg-2);">
                                    <div class="h-full rounded-full" style="width: {{ round($tp->total_ingresos / $maxP * 100) }}%; background: rgba(200,160,48,0.7);"></div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="px-5 py-6 text-center text-sm" style="color: var(--vd-muted-2);">Sin ventas registradas</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    {{-- ════════════════ SITIO ════════════════ --}}
    <div x-show="tab === 'sitio'" x-transition x-cloak class="space-y-8">

        {{-- Stat cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @php
            $siteCards = [
                ['label' => 'Conversaciones',  'val' => $totalConv, 'color' => 'var(--vd-text)',     'pct' => $pctConv],
                ['label' => 'Abiertas (ahora)','val' => $activas,   'color' => 'var(--vd-green-lt)', 'pct' => null],
                ['label' => 'En espera',        'val' => $esperando, 'color' => '#f59e0b',            'pct' => null],
                ['label' => 'Nuevas hoy',       'val' => $convHoy,  'color' => 'var(--vd-muted)',     'pct' => null],
            ];
            @endphp
            @foreach($siteCards as $c)
            <div class="card text-center">
                <p class="text-2xl font-bold font-condensed tabular-nums" style="color: {{ $c['color'] }};">{{ $c['val'] }}</p>
                <p class="text-xs mt-1" style="color: var(--vd-muted-2);">{{ $c['label'] }}</p>
                @if($c['pct'] !== null)
                <p class="text-xs mt-2 font-semibold"
                   style="color: {{ $c['pct'] >= 0 ? 'var(--vd-green-lt)' : '#f87171' }};">
                    {{ $c['pct'] >= 0 ? '↑' : '↓' }} {{ abs($c['pct']) }}%
                    <span style="color: var(--vd-muted-2); font-weight: 400;">vs ant.</span>
                </p>
                @endif
            </div>
            @endforeach
        </div>

        {{-- Embudo de conversión --}}
        <div class="card">
            <h3 class="font-semibold text-sm mb-5" style="color: var(--vd-text);">
                Embudo de conversión
                <span class="font-normal text-xs ml-2" style="color: var(--vd-muted);">conversaciones → órdenes</span>
            </h3>
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-32 rounded-xl p-4 text-center" style="background: rgba(58,125,68,0.15); border: 1px solid rgba(78,158,90,0.2);">
                    <p class="text-2xl font-bold font-condensed" style="color: var(--vd-text);">{{ $totalConv }}</p>
                    <p class="text-xs mt-1" style="color: var(--vd-muted);">Conversaciones</p>
                </div>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--vd-muted-2); flex-shrink: 0;"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                <div class="flex-1 min-w-32 rounded-xl p-4 text-center" style="background: rgba(58,125,68,0.28); border: 1px solid rgba(78,158,90,0.35);">
                    <p class="text-2xl font-bold font-condensed" style="color: var(--vd-green-lt);">{{ $clientesConOrden }}</p>
                    <p class="text-xs mt-1" style="color: var(--vd-muted);">Clientes que ordenaron</p>
                </div>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--vd-muted-2); flex-shrink: 0;"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                <div class="flex-1 min-w-32 rounded-xl p-4 text-center" style="background: rgba(200,160,48,0.15); border: 1px solid rgba(200,160,48,0.3);">
                    <p class="text-2xl font-bold font-condensed" style="color: #c8a030;">{{ $tasaConv }}%</p>
                    <p class="text-xs mt-1" style="color: var(--vd-muted);">Tasa de conversión</p>
                </div>
            </div>
        </div>

        {{-- Conversaciones por día chart --}}
        <div class="card p-0 overflow-hidden"
             x-data="{
                chart: null,
                mkChart(el, data) {
                    if (this.chart) this.chart.destroy();
                    this.chart = new Chart(el, {
                        type: 'bar',
                        data: {
                            labels: data.map(d => d.fecha),
                            datasets: [{
                                label: 'Conversaciones',
                                data: data.map(d => d.conv),
                                backgroundColor: 'rgba(78,158,90,0.5)',
                                borderColor: '#4e9e5a', borderWidth: 1, borderRadius: 4
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: { backgroundColor: 'rgba(11,24,40,0.95)', borderColor: 'rgba(58,125,68,0.35)', borderWidth: 1 }
                            },
                            scales: {
                                x: { ticks: { color: 'rgba(240,244,240,0.4)', font: { size: 10 } }, grid: { color: 'rgba(255,255,255,0.05)' } },
                                y: { ticks: { color: 'rgba(240,244,240,0.4)', font: { size: 10 }, stepSize: 1 }, grid: { color: 'rgba(255,255,255,0.05)' } }
                            }
                        }
                    });
                }
             }"
             x-init="$nextTick(() => mkChart($refs.canvasS, {{ json_encode($chartData) }}))"
             @charts-refresh.window="mkChart($refs.canvasS, $event.detail.chartData)">
            <div class="px-6 py-4" style="border-bottom: 1px solid var(--vd-bdr);">
                <h3 class="font-semibold text-sm" style="color: var(--vd-text);">Conversaciones por día</h3>
            </div>
            <div class="px-6 py-5" style="height: 200px;">
                <canvas wire:ignore x-ref="canvasS"></canvas>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            {{-- Conv por zona --}}
            <div class="card p-0 overflow-hidden">
                <div class="px-6 py-4" style="border-bottom: 1px solid var(--vd-bdr);">
                    <h3 class="font-semibold text-sm" style="color: var(--vd-text);">Conversaciones por zona</h3>
                </div>
                @php $maxCZ = $convPorZona->max('total') ?: 1; @endphp
                <table class="w-full text-sm">
                    <thead style="background: var(--vd-bg-2);">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold uppercase" style="color: var(--vd-muted-2);">Zona</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold uppercase" style="color: var(--vd-muted-2);">Total</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold uppercase" style="color: var(--vd-muted-2);">Abiertas</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold uppercase" style="color: var(--vd-muted-2);">Cerradas</th>
                            <th class="px-5 py-3 w-20"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($convPorZona as $z)
                        <tr style="border-bottom: 1px solid var(--vd-bdr-soft);">
                            <td class="px-5 py-3 font-medium text-xs" style="color: var(--vd-muted);">{{ $z['zona'] }}</td>
                            <td class="px-5 py-3 text-right font-semibold" style="color: var(--vd-text);">{{ $z['total'] }}</td>
                            <td class="px-5 py-3 text-right"><span class="badge-green">{{ $z['activas'] }}</span></td>
                            <td class="px-5 py-3 text-right"><span class="badge-gray">{{ $z['cerradas'] }}</span></td>
                            <td class="px-5 py-3">
                                <div class="h-1.5 rounded-full overflow-hidden" style="background: var(--vd-bg-2);">
                                    <div class="h-full rounded-full" style="width: {{ round($z['total'] / $maxCZ * 100) }}%; background: var(--vd-green-lt);"></div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="px-5 py-6 text-center text-sm" style="color: var(--vd-muted-2);">Sin datos en este período</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Usuarios por tipo --}}
            <div class="card p-0 overflow-hidden">
                <div class="px-6 py-4 flex items-center justify-between" style="border-bottom: 1px solid var(--vd-bdr);">
                    <h3 class="font-semibold text-sm" style="color: var(--vd-text);">Usuarios por tipo</h3>
                    <span class="text-xs" style="color: var(--vd-muted-2);">{{ $totalUsuarios }} en total</span>
                </div>
                @php
                $rolColors = ['Administrador' => 'var(--vd-green-lt)', 'Resp. de Zona' => '#60a5fa', 'Colaborador' => 'var(--vd-muted)', 'Cliente' => '#f59e0b'];
                @endphp
                <div class="grid grid-cols-2">
                    @foreach(\App\Models\User::rolesLabels() as $val => $lbl)
                    @php $cnt = $porRol->firstWhere('rol', $lbl)['total'] ?? 0; @endphp
                    <div class="px-6 py-5 text-center" style="border-right: 1px solid var(--vd-bdr-soft); border-bottom: 1px solid var(--vd-bdr-soft);">
                        <p class="text-2xl font-bold font-condensed" style="color: {{ $rolColors[$lbl] ?? 'var(--vd-text)' }};">{{ $cnt }}</p>
                        <p class="text-xs mt-1" style="color: var(--vd-muted-2);">{{ $lbl }}</p>
                    </div>
                    @endforeach
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
