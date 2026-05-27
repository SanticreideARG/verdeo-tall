<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\HojaRuta;
use App\Models\Orden;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

new #[Layout('layouts.microsite')] class extends Component {

    public ?HojaRuta $hr = null;
    public bool $expirada = false;
    public bool $noEncontrada = false;

    // Feedback messages por orden
    public array $mensajesEnviados = [];  // [orden_id => 'en_camino'|'estoy_afuera']
    public array $mensajeError     = [];  // [orden_id => string]
    public ?int  $notificando      = null;

    public function mount(string $token): void
    {
        $hr = HojaRuta::with([
            'ordenes' => fn($q) => $q->with(['items.producto', 'cliente'])->orderBy('id'),
        ])->where('token', $token)->first();

        if (! $hr) {
            $this->noEncontrada = true;
            return;
        }

        if ($hr->isExpired() || $hr->estado === 'cancelada') {
            $this->expirada = true;
            $this->hr = $hr;
            return;
        }

        $this->hr = $hr;

        // Pasar a "en_reparto" automáticamente al abrir el link
        if ($hr->estado === 'activa') {
            $hr->update(['estado' => 'en_reparto']);
        }
    }

    /* ── WA Helpers ─────────────────────────────────────────────────────────── */

    private function enviarWA(Orden $orden, string $texto): bool
    {
        $url = Setting::get('wa_evolution_url', '');
        $key = Setting::get('wa_evolution_key', '');
        if (! $url || ! $key) return false;

        $instancias = [
            'bsas'      => Setting::get('wa_evolution_inst_bsas', ''),
            'valle_nqn' => Setting::get('wa_evolution_inst_valle_nqn', ''),
            'cordoba'   => Setting::get('wa_evolution_inst_cordoba', ''),
            'mendoza'   => Setting::get('wa_evolution_inst_mendoza', ''),
        ];

        $instancia = $instancias[$orden->zona] ?? '';
        if (! $instancia) return false;

        $phone = preg_replace('/\D/', '', $orden->cliente?->whatsapp ?? '');
        if (! $phone) return false;

        try {
            $resp = Http::timeout(10)
                ->withHeaders(['apikey' => $key])
                ->post(rtrim($url, '/') . "/message/sendText/{$instancia}", [
                    'number' => $phone,
                    'text'   => $texto,
                ]);
            return $resp->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /* ── Acciones ────────────────────────────────────────────────────────────── */

    public function enviarEnCamino(int $ordenId): void
    {
        if (! $this->hr) return;
        $orden = $this->hr->ordenes->firstWhere('id', $ordenId);
        if (! $orden) return;

        $this->notificando = $ordenId;

        $empresa = Setting::get('app_nombre', 'Verdeo');
        $texto   = "¡Hola! 🛵 Tu pedido de {$empresa} está en camino. Llegamos pronto.";

        if ($this->enviarWA($orden, $texto)) {
            $this->mensajesEnviados[$ordenId] = 'en_camino';
            unset($this->mensajeError[$ordenId]);
        } else {
            $this->mensajeError[$ordenId] = 'No se pudo enviar el mensaje. Verificá la configuración de WhatsApp.';
        }

        $this->notificando = null;
    }

    public function enviarEstoyAfuera(int $ordenId): void
    {
        if (! $this->hr) return;
        $orden = $this->hr->ordenes->firstWhere('id', $ordenId);
        if (! $orden) return;

        $this->notificando = $ordenId;

        $empresa = Setting::get('app_nombre', 'Verdeo');
        $texto   = "¡Hola! 🏠 El repartidor de {$empresa} ya está en tu domicilio.";

        if ($this->enviarWA($orden, $texto)) {
            $this->mensajesEnviados[$ordenId] = 'estoy_afuera';
            unset($this->mensajeError[$ordenId]);
        } else {
            $this->mensajeError[$ordenId] = 'No se pudo enviar el mensaje.';
        }

        $this->notificando = null;
    }

    public function marcarEntregado(int $ordenId): void
    {
        if (! $this->hr) return;
        $orden = Orden::find($ordenId);
        if (! $orden || $orden->hoja_ruta_id !== $this->hr->id) return;

        $orden->update(['transportista_confirma_at' => now()]);

        // Refrescar la HR en memoria
        $this->hr->load([
            'ordenes' => fn($q) => $q->with(['items.producto', 'cliente'])->orderBy('id'),
        ]);

        // Verificar si la HR puede cerrarse
        $this->hr->checkCompletada();
        $this->hr->refresh();
    }

    public function with(): array
    {
        $totalCobrar = 0;
        $pendienteCobrar = 0;

        if ($this->hr) {
            foreach ($this->hr->ordenes as $o) {
                if ($o->items->first()?->forma_pago === 'en_destino') {
                    $totalCobrar += $o->total;
                    if (! $o->transportista_confirma_at) {
                        $pendienteCobrar += $o->total;
                    }
                }
            }
        }

        return [
            'totalCobrar'    => $totalCobrar,
            'pendienteCobrar' => $pendienteCobrar,
        ];
    }

}; ?>

<div class="min-h-screen" style="background: #0f172a; padding-bottom: 40px;">

    {{-- ── No encontrada ──────────────────────────────────────────────────── --}}
    @if($noEncontrada)
    <div class="flex flex-col items-center justify-center min-h-screen px-6 text-center">
        <div class="text-5xl mb-4">🔍</div>
        <h1 class="text-xl font-bold text-white mb-2">Hoja de ruta no encontrada</h1>
        <p class="text-slate-400 text-sm">Este enlace no existe o ya fue eliminado.</p>
    </div>

    {{-- ── Expirada / Cancelada ───────────────────────────────────────────── --}}
    @elseif($expirada)
    <div class="flex flex-col items-center justify-center min-h-screen px-6 text-center">
        <div class="text-5xl mb-4">⏰</div>
        <h1 class="text-xl font-bold text-white mb-2">
            @if($hr?->estado === 'cancelada') Ruta cancelada @else Enlace expirado @endif
        </h1>
        <p class="text-slate-400 text-sm">
            @if($hr?->estado === 'cancelada')
                Esta hoja de ruta fue cancelada.
            @else
                El enlace {{ $hr?->numero }} expiró. Los links duran 24 horas.
            @endif
        </p>
    </div>

    {{-- ── Microsite principal ─────────────────────────────────────────────── --}}
    @elseif($hr)

    {{-- Header --}}
    <div style="background: rgba(78,158,90,0.12); border-bottom: 1px solid rgba(78,158,90,0.2); padding: 16px 20px;">
        <div style="max-width: 600px; margin: 0 auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                <span style="font-size:13px; font-weight:700; color:#4ade80; letter-spacing:1px; text-transform:uppercase;">
                    🥗 Verdeo
                </span>
                <span style="font-size:11px; color:#64748b;">
                    Exp. {{ $hr->expires_at->format('H:i') }} hs
                </span>
            </div>
            <h1 style="font-size:18px; font-weight:800; color:#f1f5f9; margin:0 0 2px;">
                {{ $hr->numero }}
            </h1>
            <p style="font-size:13px; color:#94a3b8; margin:0;">
                {{ $hr->ciudad }}
                @if($hr->transportista_nombre) · Para {{ $hr->transportista_nombre }} @endif
            </p>
        </div>
    </div>

    <div style="max-width: 600px; margin: 0 auto; padding: 16px;">

        {{-- Progreso --}}
        @php
            $total      = $hr->ordenes->count();
            $entregados = $hr->ordenes->whereNotNull('transportista_confirma_at')->count();
            $pct        = $total > 0 ? round($entregados / $total * 100) : 0;
        @endphp

        <div class="ms-card" style="margin-bottom:16px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <span style="font-size:13px; color:#94a3b8;">Progreso</span>
                <span style="font-size:15px; font-weight:700; color:#f1f5f9;">
                    {{ $entregados }}/{{ $total }} pedidos
                </span>
            </div>
            <div style="background: rgba(255,255,255,0.08); border-radius:8px; height:8px; overflow:hidden;">
                <div style="background: #4ade80; height:100%; width:{{ $pct }}%; transition:width 0.4s; border-radius:8px;"></div>
            </div>

            @if($totalCobrar > 0)
            <div style="display:flex; justify-content:space-between; margin-top:10px; padding-top:10px; border-top:1px solid rgba(255,255,255,0.08);">
                <span style="font-size:13px; color:#94a3b8;">💳 A cobrar en destino</span>
                <span style="font-size:14px; font-weight:700; color:#facc15;">
                    ${{ number_format($pendienteCobrar, 0, ',', '.') }}
                    @if($pendienteCobrar < $totalCobrar)
                    <span style="color:#64748b; font-weight:400; font-size:12px;">/ ${{ number_format($totalCobrar, 0, ',', '.') }}</span>
                    @endif
                </span>
            </div>
            @endif

            @if($hr->estado === 'completada')
            <div style="margin-top:10px; padding:8px 12px; background:rgba(78,158,90,0.15); border-radius:8px; text-align:center; color:#4ade80; font-size:13px; font-weight:600;">
                ✓ Ruta completada. Gracias!
            </div>
            @endif
        </div>

        {{-- Lista de pedidos --}}
        <div style="display:flex; flex-direction:column; gap:12px;">
        @foreach($hr->ordenes->sortBy('id') as $idx => $orden)

        @php
            $entregado   = $orden->transportista_confirma_at !== null;
            $fp          = $orden->items->first()?->forma_pago ?? '';
            $cobrar      = $fp === 'en_destino';
            $msgEnviado  = $mensajesEnviados[$orden->id] ?? null;
            $errMsg      = $mensajeError[$orden->id] ?? null;
        @endphp

        <div class="ms-card"
             style="{{ $entregado ? 'border-color: rgba(78,158,90,0.4); background: rgba(78,158,90,0.07);' : '' }}">

            {{-- Cabecera --}}
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <div style="width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px; flex-shrink:0;
                                background: {{ $entregado ? 'rgba(78,158,90,0.25)' : 'rgba(255,255,255,0.08)' }};
                                color: {{ $entregado ? '#4ade80' : '#94a3b8' }};">
                        {{ $entregado ? '✓' : ($idx + 1) }}
                    </div>
                    <div>
                        <p style="font-size:14px; font-weight:700; color:#f1f5f9; margin:0 0 1px;">
                            Pedido {{ $idx + 1 }}
                        </p>
                        <p style="font-size:11px; color:#64748b; margin:0; font-family:monospace;">
                            #{{ $orden->numero }}
                        </p>
                    </div>
                </div>
                @if($cobrar)
                <div style="text-align:right;">
                    <p style="font-size:16px; font-weight:800; color: {{ $entregado ? '#64748b' : '#facc15' }}; margin:0; {{ $entregado ? 'text-decoration:line-through;' : '' }}">
                        ${{ number_format($orden->total, 0, ',', '.') }}
                    </p>
                    <p style="font-size:10px; color:#ef4444; margin:0; font-weight:600;">
                        COBRAR EN DESTINO
                    </p>
                </div>
                @endif
            </div>

            {{-- Dirección --}}
            @if($orden->direccion)
            <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($orden->direccion) }}"
               target="_blank"
               style="display:flex; align-items:flex-start; gap:8px; padding:10px 12px; background:rgba(59,130,246,0.1); border:1px solid rgba(59,130,246,0.2); border-radius:10px; text-decoration:none; margin-bottom:10px;">
                <span style="font-size:16px; flex-shrink:0;">📍</span>
                <span style="font-size:14px; color:#93c5fd; font-weight:500; line-height:1.4;">
                    {{ $orden->direccion }}
                </span>
            </a>
            @else
            <div style="padding:8px 12px; background:rgba(255,255,255,0.04); border-radius:8px; margin-bottom:10px;">
                <span style="font-size:13px; color:#64748b;">Sin dirección registrada</span>
            </div>
            @endif

            {{-- Menús (sin nombre de cliente) --}}
            <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:10px;">
                @foreach($orden->items as $item)
                <span style="font-size:12px; padding:4px 10px; border-radius:6px; font-weight:600;
                              background: {{ $item->tamano === '400kcal' ? 'rgba(168,85,247,0.15)' : 'rgba(78,158,90,0.15)' }};
                              color: {{ $item->tamano === '400kcal' ? '#c084fc' : '#4ade80' }};
                              border: 1px solid {{ $item->tamano === '400kcal' ? 'rgba(168,85,247,0.3)' : 'rgba(78,158,90,0.3)' }};">
                    {{ $item->cantidad }}× {{ $item->producto?->nombre ?? '—' }} {{ $item->tamano }}
                </span>
                @endforeach
            </div>

            {{-- Error de WA --}}
            @if($errMsg)
            <div style="padding:8px 12px; background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.25); border-radius:8px; font-size:12px; color:#fca5a5; margin-bottom:8px;">
                ⚠️ {{ $errMsg }}
            </div>
            @endif

            {{-- Mensaje ya enviado --}}
            @if($msgEnviado)
            <div style="padding:6px 12px; background:rgba(78,158,90,0.1); border-radius:8px; font-size:12px; color:#4ade80; margin-bottom:8px;">
                ✓ {{ $msgEnviado === 'en_camino' ? 'Mensaje "En camino" enviado' : 'Mensaje "Estoy afuera" enviado' }}
            </div>
            @endif

            {{-- Acciones --}}
            @if($entregado)
            <div style="padding:8px 12px; background:rgba(78,158,90,0.12); border-radius:8px; text-align:center; font-size:13px; font-weight:600; color:#4ade80;">
                ✓ Entregado — {{ $orden->transportista_confirma_at->format('H:i') }} hs
            </div>
            @else
            <div style="display:flex; flex-direction:column; gap:8px;">
                {{-- Notificaciones WA --}}
                <div style="display:flex; gap:8px;">
                    <button type="button"
                            wire:click="enviarEnCamino({{ $orden->id }})"
                            wire:loading.attr="disabled"
                            wire:target="enviarEnCamino({{ $orden->id }})"
                            class="ms-btn ms-btn-blue"
                            style="flex:1; justify-content:center;">
                        <span wire:loading.remove wire:target="enviarEnCamino({{ $orden->id }})">🛵 En camino</span>
                        <span wire:loading wire:target="enviarEnCamino({{ $orden->id }})">Enviando…</span>
                    </button>
                    <button type="button"
                            wire:click="enviarEstoyAfuera({{ $orden->id }})"
                            wire:loading.attr="disabled"
                            wire:target="enviarEstoyAfuera({{ $orden->id }})"
                            class="ms-btn ms-btn-yellow"
                            style="flex:1; justify-content:center;">
                        <span wire:loading.remove wire:target="enviarEstoyAfuera({{ $orden->id }})">🏠 Estoy afuera</span>
                        <span wire:loading wire:target="enviarEstoyAfuera({{ $orden->id }})">Enviando…</span>
                    </button>
                </div>
                {{-- Confirmar entrega --}}
                <button type="button"
                        wire:click="marcarEntregado({{ $orden->id }})"
                        wire:loading.attr="disabled"
                        wire:target="marcarEntregado({{ $orden->id }})"
                        class="ms-btn ms-btn-green"
                        style="width:100%; justify-content:center; padding:14px;">
                    <span wire:loading.remove wire:target="marcarEntregado({{ $orden->id }})">
                        ✓ Confirmar entrega{{ $cobrar ? ' · $' . number_format($orden->total, 0, ',', '.') . ' cobrado' : '' }}
                    </span>
                    <span wire:loading wire:target="marcarEntregado({{ $orden->id }})">Guardando…</span>
                </button>
            </div>
            @endif

        </div>
        @endforeach
        </div>

        {{-- Footer --}}
        <div style="text-align:center; margin-top:32px; color:#334155; font-size:12px;">
            Verdeo · {{ $hr->numero }} · Este enlace expira el {{ $hr->expires_at->format('d/m H:i') }} hs
        </div>

    </div>{{-- /max-width --}}

    @endif{{-- /if hr --}}

</div>
