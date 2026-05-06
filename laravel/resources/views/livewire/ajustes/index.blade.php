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

        foreach (['app_nombre', 'ai_modelo', 'timezone', 'wa_bsas', 'wa_valle_nqn', 'wa_cordoba', 'wa_mendoza'] as $key) {
            Setting::set($key, $this->$key);
        }

        $this->guardado = true;
        $this->dispatch('guardado');
    }

}; ?>

<div x-data="{ flash: false }" @guardado.window="flash = true; setTimeout(() => flash = false, 3000)">

    {{-- Flash --}}
    <div x-show="flash" x-transition
         class="mb-6 badge-green px-4 py-3 rounded-xl text-sm font-medium">
        Ajustes guardados correctamente.
    </div>

    <form wire:submit="guardar" class="space-y-6">

        {{-- General --}}
        <div class="card">
            <h3 class="font-condensed font-bold tracking-wide mb-4"
                style="color: var(--vd-text); letter-spacing: 1px; text-transform: uppercase; font-size: 12px;
                       border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 12px;">
                General
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="label">Nombre de la empresa</label>
                    <input type="text" wire:model="app_nombre" class="input" placeholder="Verdeo">
                    @error('app_nombre') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Zona horaria</label>
                    <select wire:model="timezone" class="input">
                        <option value="America/Argentina/Buenos_Aires"
                                style="background: var(--vd-bg-2); color: var(--vd-text);">Buenos Aires (ART)</option>
                        <option value="America/Mendoza"
                                style="background: var(--vd-bg-2); color: var(--vd-text);">Mendoza</option>
                        <option value="UTC"
                                style="background: var(--vd-bg-2); color: var(--vd-text);">UTC</option>
                    </select>
                    @error('timezone') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- AI --}}
        <div class="card">
            <h3 class="font-condensed font-bold tracking-wide mb-1"
                style="color: var(--vd-text); letter-spacing: 1px; text-transform: uppercase; font-size: 12px;">
                Inteligencia Artificial
            </h3>
            <p class="text-sm mb-4" style="color: var(--vd-muted);">Modelo Ollama usado para responder mensajes.</p>
            <div class="max-w-xs">
                <label class="label">Modelo</label>
                <select wire:model="ai_modelo" class="input">
                    <option value="mistral"  style="background: var(--vd-bg-2); color: var(--vd-text);">Mistral 7B</option>
                    <option value="llama3"   style="background: var(--vd-bg-2); color: var(--vd-text);">Llama 3 8B</option>
                    <option value="phi3"     style="background: var(--vd-bg-2); color: var(--vd-text);">Phi-3 Mini</option>
                    <option value="gemma2"   style="background: var(--vd-bg-2); color: var(--vd-text);">Gemma 2 9B</option>
                </select>
                @error('ai_modelo') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- WhatsApp numbers --}}
        <div class="card">
            <h3 class="font-condensed font-bold tracking-wide mb-1"
                style="color: var(--vd-text); letter-spacing: 1px; text-transform: uppercase; font-size: 12px;">
                Números de WhatsApp por zona
            </h3>
            <p class="text-sm mb-4" style="color: var(--vd-muted);">Formato internacional sin + ni espacios (ej: 5491158393179).</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="label">Buenos Aires</label>
                    <input type="text" wire:model="wa_bsas" class="input font-mono" placeholder="5491158393179">
                    @error('wa_bsas') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Valle NQN / Roca</label>
                    <input type="text" wire:model="wa_valle_nqn" class="input font-mono" placeholder="5492995493102">
                    @error('wa_valle_nqn') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Córdoba</label>
                    <input type="text" wire:model="wa_cordoba" class="input font-mono" placeholder="5493513007925">
                    @error('wa_cordoba') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Mendoza</label>
                    <input type="text" wire:model="wa_mendoza" class="input font-mono" placeholder="5492615117163">
                    @error('wa_mendoza') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
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
