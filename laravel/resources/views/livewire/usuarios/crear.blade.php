<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

new #[Layout('layouts.app', ['title' => 'Nuevo usuario'])] class extends Component {

    use WithFileUploads;

    public string $nombre    = '';
    public string $apellido  = '';
    public string $ciudad    = '';
    public string $whatsapp  = '';
    public string $email     = '';
    public string $role      = 'colaborador';
    public string $password  = '';
    public        $foto      = null;

    public function guardar(): void
    {
        $this->validate([
            'nombre'   => 'required|min:2|max:80',
            'apellido' => 'required|min:2|max:80',
            'ciudad'   => 'nullable|max:80',
            'whatsapp' => 'nullable|max:25',
            'email'    => 'required|email|unique:users,email',
            'role'     => 'required|in:admin,responsable_zona,colaborador,cliente',
            'password' => 'required|min:8',
            'foto'     => 'nullable|image|max:2048',
        ], [
            'nombre.required'   => 'El nombre es obligatorio.',
            'nombre.min'        => 'Mínimo 2 caracteres.',
            'apellido.required' => 'El apellido es obligatorio.',
            'apellido.min'      => 'Mínimo 2 caracteres.',
            'email.required'    => 'El email es obligatorio.',
            'email.email'       => 'Ingresá un email válido.',
            'email.unique'      => 'Ese email ya está registrado.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min'      => 'Mínimo 8 caracteres.',
            'role.required'     => 'Seleccioná un rol.',
            'role.in'           => 'Rol inválido.',
            'foto.image'        => 'Debe ser una imagen (jpg, png, webp).',
            'foto.max'          => 'Máx 2 MB.',
        ]);

        $fotoPath = $this->foto
            ? $this->foto->store('usuarios', 'public')
            : null;

        User::create([
            'name'      => $this->nombre,
            'apellido'  => $this->apellido,
            'email'     => $this->email,
            'password'  => Hash::make($this->password),
            'role'      => $this->role,
            'whatsapp'  => $this->whatsapp  ?: null,
            'ciudad'    => $this->ciudad    ?: null,
            'foto'      => $fotoPath,
        ]);

        session()->flash('success', 'Usuario creado correctamente.');
        $this->redirect(route('usuarios'), navigate: true);
    }

}; ?>

<div>

    {{-- Back breadcrumb --}}
    <div class="mb-6">
        <a href="{{ route('usuarios') }}" wire:navigate
           class="inline-flex items-center gap-2 text-sm transition-colors duration-150"
           style="color: rgba(240,244,240,0.45);"
           onmouseover="this.style.color='#4e9e5a'" onmouseout="this.style.color='rgba(240,244,240,0.45)'">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Volver a Usuarios
        </a>
    </div>

    <form wire:submit="guardar" enctype="multipart/form-data" novalidate>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left col: photo + role --}}
        <div class="flex flex-col gap-4">

            {{-- Photo card --}}
            <div class="card flex flex-col items-center gap-4"
                 x-data="{ preview: null }">

                <div class="w-28 h-28 rounded-full overflow-hidden flex-shrink-0 flex items-center justify-center"
                     style="background: linear-gradient(135deg, #3a7d44, #4e9e5a);
                            border: 2px solid rgba(78,158,90,0.4);
                            box-shadow: 0 0 30px rgba(58,125,68,0.25);">
                    <img x-show="preview" :src="preview"
                         class="w-28 h-28 object-cover" style="display:none;">
                    <span x-show="!preview"
                          class="font-condensed font-bold text-white select-none"
                          style="font-size: 44px; line-height:1;"
                          x-text="($wire.nombre ? $wire.nombre[0] : 'U').toUpperCase()">U</span>
                </div>

                <div class="w-full text-center">
                    <label class="label justify-center">Foto de perfil</label>
                    <label class="btn-secondary cursor-pointer inline-flex mt-1" style="font-size:11px;">
                        <input type="file" wire:model="foto" accept="image/*" class="sr-only"
                               @change="const f=$event.target.files[0]; if(f){ const r=new FileReader(); r.onload=e=>preview=e.target.result; r.readAsDataURL(f); } else { preview=null; }">
                        Elegir imagen
                    </label>
                    <p class="text-xs mt-2" style="color: rgba(240,244,240,0.3);">JPG, PNG, WEBP · Máx 2 MB</p>
                    @error('foto')
                        <p class="text-xs mt-1" style="color: #fca5a5;">{{ $message }}</p>
                    @enderror
                </div>

            </div>

            {{-- Role card --}}
            <div class="card">
                <label class="label">Rol del usuario</label>
                <div class="flex flex-col gap-2 mt-3">
                    @foreach(\App\Models\User::rolesLabels() as $val => $label)
                    @php
                        $desc = match($val) {
                            'admin'            => 'Acceso total al sistema',
                            'responsable_zona' => 'Gestiona pedidos de su zona',
                            'colaborador'      => 'Apoyo operativo, sin administración',
                            'cliente'          => 'Realiza pedidos vía WhatsApp',
                        };
                        $color = match($val) {
                            'admin'            => '#4e9e5a',
                            'responsable_zona' => '#60a5fa',
                            'colaborador'      => '#a78bfa',
                            'cliente'          => '#c8a030',
                        };
                    @endphp
                    <label class="flex items-start gap-3 p-3 rounded-xl cursor-pointer transition-all duration-150"
                           style="border: 1px solid rgba(255,255,255,0.06);"
                           wire:click="$set('role', '{{ $val }}')"
                           :class="$wire.role === '{{ $val }}' ? 'border-color:{{ $color }}' : ''"
                           x-bind:style="$wire.role === '{{ $val }}' ? 'background: rgba(58,125,68,0.12); border-color: {{ $color }}55' : ''">
                        <input type="radio" name="role" value="{{ $val }}"
                               wire:model="role" class="sr-only">
                        <div class="w-4 h-4 rounded-full mt-0.5 flex-shrink-0 flex items-center justify-center"
                             style="border: 2px solid {{ $color }};"
                             x-bind:style="$wire.role === '{{ $val }}' ? 'background: {{ $color }}; border-color: {{ $color }}' : 'border-color: {{ $color }}80'">
                            <div class="w-1.5 h-1.5 rounded-full bg-white"
                                 x-show="$wire.role === '{{ $val }}'"></div>
                        </div>
                        <div>
                            <p class="text-sm font-semibold" style="color: #f0f4f0;">{{ $label }}</p>
                            <p class="text-xs" style="color: rgba(240,244,240,0.4);">{{ $desc }}</p>
                        </div>
                    </label>
                    @endforeach
                </div>
                @error('role')
                    <p class="text-xs mt-2" style="color: #fca5a5;">{{ $message }}</p>
                @enderror
            </div>

        </div>

        {{-- Right col: personal data --}}
        <div class="lg:col-span-2 flex flex-col gap-4">

            {{-- Personal info --}}
            <div class="card">
                <h3 class="font-condensed font-bold text-base tracking-wide mb-5"
                    style="color: rgba(240,244,240,0.7); letter-spacing: 1px; text-transform: uppercase; font-size: 11px; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 12px;">
                    Datos personales
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                    <div>
                        <label class="label">Nombre <span style="color:#fca5a5">*</span></label>
                        <input type="text" wire:model="nombre"
                               class="input @error('nombre') border-red-400 @enderror"
                               placeholder="Juan">
                        @error('nombre')
                            <p class="text-xs mt-1" style="color: #fca5a5;">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="label">Apellido <span style="color:#fca5a5">*</span></label>
                        <input type="text" wire:model="apellido"
                               class="input @error('apellido') border-red-400 @enderror"
                               placeholder="Pérez">
                        @error('apellido')
                            <p class="text-xs mt-1" style="color: #fca5a5;">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="label">Ciudad</label>
                        <input type="text" wire:model="ciudad"
                               class="input @error('ciudad') border-red-400 @enderror"
                               placeholder="Buenos Aires">
                        @error('ciudad')
                            <p class="text-xs mt-1" style="color: #fca5a5;">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="label">WhatsApp</label>
                        <input type="text" wire:model="whatsapp"
                               class="input @error('whatsapp') border-red-400 @enderror"
                               placeholder="+5491112345678">
                        @error('whatsapp')
                            <p class="text-xs mt-1" style="color: #fca5a5;">{{ $message }}</p>
                        @enderror
                    </div>

                </div>
            </div>

            {{-- Account info --}}
            <div class="card">
                <h3 class="font-condensed font-bold tracking-wide mb-5"
                    style="color: rgba(240,244,240,0.7); letter-spacing: 1px; text-transform: uppercase; font-size: 11px; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 12px;">
                    Cuenta de acceso
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                    <div class="sm:col-span-2">
                        <label class="label">Email <span style="color:#fca5a5">*</span></label>
                        <input type="email" wire:model="email"
                               class="input @error('email') border-red-400 @enderror"
                               placeholder="juan.perez@verdeo.com.ar">
                        @error('email')
                            <p class="text-xs mt-1" style="color: #fca5a5;">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="label">Contraseña <span style="color:#fca5a5">*</span></label>
                        <input type="password" wire:model="password"
                               class="input @error('password') border-red-400 @enderror"
                               placeholder="Mínimo 8 caracteres" autocomplete="new-password">
                        @error('password')
                            <p class="text-xs mt-1" style="color: #fca5a5;">{{ $message }}</p>
                        @enderror
                    </div>

                </div>
            </div>

            {{-- Actions --}}
            <div class="flex justify-end gap-3">
                <a href="{{ route('usuarios') }}" wire:navigate class="btn-secondary">
                    Cancelar
                </a>
                <button type="submit" class="btn-primary px-8"
                        wire:loading.attr="disabled" wire:target="guardar">
                    <span wire:loading.remove wire:target="guardar">Crear usuario</span>
                    <span wire:loading wire:target="guardar">Creando…</span>
                </button>
            </div>

        </div>

    </div>
    </form>

</div>
