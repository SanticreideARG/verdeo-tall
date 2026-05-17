<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Orden;
use App\Services\GeocodingService;
use Carbon\Carbon;

new #[Layout('layouts.app', ['title' => 'Hoja de Ruta'])] class extends Component {

    public string $fecha = '';
    public string $zona  = '';

    // Geocoding state
    public ?int  $geocodingId = null;

    public function mount(): void
    {
        if (! (auth()->user()->isAdmin() || auth()->user()->isResponsableZona())) {
            $this->redirect(route('dashboard'), navigate: true);
        }
        $this->fecha = today()->format('Y-m-d');
        if (auth()->user()->isResponsableZona()) {
            $this->zona = auth()->user()->zona ?? '';
        }
    }

    public function with(): array
    {
        $ordenes = Orden::whereDate('created_at', $this->fecha)
            ->when($this->zona, fn($q) => $q->where('zona', $this->zona))
            ->whereIn('estado', ['aprobada', 'lista_para_entrega', 'entregada'])
            ->with(['items.producto', 'cliente'])
            ->orderBy('created_at')
            ->get();

        $zonaCoords = [
            'bsas'      => [-34.6037, -58.3816],
            'valle_nqn' => [-38.9516, -68.0591],
            'cordoba'   => [-31.4201, -64.1888],
            'mendoza'   => [-32.8908, -68.8272],
        ];

        $markers = $ordenes->map(function ($o) use ($zonaCoords) {
            $lat = $o->latitud  ?? ($zonaCoords[$o->zona][0] ?? -34.6037);
            $lng = $o->longitud ?? ($zonaCoords[$o->zona][1] ?? -58.3816);
            return [
                'id'      => $o->id,
                'numero'  => $o->numero,
                'cliente' => $o->cliente?->nombreCompleto() ?? '—',
                'dir'     => $o->direccion ?? '',
                'lat'     => $lat,
                'lng'     => $lng,
                'real'    => $o->latitud !== null,
                'estado'  => $o->estado,
            ];
        })->values()->toArray();

        $defaultLat = isset($zonaCoords[$this->zona]) ? $zonaCoords[$this->zona][0] : -34.6037;
        $defaultLng = isset($zonaCoords[$this->zona]) ? $zonaCoords[$this->zona][1] : -58.3816;

        return compact('ordenes', 'markers', 'defaultLat', 'defaultLng');
    }

    public function geocodificar(int $id): void
    {
        $orden = Orden::findOrFail($id);
        if (! $orden->direccion) return;

        $this->geocodingId = $id;
        $geo = app(GeocodingService::class)->geocodificar($orden->direccion);

        if ($geo) {
            $orden->update(['latitud' => $geo['lat'], 'longitud' => $geo['lng']]);
            session()->flash('success', "Coordenadas obtenidas para orden {$orden->numero}.");
        } else {
            session()->flash('error', "No se encontraron coordenadas para: {$orden->direccion}");
        }
        $this->geocodingId = null;
    }

}; ?>

<style>
@media print {
    .no-print { display: none !important; }
    .print-only { display: block !important; }
    body { background: white !important; color: black !important; }
    .verdeo-sidebar, header, #verdeo-bg { display: none !important; }
    main { padding: 0 !important; }
    .card { border: 1px solid #ccc !important; background: white !important; box-shadow: none !important; }
}
</style>

<div>

    {{-- Flash --}}
    @if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         class="mb-4 badge-green px-3 py-2 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
         class="mb-4 px-3 py-2 text-sm rounded-xl"
         style="background: rgba(239,68,68,0.1); color: #fca5a5; border: 1px solid rgba(239,68,68,0.3);">
        {{ session('error') }}
    </div>
    @endif

    {{-- Toolbar --}}
    <div class="no-print flex flex-wrap items-center gap-3 mb-6">
        <input type="date" wire:model.live="fecha" class="input w-44">
        <select wire:model.live="zona" class="input w-44">
            <option value="">Todas las zonas</option>
            <option value="bsas">Buenos Aires</option>
            <option value="valle_nqn">Valle NQN / Roca</option>
            <option value="cordoba">Córdoba</option>
            <option value="mendoza">Mendoza</option>
        </select>
        <div class="flex-1"></div>
        <button onclick="window.print()" class="btn-secondary no-print flex items-center gap-2">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/>
            </svg>
            Imprimir
        </button>
    </div>

    {{-- Print header --}}
    <div class="hidden print-only mb-4">
        <h1 style="font-size:18px;font-weight:700;">Hoja de Ruta — {{ \Carbon\Carbon::parse($fecha)->isoFormat('D/MM/YYYY') }}
            @if($zona) — {{ match($zona) { 'bsas'=>'Buenos Aires','valle_nqn'=>'Valle NQN','cordoba'=>'Córdoba','mendoza'=>'Mendoza',default=>$zona } }} @endif
        </h1>
    </div>

    @if($ordenes->isEmpty())
    <div class="card text-center py-16">
        <p class="text-lg font-condensed font-bold mb-2" style="color: var(--vd-text-soft);">Sin órdenes</p>
        <p class="text-sm" style="color: var(--vd-muted-2);">No hay órdenes aprobadas o entregadas para esta fecha y zona.</p>
    </div>
    @else

    {{-- Map (no print) --}}
    <div class="no-print card p-0 overflow-hidden mb-6"
         x-data="{
             map: null,
             init() {
                 this.$nextTick(() => {
                     if (typeof L === 'undefined') return;
                     const markers = {{ Js::from($markers) }};
                     this.map = L.map(this.$refs.map, { zoomControl: true }).setView([{{ $defaultLat }}, {{ $defaultLng }}], 12);
                     L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                         attribution: '© OpenStreetMap',
                         maxZoom: 19
                     }).addTo(this.map);
                     markers.forEach((m, i) => {
                         const icon = L.divIcon({
                             html: `<div style='background:${m.real ? '#4e9e5a' : '#c8a030'};color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;border:2px solid rgba(255,255,255,0.6);box-shadow:0 2px 8px rgba(0,0,0,0.4)'>${i+1}</div>`,
                             className: '', iconSize: [28,28], iconAnchor: [14,14]
                         });
                         L.marker([m.lat, m.lng], {icon})
                             .addTo(this.map)
                             .bindPopup(`<b>${m.numero}</b><br>${m.cliente}<br><small>${m.dir}</small>`);
                     });
                     if (markers.length > 1) {
                         const bounds = L.latLngBounds(markers.map(m => [m.lat, m.lng]));
                         this.map.fitBounds(bounds, { padding: [30, 30] });
                     }
                 });
             }
         }">
        <div class="px-5 py-3 flex items-center justify-between" style="border-bottom: 1px solid var(--vd-bdr);">
            <h3 class="font-semibold text-sm" style="color: var(--vd-text);">Mapa de entregas</h3>
            <div class="flex items-center gap-3 text-xs" style="color: var(--vd-muted-2);">
                <span class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-full inline-block" style="background:#4e9e5a;"></span> Geolocalizada
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-full inline-block" style="background:#c8a030;"></span> Referencia de zona
                </span>
            </div>
        </div>
        <div x-ref="map" style="height:320px;"></div>
    </div>

    {{-- Table --}}
    <div class="card p-0 overflow-hidden overflow-x-auto">
        <table class="w-full text-sm min-w-[760px]">
            <thead style="background: var(--vd-bg-2); border-bottom: 1px solid var(--vd-bdr);">
                <tr>
                    <th class="text-left px-4 py-3 text-xs font-condensed font-bold uppercase tracking-wide" style="color: var(--vd-muted-2);">#</th>
                    <th class="text-left px-4 py-3 text-xs font-condensed font-bold uppercase tracking-wide" style="color: var(--vd-muted-2);">Cliente</th>
                    <th class="text-left px-4 py-3 text-xs font-condensed font-bold uppercase tracking-wide" style="color: var(--vd-muted-2);">Dirección</th>
                    <th class="text-left px-4 py-3 text-xs font-condensed font-bold uppercase tracking-wide" style="color: var(--vd-muted-2);">Menús</th>
                    <th class="text-right px-4 py-3 text-xs font-condensed font-bold uppercase tracking-wide" style="color: var(--vd-muted-2);">Precio</th>
                    <th class="text-center px-4 py-3 text-xs font-condensed font-bold uppercase tracking-wide" style="color: var(--vd-muted-2);">Pago</th>
                    <th class="text-center px-4 py-3 text-xs font-condensed font-bold uppercase tracking-wide no-print" style="color: var(--vd-muted-2);">Coord.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ordenes as $idx => $o)
                <tr style="border-bottom: 1px solid var(--vd-bdr-soft);">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                                  style="background: rgba(78,158,90,0.15); color: #4e9e5a;">{{ $idx + 1 }}</span>
                            <span class="font-mono text-xs" style="color: var(--vd-muted);">{{ $o->numero }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <p class="font-semibold text-xs" style="color: var(--vd-text);">{{ $o->cliente?->nombreCompleto() ?? '—' }}</p>
                        @if($o->cliente?->whatsapp)
                        <p class="text-xs" style="color: var(--vd-muted-2);">{{ $o->cliente->whatsapp }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-xs" style="color: var(--vd-text-soft);">{{ $o->direccion ?? '—' }}</p>
                        @if($o->latitud && $o->longitud)
                        <a href="https://www.google.com/maps?q={{ $o->latitud }},{{ $o->longitud }}"
                           target="_blank" class="text-xs no-print" style="color: #4e9e5a;">Ver en mapa</a>
                        @else
                        <span class="text-xs" style="color: var(--vd-muted-2);">Sin coords</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @foreach($o->items as $it)
                        <p class="text-xs leading-5" style="color: var(--vd-text-soft);">
                            {{ $it->producto?->nombre ?? '—' }} · {{ $it->tamano }}
                            @if($it->cantidad > 1) <span style="color: var(--vd-muted-2);">x{{ $it->cantidad }}</span>@endif
                        </p>
                        @endforeach
                    </td>
                    <td class="px-4 py-3 text-right font-mono font-semibold text-xs" style="color: var(--vd-text);">
                        ${{ number_format($o->total, 0, ',', '.') }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        @php $fp = $o->items->first()?->forma_pago ?? 'no_definido'; @endphp
                        <span class="text-xs px-2 py-0.5 rounded-full"
                              style="background: {{ $fp === 'transferencia' ? 'rgba(96,165,250,0.1)' : 'rgba(78,158,90,0.1)' }};
                                     color: {{ $fp === 'transferencia' ? '#60a5fa' : '#4e9e5a' }};
                                     border: 1px solid {{ $fp === 'transferencia' ? 'rgba(96,165,250,0.25)' : 'rgba(78,158,90,0.25)' }};">
                            {{ match($fp) { 'transferencia'=>'Transfer.', 'en_destino'=>'Efectivo', default=>'—' } }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center no-print">
                        @if($o->latitud && $o->longitud)
                        <span class="text-xs font-mono" style="color: #4e9e5a;">
                            {{ round($o->latitud, 4) }}, {{ round($o->longitud, 4) }}
                        </span>
                        @elseif($o->direccion)
                        <button wire:click="geocodificar({{ $o->id }})"
                                wire:loading.attr="disabled"
                                wire:target="geocodificar({{ $o->id }})"
                                class="text-xs px-2.5 py-1 rounded-lg transition-all"
                                style="background: rgba(200,160,48,0.1); color: #c8a030; border: 1px solid rgba(200,160,48,0.25);"
                                onmouseover="this.style.background='rgba(200,160,48,0.2)'"
                                onmouseout="this.style.background='rgba(200,160,48,0.1)'">
                            <span wire:loading.remove wire:target="geocodificar({{ $o->id }})">Geocodificar</span>
                            <span wire:loading wire:target="geocodificar({{ $o->id }})">…</span>
                        </button>
                        @else
                        <span class="text-xs" style="color: var(--vd-muted-2);">Sin dirección</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot style="background: var(--vd-bg-2); border-top: 1px solid var(--vd-bdr);">
                <tr>
                    <td colspan="4" class="px-4 py-2 text-right text-xs font-semibold" style="color: var(--vd-muted);">
                        {{ $ordenes->count() }} órdenes · Total:
                    </td>
                    <td class="px-4 py-2 text-right font-mono font-bold text-sm" style="color: var(--vd-text);">
                        ${{ number_format($ordenes->sum('total'), 0, ',', '.') }}
                    </td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    @endif

    {{-- Leaflet CDN — must be inside the single root element --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV/XN/WPaA=" crossorigin=""></script>
</div>
