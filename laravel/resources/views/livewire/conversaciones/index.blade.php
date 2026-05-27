<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\Conversacion;
use App\Services\AiRouter;

new #[Layout('layouts.app', ['title' => 'Conversaciones'])] class extends Component {

    use WithPagination;

    public string $buscar    = '';
    public string $zona      = '';
    public string $estado    = '';
    public string $canal     = '';
    public string $categoria = 'todos';

    public ?int   $sugerenciaId       = null;
    public string $sugerenciaTexto    = '';
    public string $sugerenciaMensaje  = '';
    public bool   $sugerenciaCargando = false;

    public function updatingBuscar(): void    { $this->resetPage(); }
    public function updatingZona(): void      { $this->resetPage(); }
    public function updatingEstado(): void    { $this->resetPage(); }
    public function updatingCanal(): void     { $this->resetPage(); }
    public function updatingCategoria(): void { $this->resetPage(); }

    public function with(): array
    {
        $activeStates = ['pendiente', 'aprobada', 'lista_para_entrega'];

        $base = Conversacion::query()
            ->when($this->buscar, fn($q) =>
                $q->where(fn($q) =>
                    $q->where('telefono', 'like', "%{$this->buscar}%")
                      ->orWhere('nombre',   'like', "%{$this->buscar}%")
                      ->orWhere('canal_id', 'like', "%{$this->buscar}%")
                )
            )
            ->when($this->zona,   fn($q) => $q->zona($this->zona))
            ->when($this->estado, fn($q) => $q->where('estado', $this->estado))
            ->when($this->canal,  fn($q) => $q->where('canal', $this->canal));

        $hasPendiente = fn($q) => $q->whereHas('usuarioVinculado', fn($u) =>
            $u->whereHas('ordenes', fn($o) => $o->whereIn('estado', $activeStates))
        );
        $hasEntregado = fn($q) => $q->whereHas('usuarioVinculado', fn($u) =>
            $u->whereHas('ordenes', fn($o) => $o->where('estado', 'entregada'))
        );
        $noActivePedido = fn($q) => $q->whereDoesntHave('usuarioVinculado', fn($u) =>
            $u->whereHas('ordenes', fn($o) => $o->whereIn('estado', $activeStates))
        );
        $esConsulta = fn($q) => $q->where(fn($q) =>
            $q->whereDoesntHave('usuarioVinculado')
              ->orWhereHas('usuarioVinculado', fn($u) => $u->whereDoesntHave('ordenes'))
        );

        $counts = [
            'todos'     => (clone $base)->count(),
            'consulta'  => (clone $base)->tap($esConsulta)->count(),
            'pendiente' => (clone $base)->tap($hasPendiente)->count(),
            'entregado' => (clone $base)->tap($hasEntregado)->tap($noActivePedido)->count(),
        ];

        $canalCounts = [
            'whatsapp'  => (clone $base)->where('canal', 'whatsapp')->count(),
            'messenger' => (clone $base)->where('canal', 'messenger')->count(),
            'instagram' => (clone $base)->where('canal', 'instagram')->count(),
        ];

        $conversaciones = (clone $base)
            ->when($this->categoria === 'consulta',  $esConsulta)
            ->when($this->categoria === 'pendiente', $hasPendiente)
            ->when($this->categoria === 'entregado', fn($q) => $q->tap($hasEntregado)->tap($noActivePedido))
            ->orderByDesc('ultimo_mensaje_at')
            ->paginate(20);

        return compact('conversaciones', 'counts', 'canalCounts');
    }

    public function sugerirRespuesta(int $id, AiRouter $ai): void
    {
        $available = $ai->available();
        if (empty($available)) {
            $this->sugerenciaTexto   = 'No hay proveedor IA configurado. Agregá una API key en Ajustes → IA.';
            $this->sugerenciaId      = $id;
            $this->sugerenciaMensaje = '';
            return;
        }

        $conv = Conversacion::findOrFail($id);
        $this->sugerenciaId       = $id;
        $this->sugerenciaMensaje  = $conv->ultimo_mensaje ?? '';
        $this->sugerenciaTexto    = '';
        $this->sugerenciaCargando = true;

        try {
            $context = "Cliente: {$conv->nombre}, Teléfono: {$conv->telefono}, Zona: {$conv->zona}, Estado: {$conv->estado}";
            $this->sugerenciaTexto = $ai->suggestReply($conv->ultimo_mensaje ?? '', $context);
        } catch (\Throwable $e) {
            $this->sugerenciaTexto = 'Error al generar sugerencia: ' . $e->getMessage();
        } finally {
            $this->sugerenciaCargando = false;
        }
    }

    public function cerrarSugerencia(): void
    {
        $this->sugerenciaId       = null;
        $this->sugerenciaTexto    = '';
        $this->sugerenciaMensaje  = '';
        $this->sugerenciaCargando = false;
    }

}; ?>

<div>

    {{-- Sugerencia modal --}}
    @if($sugerenciaId)
    <div class="fixed inset-0 z-50 flex items-center justify-center"
         style="background: rgba(0,0,0,0.55); backdrop-filter: blur(4px);">
        <div class="w-full max-w-lg rounded-2xl p-6"
             style="background: var(--vd-surface-2); border: 1px solid rgba(78,158,90,0.3); box-shadow: 0 24px 60px rgba(0,0,0,0.4);">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <svg width="16" height="16" fill="none" stroke="#4e9e5a" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                    </svg>
                    <h3 class="font-condensed font-bold text-base" style="color: var(--vd-text);">Sugerencia de respuesta</h3>
                </div>
                <button wire:click="cerrarSugerencia" style="color: var(--vd-muted-2);"
                        onmouseover="this.style.color='var(--vd-text)'" onmouseout="this.style.color='var(--vd-muted-2)'">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            @if($sugerenciaMensaje)
            <div class="mb-4 rounded-xl px-4 py-3" style="background: var(--vd-input-bg); border: 1px solid var(--vd-bdr-soft);">
                <p class="text-xs font-condensed uppercase tracking-wide mb-1" style="color: var(--vd-muted-2);">Último mensaje del cliente</p>
                <p class="text-sm" style="color: var(--vd-text-soft);">{{ $sugerenciaMensaje }}</p>
            </div>
            @endif
            <div class="rounded-xl px-4 py-3 mb-4 min-h-[80px]"
                 style="background: rgba(58,125,68,0.08); border: 1px solid rgba(78,158,90,0.2);">
                <p class="text-xs font-condensed uppercase tracking-wide mb-1" style="color: #4e9e5a;">Respuesta sugerida</p>
                @if($sugerenciaCargando)
                <div class="flex items-center gap-2 mt-2">
                    <span class="w-2 h-2 rounded-full animate-bounce" style="background:#4e9e5a;animation-delay:0ms;"></span>
                    <span class="w-2 h-2 rounded-full animate-bounce" style="background:#4e9e5a;animation-delay:150ms;"></span>
                    <span class="w-2 h-2 rounded-full animate-bounce" style="background:#4e9e5a;animation-delay:300ms;"></span>
                    <span class="text-xs" style="color: var(--vd-muted);">Generando...</span>
                </div>
                @else
                <p class="text-sm leading-relaxed" style="color: var(--vd-text);">{{ $sugerenciaTexto }}</p>
                @endif
            </div>
            <div class="flex justify-end gap-3">
                <button wire:click="cerrarSugerencia" class="btn-secondary text-sm">Cerrar</button>
                @if($sugerenciaTexto && !$sugerenciaCargando)
                <button onclick="navigator.clipboard.writeText({{ json_encode($sugerenciaTexto) }}).then(() => { this.textContent = '¡Copiado!'; setTimeout(() => this.textContent = 'Copiar texto', 2000); })"
                        class="btn-primary text-sm">Copiar texto</button>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- Filtros secundarios --}}
    <div class="flex flex-wrap gap-3 mb-3">
        <input type="text" wire:model.live.debounce.300ms="buscar"
               placeholder="Buscar por número o nombre…" class="input w-64">
        <select wire:model.live="zona" class="input w-48">
            <option value="">Todas las zonas</option>
            @foreach(Conversacion::zonas() as $slug => $label)
            <option value="{{ $slug }}">{{ $label }}</option>
            @endforeach
        </select>
        <select wire:model.live="estado" class="input w-40">
            <option value="">Cualquier estado</option>
            @foreach(Conversacion::estadosConv() as $val => $label)
            <option value="{{ $val }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    {{-- Filtro por canal ──────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-2 mb-4">
        @php
        $canalesFiltro = [
            ''          => ['Todos los canales', 'var(--vd-muted)',  null],
            'whatsapp'  => ['WhatsApp',           '#25d366',         '<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>'],
            'messenger' => ['Messenger',           '#0078ff',         '<path d="M12 2C6.36 2 2 6.13 2 11.7c0 2.91 1.19 5.44 3.14 7.17.16.13.26.35.27.56l.05 1.76a.75.75 0 001.05.66l1.96-.87c.17-.07.36-.1.54-.07.9.25 1.9.38 2.99.38 5.64 0 10-4.13 10-9.7S17.64 2 12 2zm6 7.46l-2.93 4.67a1.51 1.51 0 01-2.18.4L10.77 13a.6.6 0 00-.72 0l-2.84 2.16c-.38.29-.88-.17-.63-.58L9.51 9.9a1.51 1.51 0 012.18-.4l2.12 1.53a.6.6 0 00.72 0l2.84-2.16c.38-.29.88.17.63.59z"/>'],
            'instagram' => ['Instagram',           '#e1306c',         '<path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>'],
        ];
        @endphp
        @foreach($canalesFiltro as $slug => [$label, $color, $iconPath])
        @php $activo = $canal === $slug; @endphp
        <button type="button" wire:click="$set('canal', '{{ $slug }}')"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition-all"
                style="{{ $activo
                    ? 'background:' . $color . '22; color:' . $color . '; border:1px solid ' . $color . '55;'
                    : 'background:var(--vd-bg-2); color:var(--vd-muted); border:1px solid var(--vd-bdr);' }}">
            @if($iconPath)
            <svg width="11" height="11" viewBox="0 0 24 24" fill="{{ $activo ? $color : 'currentColor' }}">{!! $iconPath !!}</svg>
            @endif
            {{ $label }}
            @if($slug && isset($canalCounts[$slug]) && $canalCounts[$slug] > 0)
            <span class="rounded-full min-w-[16px] h-4 px-1 flex items-center justify-center"
                  style="font-size:9px; font-weight:700;
                         background: {{ $activo ? $color . '33' : 'rgba(255,255,255,0.07)' }};
                         color: {{ $activo ? $color : 'var(--vd-muted-2)' }};">
                {{ $canalCounts[$slug] }}
            </span>
            @endif
        </button>
        @endforeach
    </div>

    {{-- Tabs de categoría --}}
    <div class="flex gap-1 mb-5 p-1 rounded-xl" style="background: rgba(0,0,0,0.2); border: 1px solid var(--vd-bdr-soft);">
        @foreach([
            'todos'     => ['Todos',     null],
            'consulta'  => ['Consulta',  'rgba(120,120,130,0.5)'],
            'pendiente' => ['Pendiente', 'rgba(200,160,48,0.7)'],
            'entregado' => ['Entregado', 'rgba(78,158,90,0.7)'],
        ] as $tab => [$label, $badgeColor])
        <button type="button" wire:click="$set('categoria', '{{ $tab }}')"
                class="flex-1 flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-sm font-semibold transition-all"
                style="{{ $categoria === $tab
                    ? 'background: rgba(78,158,90,0.18); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.35);'
                    : 'color: var(--vd-muted); border: 1px solid transparent;' }}">
            {{ $label }}
            <span class="rounded-full min-w-[20px] h-5 px-1.5 flex items-center justify-center font-bold"
                  style="font-size: 10px; background: {{ $categoria === $tab ? 'rgba(78,158,90,0.25)' : 'rgba(255,255,255,0.06)' }}; color: {{ $categoria === $tab ? '#4e9e5a' : 'var(--vd-muted-2)' }};">
                {{ $counts[$tab] }}
            </span>
        </button>
        @endforeach
    </div>

    {{-- Tabla --}}
    <div class="card p-0 overflow-hidden">
        <table class="w-full text-sm">
            <thead style="background: var(--vd-bg-2); border-bottom: 1px solid var(--vd-bdr);">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-semibold uppercase tracking-wider" style="color: var(--vd-muted-2);">Contacto</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold uppercase tracking-wider" style="color: var(--vd-muted-2);">Zona</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold uppercase tracking-wider" style="color: var(--vd-muted-2);">Último mensaje</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold uppercase tracking-wider" style="color: var(--vd-muted-2);">Estado</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($conversaciones as $conv)
                <tr wire:key="{{ $conv->id }}"
                    style="border-bottom: 1px solid var(--vd-bdr-soft); cursor: pointer; transition: background .12s;"
                    onmouseover="this.style.background='var(--vd-nav-hover)'"
                    onmouseout="this.style.background=''"
                    onclick="window.location='{{ route('conversaciones.ver', $conv) }}'">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0"
                                 style="background: rgba(58,125,68,0.18); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.25);">
                                {{ strtoupper(substr($conv->nombre ?? '?', 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <p class="font-medium truncate" style="color: var(--vd-text);">{{ $conv->nombre ?? 'Sin nombre' }}</p>
                                <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                                    <p class="text-xs font-mono" style="color: var(--vd-muted-2);">{{ $conv->canalIdentifier() }}</p>
                                    {{-- Canal pill --}}
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full font-semibold leading-none flex-shrink-0"
                                          style="font-size:9px; background: {{ $conv->canalColor() }}18; color: {{ $conv->canalColor() }}; border: 1px solid {{ $conv->canalColor() }}40;">
                                        @if(($conv->canal ?? 'whatsapp') === 'messenger')
                                        <svg width="8" height="8" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.36 2 2 6.13 2 11.7c0 2.91 1.19 5.44 3.14 7.17.16.13.26.35.27.56l.05 1.76a.75.75 0 001.05.66l1.96-.87c.17-.07.36-.1.54-.07.9.25 1.9.38 2.99.38 5.64 0 10-4.13 10-9.7S17.64 2 12 2zm6 7.46l-2.93 4.67a1.51 1.51 0 01-2.18.4L10.77 13a.6.6 0 00-.72 0l-2.84 2.16c-.38.29-.88-.17-.63-.58L9.51 9.9a1.51 1.51 0 012.18-.4l2.12 1.53a.6.6 0 00.72 0l2.84-2.16c.38-.29.88.17.63.59z"/></svg>
                                        @elseif(($conv->canal ?? 'whatsapp') === 'instagram')
                                        <svg width="8" height="8" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                                        @else
                                        <svg width="8" height="8" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                        @endif
                                        {{ $conv->canalLabel() }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4" style="color: var(--vd-muted);">{{ $conv->zonaLabel() }}</td>
                    <td class="px-6 py-4 max-w-xs">
                        <p class="truncate" style="color: var(--vd-text);">{{ $conv->ultimo_mensaje ?? '—' }}</p>
                        @if($conv->ultimo_mensaje_at)
                        <p class="text-xs mt-0.5" style="color: var(--vd-muted-2);">{{ $conv->ultimo_mensaje_at->diffForHumans() }}</p>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <span class="{{ match($conv->estado) {
                            'abierta'   => 'badge-green',
                            'esperando' => 'badge-yellow',
                            default     => 'badge-gray',
                        } }}">{{ ucfirst($conv->estado) }}</span>
                    </td>
                    <td class="px-6 py-4 text-right" onclick="event.stopPropagation()">
                        <div class="flex items-center justify-end gap-2">
                            @if($conv->ultimo_mensaje && (auth()->user()->isAdmin() || auth()->user()->isResponsableZona()))
                            <button wire:click.stop="sugerirRespuesta({{ $conv->id }})"
                                    class="btn-secondary text-xs px-3 py-1.5 flex items-center gap-1.5"
                                    style="color: #4e9e5a; border-color: rgba(78,158,90,0.3);"
                                    onmouseover="this.style.background='rgba(58,125,68,0.1)'"
                                    onmouseout="this.style.background=''">
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                                </svg>
                                IA
                            </button>
                            @endif
                            <a href="{{ route('conversaciones.ver', $conv) }}" wire:navigate
                               class="btn-secondary text-xs px-3 py-1.5 flex items-center gap-1"
                               onclick="event.stopPropagation()">
                                Ver
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-16 text-center">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3"
                             style="background: rgba(58,125,68,0.08);">
                            <svg width="22" height="22" fill="none" stroke="#4e9e5a" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>
                            </svg>
                        </div>
                        <p class="font-semibold mb-1" style="color: var(--vd-muted);">Sin conversaciones en esta categoría</p>
                        <p class="text-sm" style="color: var(--vd-muted-2);">
                            @if($categoria === 'pendiente') Ningún contacto tiene un pedido activo en este momento.
                            @elseif($categoria === 'entregado') Ningún contacto tiene pedidos entregados aún.
                            @elseif($categoria === 'consulta') No hay contactos sin pedidos registrados.
                            @else Conectá una instancia de WhatsApp en <a href="{{ route('zonas') }}" style="color: var(--vd-green-lt);" class="underline">Zonas</a> para comenzar.
                            @endif
                        </p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        @if($conversaciones->hasPages())
        <div class="px-6 py-4" style="border-top: 1px solid var(--vd-bdr);">
            {{ $conversaciones->links() }}
        </div>
        @endif
    </div>
</div>
