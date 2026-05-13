<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

new #[Layout('layouts.app', ['title' => 'Registrar colaborador'])] class extends Component {

    use WithFileUploads;

    public string $nombre   = '';
    public string $apellido = '';
    public string $ciudad   = '';
    public string $whatsapp = '';
    public string $email    = '';
    public string $password = '';
    public        $foto     = null;

    public function mount(): void
    {
        if (! auth()->user()->isAdmin()) {
            $this->redirect(route('dashboard'), navigate: true);
        }
    }

    public function guardar(): void
    {
        $this->validate([
            'nombre'   => 'required|min:2|max:80',
            'apellido' => 'required|min:2|max:80',
            'ciudad'   => 'nullable|max:80',
            'whatsapp' => 'nullable|max:25',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'foto'     => 'nullable|image|max:2048',
        ], [
            'nombre.required'   => 'El nombre es obligatorio.',
            'nombre.min'        => 'Mínimo 2 caracteres.',
            'apellido.required' => 'El apellido es obligatorio.',
            'email.required'    => 'El email es obligatorio.',
            'email.email'       => 'Email inválido.',
            'email.unique'      => 'Ese email ya está registrado.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min'      => 'Mínimo 8 caracteres.',
            'foto.image'        => 'Debe ser una imagen.',
            'foto.max'          => 'Máx 2 MB.',
        ]);

        $fotoPath = $this->foto ? $this->foto->store('usuarios', 'public') : null;

        User::create([
            'name'     => $this->nombre,
            'apellido' => $this->apellido,
            'email'    => $this->email,
            'password' => Hash::make($this->password),
            'role'     => 'colaborador',
            'whatsapp' => $this->whatsapp ?: null,
            'ciudad'   => $this->ciudad   ?: null,
            'foto'     => $fotoPath,
        ]);

        session()->flash('success', 'Colaborador registrado correctamente.');
        $this->redirect(route('usuarios'), navigate: true);
    }

}; ?>

<div>

    <div class="mb-6">
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
    </div>

    {{-- Role badge header --}}
    <div class="flex items-center gap-3 mb-8">
        <div class="w-11 h-11 rounded-2xl flex items-center justify-center flex-shrink-0"
             style="background: rgba(167,139,250,0.12); border: 1px solid rgba(167,139,250,0.3);">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"
                 style="color: #a78bfa;">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
            </svg>
        </div>
        <div>
            <h2 class="font-condensed font-bold text-xl" style="color: var(--vd-text);">Registrar colaborador</h2>
            <p class="text-sm mt-0.5" style="color: var(--vd-muted);">
                El usuario será creado con rol
                <span class="font-semibold" style="color: #a78bfa;">Colaborador</span>.
            </p>
        </div>
    </div>

    <form wire:submit="guardar" enctype="multipart/form-data" novalidate>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Foto --}}
        <div class="card flex flex-col items-center gap-5" x-data="{ preview: null }">
            <div class="w-28 h-28 rounded-2xl overflow-hidden flex-shrink-0 flex items-center justify-center relative"
                 style="background: rgba(167,139,250,0.15);
                        border: 2px solid rgba(167,139,250,0.3);">
                <span x-show="!preview" class="font-condensed font-bold select-none"
                      style="font-size: 44px; line-height:1; color: #a78bfa;"
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
                        <input type="text" wire:model="nombre" class="input @error('nombre') border-red-400 @enderror" placeholder="Juan">
                        @error('nombre') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Apellido <span style="color:#fca5a5">*</span></label>
                        <input type="text" wire:model="apellido" class="input @error('apellido') border-red-400 @enderror" placeholder="Pérez">
                        @error('apellido') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Ciudad</label>
                        <input type="text" wire:model="ciudad" class="input" placeholder="Buenos Aires">
                    </div>
                    <div>
                        <label class="label">WhatsApp</label>
                        <input type="text" wire:model="whatsapp" class="input" placeholder="+5491112345678">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Email <span style="color:#fca5a5">*</span></label>
                        <input type="email" wire:model="email" class="input @error('email') border-red-400 @enderror">
                        @error('email') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Contraseña <span style="color:#fca5a5">*</span></label>
                        <input type="password" wire:model="password" class="input @error('password') border-red-400 @enderror"
                               placeholder="Mínimo 8 caracteres" autocomplete="new-password">
                        @error('password') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('usuarios') }}" wire:navigate class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-primary px-8"
                        wire:loading.attr="disabled" wire:target="guardar">
                    <span wire:loading.remove wire:target="guardar">Registrar colaborador</span>
                    <span wire:loading wire:target="guardar">Guardando…</span>
                </button>
            </div>
        </div>

    </div>
    </form>

</div>
