<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Conversacion;
use App\Models\Orden;
use App\Models\User;
use App\Models\Zona;
use Illuminate\Support\Facades\Redis;

new #[Layout('layouts.app', ['title' => 'Dashboard'])] class extends Component {

    public int   $totalConversaciones   = 0;
    public int   $conversacionesHoy     = 0;
    public int   $mensajesEnCola        = 0;
    public int   $totalClientes         = 0;
    public int   $ordenesPendientes     = 0;
    public int   $ordenesHoy            = 0;
    public float $ingresosHoy           = 0;
    public array $porZona               = [];
    public array $ventasPorZona         = [];
    public array $ultimasConversaciones = [];

    public function mount(): void
    {
        if (auth()->user()->isCliente()) {
            $this->redirect(route('portal'), navigate: true);
            return;
        }
        if (auth()->user()->isCocina()) {
            $this->redirect(route('cocina'), navigate: true);
            return;
        }

        $this->totalConversaciones = Conversacion::activas()->count();
        $this->conversacionesHoy   = Conversacion::hoy()->count();
        $this->totalClientes       = User::where('role', 'cliente')->count();
        $this->ordenesPendientes   = Orden::where('estado', 'pendiente')->count();
        $this->ordenesHoy          = Orden::whereDate('created_at', today())->count();
        $this->ingresosHoy         = (float) Orden::where('estado', 'entregada')
                                        ->whereDate('created_at', today())->sum('total');

        try {
            $this->mensajesEnCola = Redis::llen('queues:default') + Redis::llen('queues:whatsapp');
        } catch (\Exception) {
            $this->mensajesEnCola = 0;
        }

        $activas = Conversacion::activas()
            ->selectRaw('zona, count(*) as total')
            ->groupBy('zona')
            ->pluck('total', 'zona');

        $this->porZona = Zona::where('activa', true)
            ->orderBy('nombre')
            ->get()
            ->map(fn($z) => [
                'zona'    => $z->nombre,
                'numero'  => $z->whatsapp ?? '—',
                'slug'    => $z->slug,
                'activas' => $activas->get($z->slug, 0),
            ])
            ->all();

        $zonaLabels = ['bsas' => 'Buenos Aires', 'valle_nqn' => 'Valle NQN', 'cordoba' => 'Córdoba', 'mendoza' => 'Mendoza'];
        $ventasRaw  = Orden::selectRaw('zona, count(*) as total_ordenes, COALESCE(sum(total),0) as total_ingresos')
            ->whereBetween('created_at', [now()->subDays(29)->startOfDay(), now()->endOfDay()])
            ->groupBy('zona')->get()->keyBy('zona');

        $this->ventasPorZona = collect($zonaLabels)->map(fn($label, $slug) => [
            'slug'    => $slug,
            'label'   => $label,
            'ordenes' => (int) ($ventasRaw->get($slug)?->total_ordenes ?? 0),
            'ingresos'=> (float) ($ventasRaw->get($slug)?->total_ingresos ?? 0),
        ])->values()->all();

        $this->ultimasConversaciones = Conversacion::whereNotNull('ultimo_mensaje_at')
            ->orderByDesc('ultimo_mensaje_at')
            ->limit(5)
            ->get()
            ->map(fn($c) => [
                'id'      => $c->id,
                'nombre'  => $c->nombre ?? $c->telefono,
                'zona'    => $c->zonaLabel(),
                'estado'  => $c->estado,
                'hace'    => $c->ultimo_mensaje_at->diffForHumans(),
                'msg'     => $c->ultimo_mensaje,
            ])->all();
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

    {{-- Quick actions --}}
    <div class="grid grid-cols-3 gap-3 mb-6">
        <a href="{{ route('ordenes') }}" wire:navigate
           class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all"
           style="background: rgba(78,158,90,0.1); border: 1px solid rgba(78,158,90,0.25);"
           onmouseover="this.style.background='rgba(78,158,90,0.18)'" onmouseout="this.style.background='rgba(78,158,90,0.1)'">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                 style="background: rgba(78,158,90,0.2);">
                <svg width="16" height="16" fill="none" stroke="#4e9e5a" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold" style="color: #4e9e5a;">Nueva orden</p>
                <p class="text-xs" style="color: var(--vd-muted);">Registrar pedido</p>
            </div>
        </a>
        <a href="{{ route('conversaciones') }}?categoria=pendiente" wire:navigate
           class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all"
           style="background: rgba(200,160,48,0.08); border: 1px solid rgba(200,160,48,0.2);"
           onmouseover="this.style.background='rgba(200,160,48,0.15)'" onmouseout="this.style.background='rgba(200,160,48,0.08)'">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                 style="background: rgba(200,160,48,0.15);">
                <svg width="16" height="16" fill="none" stroke="#c8a030" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold" style="color: #c8a030;">Pendientes</p>
                <p class="text-xs" style="color: var(--vd-muted);">{{ $ordenesPendientes }} con pedido activo</p>
            </div>
        </a>
        <a href="{{ route('estadisticas') }}" wire:navigate
           class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all"
           style="background: rgba(96,165,250,0.08); border: 1px solid rgba(96,165,250,0.2);"
           onmouseover="this.style.background='rgba(96,165,250,0.15)'" onmouseout="this.style.background='rgba(96,165,250,0.08)'">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                 style="background: rgba(96,165,250,0.15);">
                <svg width="16" height="16" fill="none" stroke="#60a5fa" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold" style="color: #60a5fa;">Estadísticas</p>
                <p class="text-xs" style="color: var(--vd-muted);">Ver métricas</p>
            </div>
        </a>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">

        <a href="{{ route('conversaciones') }}" wire:navigate class="stat-card block"
           style="transition: border-color .2s, box-shadow .2s; cursor: pointer;"
           onmouseover="this.style.borderColor='rgba(78,158,90,0.4)'; this.style.boxShadow='0 8px 28px rgba(0,0,0,0.35)'"
           onmouseout="this.style.borderColor=''; this.style.boxShadow=''">
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
        </a>

        <a href="{{ route('conversaciones') }}" wire:navigate class="stat-card block"
           style="transition: border-color .2s, box-shadow .2s; cursor: pointer;"
           onmouseover="this.style.borderColor='rgba(78,158,90,0.4)'; this.style.boxShadow='0 8px 28px rgba(0,0,0,0.35)'"
           onmouseout="this.style.borderColor=''; this.style.boxShadow=''">
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
        </a>

        <a href="{{ route('clientes.crm') }}" wire:navigate class="stat-card block"
           style="transition: border-color .2s, box-shadow .2s; cursor: pointer;"
           onmouseover="this.style.borderColor='rgba(78,158,90,0.4)'; this.style.boxShadow='0 8px 28px rgba(0,0,0,0.35)'"
           onmouseout="this.style.borderColor=''; this.style.boxShadow=''">
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
        </a>

        <a href="{{ route('ordenes') }}" wire:navigate class="stat-card block"
           style="transition: border-color .2s, box-shadow .2s; cursor: pointer;"
           onmouseover="this.style.borderColor='{{ $ordenesPendientes > 0 ? 'rgba(200,160,48,0.5)' : 'rgba(78,158,90,0.4)' }}'; this.style.boxShadow='0 8px 28px rgba(0,0,0,0.35)'"
           onmouseout="this.style.borderColor=''; this.style.boxShadow=''">
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
        </a>

    </div>

    {{-- Resumen de hoy --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="card py-4">
            <p class="text-xs font-condensed font-bold uppercase tracking-widest mb-3"
               style="color: var(--vd-muted-2); letter-spacing: 1.5px;">Órdenes hoy</p>
            <p class="font-condensed font-bold text-3xl" style="color: var(--vd-text);">{{ $ordenesHoy }}</p>
            <p class="text-xs mt-1" style="color: var(--vd-muted);">pedidos ingresados</p>
        </div>
        <div class="card py-4">
            <p class="text-xs font-condensed font-bold uppercase tracking-widest mb-3"
               style="color: var(--vd-muted-2); letter-spacing: 1.5px;">Chats hoy</p>
            <p class="font-condensed font-bold text-3xl" style="color: var(--vd-text);">{{ $conversacionesHoy }}</p>
            <p class="text-xs mt-1" style="color: var(--vd-muted);">conversaciones nuevas</p>
        </div>
        <div class="card py-4">
            <p class="text-xs font-condensed font-bold uppercase tracking-widest mb-3"
               style="color: var(--vd-muted-2); letter-spacing: 1.5px;">Ingresos hoy</p>
            <p class="font-condensed font-bold text-3xl" style="color: #4e9e5a;">
                ${{ $ingresosHoy > 0 ? number_format($ingresosHoy, 0, ',', '.') : '—' }}
            </p>
            <p class="text-xs mt-1" style="color: var(--vd-muted);">de órdenes entregadas</p>
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
                <a href="{{ route('zonas') }}" wire:navigate class="zone-row"
                   style="transition: background .15s, border-color .15s; cursor: pointer; text-decoration: none;"
                   onmouseover="this.style.background='rgba(58,125,68,0.06)'; this.style.borderRadius='12px';"
                   onmouseout="this.style.background=''; this.style.borderRadius='';">
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
                </a>
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

    {{-- Ventas por zona + Últimas conversaciones --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">

        {{-- Ventas por zona (2/3) --}}
        <div class="md:col-span-2 card">
            <div class="flex items-end justify-between mb-4"
                 style="border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 12px;">
                <div>
                    <h3 class="font-condensed font-bold text-lg" style="color: var(--vd-text);">Ventas por zona</h3>
                    <p class="text-xs mt-0.5" style="color: var(--vd-muted);">Últimos 30 días</p>
                </div>
                <a href="{{ route('estadisticas') }}" wire:navigate
                   class="text-xs font-condensed font-bold tracking-wide uppercase transition-colors"
                   style="color: var(--vd-green-lt);"
                   onmouseover="this.style.color='var(--vd-text)'" onmouseout="this.style.color='var(--vd-green-lt)'">
                    Ver estadísticas →
                </a>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr>
                        <th class="text-left pb-2 text-xs font-condensed font-bold uppercase tracking-wide"
                            style="color: var(--vd-muted-2);">Zona</th>
                        <th class="text-right pb-2 text-xs font-condensed font-bold uppercase tracking-wide"
                            style="color: var(--vd-muted-2);">Órdenes</th>
                        <th class="text-right pb-2 text-xs font-condensed font-bold uppercase tracking-wide"
                            style="color: var(--vd-muted-2);">Ingresos</th>
                    </tr>
                </thead>
                <tbody class="divide-y" style="border-color: var(--vd-bdr-soft);">
                    @foreach($ventasPorZona as $vz)
                    <tr>
                        <td class="py-2.5">
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full" style="background: #4e9e5a; opacity: {{ $vz['ordenes'] > 0 ? '1' : '0.25' }};"></div>
                                <span style="color: var(--vd-text);">{{ $vz['label'] }}</span>
                            </div>
                        </td>
                        <td class="py-2.5 text-right font-mono font-semibold" style="color: var(--vd-text);">
                            {{ $vz['ordenes'] ?: '—' }}
                        </td>
                        <td class="py-2.5 text-right font-mono font-semibold"
                            style="color: {{ $vz['ingresos'] > 0 ? '#4e9e5a' : 'var(--vd-muted-2)' }};">
                            {{ $vz['ingresos'] > 0 ? '$'.number_format($vz['ingresos'],0,',','.') : '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot style="border-top: 1px solid var(--vd-bdr);">
                    <tr>
                        <td class="pt-3 text-xs font-semibold" style="color: var(--vd-muted);">Total</td>
                        <td class="pt-3 text-right font-mono font-bold text-sm" style="color: var(--vd-text);">
                            {{ array_sum(array_column($ventasPorZona, 'ordenes')) ?: '—' }}
                        </td>
                        <td class="pt-3 text-right font-mono font-bold text-sm" style="color: #4e9e5a;">
                            @php $totalIngresos = array_sum(array_column($ventasPorZona, 'ingresos')); @endphp
                            {{ $totalIngresos > 0 ? '$'.number_format($totalIngresos,0,',','.') : '—' }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Últimas conversaciones (1/3) --}}
        <div class="card">
            <div class="flex items-end justify-between mb-4"
                 style="border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 12px;">
                <h3 class="font-condensed font-bold text-lg" style="color: var(--vd-text);">Recientes</h3>
                <a href="{{ route('conversaciones') }}" wire:navigate
                   class="text-xs font-condensed font-bold tracking-wide uppercase transition-colors"
                   style="color: var(--vd-green-lt);"
                   onmouseover="this.style.color='var(--vd-text)'" onmouseout="this.style.color='var(--vd-green-lt)'">
                    Ver todas →
                </a>
            </div>
            @if(empty($ultimasConversaciones))
                <p class="text-sm text-center py-6" style="color: var(--vd-muted);">Sin conversaciones aún.</p>
            @else
            <div class="space-y-3">
                @foreach($ultimasConversaciones as $conv)
                <a href="{{ route('conversaciones.ver', $conv['id']) }}" wire:navigate
                   class="flex items-start gap-2.5 group"
                   style="text-decoration: none;">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold mt-0.5"
                         style="background: rgba(58,125,68,0.15); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.25);">
                        {{ strtoupper(substr($conv['nombre'], 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-sm font-semibold truncate group-hover:underline"
                                  style="color: var(--vd-text);">{{ $conv['nombre'] }}</span>
                            <span class="{{ match($conv['estado']) {
                                'abierta'   => 'badge-green',
                                'esperando' => 'badge-yellow',
                                default     => 'badge-gray',
                            } }}" style="font-size: 9px; padding: 1px 6px;">{{ $conv['estado'] }}</span>
                        </div>
                        <p class="text-xs truncate mt-0.5" style="color: var(--vd-muted);">
                            {{ $conv['zona'] }} · {{ $conv['hace'] }}
                        </p>
                    </div>
                </a>
                @endforeach
            </div>
            @endif
        </div>

    </div>

</div>
