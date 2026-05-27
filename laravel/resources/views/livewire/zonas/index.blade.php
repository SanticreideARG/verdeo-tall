<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use App\Models\Zona;
use App\Models\User;
use App\Models\Conversacion;
use Illuminate\Support\Str;

new #[Layout('layouts.app', ['title' => 'Zonas'])] class extends Component {

    use WithFileUploads;

    // ── Modal state ──────────────────────────────────────────────────────────
    public bool   $showModal      = false;
    public ?int   $editingId      = null;
    public ?int   $deletingId     = null;
    public string $deletingNombre = '';

    // ── Form fields ──────────────────────────────────────────────────────────
    public string  $nombre          = '';
    public string  $alcance         = '';
    public string  $ciudad          = '';
    public string  $caracteristica  = '';
    public ?int    $responsable_id  = null;
    public string  $precio_400kcal  = '';
    public string  $precio_250kcal  = '';
    public array   $menus_semanales = [];
    public bool    $activa          = true;
    public string  $whatsapp        = '';
    public         $fotoFile        = null;
    public ?string $fotoActual      = null;

    public function mount(): void
    {
        if (auth()->user()->isCliente()) {
            $this->redirect(route('dashboard'), navigate: true);
        }
    }

    public function with(): array
    {
        return [
            'zonas'        => Zona::with('responsable')->orderBy('nombre')->get(),
            'responsables' => User::whereIn('role', ['admin', 'responsable_zona'])
                                  ->orderBy('name')->get(),
            'convActivas'  => Conversacion::activas()
                                  ->selectRaw('zona, count(*) as total')
                                  ->groupBy('zona')
                                  ->pluck('total', 'zona'),
        ];
    }

    public function abrirCrear(): void
    {
        if (! auth()->user()->isAdmin()) return;
        $this->resetForm();
        $this->editingId = null;
        $this->showModal = true;
    }

    public function abrirEditar(int $id): void
    {
        if (! auth()->user()->isAdmin()) return;
        $zona = Zona::findOrFail($id);
        $this->editingId        = $id;
        $this->nombre           = $zona->nombre;
        $this->alcance          = $zona->alcance ?? '';
        $this->ciudad           = $zona->ciudad ?? '';
        $this->caracteristica   = $zona->caracteristica ?? '';
        $this->responsable_id   = $zona->responsable_id;
        $this->precio_400kcal   = $zona->precio_400kcal !== null ? (string) $zona->precio_400kcal : '';
        $this->precio_250kcal   = $zona->precio_250kcal !== null ? (string) $zona->precio_250kcal : '';
        $this->menus_semanales  = $this->normalizeMenus($zona->menus_semanales ?? []);
        $this->activa           = $zona->activa;
        $this->whatsapp         = $zona->whatsapp ?? '';
        $this->fotoActual       = $zona->foto;
        $this->fotoFile         = null;
        $this->showModal        = true;
    }

    /** Ensure every menu entry has the expected keys + platos as plain array */
    private function normalizeMenus(array $menus): array
    {
        return array_map(fn($m) => [
            'nombre'      => $m['nombre'] ?? '',
            'tipo'        => $m['tipo'] ?? '',
            'descripcion' => $m['descripcion'] ?? '',
            'platos'      => array_values(
                array_map('strval', $m['platos'] ?? [])
            ),
        ], $menus);
    }

    public function guardar(): void
    {
        if (! auth()->user()->isAdmin()) return;

        $this->validate([
            'nombre'         => 'required|min:2|max:100',
            'alcance'        => 'nullable|max:200',
            'ciudad'         => 'nullable|max:80',
            'caracteristica' => 'nullable|max:1000',
            'responsable_id' => 'nullable|exists:users,id',
            'precio_400kcal' => 'nullable|integer|min:0',
            'precio_250kcal' => 'nullable|integer|min:0',
            'whatsapp'       => 'nullable|max:30',
            'fotoFile'       => 'nullable|image|max:3072',
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.min'      => 'Mínimo 2 caracteres.',
            'fotoFile.image'  => 'Debe ser una imagen.',
            'fotoFile.max'    => 'Máx 3 MB.',
        ]);

        // Clean menus: drop empty names, strip empty plato strings
        $menus = array_values(array_filter(
            $this->menus_semanales,
            fn($m) => ! empty(trim($m['nombre'] ?? ''))
        ));
        $menus = array_map(function ($m) {
            $m['platos'] = array_values(array_filter(
                $m['platos'] ?? [],
                fn($p) => ! empty(trim((string) $p))
            ));
            return $m;
        }, $menus);

        $data = [
            'nombre'          => $this->nombre,
            'alcance'         => $this->alcance ?: null,
            'ciudad'          => $this->ciudad ?: null,
            'caracteristica'  => $this->caracteristica ?: null,
            'responsable_id'  => $this->responsable_id ?: null,
            'precio_400kcal'  => $this->precio_400kcal !== '' ? (int) $this->precio_400kcal : null,
            'precio_250kcal'  => $this->precio_250kcal !== '' ? (int) $this->precio_250kcal : null,
            'menus_semanales' => $menus ?: null,
            'activa'          => $this->activa,
            'whatsapp'        => $this->whatsapp ?: null,
        ];

        if ($this->fotoFile) {
            if ($this->editingId) {
                Zona::findOrFail($this->editingId)->deleteFoto();
            }
            $data['foto'] = $this->fotoFile->store('zonas', 'public');
        }

        if ($this->editingId) {
            Zona::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Zona actualizada.');
        } else {
            $data['slug'] = Zona::generateSlug($this->nombre);
            Zona::create($data);
            session()->flash('success', 'Zona creada correctamente.');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function toggleActiva(int $id): void
    {
        if (! auth()->user()->isAdmin()) return;
        $zona = Zona::findOrFail($id);
        $zona->update(['activa' => ! $zona->activa]);
    }

    public function confirmarEliminar(int $id): void
    {
        if (! auth()->user()->isAdmin()) return;
        $zona = Zona::findOrFail($id);
        $this->deletingId     = $id;
        $this->deletingNombre = $zona->nombre;
    }

    public function eliminar(): void
    {
        if (! auth()->user()->isAdmin()) return;
        $zona = Zona::findOrFail($this->deletingId);
        $zona->deleteFoto();
        $zona->delete();
        $this->deletingId     = null;
        $this->deletingNombre = '';
        session()->flash('success', 'Zona eliminada.');
    }

    public function cancelar(): void
    {
        $this->showModal      = false;
        $this->deletingId     = null;
        $this->deletingNombre = '';
        $this->resetForm();
    }

    // ── Menus semanales ──────────────────────────────────────────────────────

    public function addMenu(): void
    {
        $this->menus_semanales[] = ['nombre' => '', 'tipo' => '', 'descripcion' => '', 'platos' => []];
    }

    public function removeMenu(int $idx): void
    {
        array_splice($this->menus_semanales, $idx, 1);
        $this->menus_semanales = array_values($this->menus_semanales);
    }

    public function addPlatoToMenu(int $midx): void
    {
        $this->menus_semanales[$midx]['platos'][] = '';
    }

    public function removePlatoFromMenu(int $midx, int $pidx): void
    {
        array_splice($this->menus_semanales[$midx]['platos'], $pidx, 1);
        $this->menus_semanales[$midx]['platos'] = array_values($this->menus_semanales[$midx]['platos']);
    }

    private function resetForm(): void
    {
        $this->nombre          = '';
        $this->alcance         = '';
        $this->ciudad          = '';
        $this->caracteristica  = '';
        $this->responsable_id  = null;
        $this->precio_400kcal  = '';
        $this->precio_250kcal  = '';
        $this->menus_semanales = [];
        $this->activa          = true;
        $this->whatsapp        = '';
        $this->fotoFile        = null;
        $this->fotoActual      = null;
        $this->resetValidation();
    }

}; ?>

<div>

    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="mb-6 badge-green px-4 py-3 rounded-xl text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <p class="text-sm" style="color: var(--vd-muted);">
            {{ $zonas->count() }} zona{{ $zonas->count() !== 1 ? 's' : '' }} configurada{{ $zonas->count() !== 1 ? 's' : '' }}
        </p>
        @if(auth()->user()->isAdmin())
        <button wire:click="abrirCrear" class="btn-primary text-sm flex items-center gap-2">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            Nueva zona
        </button>
        @endif
    </div>

    {{-- Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @forelse($zonas as $zona)
        <div class="card p-0 overflow-hidden" style="border: 1px solid var(--vd-bdr-soft);">

            {{-- Foto / banner --}}
            <div class="relative h-28 flex-shrink-0"
                 style="background: {{ $zona->foto ? '' : 'linear-gradient(135deg, rgba(58,125,68,0.22), rgba(78,158,90,0.08))' }};">
                @if($zona->fotoUrl())
                    <img src="{{ $zona->fotoUrl() }}" alt="{{ $zona->nombre }}"
                         class="absolute inset-0 w-full h-full object-cover">
                    <div class="absolute inset-0" style="background: linear-gradient(to top, rgba(11,24,40,0.78) 0%, transparent 55%);"></div>
                @else
                    <div class="absolute inset-0 flex items-center justify-center opacity-40">
                        <svg width="28" height="28" fill="none" stroke="rgba(78,158,90,0.7)" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z"/>
                        </svg>
                    </div>
                @endif
                <div class="absolute bottom-0 left-0 right-0 px-4 pb-2.5 flex items-end justify-between">
                    <div>
                        <h3 class="font-condensed font-bold text-base leading-tight"
                            style="color: {{ $zona->foto ? '#fff' : 'var(--vd-text)' }}; text-shadow: {{ $zona->foto ? '0 1px 6px rgba(0,0,0,0.7)' : 'none' }};">
                            {{ $zona->nombre }}
                        </h3>
                        @if($zona->alcance)
                        <p class="text-xs leading-tight" style="color: {{ $zona->foto ? 'rgba(255,255,255,0.72)' : 'var(--vd-muted)' }}; text-shadow: {{ $zona->foto ? '0 1px 4px rgba(0,0,0,0.5)' : 'none' }};">{{ $zona->alcance }}</p>
                        @endif
                    </div>
                    @if(auth()->user()->isAdmin())
                    <button wire:click="toggleActiva({{ $zona->id }})"
                            class="{{ $zona->activa ? 'badge-green' : 'badge-gray' }} cursor-pointer select-none text-xs flex-shrink-0 ml-2">
                        {{ $zona->activa ? 'Activa' : 'Inactiva' }}
                    </button>
                    @else
                    <span class="{{ $zona->activa ? 'badge-green' : 'badge-gray' }} text-xs flex-shrink-0 ml-2">
                        {{ $zona->activa ? 'Activa' : 'Inactiva' }}
                    </span>
                    @endif
                </div>
            </div>

            {{-- Body --}}
            <div class="px-4 py-3 space-y-2.5">

                @if($zona->caracteristica)
                <p class="text-xs" style="color: var(--vd-text-soft);">{{ Str::limit($zona->caracteristica, 120) }}</p>
                @endif

                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="rounded-lg px-2.5 py-1.5" style="background: var(--vd-input-bg); border: 1px solid var(--vd-bdr-soft);">
                        <p class="mb-0.5" style="color: var(--vd-muted-2);">Responsable</p>
                        <p class="font-medium" style="color: var(--vd-text);">{{ $zona->responsable?->nombreCompleto() ?? '—' }}</p>
                    </div>
                    <div class="rounded-lg px-2.5 py-1.5" style="background: var(--vd-input-bg); border: 1px solid var(--vd-bdr-soft);">
                        <p class="mb-0.5" style="color: var(--vd-muted-2);">WhatsApp</p>
                        <p class="font-mono text-xs" style="color: var(--vd-text);">{{ $zona->whatsapp ?? '—' }}</p>
                    </div>
                </div>

                @if($zona->precio_400kcal || $zona->precio_250kcal)
                <div class="rounded-lg px-2.5 py-2" style="background: rgba(200,160,48,0.06); border: 1px solid rgba(200,160,48,0.2);">
                    <p class="text-xs mb-1.5 font-condensed uppercase tracking-wide" style="color: rgba(200,160,48,0.8); letter-spacing: 1px; font-size: 10px;">Precio de Menús</p>
                    <div class="flex gap-5 text-xs">
                        <div>
                            <span style="color: var(--vd-muted); font-size: 10px;">400 Kcal</span>
                            <p class="font-condensed font-bold" style="color: #c8a030;">{{ $zona->precioFormateado('400kcal') }}</p>
                        </div>
                        <div>
                            <span style="color: var(--vd-muted); font-size: 10px;">250 Kcal</span>
                            <p class="font-condensed font-bold" style="color: #c8a030;">{{ $zona->precioFormateado('250kcal') }}</p>
                        </div>
                    </div>
                </div>
                @endif

                @if($zona->menus_semanales && count($zona->menus_semanales))
                <div class="rounded-lg px-2.5 py-2" style="background: var(--vd-input-bg); border: 1px solid var(--vd-bdr-soft);">
                    <p class="text-xs mb-1.5 font-condensed uppercase tracking-wide" style="color: var(--vd-muted-2); letter-spacing: 1px; font-size: 10px;">
                        Menús · {{ count($zona->menus_semanales) }}
                    </p>
                    <div class="space-y-0.5">
                        @foreach($zona->menus_semanales as $menu)
                        <div class="flex items-center justify-between text-xs">
                            <div class="flex items-center gap-1.5 min-w-0">
                                <span class="flex-shrink-0 w-1.5 h-1.5 rounded-full" style="background: #4e9e5a;"></span>
                                <span class="font-medium truncate" style="color: var(--vd-text);">{{ $menu['nombre'] }}</span>
                            </div>
                            @if(! empty($menu['platos']))
                            <span class="flex-shrink-0 ml-2" style="color: var(--vd-muted-2); font-size: 10px;">{{ count($menu['platos']) }} platos</span>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <div class="flex justify-between text-xs pt-0.5" style="color: var(--vd-muted);">
                    <span>Conversaciones activas</span>
                    <span class="font-medium" style="color: var(--vd-text);">{{ $convActivas[$zona->slug] ?? 0 }}</span>
                </div>

                @if(auth()->user()->isAdmin())
                <div class="flex items-center justify-between gap-2 pt-2" style="border-top: 1px solid var(--vd-bdr-soft);">
                    <a href="{{ route('n8n') }}" target="_blank" class="btn-secondary text-xs px-3 py-1.5">Conectar WhatsApp</a>
                    <div class="flex gap-2">
                        <button wire:click="abrirEditar({{ $zona->id }})" class="btn-secondary text-xs px-3 py-1.5">Editar</button>
                        <button wire:click="confirmarEliminar({{ $zona->id }})"
                                class="btn-secondary text-xs px-3 py-1.5"
                                style="color: #fca5a5; border-color: rgba(220,68,68,0.25);"
                                onmouseover="this.style.background='rgba(220,68,68,0.12)'"
                                onmouseout="this.style.background=''">Eliminar</button>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @empty
        <div class="md:col-span-2 card text-center py-16" style="color: var(--vd-muted);">
            No hay zonas configuradas.
        </div>
        @endforelse
    </div>

    {{-- Delete modal --}}
    @if($deletingId)
    <div class="fixed inset-0 z-50 flex items-center justify-center px-4"
         style="background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);">
        <div class="w-full max-w-sm rounded-2xl p-6"
             style="background: var(--vd-surface-2); border: 1px solid rgba(220,68,68,0.35); box-shadow: 0 24px 60px rgba(0,0,0,0.4);">
            <h3 class="font-condensed font-bold text-lg mb-1" style="color: var(--vd-text);">¿Eliminar zona?</h3>
            <p class="text-sm mb-1" style="color: var(--vd-muted);">
                Estás por eliminar <span class="font-semibold" style="color: var(--vd-text);">{{ $deletingNombre }}</span>.
            </p>
            <p class="text-xs mb-6" style="color: var(--vd-muted-2);">Esta acción no se puede deshacer.</p>
            <div class="flex justify-end gap-3">
                <button wire:click="cancelar" class="btn-secondary">Cancelar</button>
                <button wire:click="eliminar" class="btn-danger">Eliminar</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Create / Edit modal --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto py-6 px-4"
         style="background: rgba(0,0,0,0.55); backdrop-filter: blur(4px);">
        <div class="w-full max-w-xl rounded-2xl my-auto"
             style="background: var(--vd-surface-2); border: 1px solid var(--vd-bdr-soft); box-shadow: 0 24px 60px rgba(0,0,0,0.45);">

            <div class="flex items-center justify-between px-5 py-4" style="border-bottom: 1px solid var(--vd-bdr-soft);">
                <h3 class="font-condensed font-bold text-lg" style="color: var(--vd-text);">
                    {{ $editingId ? 'Editar zona' : 'Nueva zona' }}
                </h3>
                <button wire:click="cancelar" class="btn-secondary text-xs px-3 py-1.5">✕</button>
            </div>

            <form wire:submit="guardar" enctype="multipart/form-data" novalidate class="px-5 py-4 space-y-4">

                {{-- Foto de zona --}}
                <div x-data="{ preview: null }">
                    <label class="label">Foto de la zona</label>
                    <div class="flex items-center gap-4">
                        <div class="w-20 h-16 rounded-xl overflow-hidden flex-shrink-0 flex items-center justify-center"
                             style="background: rgba(58,125,68,0.1); border: 1px solid var(--vd-bdr-soft);">
                            @if($fotoActual)
                                <img x-show="!preview" src="{{ \Illuminate\Support\Facades\Storage::url($fotoActual) }}"
                                     class="w-full h-full object-cover">
                            @else
                                <svg x-show="!preview" width="20" height="20" fill="none" stroke="rgba(78,158,90,0.4)" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                                </svg>
                            @endif
                            <img x-show="preview" :src="preview" class="w-full h-full object-cover" style="display:none;">
                        </div>
                        <div>
                            <label class="btn-secondary cursor-pointer inline-flex text-xs">
                                <input type="file" wire:model="fotoFile" accept="image/*" class="sr-only"
                                       @change="const f=$event.target.files[0]; if(f){const r=new FileReader();r.onload=e=>preview=e.target.result;r.readAsDataURL(f);}else{preview=null;}">
                                {{ $fotoActual ? 'Cambiar foto' : 'Subir foto' }}
                            </label>
                            <p class="text-xs mt-1" style="color: var(--vd-muted-2);">JPG, PNG · Máx 3 MB</p>
                            @error('fotoFile') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                {{-- Nombre + Activa --}}
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label class="label">Nombre <span style="color:#fca5a5">*</span></label>
                        <input type="text" wire:model="nombre"
                               class="input @error('nombre') border-red-400 @enderror" placeholder="Buenos Aires">
                        @error('nombre') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Estado</label>
                        <div class="flex items-center gap-2 mt-2.5">
                            <input type="checkbox" wire:model="activa" id="activa-check"
                                   class="rounded" style="accent-color: #4e9e5a; width:16px; height:16px;">
                            <label for="activa-check" class="text-sm" style="color: var(--vd-text-soft);">Activa</label>
                        </div>
                    </div>
                </div>

                {{-- Ciudad + Alcance --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">
                            Ciudad
                            <span class="ml-1 text-xs font-normal" style="color: var(--vd-muted);">(agrupa zonas en Cocina)</span>
                        </label>
                        <input type="text" wire:model="ciudad" class="input"
                               placeholder="Ej: Buenos Aires">
                        @error('ciudad') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Alcance</label>
                        <input type="text" wire:model="alcance" class="input"
                               placeholder="Ej: CABA y GBA norte · reparto propio">
                    </div>
                </div>

                {{-- Característica --}}
                <div>
                    <label class="label">Característica</label>
                    <textarea wire:model="caracteristica" class="input" rows="2"
                              placeholder="Descripción, particularidades, logística…"></textarea>
                </div>

                {{-- Responsable + WhatsApp --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Responsable</label>
                        <select wire:model="responsable_id" class="input">
                            <option value="">— Sin asignar —</option>
                            @foreach($responsables as $resp)
                                <option value="{{ $resp->id }}">{{ $resp->nombreCompleto() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">WhatsApp de la zona</label>
                        <input type="text" wire:model="whatsapp" class="input" placeholder="5491158393179">
                    </div>
                </div>

                {{-- Precios --}}
                <div>
                    <label class="label">Precio de menús</label>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs mb-1 block" style="color: var(--vd-muted);">Menú 400 Kcal</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm pointer-events-none select-none"
                                      style="color: var(--vd-muted);">$</span>
                                <input type="number" wire:model="precio_400kcal"
                                       class="input pl-7 @error('precio_400kcal') border-red-400 @enderror"
                                       placeholder="80000" min="0">
                            </div>
                            @error('precio_400kcal') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-xs mb-1 block" style="color: var(--vd-muted);">Menú 250 Kcal</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm pointer-events-none select-none"
                                      style="color: var(--vd-muted);">$</span>
                                <input type="number" wire:model="precio_250kcal"
                                       class="input pl-7 @error('precio_250kcal') border-red-400 @enderror"
                                       placeholder="65000" min="0">
                            </div>
                            @error('precio_250kcal') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                {{-- Menús semanales --}}
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="label mb-0">Menús semanales</label>
                        <button type="button" wire:click="addMenu"
                                class="btn-secondary text-xs px-3 py-1" style="color: var(--vd-green-lt);">
                            + Sección
                        </button>
                    </div>
                    <p class="text-xs mb-2.5" style="color: var(--vd-muted-2);">
                        Sincronizado desde Productos · editá manualmente para personalizar esta zona.
                    </p>
                    @if(count($menus_semanales))
                    <div class="space-y-2.5">
                        @foreach($menus_semanales as $midx => $menu)
                        <div class="rounded-xl p-3" style="background: var(--vd-input-bg); border: 1px solid var(--vd-bdr-soft);">
                            {{-- Menu header row --}}
                            <div class="flex items-center gap-2 mb-2">
                                <input type="text"
                                       wire:model="menus_semanales.{{ $midx }}.nombre"
                                       class="input text-sm py-1.5 flex-1 font-semibold"
                                       placeholder="Nombre del menú (ej: Menú Keto)">
                                <button type="button" wire:click="removeMenu({{ $midx }})"
                                        class="flex-shrink-0 text-xs px-2 py-1.5 rounded-lg"
                                        style="color:#fca5a5;background:rgba(220,68,68,0.08);"
                                        onmouseover="this.style.background='rgba(220,68,68,0.18)'"
                                        onmouseout="this.style.background='rgba(220,68,68,0.08)'">✕</button>
                            </div>
                            {{-- Description --}}
                            <input type="text"
                                   wire:model="menus_semanales.{{ $midx }}.descripcion"
                                   class="input text-xs py-1.5 mb-2 w-full"
                                   placeholder="Descripción corta (opcional)">
                            {{-- Platos --}}
                            @if(! empty($menu['platos']))
                            <div class="space-y-1.5 mb-2">
                                @foreach($menu['platos'] as $pidx => $plato)
                                <div class="flex gap-1.5 items-center">
                                    <span class="flex-shrink-0 w-1.5 h-1.5 rounded-full mt-px" style="background:#4e9e5a;"></span>
                                    <input type="text"
                                           wire:model="menus_semanales.{{ $midx }}.platos.{{ $pidx }}"
                                           class="input text-xs py-1 flex-1"
                                           placeholder="Nombre del plato">
                                    <button type="button"
                                            wire:click="removePlatoFromMenu({{ $midx }}, {{ $pidx }})"
                                            class="flex-shrink-0 text-xs px-1.5 py-1 rounded"
                                            style="color:#fca5a5;background:rgba(220,68,68,0.06);"
                                            onmouseover="this.style.background='rgba(220,68,68,0.15)'"
                                            onmouseout="this.style.background='rgba(220,68,68,0.06)'">✕</button>
                                </div>
                                @endforeach
                            </div>
                            @endif
                            <button type="button" wire:click="addPlatoToMenu({{ $midx }})"
                                    class="text-xs px-3 py-1 rounded-lg"
                                    style="color: var(--vd-green-lt); background: rgba(78,158,90,0.08); border: 1px solid rgba(78,158,90,0.2);">
                                + Plato
                            </button>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p class="text-xs py-2.5 px-1" style="color: var(--vd-muted-2);">
                        Sin menús. Guardá cambios en la página de Productos para sincronizar, o agregá una sección manualmente.
                    </p>
                    @endif
                </div>

                {{-- Actions --}}
                <div class="flex justify-end gap-3 pt-2" style="border-top: 1px solid var(--vd-bdr-soft);">
                    <button type="button" wire:click="cancelar" class="btn-secondary">Cancelar</button>
                    <button type="submit" class="btn-primary px-8"
                            wire:loading.attr="disabled" wire:target="guardar">
                        <span wire:loading.remove wire:target="guardar">{{ $editingId ? 'Guardar cambios' : 'Crear zona' }}</span>
                        <span wire:loading wire:target="guardar">Guardando…</span>
                    </button>
                </div>

            </form>
        </div>
    </div>
    @endif

</div>
