<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

new #[Layout('layouts.app', ['title' => 'Ajustes'])] class extends Component {

    // ── General ────────────────────────────────────────────────────────────────
    public string $app_nombre = '';
    public string $timezone   = '';

    // ── Chatbot IA (WhatsApp) ──────────────────────────────────────────────────
    public string $chatbot_ia_proveedor   = 'ollama';
    public string $chatbot_ia_modelo      = 'mistral';
    public string $chatbot_ia_api_key     = '';
    public string $chatbot_ia_prompt      = '';
    public string $chatbot_ia_temperatura = '0.7';
    public bool   $chatbotHasKey          = false;

    // ── Asistente interno ──────────────────────────────────────────────────────
    public string $asistente_ia_proveedor   = 'ollama';
    public string $asistente_ia_modelo      = 'mistral';
    public string $asistente_ia_api_key     = '';
    public string $asistente_ia_temperatura = '0.5';
    public bool   $asistenteHasKey          = false;

    // ── WhatsApp ───────────────────────────────────────────────────────────────
    public array  $wa_bsas          = [];
    public array  $wa_valle_nqn     = [];
    public array  $wa_cordoba       = [];
    public array  $wa_mendoza       = [];
    public string $nuevo_wa_bsas      = '';
    public string $nuevo_wa_valle_nqn = '';
    public string $nuevo_wa_cordoba   = '';
    public string $nuevo_wa_mendoza   = '';

    // ── WhatsApp API ───────────────────────────────────────────────────────────
    public string $wa_proveedor            = 'evolution';
    public string $wa_evolution_url        = '';
    public string $wa_evolution_key        = '';  // cleared after save; never returned
    public bool   $wa_evolution_hasKey     = false;
    public string $wa_evolution_inst_bsas      = '';
    public string $wa_evolution_inst_valle_nqn = '';
    public string $wa_evolution_inst_cordoba   = '';
    public string $wa_evolution_inst_mendoza   = '';
    public string $wa_meta_app_id          = '';
    public string $wa_meta_token           = '';  // cleared after save
    public bool   $wa_meta_hasToken        = false;
    public string $wa_meta_phone_bsas      = '';
    public string $wa_meta_phone_valle_nqn = '';
    public string $wa_meta_phone_cordoba   = '';
    public string $wa_meta_phone_mendoza   = '';
    public ?string $waTestResult           = null;  // null | 'ok' | 'error'
    public string  $waTestMsg              = '';

    public string $tema     = 'bosque';
    public bool   $guardado = false;

    protected function simpleKeys(): array
    {
        return [
            'app_nombre', 'timezone',
            'chatbot_ia_proveedor', 'chatbot_ia_modelo', 'chatbot_ia_prompt', 'chatbot_ia_temperatura',
            'asistente_ia_proveedor', 'asistente_ia_modelo', 'asistente_ia_temperatura',
            'tema',
            'wa_proveedor', 'wa_evolution_url',
            'wa_evolution_inst_bsas', 'wa_evolution_inst_valle_nqn', 'wa_evolution_inst_cordoba', 'wa_evolution_inst_mendoza',
            'wa_meta_app_id',
            'wa_meta_phone_bsas', 'wa_meta_phone_valle_nqn', 'wa_meta_phone_cordoba', 'wa_meta_phone_mendoza',
        ];
    }

    public static function modelosPor(string $proveedor): array
    {
        return match ($proveedor) {
            'claude' => [
                'claude-opus-4-7'           => 'Claude Opus 4.7 — más capaz',
                'claude-sonnet-4-6'         => 'Claude Sonnet 4.6 — balanceado',
                'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 — rápido y económico',
            ],
            'gpt'    => [
                'gpt-4o'        => 'GPT-4o',
                'gpt-4o-mini'   => 'GPT-4o Mini',
                'gpt-4-turbo'   => 'GPT-4 Turbo',
                'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
            ],
            default  => [
                'mistral'    => 'Mistral 7B',
                'llama3'     => 'Llama 3 8B',
                'phi3'       => 'Phi-3 Mini',
                'gemma2'     => 'Gemma 2 9B',
                'llava'      => 'LLaVA (multimodal)',
                'codellama'  => 'Code Llama',
            ],
        };
    }

    public function mount(): void
    {
        $defaults = Setting::defaults();
        foreach ($this->simpleKeys() as $key) {
            $this->$key = Setting::get($key, $defaults[$key] ?? '');
        }
        $this->chatbotHasKey       = !empty(Setting::get('chatbot_ia_api_key', ''));
        $this->asistenteHasKey     = !empty(Setting::get('asistente_ia_api_key', ''));
        $this->wa_evolution_hasKey = !empty(Setting::get('wa_evolution_key', ''));
        $this->wa_meta_hasToken    = !empty(Setting::get('wa_meta_token', ''));

        foreach (['bsas', 'valle_nqn', 'cordoba', 'mendoza'] as $zona) {
            $key     = 'wa_' . $zona;
            $stored  = Setting::get($key, '');
            $decoded = json_decode($stored, true);
            $this->$key = is_array($decoded) ? $decoded : ($stored ? [$stored] : []);
        }
    }

    public function updatedChatbotIaProveedor(): void
    {
        $modelos = static::modelosPor($this->chatbot_ia_proveedor);
        if (!array_key_exists($this->chatbot_ia_modelo, $modelos)) {
            $this->chatbot_ia_modelo = array_key_first($modelos);
        }
    }

    public function updatedAsistenteIaProveedor(): void
    {
        $modelos = static::modelosPor($this->asistente_ia_proveedor);
        if (!array_key_exists($this->asistente_ia_modelo, $modelos)) {
            $this->asistente_ia_modelo = array_key_first($modelos);
        }
    }

    public function agregarNumero(string $zona): void
    {
        $prop = 'nuevo_wa_' . $zona;
        $arr  = 'wa_' . $zona;
        $num  = preg_replace('/\D/', '', trim($this->$prop));
        if ($num && !in_array($num, $this->$arr, true)) {
            $this->$arr[] = $num;
        }
        $this->$prop = '';
    }

    public function quitarNumero(string $zona, int $idx): void
    {
        $arr = 'wa_' . $zona;
        $nums = $this->$arr;
        array_splice($nums, $idx, 1);
        $this->$arr = array_values($nums);
    }

    public function cambiarTema(string $tema): void
    {
        $temas = ['bosque', 'carbon', 'aurora', 'cielo', 'natural'];
        if (!in_array($tema, $temas)) return;
        $this->tema = $tema;
        Setting::set('tema', $tema);
        $this->dispatch('tema-cambiado', tema: $tema);
    }

    public function verificarWa(): void
    {
        $this->waTestResult = null;
        $this->waTestMsg    = '';

        try {
            if ($this->wa_proveedor === 'evolution') {
                $url = rtrim(Setting::get('wa_evolution_url', $this->wa_evolution_url), '/');
                $key = Setting::get('wa_evolution_key', '');
                if (!$url) { $this->waTestResult = 'error'; $this->waTestMsg = 'Configurá la URL primero.'; return; }
                $resp = Http::timeout(8)->withHeaders(['apikey' => $key])->get($url . '/instance/fetchInstances');
                if ($resp->successful()) {
                    $count = count($resp->json() ?? []);
                    $this->waTestResult = 'ok';
                    $this->waTestMsg    = "Conexión OK — {$count} instancia(s) encontrada(s).";
                } else {
                    $this->waTestResult = 'error';
                    $this->waTestMsg    = "Error {$resp->status()}: " . ($resp->json('message') ?? $resp->body());
                }
            } else {
                $token = Setting::get('wa_meta_token', '');
                if (!$token) { $this->waTestResult = 'error'; $this->waTestMsg = 'Configurá el token primero.'; return; }
                $resp = Http::timeout(8)->get('https://graph.facebook.com/v19.0/me', ['access_token' => $token]);
                if ($resp->successful()) {
                    $name = $resp->json('name') ?? 'desconocido';
                    $this->waTestResult = 'ok';
                    $this->waTestMsg    = "Conexión OK — cuenta: {$name}.";
                } else {
                    $this->waTestResult = 'error';
                    $this->waTestMsg    = $resp->json('error.message') ?? "Error {$resp->status()}";
                }
            }
        } catch (\Throwable $e) {
            $this->waTestResult = 'error';
            $this->waTestMsg    = 'Sin respuesta: ' . $e->getMessage();
        }
    }

    public function guardar(): void
    {
        $rules = [
            'app_nombre'               => 'required|min:2|max:60',
            'timezone'                 => 'required|timezone',
            'chatbot_ia_proveedor'     => 'required|in:claude,gpt,ollama',
            'chatbot_ia_modelo'        => 'required|string|max:80',
            'chatbot_ia_prompt'        => 'nullable|max:2000',
            'chatbot_ia_temperatura'   => 'required|numeric|min:0|max:1',
            'asistente_ia_proveedor'   => 'required|in:claude,gpt,ollama',
            'asistente_ia_modelo'      => 'required|string|max:80',
            'asistente_ia_temperatura' => 'required|numeric|min:0|max:1',
        ];

        // API key requerida si el proveedor la necesita y aún no hay una guardada
        foreach (['chatbot', 'asistente'] as $ctx) {
            $prov  = $ctx === 'chatbot' ? $this->chatbot_ia_proveedor : $this->asistente_ia_proveedor;
            $field = "{$ctx}_ia_api_key";
            if (in_array($prov, ['claude', 'gpt'])) {
                $existing = Setting::get($field, '');
                if (!empty($this->$field) || empty($existing)) {
                    $rules[$field] = 'required|min:20|max:300';
                }
            }
        }

        $this->validate($rules);

        foreach ($this->simpleKeys() as $key) {
            Setting::set($key, $this->$key);
        }

        // API keys: guardar solo si se proporcionó una nueva; limpiar del estado
        foreach (['chatbot', 'asistente'] as $ctx) {
            $field = "{$ctx}_ia_api_key";
            if (!empty($this->$field)) {
                Setting::set($field, $this->$field);
                $this->$field = '';
            }
        }

        $this->chatbotHasKey   = !empty(Setting::get('chatbot_ia_api_key', ''));
        $this->asistenteHasKey = !empty(Setting::get('asistente_ia_api_key', ''));

        // WA API secure keys: guardar solo si se proporcionó un nuevo valor
        if (!empty($this->wa_evolution_key)) {
            Setting::set('wa_evolution_key', $this->wa_evolution_key);
            $this->wa_evolution_key = '';
        }
        if (!empty($this->wa_meta_token)) {
            Setting::set('wa_meta_token', $this->wa_meta_token);
            $this->wa_meta_token = '';
        }
        $this->wa_evolution_hasKey = !empty(Setting::get('wa_evolution_key', ''));
        $this->wa_meta_hasToken    = !empty(Setting::get('wa_meta_token', ''));

        // Guardar números de WhatsApp como JSON arrays
        foreach (['bsas', 'valle_nqn', 'cordoba', 'mendoza'] as $zona) {
            $key = 'wa_' . $zona;
            Setting::set($key, json_encode(array_values($this->$key)));
        }

        $this->guardado = true;
        $this->dispatch('guardado');
    }

}; ?>

<div x-data="{ flash: false }" @guardado.window="flash = true; setTimeout(() => flash = false, 3500)">

    <div x-show="flash" x-transition
         class="mb-6 badge-green px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
        Ajustes guardados correctamente.
    </div>

    {{-- Theme change: apply immediately via JS + localStorage --}}
    <div x-data @tema-cambiado.window="
        var t = $event.detail.tema;
        document.documentElement.setAttribute('data-theme', t);
        localStorage.setItem('verdeo-theme', t);
    "></div>

    <form wire:submit="guardar" class="space-y-6">

        {{-- ── APARIENCIA ──────────────────────────────────────────────────────── --}}
        <div class="card">
            <div class="flex items-start gap-3 mb-5" style="border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 14px;">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                     style="background: rgba(78,158,90,0.12); border: 1px solid rgba(78,158,90,0.25);">
                    <svg width="16" height="16" fill="none" stroke="#4e9e5a" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 00-5.78 1.128 2.25 2.25 0 01-2.4 2.245 4.5 4.5 0 008.4-2.245c0-.399-.078-.78-.22-1.128zm0 0a15.998 15.998 0 003.388-1.62m-5.043-.025a15.994 15.994 0 011.622-3.395m3.42 3.42a15.995 15.995 0 004.764-4.648l3.876-5.814a1.151 1.151 0 00-1.597-1.597L14.146 6.32a15.996 15.996 0 00-4.649 4.763m3.42 3.42a6.776 6.776 0 00-3.42-3.42"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-condensed font-bold tracking-wide"
                        style="color: var(--vd-text); letter-spacing: 1px; text-transform: uppercase; font-size: 12px;">
                        Apariencia
                    </h3>
                    <p class="text-xs mt-0.5" style="color: var(--vd-muted);">
                        Elegí el tema visual del sistema. El cambio es inmediato.
                    </p>
                </div>
            </div>

            @php
            $temas = [
                'bosque'  => ['Bosque',   '#0b1828', '#3a7d44', '#4e9e5a', 'Oscuro · verde'],
                'carbon'  => ['Carbón',   '#111827', '#4b5563', '#6b7280', 'Oscuro · gris'],
                'aurora'  => ['Aurora',   '#0f0b1e', '#7c3aed', '#a855f7', 'Oscuro · violeta'],
                'cielo'   => ['Cielo',    '#f0f4f8', '#1d4ed8', '#2563eb', 'Claro · azul'],
                'natural' => ['Natural',  '#f0f5f0', '#2d6e38', '#3a7d44', 'Claro · verde'],
            ];
            @endphp

            <div class="flex flex-wrap gap-3">
                @foreach($temas as $slug => [$label, $bg, $acc1, $acc2, $desc])
                <button type="button"
                        wire:click="cambiarTema('{{ $slug }}')"
                        class="group flex flex-col items-center gap-2 p-0 rounded-xl transition-all duration-200"
                        style="background: none; border: none; cursor: pointer;">

                    {{-- Color preview card --}}
                    <div class="relative rounded-xl overflow-hidden transition-all duration-200"
                         style="width: 72px; height: 48px; background: {{ $bg }};
                                box-shadow: {{ $tema === $slug ? '0 0 0 2px #4e9e5a, 0 4px 16px rgba(0,0,0,0.3)' : '0 2px 8px rgba(0,0,0,0.2)' }};
                                border: 2px solid {{ $tema === $slug ? '#4e9e5a' : 'rgba(255,255,255,0.08)' }};">

                        {{-- Sidebar strip --}}
                        <div style="position:absolute; left:0; top:0; bottom:0; width:20px; background: {{ $acc1 }}; opacity: 0.85;"></div>

                        {{-- Content dots --}}
                        <div style="position:absolute; left:26px; top:10px; right:6px;">
                            <div style="height:5px; border-radius:3px; background:{{ $acc2 }}; opacity:0.7; margin-bottom:4px;"></div>
                            <div style="height:4px; border-radius:3px; background:{{ $acc2 }}; opacity:0.4; width:70%;"></div>
                        </div>

                        @if($tema === $slug)
                        <div style="position:absolute; bottom:4px; right:4px;">
                            <svg width="12" height="12" fill="none" stroke="#4e9e5a" stroke-width="3" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                            </svg>
                        </div>
                        @endif
                    </div>

                    {{-- Label --}}
                    <div class="text-center">
                        <p class="text-xs font-semibold"
                           style="color: {{ $tema === $slug ? 'var(--vd-green-lt)' : 'var(--vd-text-soft)' }};">
                            {{ $label }}
                        </p>
                        <p class="text-[10px]" style="color: var(--vd-muted-2);">{{ $desc }}</p>
                    </div>
                </button>
                @endforeach
            </div>
        </div>

        {{-- ── GENERAL ─────────────────────────────────────────────────────────── --}}
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
                        <option value="America/Argentina/Buenos_Aires" style="background:var(--vd-bg-2);color:var(--vd-text);">Buenos Aires (ART)</option>
                        <option value="America/Mendoza"                style="background:var(--vd-bg-2);color:var(--vd-text);">Mendoza</option>
                        <option value="UTC"                            style="background:var(--vd-bg-2);color:var(--vd-text);">UTC</option>
                    </select>
                    @error('timezone') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- ── INTELIGENCIA ARTIFICIAL ──────────────────────────────────────────── --}}
        <div class="card">
            <div class="flex items-start gap-3 mb-5" style="border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 14px;">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                     style="background: rgba(78,158,90,0.12); border: 1px solid rgba(78,158,90,0.25);">
                    <svg width="16" height="16" fill="none" stroke="#4e9e5a" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-condensed font-bold tracking-wide"
                        style="color: var(--vd-text); letter-spacing: 1px; text-transform: uppercase; font-size: 12px;">
                        Inteligencia Artificial
                    </h3>
                    <p class="text-xs mt-0.5" style="color: var(--vd-muted);">
                        Configurá el proveedor y modelo para cada contexto de uso.
                    </p>
                </div>
            </div>

            <div x-data="{ tab: 'chatbot' }">

                {{-- Tabs --}}
                <div class="flex gap-1.5 p-1.5 rounded-xl mb-6"
                     style="background: rgba(0,0,0,0.2); border: 1px solid var(--vd-bdr-soft);">
                    <button type="button" @click="tab = 'chatbot'"
                            class="flex-1 flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-sm font-semibold transition-all"
                            :style="tab === 'chatbot'
                                ? 'background: rgba(78,158,90,0.18); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.35);'
                                : 'color: var(--vd-muted); border: 1px solid transparent;'">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>
                        </svg>
                        Chatbot WhatsApp
                    </button>
                    <button type="button" @click="tab = 'asistente'"
                            class="flex-1 flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-sm font-semibold transition-all"
                            :style="tab === 'asistente'
                                ? 'background: rgba(78,158,90,0.18); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.35);'
                                : 'color: var(--vd-muted); border: 1px solid transparent;'">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                        </svg>
                        Asistente interno
                    </button>
                </div>

                {{-- ── TAB: CHATBOT ──────────────────────────────────────────────── --}}
                <div x-show="tab === 'chatbot'" x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">

                    {{-- Provider --}}
                    <div class="mb-5">
                        <label class="label mb-2">Proveedor</label>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach([
                                'claude'  => ['label' => 'Claude',  'sub' => 'Anthropic',    'color' => '#a855f7', 'char' => 'C'],
                                'gpt'     => ['label' => 'GPT',     'sub' => 'OpenAI',       'color' => '#10b981', 'char' => 'G'],
                                'ollama'  => ['label' => 'Ollama',  'sub' => 'Local / gratis','color' => '#f59e0b', 'char' => 'O'],
                            ] as $prov => $info)
                            <button type="button" wire:click="$set('chatbot_ia_proveedor', '{{ $prov }}')"
                                    class="flex flex-col items-center gap-2 py-3 px-2 rounded-xl border transition-all"
                                    style="{{ $chatbot_ia_proveedor === $prov
                                        ? 'border-color:' . $info['color'] . ';background:' . $info['color'] . '1a;'
                                        : 'border-color:var(--vd-bdr);' }}">
                                <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-base flex-shrink-0"
                                     style="background: {{ $info['color'] }}22; color: {{ $info['color'] }}; border: 2px solid {{ $info['color'] }}44;">
                                    {{ $info['char'] }}
                                </div>
                                <div class="text-center">
                                    <div class="text-sm font-bold leading-tight"
                                         style="color: {{ $chatbot_ia_proveedor === $prov ? $info['color'] : 'var(--vd-text)' }}">
                                        {{ $info['label'] }}
                                    </div>
                                    <div class="text-xs leading-tight" style="color: var(--vd-muted);">{{ $info['sub'] }}</div>
                                </div>
                            </button>
                            @endforeach
                        </div>
                        {{-- Descripción del proveedor seleccionado --}}
                        <p class="text-xs mt-2 px-1" style="color: var(--vd-muted);">
                            @if($chatbot_ia_proveedor === 'claude') Mejor comprensión contextual y respuestas largas. Requiere API key de Anthropic.
                            @elseif($chatbot_ia_proveedor === 'gpt') Ampliamente adoptado, buen equilibrio velocidad/costo. Requiere API key de OpenAI.
                            @else Corre en tu servidor, sin costo adicional por token. Privacidad total. @endif
                        </p>
                    </div>

                    {{-- Modelo --}}
                    <div class="mb-5">
                        <label class="label">Modelo</label>
                        <select wire:model="chatbot_ia_modelo" class="input">
                            @if($chatbot_ia_proveedor === 'claude')
                                <option value="claude-opus-4-7"           style="background:var(--vd-bg-2);color:var(--vd-text);">Claude Opus 4.7 — más capaz</option>
                                <option value="claude-sonnet-4-6"         style="background:var(--vd-bg-2);color:var(--vd-text);">Claude Sonnet 4.6 — balanceado ✓</option>
                                <option value="claude-haiku-4-5-20251001" style="background:var(--vd-bg-2);color:var(--vd-text);">Claude Haiku 4.5 — rápido y económico</option>
                            @elseif($chatbot_ia_proveedor === 'gpt')
                                <option value="gpt-4o"        style="background:var(--vd-bg-2);color:var(--vd-text);">GPT-4o</option>
                                <option value="gpt-4o-mini"   style="background:var(--vd-bg-2);color:var(--vd-text);">GPT-4o Mini ✓</option>
                                <option value="gpt-4-turbo"   style="background:var(--vd-bg-2);color:var(--vd-text);">GPT-4 Turbo</option>
                                <option value="gpt-3.5-turbo" style="background:var(--vd-bg-2);color:var(--vd-text);">GPT-3.5 Turbo</option>
                            @else
                                <option value="mistral"   style="background:var(--vd-bg-2);color:var(--vd-text);">Mistral 7B ✓</option>
                                <option value="llama3"    style="background:var(--vd-bg-2);color:var(--vd-text);">Llama 3 8B</option>
                                <option value="phi3"      style="background:var(--vd-bg-2);color:var(--vd-text);">Phi-3 Mini</option>
                                <option value="gemma2"    style="background:var(--vd-bg-2);color:var(--vd-text);">Gemma 2 9B</option>
                                <option value="llava"     style="background:var(--vd-bg-2);color:var(--vd-text);">LLaVA (multimodal)</option>
                                <option value="codellama" style="background:var(--vd-bg-2);color:var(--vd-text);">Code Llama</option>
                            @endif
                        </select>
                        @error('chatbot_ia_modelo') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                    </div>

                    {{-- API Key (solo si no es Ollama) --}}
                    @if($chatbot_ia_proveedor !== 'ollama')
                    <div class="mb-5" x-data="{ show: false, cambiar: false }">
                        <label class="label">
                            API Key
                            <span class="ml-1 text-xs font-normal" style="color: var(--vd-muted);">
                                ({{ $chatbot_ia_proveedor === 'claude' ? 'console.anthropic.com' : 'platform.openai.com' }})
                            </span>
                        </label>
                        @if($chatbotHasKey && empty($chatbot_ia_api_key))
                        <div class="flex items-center gap-3" x-show="!cambiar">
                            <span class="badge-green text-xs px-3 py-1.5 rounded-lg flex items-center gap-1.5">
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                Clave configurada
                            </span>
                            <button type="button" @click="cambiar = true"
                                    class="text-xs transition-colors" style="color: var(--vd-muted);"
                                    onmouseover="this.style.color='var(--vd-text)'" onmouseout="this.style.color='var(--vd-muted)'">
                                Cambiar clave →
                            </button>
                        </div>
                        <div x-show="cambiar">
                            <div class="relative">
                                <input :type="show ? 'text' : 'password'" wire:model="chatbot_ia_api_key"
                                       class="input font-mono pr-10" placeholder="sk-ant-...">
                                <button type="button" @click="show = !show"
                                        class="absolute right-3 top-1/2 -translate-y-1/2" style="color: var(--vd-muted);">
                                    <svg x-show="!show" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <svg x-show="show" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                                </button>
                            </div>
                        </div>
                        @else
                        <div class="relative">
                            <input :type="show ? 'text' : 'password'" wire:model="chatbot_ia_api_key"
                                   class="input font-mono pr-10"
                                   placeholder="{{ $chatbot_ia_proveedor === 'claude' ? 'sk-ant-api03-...' : 'sk-proj-...' }}">
                            <button type="button" @click="show = !show"
                                    class="absolute right-3 top-1/2 -translate-y-1/2" style="color: var(--vd-muted);">
                                <svg x-show="!show" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <svg x-show="show" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                            </button>
                        </div>
                        @endif
                        @error('chatbot_ia_api_key') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                    </div>
                    @endif

                    {{-- Prompt del sistema --}}
                    <div class="mb-5" x-data="{ chars: {{ strlen($chatbot_ia_prompt) }} }">
                        <div class="flex items-baseline justify-between mb-1">
                            <label class="label">Prompt del sistema</label>
                            <span class="text-xs" style="color: var(--vd-muted);">
                                <span x-text="chars"></span>/2000
                            </span>
                        </div>
                        <textarea wire:model="chatbot_ia_prompt"
                                  @input="chars = $event.target.value.length"
                                  class="input resize-none" rows="4"
                                  placeholder="Instrucciones de comportamiento para el chatbot de WhatsApp. Variables disponibles: @{{zona}}, @{{empresa}}">{{ $chatbot_ia_prompt }}</textarea>
                        <p class="text-xs mt-1" style="color: var(--vd-muted);">
                            Define el tono, límites y personalidad del bot. No incluyas información de pedidos en curso.
                        </p>
                        @error('chatbot_ia_prompt') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                    </div>

                    {{-- Temperatura --}}
                    <div x-data="{ temp: parseFloat('{{ $chatbot_ia_temperatura }}') }">
                        <div class="flex items-baseline justify-between mb-2">
                            <label class="label">Temperatura</label>
                            <span class="text-xs font-mono font-bold" style="color: #4e9e5a;" x-text="temp.toFixed(1)"></span>
                        </div>
                        <input type="range" wire:model="chatbot_ia_temperatura"
                               @input="temp = parseFloat($event.target.value)"
                               min="0" max="1" step="0.1" class="w-full" style="accent-color: #4e9e5a;">
                        <div class="flex justify-between text-xs mt-1" style="color: var(--vd-muted);">
                            <span>Conservador (preciso)</span>
                            <span>Creativo (variado)</span>
                        </div>
                    </div>
                </div>

                {{-- ── TAB: ASISTENTE INTERNO ────────────────────────────────────── --}}
                <div x-show="tab === 'asistente'" x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">

                    {{-- Provider --}}
                    <div class="mb-5">
                        <label class="label mb-2">Proveedor</label>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach([
                                'claude'  => ['label' => 'Claude',  'sub' => 'Anthropic',    'color' => '#a855f7', 'char' => 'C'],
                                'gpt'     => ['label' => 'GPT',     'sub' => 'OpenAI',       'color' => '#10b981', 'char' => 'G'],
                                'ollama'  => ['label' => 'Ollama',  'sub' => 'Local / gratis','color' => '#f59e0b', 'char' => 'O'],
                            ] as $prov => $info)
                            <button type="button" wire:click="$set('asistente_ia_proveedor', '{{ $prov }}')"
                                    class="flex flex-col items-center gap-2 py-3 px-2 rounded-xl border transition-all"
                                    style="{{ $asistente_ia_proveedor === $prov
                                        ? 'border-color:' . $info['color'] . ';background:' . $info['color'] . '1a;'
                                        : 'border-color:var(--vd-bdr);' }}">
                                <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-base flex-shrink-0"
                                     style="background: {{ $info['color'] }}22; color: {{ $info['color'] }}; border: 2px solid {{ $info['color'] }}44;">
                                    {{ $info['char'] }}
                                </div>
                                <div class="text-center">
                                    <div class="text-sm font-bold leading-tight"
                                         style="color: {{ $asistente_ia_proveedor === $prov ? $info['color'] : 'var(--vd-text)' }}">
                                        {{ $info['label'] }}
                                    </div>
                                    <div class="text-xs leading-tight" style="color: var(--vd-muted);">{{ $info['sub'] }}</div>
                                </div>
                            </button>
                            @endforeach
                        </div>
                        <p class="text-xs mt-2 px-1" style="color: var(--vd-muted);">
                            @if($asistente_ia_proveedor === 'claude') Mayor precisión en análisis y generación de contenido interno.
                            @elseif($asistente_ia_proveedor === 'gpt') Ampliamente adoptado, buen equilibrio velocidad/costo. Requiere API key de OpenAI.
                            @else Corre en tu servidor, sin costo adicional por token. Privacidad total. @endif
                        </p>
                    </div>

                    {{-- Modelo --}}
                    <div class="mb-5">
                        <label class="label">Modelo</label>
                        <select wire:model="asistente_ia_modelo" class="input">
                            @if($asistente_ia_proveedor === 'claude')
                                <option value="claude-opus-4-7"           style="background:var(--vd-bg-2);color:var(--vd-text);">Claude Opus 4.7 — más capaz</option>
                                <option value="claude-sonnet-4-6"         style="background:var(--vd-bg-2);color:var(--vd-text);">Claude Sonnet 4.6 — balanceado ✓</option>
                                <option value="claude-haiku-4-5-20251001" style="background:var(--vd-bg-2);color:var(--vd-text);">Claude Haiku 4.5 — rápido y económico</option>
                            @elseif($asistente_ia_proveedor === 'gpt')
                                <option value="gpt-4o"        style="background:var(--vd-bg-2);color:var(--vd-text);">GPT-4o</option>
                                <option value="gpt-4o-mini"   style="background:var(--vd-bg-2);color:var(--vd-text);">GPT-4o Mini ✓</option>
                                <option value="gpt-4-turbo"   style="background:var(--vd-bg-2);color:var(--vd-text);">GPT-4 Turbo</option>
                                <option value="gpt-3.5-turbo" style="background:var(--vd-bg-2);color:var(--vd-text);">GPT-3.5 Turbo</option>
                            @else
                                <option value="mistral"   style="background:var(--vd-bg-2);color:var(--vd-text);">Mistral 7B ✓</option>
                                <option value="llama3"    style="background:var(--vd-bg-2);color:var(--vd-text);">Llama 3 8B</option>
                                <option value="phi3"      style="background:var(--vd-bg-2);color:var(--vd-text);">Phi-3 Mini</option>
                                <option value="gemma2"    style="background:var(--vd-bg-2);color:var(--vd-text);">Gemma 2 9B</option>
                                <option value="llava"     style="background:var(--vd-bg-2);color:var(--vd-text);">LLaVA (multimodal)</option>
                                <option value="codellama" style="background:var(--vd-bg-2);color:var(--vd-text);">Code Llama</option>
                            @endif
                        </select>
                        @error('asistente_ia_modelo') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                    </div>

                    {{-- API Key --}}
                    @if($asistente_ia_proveedor !== 'ollama')
                    <div class="mb-5" x-data="{ show: false, cambiar: false }">
                        <label class="label">
                            API Key
                            <span class="ml-1 text-xs font-normal" style="color: var(--vd-muted);">
                                ({{ $asistente_ia_proveedor === 'claude' ? 'console.anthropic.com' : 'platform.openai.com' }})
                            </span>
                        </label>
                        @if($asistenteHasKey && empty($asistente_ia_api_key))
                        <div class="flex items-center gap-3" x-show="!cambiar">
                            <span class="badge-green text-xs px-3 py-1.5 rounded-lg flex items-center gap-1.5">
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                Clave configurada
                            </span>
                            <button type="button" @click="cambiar = true"
                                    class="text-xs transition-colors" style="color: var(--vd-muted);"
                                    onmouseover="this.style.color='var(--vd-text)'" onmouseout="this.style.color='var(--vd-muted)'">
                                Cambiar clave →
                            </button>
                        </div>
                        <div x-show="cambiar">
                            <div class="relative">
                                <input :type="show ? 'text' : 'password'" wire:model="asistente_ia_api_key"
                                       class="input font-mono pr-10" placeholder="sk-ant-...">
                                <button type="button" @click="show = !show"
                                        class="absolute right-3 top-1/2 -translate-y-1/2" style="color: var(--vd-muted);">
                                    <svg x-show="!show" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <svg x-show="show" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                                </button>
                            </div>
                        </div>
                        @else
                        <div class="relative">
                            <input :type="show ? 'text' : 'password'" wire:model="asistente_ia_api_key"
                                   class="input font-mono pr-10"
                                   placeholder="{{ $asistente_ia_proveedor === 'claude' ? 'sk-ant-api03-...' : 'sk-proj-...' }}">
                            <button type="button" @click="show = !show"
                                    class="absolute right-3 top-1/2 -translate-y-1/2" style="color: var(--vd-muted);">
                                <svg x-show="!show" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <svg x-show="show" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                            </button>
                        </div>
                        @endif
                        @error('asistente_ia_api_key') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                    </div>
                    @endif

                    {{-- Temperatura --}}
                    <div x-data="{ temp: parseFloat('{{ $asistente_ia_temperatura }}') }">
                        <div class="flex items-baseline justify-between mb-2">
                            <label class="label">Temperatura</label>
                            <span class="text-xs font-mono font-bold" style="color: #4e9e5a;" x-text="temp.toFixed(1)"></span>
                        </div>
                        <input type="range" wire:model="asistente_ia_temperatura"
                               @input="temp = parseFloat($event.target.value)"
                               min="0" max="1" step="0.1" class="w-full" style="accent-color: #4e9e5a;">
                        <div class="flex justify-between text-xs mt-1" style="color: var(--vd-muted);">
                            <span>Conservador (preciso)</span>
                            <span>Creativo (variado)</span>
                        </div>
                    </div>
                </div>

            </div>{{-- /x-data tabs --}}
        </div>

        {{-- ── WHATSAPP POR ZONA ────────────────────────────────────────────────── --}}
        <div class="card">
            <h3 class="font-condensed font-bold tracking-wide mb-1"
                style="color: var(--vd-text); letter-spacing: 1px; text-transform: uppercase; font-size: 12px;">
                Números de WhatsApp por zona
            </h3>
            <p class="text-sm mb-5" style="color: var(--vd-muted);">
                Podés agregar múltiples números por zona. Formato internacional sin + ni espacios (ej: 5491158393179).
            </p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                @foreach([
                    'bsas'      => ['label' => 'Buenos Aires',     'placeholder' => '5491158393179',  'numeros' => $wa_bsas],
                    'valle_nqn' => ['label' => 'Valle NQN / Roca', 'placeholder' => '5492995493102',  'numeros' => $wa_valle_nqn],
                    'cordoba'   => ['label' => 'Córdoba',           'placeholder' => '5493513007925',  'numeros' => $wa_cordoba],
                    'mendoza'   => ['label' => 'Mendoza',           'placeholder' => '5492615117163',  'numeros' => $wa_mendoza],
                ] as $slug => $zona)
                <div>
                    <label class="label mb-2">{{ $zona['label'] }}</label>

                    {{-- Chips de números existentes --}}
                    <div class="flex flex-wrap gap-1.5 mb-2 min-h-[28px]">
                        @forelse($zona['numeros'] as $idx => $num)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg font-mono text-xs font-medium"
                              style="background: rgba(78,158,90,0.12); border: 1px solid rgba(78,158,90,0.3); color: #4e9e5a;">
                            +{{ $num }}
                            <button type="button"
                                    wire:click="quitarNumero('{{ $slug }}', {{ $idx }})"
                                    class="transition-opacity hover:opacity-100 opacity-50 leading-none"
                                    style="color: #ef4444; font-size: 11px;">✕</button>
                        </span>
                        @empty
                        <span class="text-xs" style="color: var(--vd-muted-2);">Sin números configurados</span>
                        @endforelse
                    </div>

                    {{-- Input para agregar --}}
                    <div class="flex gap-2">
                        <input type="text"
                               wire:model="nuevo_wa_{{ $slug }}"
                               wire:keydown.enter.prevent="agregarNumero('{{ $slug }}')"
                               class="input font-mono text-sm flex-1"
                               placeholder="{{ $zona['placeholder'] }}">
                        <button type="button"
                                wire:click="agregarNumero('{{ $slug }}')"
                                class="btn-secondary text-xs px-3 flex-shrink-0">
                            + Agregar
                        </button>
                    </div>
                </div>
                @endforeach

            </div>
        </div>

        {{-- ── API DE WHATSAPP ─────────────────────────────────────────────────── --}}
        <div class="card">
            <div class="flex items-start gap-3 mb-5" style="border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 14px;">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                     style="background: rgba(37,211,102,0.12); border: 1px solid rgba(37,211,102,0.25);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="#25d366">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-condensed font-bold tracking-wide"
                        style="color: var(--vd-text); letter-spacing: 1px; text-transform: uppercase; font-size: 12px;">
                        API de WhatsApp
                    </h3>
                    <p class="text-xs mt-0.5" style="color: var(--vd-muted);">
                        Configurá la API de envío para mensajes masivos y notificaciones.
                    </p>
                </div>
            </div>

            {{-- Proveedor pills --}}
            <div class="flex gap-2 mb-6">
                <button type="button" wire:click="$set('wa_proveedor', 'evolution')"
                        class="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold transition-all"
                        style="{{ $wa_proveedor === 'evolution'
                            ? 'background: rgba(37,211,102,0.15); color: #25d366; border: 1px solid rgba(37,211,102,0.4);'
                            : 'background: var(--vd-bg-2); color: var(--vd-muted); border: 1px solid var(--vd-bdr);' }}">
                    <span class="font-mono text-xs">Evo</span> Evolution API
                </button>
                <button type="button" wire:click="$set('wa_proveedor', 'meta')"
                        class="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold transition-all"
                        style="{{ $wa_proveedor === 'meta'
                            ? 'background: rgba(24,119,242,0.15); color: #1877f2; border: 1px solid rgba(24,119,242,0.4);'
                            : 'background: var(--vd-bg-2); color: var(--vd-muted); border: 1px solid var(--vd-bdr);' }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="{{ $wa_proveedor === 'meta' ? '#1877f2' : 'currentColor' }}">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                    META WhatsApp Business
                </button>
            </div>

            {{-- Test result badge --}}
            @if($waTestResult)
            <div class="mb-5 px-4 py-2.5 rounded-xl text-sm font-medium flex items-center gap-2"
                 style="background: {{ $waTestResult === 'ok' ? 'rgba(78,158,90,0.12)' : 'rgba(239,68,68,0.10)' }};
                        border: 1px solid {{ $waTestResult === 'ok' ? 'rgba(78,158,90,0.3)' : 'rgba(239,68,68,0.25)' }};
                        color: {{ $waTestResult === 'ok' ? '#4e9e5a' : '#ef4444' }};">
                @if($waTestResult === 'ok')
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                @else
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                @endif
                {{ $waTestMsg }}
            </div>
            @endif

            {{-- Evolution API fields --}}
            @if($wa_proveedor === 'evolution')
            <div class="space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="label">URL de la instancia Evolution</label>
                        <input type="text" wire:model="wa_evolution_url" class="input"
                               placeholder="http://localhost:8080">
                        <p class="text-xs mt-1" style="color: var(--vd-muted-2);">Ejemplo: http://192.168.1.10:8080</p>
                    </div>
                    <div x-data="{ show: false }">
                        <label class="label">API Key</label>
                        @if($wa_evolution_hasKey)
                        <div class="flex items-center gap-2">
                            <div class="input flex-1 font-mono text-xs flex items-center" style="color: var(--vd-muted);">
                                ••••••••••••••••
                            </div>
                            <button type="button" @click="$wire.set('wa_evolution_hasKey', false)"
                                    class="btn-secondary text-xs px-3 flex-shrink-0">Cambiar</button>
                        </div>
                        @else
                        <div class="relative">
                            <input :type="show ? 'text' : 'password'" wire:model="wa_evolution_key"
                                   class="input font-mono pr-10" placeholder="tu-api-key-de-evolution">
                            <button type="button" @click="show = !show"
                                    class="absolute right-3 top-1/2 -translate-y-1/2" style="color: var(--vd-muted);">
                                <svg x-show="!show" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <svg x-show="show" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                            </button>
                        </div>
                        @endif
                        <p class="text-xs mt-1" style="color: var(--vd-muted-2);">La clave no se muestra después de guardar.</p>
                    </div>
                </div>

                <div>
                    <label class="label mb-3">Nombre de instancia por zona</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach(['bsas' => 'Buenos Aires', 'valle_nqn' => 'Valle NQN', 'cordoba' => 'Córdoba', 'mendoza' => 'Mendoza'] as $slug => $label)
                        <div class="flex items-center gap-2">
                            <span class="text-xs w-24 flex-shrink-0" style="color: var(--vd-muted);">{{ $label }}</span>
                            <input type="text" wire:model="wa_evolution_inst_{{ $slug }}" class="input text-sm"
                                   placeholder="instancia-{{ $slug }}">
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            {{-- META fields --}}
            @if($wa_proveedor === 'meta')
            <div class="space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="label">App ID</label>
                        <input type="text" wire:model="wa_meta_app_id" class="input font-mono"
                               placeholder="123456789012345">
                    </div>
                    <div x-data="{ show: false }">
                        <label class="label">Token de acceso</label>
                        @if($wa_meta_hasToken)
                        <div class="flex items-center gap-2">
                            <div class="input flex-1 font-mono text-xs flex items-center" style="color: var(--vd-muted);">
                                ••••••••••••••••
                            </div>
                            <button type="button" @click="$wire.set('wa_meta_hasToken', false)"
                                    class="btn-secondary text-xs px-3 flex-shrink-0">Cambiar</button>
                        </div>
                        @else
                        <div class="relative">
                            <input :type="show ? 'text' : 'password'" wire:model="wa_meta_token"
                                   class="input font-mono pr-10" placeholder="EAABwz...">
                            <button type="button" @click="show = !show"
                                    class="absolute right-3 top-1/2 -translate-y-1/2" style="color: var(--vd-muted);">
                                <svg x-show="!show" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <svg x-show="show" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                            </button>
                        </div>
                        @endif
                        <p class="text-xs mt-1" style="color: var(--vd-muted-2);">El token no se muestra después de guardar.</p>
                    </div>
                </div>

                <div>
                    <label class="label mb-3">Phone Number ID por zona</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach(['bsas' => 'Buenos Aires', 'valle_nqn' => 'Valle NQN', 'cordoba' => 'Córdoba', 'mendoza' => 'Mendoza'] as $slug => $label)
                        <div class="flex items-center gap-2">
                            <span class="text-xs w-24 flex-shrink-0" style="color: var(--vd-muted);">{{ $label }}</span>
                            <input type="text" wire:model="wa_meta_phone_{{ $slug }}" class="input text-sm font-mono"
                                   placeholder="1234567890123">
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            {{-- Verify button --}}
            <div class="mt-5 pt-4 flex items-center gap-3" style="border-top: 1px solid var(--vd-bdr-soft);">
                <button type="button" wire:click="verificarWa" wire:loading.attr="disabled"
                        class="btn-secondary text-sm flex items-center gap-2">
                    <svg wire:loading.remove wire:target="verificarWa" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <svg wire:loading wire:target="verificarWa" class="animate-spin" width="14" height="14" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Verificar conexión
                </button>
                <p class="text-xs" style="color: var(--vd-muted-2);">
                    Comprueba que la API esté accesible con las credenciales actuales.
                </p>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove>Guardar ajustes</span>
                <span wire:loading class="flex items-center gap-2">
                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Guardando…
                </span>
            </button>
        </div>

    </form>
</div>
