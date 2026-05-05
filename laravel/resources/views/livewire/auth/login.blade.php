<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component {

    public string $email    = '';
    public string $password = '';
    public bool   $remember = false;

    public function login(): void
    {
        $this->validate([
            'email'    => 'required|email',
            'password' => 'required|min:6',
        ]);

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            $this->addError('email', 'Credenciales incorrectas.');
            return;
        }

        session()->regenerate();
        $this->redirect(route('dashboard'));
    }

}; ?>

<form wire:submit="login" class="space-y-5">

    <div>
        <label class="label">Email</label>
        <input type="email" wire:model="email" class="input" placeholder="admin@verdeo.com.ar" autofocus>
        @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="label">Contraseña</label>
        <input type="password" wire:model="password" class="input" placeholder="••••••••">
        @error('password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="flex items-center gap-2">
        <input type="checkbox" wire:model="remember" id="remember" class="rounded border-gray-300 text-verdeo-600 focus:ring-verdeo-500">
        <label for="remember" class="text-sm text-gray-600">Recordarme</label>
    </div>

    <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">
        <span wire:loading.remove>Ingresar</span>
        <span wire:loading>Ingresando…</span>
    </button>

</form>
