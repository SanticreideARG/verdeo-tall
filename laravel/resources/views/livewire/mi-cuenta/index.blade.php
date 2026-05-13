<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.app', ['title' => 'Mi cuenta'])] class extends Component {

    use WithFileUploads;

    public string $nombre    = '';
    public string $apellido  = '';
    public string $ciudad    = '';
    public string $whatsapp  = '';
    public string $email     = '';
    public        $foto      = null;

    public string $password_actual  = '';
    public string $password_nuevo   = '';
    public string $password_confirm = '';

    public string $tab = 'perfil';

    public function mount(): void
    {
        $user = auth()->user();
        $this->nombre   = $user->name;
        $this->apellido = $user->apellido  ?? '';
        $this->ciudad   = $user->ciudad    ?? '';
        $this->whatsapp = $user->whatsapp  ?? '';
        $this->email    = $user->email;
    }

    public function setTab(string $t): void
    {
        $this->tab = $t;
    }

    public function guardarPerfil(): void
    {
        $this->validate([
            'nombre'   => 'required|min:2|max:80',
            'apellido' => 'required|min:2|max:80',
            'ciudad'   => 'nullable|max:80',
            'whatsapp' => 'nullable|max:25',
            'email'    => 'required|email|unique:users,email,' . auth()->id(),
            'foto'     => 'nullable|image|max:2048',
        ], [
            'nombre.required'   => 'El nombre es obligatorio.',
            'nombre.min'        => 'Mínimo 2 caracteres.',
            'apellido.required' => 'El apellido es obligatorio.',
            'email.required'    => 'El email es obligatorio.',
            'email.email'       => 'Ingresá un email válido.',
            'email.unique'      => 'Ese email ya está en uso.',
            'foto.image'        => 'Debe ser una imagen (jpg, png, webp).',
            'foto.max'          => 'Máx 2 MB.',
        ]);

        $user = auth()->user();

        $data = [
            'name'     => $this->nombre,
            'apellido' => $this->apellido,
            'email'    => $this->email,
            'whatsapp' => $this->whatsapp ?: null,
            'ciudad'   => $this->ciudad   ?: null,
        ];

        if ($this->foto) {
            if ($user->foto) {
                Storage::disk('public')->delete($user->foto);
            }
            $data['foto'] = $this->foto->store('usuarios', 'public');
            $this->foto = null;
        }

        $user->update($data);

        session()->flash('success', 'Perfil actualizado.');
        $this->redirect(route('mi-cuenta'), navigate: true);
    }

    public function cambiarPassword(): void
    {
        $this->validate([
            'password_actual'  => 'required',
            'password_nuevo'   => 'required|min:8',
            'password_confirm' => 'required|same:password_nuevo',
        ], [
            'password_actual.required'  => 'Ingresá tu contraseña actual.',
            'password_nuevo.required'   => 'La nueva contraseña es obligatoria.',
            'password_nuevo.min'        => 'Mínimo 8 caracteres.',
            'password_confirm.required' => 'Confirmá la nueva contraseña.',
            'password_confirm.same'     => 'Las contraseñas no coinciden.',
        ]);

        if (! Hash::check($this->password_actual, auth()->user()->password)) {
            $this->addError('password_actual', 'La contraseña actual es incorrecta.');
            return;
        }

        auth()->user()->update(['password' => Hash::make($this->password_nuevo)]);

        $this->password_actual  = '';
        $this->password_nuevo   = '';
        $this->password_confirm = '';

        session()->flash('success', 'Contraseña actualizada.');
        $this->redirect(route('mi-cuenta'), navigate: true);
    }

    public function cerrarSesion(): void
    {
        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        $this->redirect('/login');
    }

}; ?>

<div>

    {{-- Header --}}
    <div class="flex items-start justify-between mb-8">
        <div class="flex items-center gap-4">
            {{-- Avatar grande --}}
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center flex-shrink-0 text-white font-bold font-condensed"
                 style="background: linear-gradient(135deg, #3a7d44, #4e9e5a);
                        font-size: 26px;
                        box-shadow: 0 4px 16px rgba(58,125,68,0.3);
                        border: 1px solid rgba(78,158,90,0.35);">
                @if(auth()->user()->foto)
                    <img src="{{ Storage::url(auth()->user()->foto) }}"
                         alt="{{ auth()->user()->name }}"
                         class="w-14 h-14 rounded-2xl object-cover">
                @else
                    {{ strtoupper(substr(auth()->user()->name ?? 'V', 0, 1)) }}
                @endif
            </div>
            <div>
                <h2 class="font-condensed font-bold text-xl" style="color: var(--vd-text); letter-spacing: 0.5px;">
                    {{ auth()->user()->name }} {{ auth()->user()->apellido }}
                </h2>
                <p class="text-sm mt-0.5" style="color: var(--vd-muted);">
                    {{ auth()->user()->email }}
                    <span class="ml-2 font-condensed text-xs uppercase px-2 py-0.5 rounded-full"
                          style="background: rgba(78,158,90,0.12); color: var(--vd-green-lt); border: 1px solid rgba(78,158,90,0.25);">
                        {{ \App\Models\User::rolesLabels()[auth()->user()->role ?? ''] ?? 'Usuario' }}
                    </span>
                </p>
            </div>
        </div>

        {{-- Cerrar sesión --}}
        <button type="button" wire:click="cerrarSesion"
                class="btn-secondary text-sm flex items-center gap-2"
                style="color: #fca5a5; border-color: rgba(252,165,165,0.3);"
                onmouseover="this.style.background='rgba(252,165,165,0.08)'"
                onmouseout="this.style.background=''">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1"/>
            </svg>
            Cerrar sesión
        </button>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-1 mb-6 p-1 rounded-xl" style="background: var(--vd-input-bg); border: 1px solid var(--vd-bdr-soft); width: fit-content;">
        @foreach(['perfil' => 'Perfil', 'seguridad' => 'Seguridad'] as $key => $label)
        <button type="button" wire:click="setTab('{{ $key }}')"
                class="font-condensed font-bold text-sm px-4 py-1.5 rounded-lg transition-colors duration-150"
                style="{{ $tab === $key
                    ? 'background: var(--vd-green); color: #fff;'
                    : 'color: var(--vd-muted);' }}">
            {{ $label }}
        </button>
        @endforeach
    </div>

    {{-- TAB: Perfil --}}
    @if($tab === 'perfil')
    <form wire:submit="guardarPerfil" enctype="multipart/form-data" novalidate>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Foto --}}
        <div class="card flex flex-col items-center gap-5"
             x-data="{ preview: null }">

            <div class="w-28 h-28 rounded-2xl overflow-hidden flex-shrink-0 flex items-center justify-center relative"
                 style="background: linear-gradient(135deg, #3a7d44, #4e9e5a);
                        border: 2px solid rgba(78,158,90,0.4);
                        box-shadow: 0 0 30px rgba(58,125,68,0.2);">
                @if(auth()->user()->foto)
                    <img x-show="!preview" src="{{ Storage::url(auth()->user()->foto) }}"
                         alt="foto" class="w-28 h-28 object-cover">
                @else
                    <span x-show="!preview"
                          class="font-condensed font-bold text-white select-none"
                          style="font-size: 44px; line-height:1;">
                        {{ strtoupper(substr(auth()->user()->name ?? 'V', 0, 1)) }}
                    </span>
                @endif
                <img x-show="preview" :src="preview" class="w-28 h-28 object-cover absolute inset-0" style="display:none;">
            </div>

            <div class="w-full text-center">
                <label class="label justify-center">Foto de perfil</label>
                <label class="btn-secondary cursor-pointer inline-flex mt-1" style="font-size:11px;">
                    <input type="file" wire:model="foto" accept="image/*" class="sr-only"
                           @change="const f=$event.target.files[0]; if(f){ const r=new FileReader(); r.onload=e=>preview=e.target.result; r.readAsDataURL(f); } else { preview=null; }">
                    Cambiar foto
                </label>
                <p class="text-xs mt-2" style="color: var(--vd-muted-2);">JPG, PNG, WEBP · Máx 2 MB</p>
                @error('foto')
                    <p class="text-xs mt-1" style="color: #fca5a5;">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Datos --}}
        <div class="lg:col-span-2 flex flex-col gap-4">
            <div class="card">
                <h3 class="font-condensed font-bold tracking-wide mb-5"
                    style="color: var(--vd-muted); letter-spacing: 1px; text-transform: uppercase; font-size: 11px; border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 12px;">
                    Información personal
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
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn-primary px-8"
                        wire:loading.attr="disabled" wire:target="guardarPerfil">
                    <span wire:loading.remove wire:target="guardarPerfil">Guardar cambios</span>
                    <span wire:loading wire:target="guardarPerfil">Guardando…</span>
                </button>
            </div>
        </div>

    </div>
    </form>
    @endif

    {{-- TAB: Seguridad --}}
    @if($tab === 'seguridad')
    <div class="max-w-lg">
        <form wire:submit="cambiarPassword" novalidate>
        <div class="card flex flex-col gap-4">
            <h3 class="font-condensed font-bold tracking-wide mb-1"
                style="color: var(--vd-muted); letter-spacing: 1px; text-transform: uppercase; font-size: 11px; border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 12px;">
                Cambiar contraseña
            </h3>

            <div>
                <label class="label">Contraseña actual <span style="color:#fca5a5">*</span></label>
                <input type="password" wire:model="password_actual"
                       class="input @error('password_actual') border-red-400 @enderror"
                       autocomplete="current-password">
                @error('password_actual') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label">Nueva contraseña <span style="color:#fca5a5">*</span></label>
                <input type="password" wire:model="password_nuevo"
                       class="input @error('password_nuevo') border-red-400 @enderror"
                       autocomplete="new-password" placeholder="Mínimo 8 caracteres">
                @error('password_nuevo') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label">Confirmar nueva contraseña <span style="color:#fca5a5">*</span></label>
                <input type="password" wire:model="password_confirm"
                       class="input @error('password_confirm') border-red-400 @enderror"
                       autocomplete="new-password">
                @error('password_confirm') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end pt-2" style="border-top: 1px solid var(--vd-bdr-soft);">
                <button type="submit" class="btn-primary px-8"
                        wire:loading.attr="disabled" wire:target="cambiarPassword">
                    <span wire:loading.remove wire:target="cambiarPassword">Actualizar contraseña</span>
                    <span wire:loading wire:target="cambiarPassword">Actualizando…</span>
                </button>
            </div>
        </div>
        </form>

        {{-- Info de sesión --}}
        <div class="card mt-4" style="border-color: rgba(252,165,165,0.2);">
            <h3 class="font-condensed font-bold tracking-wide mb-3"
                style="color: var(--vd-muted); letter-spacing: 1px; text-transform: uppercase; font-size: 11px;">
                Sesión activa
            </h3>
            <p class="text-sm mb-4" style="color: var(--vd-muted);">
                Cerrá tu sesión en este dispositivo. Necesitarás volver a ingresar con tu email y contraseña.
            </p>
            <button type="button" wire:click="cerrarSesion"
                    class="btn-secondary text-sm flex items-center gap-2"
                    style="color: #fca5a5; border-color: rgba(252,165,165,0.3);"
                    onmouseover="this.style.background='rgba(252,165,165,0.08)'"
                    onmouseout="this.style.background=''">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1"/>
                </svg>
                Cerrar sesión
            </button>
        </div>
    </div>
    @endif

</div>
