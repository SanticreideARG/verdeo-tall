<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Conversacion;
use App\Models\User;
use App\Models\Setting;

new #[Layout('layouts.app', ['title' => 'Redacción'])] class extends Component {

    public string $zona       = '';
    public string $filtroEstado = 'todos';
    public string $mensaje    = '';
    public string $api        = 'evolution';

    public function mount(): void
    {
        if (! (auth()->user()->isAdmin() || auth()->user()->isResponsableZona())) {
            $this->redirect(route('dashboard'), navigate: true);
        }
    }

    public function with(): array
    {
        $query = Conversacion::whereNotNull('telefono');

        if ($this->zona) {
            $query->where('zona', $this->zona);
        }
        if ($this->filtroEstado !== 'todos') {
            $query->where('estado', $this->filtroEstado);
        }

        $destinatarios = $query->orderBy('updated_at', 'desc')->limit(200)->get();
        $primero       = $destinatarios->first();

        // Build live preview substituting variables with first recipient's data
        $preview = $this->mensaje;
        if ($primero) {
            $ultimoPedido = 'sin pedidos';
            $userVinculado = User::where('whatsapp', $primero->telefono)->first();
            if ($userVinculado) {
                $ultimoPedido = $userVinculado->ordenes()->latest()->first()?->numero ?? 'sin pedidos';
            }
            $zonaLabel = match($primero->zona ?? '') {
                'bsas'      => 'Buenos Aires',
                'valle_nqn' => 'Valle NQN / Roca',
                'cordoba'   => 'Córdoba',
                'mendoza'   => 'Mendoza',
                default     => $primero->zona ?? '',
            };
            $preview = str_replace(
                ['{' . '{nombre}}', '{' . '{zona}}', '{' . '{ultimo_pedido}}'],
                [$primero->nombre ?? 'Cliente', $zonaLabel, $ultimoPedido],
                $preview
            );
        }

        return [
            'destinatarios' => $destinatarios,
            'totalDest'     => $destinatarios->count(),
            'preview'       => $preview,
            'zonas'         => [
                ''          => 'Todas las zonas',
                'bsas'      => 'Buenos Aires',
                'valle_nqn' => 'Valle NQN / Roca',
                'cordoba'   => 'Córdoba',
                'mendoza'   => 'Mendoza',
            ],
        ];
    }

}; ?>

<div class="max-w-5xl mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-2">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
             style="background: rgba(96,165,250,0.12); border: 1px solid rgba(96,165,250,0.25);">
            <svg width="18" height="18" fill="none" stroke="#60a5fa" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/>
            </svg>
        </div>
        <div>
            <h2 class="font-condensed font-bold text-xl" style="color: var(--vd-text);">Redacción masiva</h2>
            <p class="text-xs" style="color: var(--vd-muted);">Composición y envío de mensajes a grupos de contactos</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

        {{-- Left: Destinatarios + Redacción --}}
        <div class="lg:col-span-3 space-y-5">

            {{-- 1. Destinatarios --}}
            <div class="card">
                <h3 class="font-condensed font-bold uppercase tracking-widest text-xs mb-4"
                    style="color: var(--vd-muted); letter-spacing: 1.5px; border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 10px;">
                    1 — Destinatarios
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                    <div>
                        <label class="label">Zona</label>
                        <select wire:model.live="zona" class="input">
                            @foreach($zonas as $val => $lbl)
                            <option value="{{ $val }}">{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Estado de conversación</label>
                        <select wire:model.live="filtroEstado" class="input">
                            <option value="todos">Todos</option>
                            <option value="abierta">Abierta</option>
                            <option value="esperando">Esperando</option>
                            <option value="cerrada">Cerrada</option>
                        </select>
                    </div>
                </div>

                {{-- Count badge --}}
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-sm font-semibold" style="color: var(--vd-text);">
                        {{ $totalDest }} {{ $totalDest === 1 ? 'destinatario' : 'destinatarios' }}
                    </span>
                    @if($totalDest > 0)
                    <span class="text-xs px-2 py-0.5 rounded-full"
                          style="background: rgba(78,158,90,0.12); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.25);">
                        seleccionados
                    </span>
                    @endif
                </div>

                {{-- Contacts list --}}
                @if($destinatarios->isNotEmpty())
                <div class="max-h-40 overflow-y-auto rounded-xl" style="border: 1px solid var(--vd-bdr-soft);">
                    @foreach($destinatarios->take(15) as $d)
                    <div class="flex items-center gap-2 px-3 py-2" style="border-bottom: 1px solid var(--vd-bdr-soft);">
                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                             style="background: rgba(78,158,90,0.15); color: #4e9e5a;">
                            {{ strtoupper(substr($d->nombre ?? '?', 0, 1)) }}
                        </div>
                        <span class="text-xs flex-1 truncate" style="color: var(--vd-text-soft);">
                            {{ $d->nombre ?? '—' }}
                        </span>
                        <span class="text-xs font-mono" style="color: var(--vd-muted-2);">
                            {{ $d->telefono ?? '' }}
                        </span>
                    </div>
                    @endforeach
                    @if($totalDest > 15)
                    <div class="px-3 py-2 text-center text-xs" style="color: var(--vd-muted-2);">
                        +{{ $totalDest - 15 }} más…
                    </div>
                    @endif
                </div>
                @else
                <div class="rounded-xl py-6 text-center text-sm"
                     style="border: 2px dashed var(--vd-bdr); color: var(--vd-muted-2);">
                    Sin contactos con los filtros seleccionados
                </div>
                @endif
            </div>

            {{-- 2. Redacción --}}
            <div class="card">
                <h3 class="font-condensed font-bold uppercase tracking-widest text-xs mb-4"
                    style="color: var(--vd-muted); letter-spacing: 1.5px; border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 10px;">
                    2 — Redacción
                </h3>

                {{-- Variables chip bar --}}
                <div class="flex flex-wrap gap-2 mb-3">
                    <span class="text-xs" style="color: var(--vd-muted);">Variables:</span>
                    @foreach(['nombre' => 'Nombre', 'zona' => 'Zona', 'ultimo_pedido' => 'Último pedido'] as $varKey => $etiqueta)
                    <button type="button"
                            @click="
                                const ta = $refs.textareaMsg;
                                const v = '\x7b\x7b{{ $varKey }}\x7d\x7d';
                                const s = ta.selectionStart; const e = ta.selectionEnd;
                                const cur = $wire.mensaje;
                                $wire.set('mensaje', cur.slice(0,s) + v + cur.slice(e));
                                $nextTick(() => { ta.focus(); ta.setSelectionRange(s+v.length, s+v.length); });
                            "
                            class="text-xs px-2.5 py-1 rounded-full font-mono transition-all"
                            style="background: rgba(96,165,250,0.1); color: #60a5fa; border: 1px solid rgba(96,165,250,0.25);"
                            onmouseover="this.style.background='rgba(96,165,250,0.2)'"
                            onmouseout="this.style.background='rgba(96,165,250,0.1)'">
                        {{ '{{'.$varKey.'}}' }}
                    </button>
                    @endforeach
                </div>

                <textarea wire:model.live="mensaje" x-ref="textareaMsg"
                          rows="6" class="input font-mono text-sm leading-relaxed resize-y"
                          placeholder="Hola @{{nombre}}, te escribimos desde Verdeo 🌿&#10;..."></textarea>

                <div class="flex items-center justify-between mt-2">
                    <span class="text-xs" style="color: var(--vd-muted-2);">
                        {{ mb_strlen($mensaje) }} caracteres
                    </span>
                    @if(mb_strlen($mensaje) > 160)
                    <span class="text-xs" style="color: var(--vd-muted-2);">
                        ≈ {{ ceil(mb_strlen($mensaje) / 160) }} SMS
                    </span>
                    @endif
                </div>
            </div>

            {{-- 3. API --}}
            <div class="card">
                <h3 class="font-condensed font-bold uppercase tracking-widest text-xs mb-4"
                    style="color: var(--vd-muted); letter-spacing: 1.5px; border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 10px;">
                    3 — API de envío
                </h3>
                <div class="flex gap-2">
                    <button wire:click="$set('api', 'evolution')"
                            class="flex-1 py-3 rounded-xl text-sm font-semibold transition-all"
                            style="{{ $api === 'evolution'
                                ? 'background: rgba(78,158,90,0.2); color: var(--vd-green-lt); border: 1.5px solid rgba(78,158,90,0.4);'
                                : 'background: var(--vd-bg-2); color: var(--vd-muted); border: 1.5px solid var(--vd-bdr-soft);' }}">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" class="inline mr-1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-13.5 0v-1.5m13.5 1.5v-1.5m0-7.5a3 3 0 00-3-3H8.25a3 3 0 00-3 3m13.5 0a3 3 0 013 3"/>
                        </svg>
                        Evolution API
                    </button>
                    <button wire:click="$set('api', 'meta')"
                            class="flex-1 py-3 rounded-xl text-sm font-semibold transition-all"
                            style="{{ $api === 'meta'
                                ? 'background: rgba(96,165,250,0.15); color: #60a5fa; border: 1.5px solid rgba(96,165,250,0.35);'
                                : 'background: var(--vd-bg-2); color: var(--vd-muted); border: 1.5px solid var(--vd-bdr-soft);' }}">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" class="inline mr-1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 20.25c4.97 0 9-3.694 9-8.25s-4.03-8.25-9-8.25S3 7.444 3 12c0 2.104.859 4.023 2.273 5.48.432.447.74 1.04.586 1.641a4.483 4.483 0 01-.923 1.785A5.969 5.969 0 006 21c1.282 0 2.47-.402 3.445-1.087.81.22 1.668.337 2.555.337z"/>
                        </svg>
                        META (WhatsApp Business)
                    </button>
                </div>
            </div>
        </div>

        {{-- Right: Preview + Send --}}
        <div class="lg:col-span-2 space-y-5">

            {{-- Preview --}}
            <div class="card">
                <h3 class="font-condensed font-bold uppercase tracking-widest text-xs mb-4"
                    style="color: var(--vd-muted); letter-spacing: 1.5px; border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 10px;">
                    Preview — 1er destinatario
                </h3>

                {{-- Phone mockup --}}
                <div class="mx-auto rounded-3xl p-4 max-w-xs"
                     style="background: #0d1117; border: 2px solid rgba(255,255,255,0.08); box-shadow: 0 8px 32px rgba(0,0,0,0.5);">
                    <div class="flex items-center gap-2 mb-3 pb-2" style="border-bottom: 1px solid rgba(255,255,255,0.06);">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center"
                             style="background: linear-gradient(135deg,#3a7d44,#4e9e5a);">
                            <svg width="14" height="14" fill="white" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/>
                            </svg>
                        </div>
                        <span class="text-xs font-semibold text-white opacity-80">Verdeo</span>
                    </div>
                    <div class="rounded-2xl rounded-tl-sm px-3 py-2.5 max-w-full text-sm leading-relaxed whitespace-pre-wrap break-words"
                         style="background: rgba(37,211,102,0.15); color: rgba(240,244,240,0.9); font-size: 13px; min-height: 60px;">
                        @if($preview)
                            {{ $preview }}
                        @else
                            <span style="color: rgba(240,244,240,0.25); font-style: italic;">El mensaje aparecerá aquí…</span>
                        @endif
                    </div>
                    <p class="text-right text-xs mt-1" style="color: rgba(255,255,255,0.25);">{{ now()->format('H:i') }} ✓✓</p>
                </div>

                @if($destinatarios->isEmpty())
                <p class="text-center text-xs mt-3" style="color: var(--vd-muted-2);">
                    Seleccioná destinatarios para ver el preview
                </p>
                @endif
            </div>

            {{-- Send button --}}
            <div class="card">
                <h3 class="font-condensed font-bold uppercase tracking-widest text-xs mb-4"
                    style="color: var(--vd-muted); letter-spacing: 1.5px; border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 10px;">
                    4 — Envío
                </h3>

                @if($totalDest > 0 && mb_strlen($mensaje) > 0)
                <div class="rounded-xl p-3 mb-4 text-sm"
                     style="background: rgba(200,160,48,0.08); border: 1px solid rgba(200,160,48,0.2); color: var(--vd-text-soft);">
                    Se enviaría el mensaje a
                    <strong style="color: #c8a030;">{{ $totalDest }} {{ $totalDest === 1 ? 'contacto' : 'contactos' }}</strong>
                    vía <strong style="color: var(--vd-text);">{{ $api === 'evolution' ? 'Evolution API' : 'META WhatsApp Business' }}</strong>.
                </div>
                @endif

                <button disabled
                        class="w-full flex items-center justify-center gap-2 py-3.5 rounded-xl font-semibold text-sm cursor-not-allowed"
                        style="background: var(--vd-bg-2); color: var(--vd-muted-2); border: 1.5px solid var(--vd-bdr-soft);">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/>
                    </svg>
                    Enviar mensajes
                    <span class="text-xs font-normal px-2 py-0.5 rounded-full ml-1"
                          style="background: rgba(200,160,48,0.15); color: #c8a030; border: 1px solid rgba(200,160,48,0.3);">
                        próximamente
                    </span>
                </button>
                <p class="text-center text-xs mt-3" style="color: var(--vd-muted-2);">
                    El envío real se habilitará al configurar la API en Ajustes.
                </p>
            </div>
        </div>
    </div>
</div>
