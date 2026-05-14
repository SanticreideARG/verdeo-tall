<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use App\Models\ChatMensaje;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.app', ['title' => 'Chat interno'])] class extends Component {

    use WithFileUploads;

    public ?int   $contactoId  = null;
    public string $texto       = '';
    public        $imagen      = null;
    public string $buscar      = '';
    public array  $mensajes    = [];
    public int    $lastMsgId   = 0;

    public function mount(): void
    {
        if (auth()->user()->isCliente()) {
            $this->redirect(route('dashboard'), navigate: true);
        }
    }

    public function with(): array
    {
        $me = auth()->id();

        // Unread per sender — 1 query
        $noLeidosPorContacto = ChatMensaje::where('to_user_id', $me)
            ->whereNull('leido_at')
            ->selectRaw('from_user_id, count(*) as total')
            ->groupBy('from_user_id')
            ->pluck('total', 'from_user_id');

        // Last message per conversation — 1 query
        $ultimosMensajes = ChatMensaje::where(fn ($q) =>
            $q->where('from_user_id', $me)->orWhere('to_user_id', $me)
        )
        ->orderBy('created_at', 'desc')
        ->get()
        ->groupBy(fn ($m) => $m->from_user_id === $me ? $m->to_user_id : $m->from_user_id)
        ->map(fn ($msgs) => $msgs->first());

        // Contacts
        $contactos = User::whereNotIn('role', ['cliente'])
            ->where('id', '!=', $me)
            ->when($this->buscar, fn ($q) => $q->where(fn ($q2) =>
                $q2->where('name', 'like', "%{$this->buscar}%")
                   ->orWhere('apellido', 'like', "%{$this->buscar}%")
            ))
            ->orderBy('name')
            ->get()
            ->map(function ($u) use ($me, $noLeidosPorContacto, $ultimosMensajes) {
                $ultimo   = $ultimosMensajes->get($u->id);
                $noLeidos = $noLeidosPorContacto->get($u->id, 0);

                return [
                    'id'         => $u->id,
                    'nombre'     => $u->nombreCompleto(),
                    'iniciales'  => $u->iniciales(),
                    'fotoUrl'    => $u->fotoUrl(),
                    'rol'        => $u->rolLabel(),
                    'noLeidos'   => (int) $noLeidos,
                    'ultimo'     => $ultimo
                        ? match ($ultimo->tipo) {
                            'ubicacion' => '📍 Ubicación compartida',
                            'imagen'    => '🖼 Imagen',
                            default     => Str::limit($ultimo->contenido ?? '', 34),
                        }
                        : null,
                    'ultimoAt'   => $ultimo?->created_at,
                    'ultimoHora' => $ultimo
                        ? ($ultimo->created_at->isToday()
                            ? $ultimo->created_at->format('H:i')
                            : $ultimo->created_at->format('d/m'))
                        : '',
                ];
            })
            ->sortByDesc('ultimoAt')
            ->values()
            ->all();

        $contactoActual = $this->contactoId
            ? collect($contactos)->firstWhere('id', $this->contactoId)
            : null;

        $totalNoLeidos = $noLeidosPorContacto->sum();
        $meIniciales   = auth()->user()->iniciales();
        $meFotoUrl     = auth()->user()->fotoUrl();

        return compact('contactos', 'contactoActual', 'totalNoLeidos', 'meIniciales', 'meFotoUrl');
    }

    // ── Contact selection ─────────────────────────────────────────────────────

    public function seleccionar(int $id): void
    {
        $this->contactoId = $id;
        $this->texto      = '';
        $this->imagen     = null;
        $this->lastMsgId  = 0;
        $this->cargarMensajes();
    }

    // ── Message loading ───────────────────────────────────────────────────────

    public function cargarMensajes(): void
    {
        if (! $this->contactoId) return;

        $me = auth()->id();

        $this->mensajes = ChatMensaje::where(fn ($q) =>
            $q->where('from_user_id', $me)->where('to_user_id', $this->contactoId)
        )->orWhere(fn ($q) =>
            $q->where('from_user_id', $this->contactoId)->where('to_user_id', $me)
        )
        ->orderBy('created_at')
        ->get()
        ->map(fn ($m) => [
            'id'        => $m->id,
            'from_me'   => $m->from_user_id === $me,
            'tipo'      => $m->tipo,
            'contenido' => $m->contenido,
            'latitud'   => $m->latitud,
            'longitud'  => $m->longitud,
            'direccion' => $m->direccion,
            'archivo'   => $m->archivo ? Storage::url($m->archivo) : null,
            'hora'      => $m->created_at->format('H:i'),
            'fecha'     => $m->created_at->isToday()
                ? null
                : $m->created_at->translatedFormat('j \d\e F, Y'),
            'leido'     => $m->leido_at !== null,
        ])
        ->all();

        if (! empty($this->mensajes)) {
            $this->lastMsgId = collect($this->mensajes)->max('id');
        }

        // Mark incoming as read
        ChatMensaje::where('from_user_id', $this->contactoId)
            ->where('to_user_id', $me)
            ->whereNull('leido_at')
            ->update(['leido_at' => now()]);

        $this->dispatch('mensajes-actualizados');
    }

    /** Lightweight 3-second poll — only reloads when new messages exist */
    public function poll(): void
    {
        if (! $this->contactoId) return;

        $me = auth()->id();

        $newest = ChatMensaje::where(fn ($q) =>
            $q->where('from_user_id', $me)->where('to_user_id', $this->contactoId)
        )->orWhere(fn ($q) =>
            $q->where('from_user_id', $this->contactoId)->where('to_user_id', $me)
        )->max('id') ?? 0;

        if ($newest > $this->lastMsgId) {
            $this->cargarMensajes();
        }
    }

    // ── Send messages ─────────────────────────────────────────────────────────

    public function enviar(): void
    {
        if (! $this->contactoId) return;

        $texto = trim($this->texto);

        if ($texto === '' && ! $this->imagen) return;

        if ($this->imagen) {
            $this->validate(
                ['imagen' => 'image|max:5120'],
                ['imagen.max' => 'Máx 5 MB.']
            );
            ChatMensaje::create([
                'from_user_id' => auth()->id(),
                'to_user_id'   => $this->contactoId,
                'tipo'         => 'imagen',
                'contenido'    => $texto ?: null,
                'archivo'      => $this->imagen->store('chat', 'public'),
            ]);
            $this->imagen = null;
            $this->dispatch('imagen-enviada');
        } elseif ($texto !== '') {
            ChatMensaje::create([
                'from_user_id' => auth()->id(),
                'to_user_id'   => $this->contactoId,
                'tipo'         => 'texto',
                'contenido'    => $texto,
            ]);
        }

        $this->texto = '';
        $this->cargarMensajes();
    }

    public function enviarUbicacion(float $lat, float $lng): void
    {
        if (! $this->contactoId) return;

        // Reverse-geocode via Nominatim (OpenStreetMap)
        $direccion = '';
        try {
            $resp = Http::withHeaders(['User-Agent' => 'Verdeo/1.0'])
                ->timeout(5)
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format'         => 'json',
                    'lat'            => $lat,
                    'lon'            => $lng,
                    'zoom'           => 16,
                    'addressdetails' => 0,
                ]);
            $direccion = $resp->json('display_name') ?? '';
        } catch (\Exception) {}

        ChatMensaje::create([
            'from_user_id' => auth()->id(),
            'to_user_id'   => $this->contactoId,
            'tipo'         => 'ubicacion',
            'latitud'      => $lat,
            'longitud'     => $lng,
            'direccion'    => $direccion ?: null,
        ]);

        $this->cargarMensajes();
    }

}; ?>

<div class="flex gap-4" style="height: calc(100vh - 7rem);" wire:poll.3s="poll">

    {{-- ══ LEFT: Contacts list ══════════════════════════════════════════════ --}}
    <div class="w-72 flex-shrink-0 flex flex-col rounded-2xl overflow-hidden"
         style="background: var(--vd-surface); border: 1px solid var(--vd-bdr-soft);">

        {{-- Header --}}
        <div class="px-4 pt-4 pb-3" style="border-bottom: 1px solid var(--vd-bdr-soft);">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-condensed font-bold text-lg leading-none" style="color: var(--vd-text);">Chat interno</h2>
                @if($totalNoLeidos > 0)
                <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-xs font-bold"
                      style="background: #4e9e5a; color: #fff;">{{ $totalNoLeidos }}</span>
                @endif
            </div>
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
                     width="13" height="13" fill="none" stroke="rgba(240,244,240,0.35)" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                </svg>
                <input wire:model.live.debounce.300ms="buscar" type="text"
                       class="input py-2 text-sm pl-8" placeholder="Buscar usuario…">
            </div>
        </div>

        {{-- Contact list --}}
        <div class="flex-1 overflow-y-auto verdeo-scroll">
            @forelse($contactos as $c)
            <button wire:click="seleccionar({{ $c['id'] }})" wire:key="contact-{{ $c['id'] }}"
                    class="w-full flex items-center gap-3 px-4 py-3 text-left transition-colors duration-100"
                    style="{{ $contactoId === $c['id']
                        ? 'background: rgba(58,125,68,0.12); border-left: 3px solid #4e9e5a; padding-left: 13px;'
                        : 'border-left: 3px solid transparent;' }}"
                    onmouseover="{{ $contactoId !== $c['id'] ? "this.style.background='rgba(255,255,255,0.04)'" : '' }}"
                    onmouseout="{{ $contactoId !== $c['id'] ? "this.style.background=''" : '' }}">

                {{-- Avatar --}}
                <div class="flex-shrink-0 relative" style="width:40px;height:40px;">
                    @if($c['fotoUrl'])
                    <img src="{{ $c['fotoUrl'] }}" alt="{{ $c['iniciales'] }}"
                         class="rounded-full object-cover block"
                         style="width:40px;height:40px;border:1px solid rgba(78,158,90,0.3);">
                    @else
                    <div class="rounded-full flex items-center justify-center font-condensed font-bold text-sm select-none"
                         style="width:40px;height:40px;background:linear-gradient(135deg,#3a7d44,#4e9e5a);color:#fff;">
                        {{ $c['iniciales'] }}
                    </div>
                    @endif
                    @if($c['noLeidos'] > 0)
                    <span class="absolute -top-0.5 -right-0.5 min-w-[16px] h-4 px-1 rounded-full text-xs font-bold flex items-center justify-center"
                          style="background:#c8a030;color:#fff;font-size:9px;line-height:1;">{{ $c['noLeidos'] }}</span>
                    @endif
                </div>

                {{-- Name + last message --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-baseline justify-between gap-1 mb-0.5">
                        <span class="text-sm font-semibold truncate" style="color: var(--vd-text);">{{ $c['nombre'] }}</span>
                        <span class="text-xs flex-shrink-0" style="color: var(--vd-muted-2); font-size: 10px;">{{ $c['ultimoHora'] }}</span>
                    </div>
                    <span class="text-xs block truncate" style="color: {{ $c['noLeidos'] > 0 ? 'var(--vd-text-soft)' : 'var(--vd-muted)' }}; font-weight: {{ $c['noLeidos'] > 0 ? '600' : '400' }};">
                        {{ $c['ultimo'] ?? $c['rol'] }}
                    </span>
                </div>
            </button>
            @empty
            <div class="px-4 py-10 text-center text-sm" style="color: var(--vd-muted-2);">
                {{ $buscar ? 'Sin resultados.' : 'Sin usuarios disponibles.' }}
            </div>
            @endforelse
        </div>
    </div>

    {{-- ══ RIGHT: Chat window ═══════════════════════════════════════════════ --}}
    <div class="flex-1 flex flex-col min-w-0 rounded-2xl overflow-hidden"
         style="background: var(--vd-surface); border: 1px solid var(--vd-bdr-soft);">

        @if($contactoId && $contactoActual)

        {{-- Header --}}
        <div class="px-5 py-3 flex items-center gap-3 flex-shrink-0"
             style="border-bottom: 1px solid var(--vd-bdr-soft); background: rgba(255,255,255,0.02);">
            @if($contactoActual['fotoUrl'])
            <img src="{{ $contactoActual['fotoUrl'] }}" alt="{{ $contactoActual['iniciales'] }}"
                 class="w-9 h-9 rounded-full flex-shrink-0 object-cover"
                 style="border: 1px solid rgba(78,158,90,0.3);">
            @else
            <div class="w-9 h-9 rounded-full flex-shrink-0 flex items-center justify-center font-condensed font-bold text-sm select-none"
                 style="background: linear-gradient(135deg, #3a7d44, #4e9e5a); color: #fff;">
                {{ $contactoActual['iniciales'] }}
            </div>
            @endif
            <div>
                <div class="font-semibold text-sm leading-tight" style="color: var(--vd-text);">{{ $contactoActual['nombre'] }}</div>
                <div class="text-xs" style="color: var(--vd-muted);">{{ $contactoActual['rol'] }}</div>
            </div>
        </div>

        {{-- Messages ─────────────────────────────────────────────────────── --}}
        <div id="mensajes-container"
             class="flex-1 overflow-y-auto verdeo-scroll px-5 py-4"
             x-data="{}"
             x-init="
                $el.scrollTop = $el.scrollHeight;
                Livewire.on('mensajes-actualizados', () => {
                    $nextTick(() => { $el.scrollTop = $el.scrollHeight; });
                });
             ">

            @forelse($mensajes as $idx => $msg)
            @php
                $prev     = $mensajes[$idx - 1] ?? null;
                $showDate = ! $prev || ($prev['fecha'] ?? '') !== ($msg['fecha'] ?? '');
            @endphp

            {{-- Date separator --}}
            @if($msg['fecha'] && $showDate)
            <div class="flex justify-center my-4">
                <span class="text-xs px-3 py-1 rounded-full" style="background: rgba(255,255,255,0.06); color: var(--vd-muted-2);">
                    {{ $msg['fecha'] }}
                </span>
            </div>
            @endif

            {{-- Message row --}}
            <div class="flex {{ $msg['from_me'] ? 'justify-end' : 'justify-start' }} items-end gap-2 mb-1.5" wire:key="msg-{{ $msg['id'] }}">

                {{-- Avatar izq (solo mensajes entrantes) --}}
                @if(! $msg['from_me'])
                @if($contactoActual['fotoUrl'])
                <img src="{{ $contactoActual['fotoUrl'] }}" alt="{{ $contactoActual['iniciales'] }}"
                     class="w-6 h-6 rounded-full flex-shrink-0 object-cover self-end"
                     style="border: 1px solid rgba(78,158,90,0.25);">
                @else
                <div class="w-6 h-6 rounded-full flex-shrink-0 flex items-center justify-center font-condensed font-bold self-end"
                     style="font-size: 9px; background: linear-gradient(135deg,#3a7d44,#4e9e5a); color:#fff; flex-shrink:0;">
                    {{ $contactoActual['iniciales'] }}
                </div>
                @endif
                @endif

                <div style="max-width: min(75%, 440px);">

                {{-- Avatar der (solo mensajes propios) --}}
                @if($msg['from_me'])
                {{-- placeholder vacío para alinear --}}
                @endif

                @if($msg['tipo'] === 'texto')
                {{-- ── Text bubble ── --}}
                <div class="px-4 py-2.5 {{ $msg['from_me'] ? 'rounded-2xl rounded-tr-sm' : 'rounded-2xl rounded-tl-sm' }}"
                     style="{{ $msg['from_me']
                        ? 'background: linear-gradient(135deg,#3a7d44,#4e9e5a); color:#fff;'
                        : 'background: rgba(255,255,255,0.07); color:var(--vd-text); border:1px solid rgba(255,255,255,0.08);' }}">
                    <p class="text-sm leading-relaxed whitespace-pre-wrap break-words">{{ $msg['contenido'] }}</p>
                    <div class="flex items-center justify-end gap-1 mt-1">
                        <span style="font-size:10px; opacity:.6;">{{ $msg['hora'] }}</span>
                        @if($msg['from_me'])
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" style="opacity:{{ $msg['leido'] ? 1 : .45 }}; flex-shrink:0;">
                            <path stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" d="M5 12l5 5L20 7"/>
                        </svg>
                        @endif
                    </div>
                </div>

                @elseif($msg['tipo'] === 'ubicacion')
                {{-- ── Location card ── --}}
                <div class="{{ $msg['from_me'] ? 'rounded-2xl rounded-tr-sm' : 'rounded-2xl rounded-tl-sm' }} overflow-hidden"
                     style="{{ $msg['from_me']
                        ? 'border:1px solid rgba(78,158,90,0.45); background:rgba(58,125,68,0.12);'
                        : 'border:1px solid rgba(255,255,255,0.1); background:rgba(255,255,255,0.05);' }}">
                    <div class="px-4 py-3">
                        <div class="flex items-start gap-2.5 mb-2.5">
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0"
                                 style="background:rgba(58,125,68,0.3);">
                                <svg width="16" height="16" fill="none" stroke="#6dbf7a" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M12 22s7-7 7-13a7 7 0 10-14 0c0 6 7 13 7 13z"/>
                                    <circle cx="12" cy="9" r="2.5"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold mb-0.5" style="color:#6dbf7a; letter-spacing:.5px; text-transform:uppercase;">Ubicación compartida</p>
                                @if($msg['direccion'])
                                <p class="text-xs leading-snug break-words" style="color:var(--vd-text-soft);">{{ Str::limit($msg['direccion'], 90) }}</p>
                                @endif
                                <p class="text-xs font-mono mt-1" style="color:var(--vd-muted);">
                                    {{ number_format($msg['latitud'], 5) }}, {{ number_format($msg['longitud'], 5) }}
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-2 mb-2">
                            <a href="https://www.google.com/maps?q={{ $msg['latitud'] }},{{ $msg['longitud'] }}"
                               target="_blank"
                               class="flex-1 text-center text-xs py-1.5 rounded-lg"
                               style="background:rgba(58,125,68,0.2);color:#6dbf7a;border:1px solid rgba(78,158,90,0.3);"
                               onmouseover="this.style.background='rgba(58,125,68,0.35)'"
                               onmouseout="this.style.background='rgba(58,125,68,0.2)'">
                                Google Maps ↗
                            </a>
                            <a href="https://www.openstreetmap.org/?mlat={{ $msg['latitud'] }}&mlon={{ $msg['longitud'] }}&zoom=16"
                               target="_blank"
                               class="flex-1 text-center text-xs py-1.5 rounded-lg"
                               style="background:rgba(255,255,255,0.06);color:var(--vd-muted);border:1px solid rgba(255,255,255,0.1);"
                               onmouseover="this.style.background='rgba(255,255,255,0.12)'"
                               onmouseout="this.style.background='rgba(255,255,255,0.06)'">
                                OpenStreetMap ↗
                            </a>
                        </div>
                        <div class="text-right">
                            <span style="font-size:10px;opacity:.5;">{{ $msg['hora'] }}</span>
                        </div>
                    </div>
                </div>

                @elseif($msg['tipo'] === 'imagen')
                {{-- ── Image message ── --}}
                <div class="{{ $msg['from_me'] ? 'rounded-2xl rounded-tr-sm' : 'rounded-2xl rounded-tl-sm' }} overflow-hidden"
                     style="{{ $msg['from_me']
                        ? 'border:1px solid rgba(78,158,90,0.45);'
                        : 'border:1px solid rgba(255,255,255,0.1);' }}">
                    <a href="{{ $msg['archivo'] }}" target="_blank" class="block">
                        <img src="{{ $msg['archivo'] }}" alt="Imagen"
                             class="block w-full" style="max-height:240px;object-fit:cover;">
                    </a>
                    @if($msg['contenido'])
                    <div class="px-3 py-2 text-sm"
                         style="{{ $msg['from_me']
                            ? 'background:linear-gradient(135deg,#3a7d44,#4e9e5a);color:#fff;'
                            : 'background:rgba(255,255,255,0.06);color:var(--vd-text);' }}">
                        {{ $msg['contenido'] }}
                    </div>
                    @endif
                    <div class="flex justify-end px-3 py-1"
                         style="{{ $msg['from_me'] ? 'background:rgba(58,125,68,0.25);' : 'background:rgba(255,255,255,0.03);' }}">
                        <span style="font-size:10px;opacity:.5;">{{ $msg['hora'] }}</span>
                    </div>
                </div>
                @endif

                </div>

                {{-- Avatar der (mensajes propios) --}}
                @if($msg['from_me'])
                @if($meFotoUrl)
                <img src="{{ $meFotoUrl }}" alt="{{ $meIniciales }}"
                     class="w-6 h-6 rounded-full flex-shrink-0 object-cover self-end"
                     style="border: 1px solid rgba(78,158,90,0.25);">
                @else
                <div class="w-6 h-6 rounded-full flex-shrink-0 flex items-center justify-center font-condensed font-bold self-end"
                     style="font-size: 9px; background: linear-gradient(135deg,#3a7d44,#4e9e5a); color:#fff;">
                    {{ $meIniciales }}
                </div>
                @endif
                @endif

            </div>

            @empty
            <div class="flex flex-col items-center justify-center h-full py-16 gap-3"
                 style="color:var(--vd-muted-2);">
                <svg width="44" height="44" fill="none" stroke="rgba(78,158,90,0.3)" stroke-width="1.2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"/>
                </svg>
                <p class="text-sm" style="color:var(--vd-muted);">Comenzá la conversación con {{ $contactoActual['nombre'] }}</p>
            </div>
            @endforelse
        </div>

        {{-- ── Input bar ──────────────────────────────────────────────────── --}}
        <div class="px-4 py-3 flex-shrink-0"
             style="border-top:1px solid var(--vd-bdr-soft);"
             x-data="{
                imgPreview: null,
                ubicLoading: false,
                ubicError: '',
                resetImg() { this.imgPreview = null; },
                compartirUbicacion() {
                    if (!navigator.geolocation) {
                        this.ubicError = 'Tu dispositivo no soporta geolocalización.';
                        return;
                    }
                    this.ubicLoading = true;
                    this.ubicError = '';
                    navigator.geolocation.getCurrentPosition(
                        (pos) => {
                            $wire.enviarUbicacion(pos.coords.latitude, pos.coords.longitude);
                            this.ubicLoading = false;
                        },
                        (err) => {
                            this.ubicError = err.code === 1
                                ? 'Permiso denegado. Habilitá la ubicación en tu navegador.'
                                : 'No se pudo obtener la ubicación.';
                            this.ubicLoading = false;
                        },
                        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                    );
                }
             }"
             @imagen-enviada.window="resetImg()">

            {{-- Image preview --}}
            <div x-show="imgPreview" x-cloak class="mb-2 relative inline-block">
                <img :src="imgPreview" class="h-20 w-auto rounded-xl object-cover"
                     style="border:1px solid rgba(78,158,90,0.4);">
                <button type="button"
                        @click="resetImg(); $wire.set('imagen', null)"
                        class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold"
                        style="background:#ef4444;color:#fff;line-height:1;">✕</button>
            </div>

            {{-- Geolocation error --}}
            <p x-show="ubicError" x-text="ubicError" x-cloak class="text-xs mb-1.5" style="color:#fca5a5;"></p>

            {{-- Row --}}
            <div class="flex items-end gap-2">

                {{-- Pin / location --}}
                <button type="button"
                        @click="compartirUbicacion()"
                        :disabled="ubicLoading"
                        class="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center transition-colors"
                        style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);color:var(--vd-muted);"
                        onmouseover="this.style.background='rgba(58,125,68,0.15)';this.style.borderColor='rgba(78,158,90,0.4)';this.style.color='#6dbf7a'"
                        onmouseout="this.style.background='rgba(255,255,255,0.05)';this.style.borderColor='rgba(255,255,255,0.1)';this.style.color='var(--vd-muted)'"
                        title="Compartir ubicación">
                    <span x-show="!ubicLoading">
                        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M12 22s7-7 7-13a7 7 0 10-14 0c0 6 7 13 7 13z"/><circle cx="12" cy="9" r="2.5"/>
                        </svg>
                    </span>
                    <span x-show="ubicLoading" class="animate-spin" style="display:none;">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                        </svg>
                    </span>
                </button>

                {{-- Image picker --}}
                <label class="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center cursor-pointer transition-colors"
                       style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);color:var(--vd-muted);"
                       onmouseover="this.style.background='rgba(58,125,68,0.15)';this.style.borderColor='rgba(78,158,90,0.4)';this.style.color='#6dbf7a'"
                       onmouseout="this.style.background='rgba(255,255,255,0.05)';this.style.borderColor='rgba(255,255,255,0.1)';this.style.color='var(--vd-muted)'"
                       title="Enviar imagen">
                    <input type="file" wire:model="imagen" accept="image/*" class="sr-only"
                           @change="const f=$event.target.files[0];if(f){const r=new FileReader();r.onload=e=>imgPreview=e.target.result;r.readAsDataURL(f);}else{imgPreview=null;}">
                    <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>
                        <path d="M21 15l-5-5L5 21"/>
                    </svg>
                </label>

                {{-- Textarea --}}
                <textarea wire:model="texto"
                          @keydown.enter="if(!$event.shiftKey){ $event.preventDefault(); $wire.enviar(); }"
                          @input="$el.style.height='auto'; $el.style.height=Math.min($el.scrollHeight,120)+'px'"
                          rows="1"
                          class="input flex-1 resize-none py-2.5 text-sm"
                          style="max-height:120px;overflow-y:auto;line-height:1.45;"
                          placeholder="Escribí un mensaje… (Enter envía · Shift+Enter nueva línea)"></textarea>

                {{-- Send --}}
                <button wire:click="enviar"
                        class="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center transition-all"
                        style="background:linear-gradient(135deg,#3a7d44,#4e9e5a);color:#fff;"
                        onmouseover="this.style.filter='brightness(1.15)'"
                        onmouseout="this.style.filter=''">
                    <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <line x1="22" y1="2" x2="11" y2="13"/>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                    </svg>
                </button>

            </div>
        </div>

        @else
        {{-- ── Empty state ── --}}
        <div class="flex-1 flex flex-col items-center justify-center gap-4" style="color:var(--vd-muted-2);">
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center"
                 style="background:rgba(58,125,68,0.08);border:1px solid rgba(78,158,90,0.15);">
                <svg width="28" height="28" fill="none" stroke="rgba(78,158,90,0.5)" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"/>
                </svg>
            </div>
            <div class="text-center">
                <p class="text-sm font-semibold mb-1" style="color:var(--vd-text-soft);">Chat interno Verdeo</p>
                <p class="text-xs">Seleccioná un contacto para comenzar</p>
            </div>
        </div>
        @endif
    </div>

</div>
