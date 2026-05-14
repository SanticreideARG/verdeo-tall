<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use App\Models\User;
use App\Models\Zona;

new #[Layout('layouts.app', ['title' => 'Registrar cliente'])] class extends Component {

    use WithFileUploads;

    public string $nombre    = '';
    public string $apellido  = '';
    public string $whatsapp  = '';
    public string $ciudad    = '';
    public string $direccion = '';
    public string $zona      = '';
    public        $foto      = null;
    public int    $proximoNumero;

    public function mount(): void
    {
        if (! auth()->user()->isAdmin()) {
            $this->redirect(route('dashboard'), navigate: true);
            return;
        }
        $this->proximoNumero = User::nextNumeroCliente();
    }

    public function with(): array
    {
        return ['zonas' => Zona::where('activa', true)->orderBy('nombre')->get()];
    }

    public function guardar(): void
    {
        $this->validate([
            'nombre'    => 'required|min:2|max:80',
            'apellido'  => 'nullable|max:80',
            'whatsapp'  => 'required|max:25|unique:users,whatsapp',
            'ciudad'    => 'nullable|max:80',
            'direccion' => 'nullable|max:200',
            'zona'      => 'required|exists:zonas,slug',
            'foto'      => 'nullable|image|max:2048',
        ], [
            'nombre.required'   => 'El nombre es obligatorio.',
            'nombre.min'        => 'Mínimo 2 caracteres.',
            'whatsapp.required' => 'El número de WhatsApp es obligatorio.',
            'whatsapp.unique'   => 'Ya existe un cliente con ese número.',
            'zona.required'     => 'Seleccioná una zona.',
            'zona.exists'       => 'Zona inválida.',
            'foto.image'        => 'Debe ser una imagen.',
            'foto.max'          => 'Máx 2 MB.',
        ]);

        $fotoPath = $this->foto ? $this->foto->store('usuarios', 'public') : null;

        User::create([
            'numero_cliente' => User::nextNumeroCliente(),
            'name'           => $this->nombre,
            'apellido'       => $this->apellido  ?: null,
            'whatsapp'       => $this->whatsapp,
            'ciudad'         => $this->ciudad    ?: null,
            'direccion'      => $this->direccion ?: null,
            'zona'           => $this->zona      ?: null,
            'foto'           => $fotoPath,
            'role'           => 'cliente',
            'email'          => null,
            'password'       => null,
        ]);

        session()->flash('success', 'Cliente registrado correctamente.');
        $this->redirect(route('usuarios'), navigate: true);
    }

}; ?>

<div>

    <div class="mb-6 flex items-center justify-between">
        <a href="{{ route('usuarios') }}" wire:navigate
           class="inline-flex items-center gap-2 text-sm transition-colors duration-150"
           style="color: var(--vd-muted);"
           onmouseover="this.style.color='var(--vd-green-lt)'"
           onmouseout="this.style.color='var(--vd-muted)'">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Volver a Usuarios
        </a>

        <button disabled
                class="btn-secondary text-sm flex items-center gap-2"
                style="border-color: rgba(78,158,90,0.3); color: var(--vd-muted); opacity: 0.55; cursor: not-allowed;"
                title="Próximamente">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
            </svg>
            Importar desde Excel
        </button>
    </div>

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-8">
        <div class="w-11 h-11 rounded-2xl flex items-center justify-center flex-shrink-0"
             style="background: rgba(200,160,48,0.12); border: 1px solid rgba(200,160,48,0.3);">
            <svg width="20" height="20" fill="none" stroke="#c8a030" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
            </svg>
        </div>
        <div>
            <h2 class="font-condensed font-bold text-xl" style="color: var(--vd-text);">Registro interno de cliente</h2>
            <p class="text-sm mt-0.5" style="color: var(--vd-muted);">
                Se asignará el número
                <span class="font-bold font-mono" style="color: #c8a030;">#{{ str_pad($proximoNumero, 4, '0', STR_PAD_LEFT) }}</span>
            </p>
        </div>
    </div>

    <form wire:submit="guardar" enctype="multipart/form-data" novalidate>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Foto --}}
        <div class="card flex flex-col items-center gap-5" x-data="{ preview: null }">
            <div class="w-28 h-28 rounded-2xl overflow-hidden flex-shrink-0 flex items-center justify-center relative"
                 style="background: rgba(200,160,48,0.15); border: 2px solid rgba(200,160,48,0.3);">
                <span x-show="!preview" class="font-condensed font-bold select-none"
                      style="font-size: 44px; line-height:1; color: #c8a030;"
                      x-text="($wire.nombre ? $wire.nombre[0] : '?').toUpperCase()">?</span>
                <img x-show="preview" :src="preview" class="w-28 h-28 object-cover absolute inset-0" style="display:none;">
            </div>
            <div class="w-full text-center">
                <label class="label justify-center">Foto de perfil</label>
                <label class="btn-secondary cursor-pointer inline-flex mt-1" style="font-size:11px;">
                    <input type="file" wire:model="foto" accept="image/*" class="sr-only"
                           @change="const f=$event.target.files[0]; if(f){ const r=new FileReader(); r.onload=e=>preview=e.target.result; r.readAsDataURL(f); } else { preview=null; }">
                    Elegir imagen
                </label>
                <p class="text-xs mt-2" style="color: var(--vd-muted-2);">JPG, PNG, WEBP · Máx 2 MB</p>
                @error('foto') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
            </div>

            {{-- Número asignado --}}
            <div class="w-full rounded-xl px-4 py-3 text-center"
                 style="background: rgba(200,160,48,0.07); border: 1px solid rgba(200,160,48,0.2);">
                <p class="text-xs mb-1" style="color: var(--vd-muted);">Número de cliente</p>
                <p class="font-condensed font-bold text-2xl font-mono" style="color: #c8a030;">
                    #{{ str_pad($proximoNumero, 4, '0', STR_PAD_LEFT) }}
                </p>
                <p class="text-xs mt-1" style="color: var(--vd-muted-2);">Se asigna al guardar</p>
            </div>

            {{-- Info box --}}
            <div class="w-full rounded-xl px-4 py-3 text-xs"
                 style="background: var(--vd-input-bg); border: 1px solid var(--vd-bdr-soft); color: var(--vd-muted);">
                <p>Este cliente no tendrá acceso al sistema. Si se registra desde el sitio web, su cuenta se asociará automáticamente.</p>
            </div>
        </div>

        {{-- Datos --}}
        <div class="lg:col-span-2 flex flex-col gap-4">
            <div class="card">
                <h3 class="font-condensed font-bold tracking-wide mb-5"
                    style="color: var(--vd-muted); letter-spacing: 1px; text-transform: uppercase; font-size: 11px;
                           border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 12px;">
                    Datos personales
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Nombre <span style="color:#fca5a5">*</span></label>
                        <input type="text" wire:model="nombre"
                               class="input @error('nombre') border-red-400 @enderror" placeholder="Juan">
                        @error('nombre') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Apellido</label>
                        <input type="text" wire:model="apellido" class="input" placeholder="Pérez">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">WhatsApp <span style="color:#fca5a5">*</span></label>
                        <input type="text" wire:model="whatsapp"
                               class="input @error('whatsapp') border-red-400 @enderror"
                               placeholder="5491112345678" autocomplete="off">
                        <p class="text-xs mt-1" style="color: var(--vd-muted-2);">Sin espacios ni símbolos. Ej: 5491112345678</p>
                        @error('whatsapp') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 class="font-condensed font-bold tracking-wide mb-5"
                    style="color: var(--vd-muted); letter-spacing: 1px; text-transform: uppercase; font-size: 11px;
                           border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 12px;">
                    Datos de entrega
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Ciudad</label>
                        <input type="text" wire:model="ciudad" class="input" placeholder="Buenos Aires">
                    </div>
                    <div>
                        <label class="label">Zona <span style="color:#fca5a5">*</span></label>
                        <select wire:model="zona" class="input @error('zona') border-red-400 @enderror">
                            <option value="">— Seleccionar zona —</option>
                            @foreach($zonas as $z)
                                <option value="{{ $z->slug }}">{{ $z->nombre }}</option>
                            @endforeach
                        </select>
                        @error('zona') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Dirección</label>
                        <input type="text" wire:model="direccion" class="input"
                               placeholder="Av. Corrientes 1234, Piso 3, Dpto A">
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('usuarios') }}" wire:navigate class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-primary px-8"
                        wire:loading.attr="disabled" wire:target="guardar">
                    <span wire:loading.remove wire:target="guardar">Registrar cliente</span>
                    <span wire:loading wire:target="guardar">Guardando…</span>
                </button>
            </div>
        </div>

    </div>
    </form>

</div>
