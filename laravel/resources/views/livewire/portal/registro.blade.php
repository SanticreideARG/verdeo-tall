<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\User;
use App\Models\Zona;
use Illuminate\Support\Facades\Hash;

new #[Layout('layouts.guest')] class extends Component {

    public string $nombre    = '';
    public string $apellido  = '';
    public string $email     = '';
    public string $password  = '';
    public string $whatsapp  = '';
    public string $zona      = '';
    public string $direccion = '';

    public function mount(): void
    {
        if (auth()->check()) {
            $this->redirect(route('portal'), navigate: true);
        }
    }

    public function with(): array
    {
        return ['zonas' => Zona::where('activa', true)->orderBy('nombre')->get()];
    }

    public function registrarse(): void
    {
        $this->validate([
            'nombre'    => 'required|min:2|max:80',
            'apellido'  => 'nullable|max:80',
            'email'     => 'required|email|max:150|unique:users,email',
            'password'  => 'required|min:8',
            'whatsapp'  => 'required|max:25|unique:users,whatsapp',
            'zona'      => 'required|exists:zonas,slug',
            'direccion' => 'nullable|max:200',
        ], [
            'nombre.required'    => 'Tu nombre es obligatorio.',
            'email.required'     => 'El email es obligatorio.',
            'email.unique'       => 'Ese email ya tiene una cuenta.',
            'password.required'  => 'La contraseña es obligatoria.',
            'password.min'       => 'Mínimo 8 caracteres.',
            'whatsapp.required'  => 'El WhatsApp es obligatorio para coordinar entregas.',
            'whatsapp.unique'    => 'Ya existe una cuenta con ese número de WhatsApp.',
            'zona.required'      => 'Elegí tu zona de entrega.',
        ]);

        $user = User::create([
            'name'            => $this->nombre,
            'apellido'        => $this->apellido ?: null,
            'email'           => $this->email,
            'password'        => Hash::make($this->password),
            'whatsapp'        => $this->whatsapp,
            'zona'            => $this->zona,
            'direccion'       => $this->direccion ?: null,
            'role'            => 'cliente',
            'numero_cliente'  => User::nextNumeroCliente(),
        ]);

        auth()->login($user);
        $this->redirect(route('portal'), navigate: true);
    }

}; ?>

<div class="min-h-screen flex flex-col justify-center py-10 px-4" style="background: var(--vd-bg);">

    {{-- Logo --}}
    <div class="text-center mb-8">
        <div class="inline-flex items-center gap-3 mb-2">
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center"
                 style="background: linear-gradient(135deg,#3a7d44,#4e9e5a); box-shadow:0 4px 16px rgba(58,125,68,0.4);">
                <img src="/images/verdeo-logo.png" alt="Verdeo"
                     class="w-8 h-8 object-cover rounded-full"
                     onerror="this.parentElement.innerHTML='<span style=\'color:#fff;font-size:20px;font-weight:900;\'>V</span>'">
            </div>
            <span class="font-condensed font-bold text-3xl tracking-wide" style="color: var(--vd-text);">Verdeo</span>
        </div>
        <p class="text-sm mt-1" style="color: var(--vd-muted);">Viandas saludables con delivery</p>
    </div>

    <div class="w-full max-w-md mx-auto">
        <div class="card">
            <h2 class="font-condensed font-bold text-2xl mb-1" style="color: var(--vd-text);">Crear cuenta</h2>
            <p class="text-sm mb-6" style="color: var(--vd-muted);">Registrate para hacer pedidos y seguir tu estado de entrega.</p>

            <form wire:submit="registrarse" class="space-y-4" novalidate>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Nombre <span style="color:#fca5a5">*</span></label>
                        <input type="text" wire:model="nombre"
                               class="input @error('nombre') border-red-400 @enderror"
                               placeholder="Ana" autocomplete="given-name">
                        @error('nombre') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Apellido</label>
                        <input type="text" wire:model="apellido" class="input" placeholder="García" autocomplete="family-name">
                    </div>
                </div>

                <div>
                    <label class="label">Email <span style="color:#fca5a5">*</span></label>
                    <input type="email" wire:model="email"
                           class="input @error('email') border-red-400 @enderror"
                           placeholder="ana@email.com" autocomplete="email">
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
                    <label class="label">WhatsApp <span style="color:#fca5a5">*</span></label>
                    <input type="tel" wire:model="whatsapp"
                           class="input @error('whatsapp') border-red-400 @enderror"
                           placeholder="5491112345678" autocomplete="tel">
                    <p class="text-xs mt-1" style="color: var(--vd-muted-2);">Código de país incluido. Ej: 5491112345678</p>
                    @error('whatsapp') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label">Zona de entrega <span style="color:#fca5a5">*</span></label>
                    <select wire:model="zona" class="input @error('zona') border-red-400 @enderror">
                        <option value="">— Seleccionar zona —</option>
                        @foreach($zonas as $z)
                        <option value="{{ $z->slug }}">{{ $z->nombre }}</option>
                        @endforeach
                    </select>
                    @error('zona') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label">Dirección de entrega habitual</label>
                    <input type="text" wire:model="direccion" class="input"
                           placeholder="Calle 123, Piso 2, Depto A">
                    <p class="text-xs mt-1" style="color: var(--vd-muted-2);">Podés cambiarla al hacer cada pedido.</p>
                </div>

                <button type="submit"
                        wire:loading.attr="disabled"
                        class="w-full flex items-center justify-center gap-2 font-bold py-3.5 rounded-xl text-base mt-2"
                        style="background: linear-gradient(135deg,#3a7d44,#4e9e5a); color:#fff; min-height:52px;">
                    <span wire:loading.remove wire:target="registrarse">Crear mi cuenta</span>
                    <span wire:loading wire:target="registrarse">Creando…</span>
                </button>
            </form>

            <p class="text-center text-xs mt-5" style="color: var(--vd-muted);">
                ¿Ya tenés cuenta?
                <a href="/login" class="font-semibold" style="color: #4e9e5a;">Iniciá sesión</a>
            </p>
        </div>
    </div>

</div>
