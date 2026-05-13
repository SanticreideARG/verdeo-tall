<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

new #[Layout('layouts.guest')] class extends Component {

    public string $nombre    = '';
    public string $apellido  = '';
    public string $whatsapp  = '';
    public string $email     = '';
    public string $password  = '';
    public string $password_confirmation = '';

    // State after submit
    public bool   $registrado = false;
    public bool   $asociado   = false; // true if merged with existing account

    public function registrarse(): void
    {
        $this->validate([
            'nombre'    => 'required|min:2|max:80',
            'apellido'  => 'nullable|max:80',
            'whatsapp'  => 'required|max:25',
            'email'     => 'required|email|max:150',
            'password'  => 'required|min:8|confirmed',
        ], [
            'nombre.required'   => 'Tu nombre es obligatorio.',
            'whatsapp.required' => 'El número de WhatsApp es obligatorio.',
            'email.required'    => 'El email es obligatorio.',
            'email.email'       => 'Email inválido.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min'      => 'Mínimo 8 caracteres.',
            'password.confirmed'=> 'Las contraseñas no coinciden.',
        ]);

        // Look for existing account by WhatsApp (primary) or email (secondary)
        $existing = User::where('whatsapp', $this->whatsapp)->first()
                 ?? User::where('email', $this->email)->first();

        if ($existing) {
            // Merge: update public data, preserve private data (zona, direccion, foto)
            $existing->update([
                'name'     => $this->nombre,
                'apellido' => $this->apellido ?: $existing->apellido,
                'whatsapp' => $this->whatsapp,
                'email'    => $this->email,
                'password' => Hash::make($this->password),
            ]);
            $this->asociado = true;
        } else {
            // Validate email uniqueness only for new accounts
            $this->validate(['email' => 'unique:users,email'], [
                'email.unique' => 'Ese email ya está registrado con otro número.',
            ]);

            User::create([
                'name'     => $this->nombre,
                'apellido' => $this->apellido ?: null,
                'whatsapp' => $this->whatsapp,
                'email'    => $this->email,
                'password' => Hash::make($this->password),
                'role'     => 'cliente',
            ]);
        }

        $this->registrado = true;
    }

}; ?>

<div class="min-h-screen flex items-center justify-center px-4 py-12"
     style="background: var(--vd-bg);">

    <div class="w-full max-w-md">

        {{-- Logo --}}
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-3 mb-2">
                <img src="/images/verdeo-logo.png" alt="Verdeo"
                     class="w-10 h-10 rounded-full object-cover"
                     style="filter: drop-shadow(0 2px 8px rgba(58,125,68,0.5));"
                     onerror="this.style.display='none'">
                <span class="font-condensed font-bold text-2xl tracking-wide" style="color: var(--vd-text);">Verdeo</span>
            </div>
            <p class="text-sm" style="color: var(--vd-muted);">Viandas saludables con delivery</p>
        </div>

        @if($registrado)
        {{-- Success state --}}
        <div class="card text-center py-10">
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4"
                 style="background: rgba(58,125,68,0.15); border: 1px solid rgba(78,158,90,0.3);">
                <svg width="28" height="28" fill="none" stroke="#4e9e5a" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h2 class="font-condensed font-bold text-xl mb-2" style="color: var(--vd-text);">
                @if($asociado) ¡Cuenta activada! @else ¡Registro exitoso! @endif
            </h2>
            <p class="text-sm mb-6" style="color: var(--vd-muted);">
                @if($asociado)
                    Encontramos tu cuenta existente y la activamos con tus nuevos datos.
                @else
                    Tu cuenta fue creada correctamente.
                @endif
            </p>
            <a href="{{ route('login') }}"
               class="btn-primary inline-block px-8">Iniciar sesión</a>
        </div>

        @else
        {{-- Form --}}
        <div class="card">
            <h1 class="font-condensed font-bold text-xl mb-1" style="color: var(--vd-text);">Crear cuenta</h1>
            <p class="text-sm mb-6" style="color: var(--vd-muted);">Si ya sos cliente, tu cuenta se activará automáticamente.</p>

            <form wire:submit="registrarse" novalidate class="flex flex-col gap-4">

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Nombre <span style="color:#fca5a5">*</span></label>
                        <input type="text" wire:model="nombre"
                               class="input @error('nombre') border-red-400 @enderror"
                               placeholder="Juan" autocomplete="given-name">
                        @error('nombre') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Apellido</label>
                        <input type="text" wire:model="apellido" class="input" placeholder="Pérez" autocomplete="family-name">
                    </div>
                </div>

                <div>
                    <label class="label">WhatsApp <span style="color:#fca5a5">*</span></label>
                    <input type="tel" wire:model="whatsapp"
                           class="input @error('whatsapp') border-red-400 @enderror"
                           placeholder="5491112345678" autocomplete="tel">
                    <p class="text-xs mt-1" style="color: var(--vd-muted-2);">Sin espacios ni símbolos. Incluí el código de país.</p>
                    @error('whatsapp') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label">Email <span style="color:#fca5a5">*</span></label>
                    <input type="email" wire:model="email"
                           class="input @error('email') border-red-400 @enderror"
                           placeholder="juan@mail.com" autocomplete="email">
                    @error('email') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label">Contraseña <span style="color:#fca5a5">*</span></label>
                    <input type="password" wire:model="password"
                           class="input @error('password') border-red-400 @enderror"
                           placeholder="Mínimo 8 caracteres" autocomplete="new-password">
                    @error('password') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label">Confirmar contraseña <span style="color:#fca5a5">*</span></label>
                    <input type="password" wire:model="password_confirmation"
                           class="input" placeholder="Repetí tu contraseña" autocomplete="new-password">
                </div>

                <button type="submit" class="btn-primary w-full mt-2"
                        wire:loading.attr="disabled" wire:target="registrarse">
                    <span wire:loading.remove wire:target="registrarse">Crear cuenta</span>
                    <span wire:loading wire:target="registrarse">Registrando…</span>
                </button>

            </form>

            <p class="text-center text-sm mt-5" style="color: var(--vd-muted);">
                ¿Ya tenés cuenta?
                <a href="{{ route('login') }}" class="underline" style="color: var(--vd-green-lt);">Iniciá sesión</a>
            </p>
        </div>
        @endif

    </div>
</div>
