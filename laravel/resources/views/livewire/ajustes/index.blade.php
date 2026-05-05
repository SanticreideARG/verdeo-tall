<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Setting;

new #[Layout('layouts.app', ['title' => 'Ajustes'])] class extends Component {

    public string $app_nombre   = '';
    public string $ai_modelo    = '';
    public string $timezone     = '';
    public string $wa_bsas      = '';
    public string $wa_valle_nqn = '';
    public string $wa_cordoba   = '';
    public string $wa_mendoza   = '';

    public bool $guardado = false;

    public function mount(): void
    {
        $defaults = Setting::defaults();

        foreach (array_keys($defaults) as $key) {
            $this->$key = Setting::get($key, $defaults[$key]);
        }
    }

    public function guardar(): void
    {
        $this->validate([
            'app_nombre'   => 'required|min:2|max:60',
            'ai_modelo'    => 'required|in:mistral,llama3,phi3,gemma2',
            'timezone'     => 'required|timezone',
            'wa_bsas'      => 'required|digits_between:10,15',
            'wa_valle_nqn' => 'required|digits_between:10,15',
            'wa_cordoba'   => 'required|digits_between:10,15',
            'wa_mendoza'   => 'required|digits_between:10,15',
        ]);

        $keys = ['app_nombre', 'ai_modelo', 'timezone', 'wa_bsas', 'wa_valle_nqn', 'wa_cordoba', 'wa_mendoza'];

        foreach ($keys as $key) {
            Setting::set($key, $this->$key);
        }

        $this->guardado = true;
        $this->dispatch('guardado');
    }

}; ?>

<div x-data="{ flash: false }" @guardado.window="flash = true; setTimeout(() => flash = false, 3000)">

    {{-- Flash --}}
    <div x-show="flash" x-transition class="mb-6 p-3 bg-verdeo-50 border border-verdeo-200 rounded-lg text-verdeo-700 text-sm font-medium">
        Ajustes guardados correctamente.
    </div>

    <form wire:submit="guardar" class="space-y-8">

        {{-- General --}}
        <div class="card">
            <h3 class="font-semibold text-gray-800 mb-4">General</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="label">Nombre de la empresa</label>
                    <input type="text" wire:model="app_nombre" class="input" placeholder="Verdeo">
                    @error('app_nombre') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Zona horaria</label>
                    <select wire:model="timezone" class="input">
                        <option value="America/Argentina/Buenos_Aires">Buenos Aires (ART)</option>
                        <option value="America/Mendoza">Mendoza</option>
                        <option value="UTC">UTC</option>
                    </select>
                    @error('timezone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- AI --}}
        <div class="card">
            <h3 class="font-semibold text-gray-800 mb-1">Inteligencia Artificial</h3>
            <p class="text-sm text-gray-500 mb-4">Modelo Ollama usado para responder mensajes.</p>
            <div class="max-w-xs">
                <label class="label">Modelo</label>
                <select wire:model="ai_modelo" class="input">
                    <option value="mistral">Mistral 7B</option>
                    <option value="llama3">Llama 3 8B</option>
                    <option value="phi3">Phi-3 Mini</option>
                    <option value="gemma2">Gemma 2 9B</option>
                </select>
                @error('ai_modelo') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- WhatsApp numbers --}}
        <div class="card">
            <h3 class="font-semibold text-gray-800 mb-1">Números de WhatsApp por zona</h3>
            <p class="text-sm text-gray-500 mb-4">Formato internacional sin + ni espacios (ej: 5491158393179).</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="label">Buenos Aires</label>
                    <input type="text" wire:model="wa_bsas" class="input font-mono" placeholder="5491158393179">
                    @error('wa_bsas') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Valle NQN / Roca</label>
                    <input type="text" wire:model="wa_valle_nqn" class="input font-mono" placeholder="5492995493102">
                    @error('wa_valle_nqn') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Córdoba</label>
                    <input type="text" wire:model="wa_cordoba" class="input font-mono" placeholder="5493513007925">
                    @error('wa_cordoba') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Mendoza</label>
                    <input type="text" wire:model="wa_mendoza" class="input font-mono" placeholder="5492615117163">
                    @error('wa_mendoza') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove>Guardar ajustes</span>
                <span wire:loading>Guardando…</span>
            </button>
        </div>

    </form>
</div>
