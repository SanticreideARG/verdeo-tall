<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Setting;
use App\Models\Zona;
use App\Models\Producto;
use App\Models\Plato;
use App\Services\BotPermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

new #[Layout('layouts.app', ['title' => 'Ajustes'])] class extends Component {

    // ── General ────────────────────────────────────────────────────────────────
    public string $app_nombre = '';
    public string $timezone   = '';

    // ── Chatbot IA (WhatsApp) ──────────────────────────────────────────────────
    public string $chatbot_ia_proveedor   = 'claude';
    public string $chatbot_ia_modelo      = 'claude-haiku-4-5-20251001';
    public string $chatbot_ia_api_key     = '';
    public string $chatbot_ia_prompt      = '';
    public string $chatbot_ia_temperatura = '0.7';
    public bool   $chatbotHasKey          = false;

    // ── Asistente interno ──────────────────────────────────────────────────────
    public string $asistente_ia_proveedor   = 'claude';
    public string $asistente_ia_modelo      = 'claude-haiku-4-5-20251001';
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

    // ── Correo electrónico (SMTP) ─────────────────────────────────────────────
    public string  $mail_preset       = 'smtp';
    public string  $mail_host         = '';
    public string  $mail_port         = '587';
    public string  $mail_username     = '';
    public string  $mail_password     = '';
    public bool    $mail_hasPassword  = false;
    public string  $mail_encryption   = 'tls';
    public string  $mail_from_address = '';
    public string  $mail_from_name    = '';
    public string  $mail_test_to      = '';
    public ?string $mailTestResult    = null;
    public string  $mailTestMsg       = '';

    // ── Meta App compartida (Messenger + Instagram + WA Business) ─────────────
    public string  $meta_app_secret        = '';   // secure, solo escritura
    public bool    $meta_hasSecret         = false;
    public string  $meta_verify_token      = '';   // string libre para webhook

    // ── Messenger ─────────────────────────────────────────────────────────────
    public bool    $messenger_habilitado   = false;
    public string  $messenger_page_id     = '';
    public string  $messenger_page_token  = '';   // secure
    public bool    $messenger_hasToken    = false;
    public ?string $messengerTestResult   = null;
    public string  $messengerTestMsg      = '';

    // ── Instagram DMs ─────────────────────────────────────────────────────────
    public bool    $instagram_habilitado   = false;
    public string  $instagram_account_id  = '';   // IG Business Account ID
    public ?string $instagramTestResult   = null;
    public string  $instagramTestMsg      = '';

    public string $tema       = 'bosque';
    public bool   $guardado   = false;

    // ── Agente Bot ─────────────────────────────────────────────────────────────
    public array $botPermisos = [];   // ['capability_key' => bool]

    // ── Servidor (Alice / Betty) ───────────────────────────────────────────────
    public string  $servidor       = 'alice';
    public ?string $sincMensaje    = null;
    public bool    $sincError      = false;
    public bool    $sincConfirmar  = false;

    // ── Zonas para sincronización de menús/precios ────────────────────────────
    public array $sincZonasIds = [];

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
            'meta_verify_token',
            'messenger_page_id',
            'instagram_account_id',
            'mail_preset', 'mail_host', 'mail_port', 'mail_username',
            'mail_encryption', 'mail_from_address', 'mail_from_name',
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
        $this->servidor = session('db_env', 'alice');

        $stored = Setting::get('sinc_zonas_ids', '');
        $decoded = json_decode($stored, true);
        $this->sincZonasIds = is_array($decoded) ? array_map('intval', $decoded) : [];
        $this->chatbotHasKey       = !empty(Setting::get('chatbot_ia_api_key', ''));
        $this->asistenteHasKey     = !empty(Setting::get('asistente_ia_api_key', ''));
        $this->wa_evolution_hasKey = !empty(Setting::get('wa_evolution_key', ''));
        $this->wa_meta_hasToken    = !empty(Setting::get('wa_meta_token', ''));

        $this->meta_hasSecret       = !empty(Setting::get('meta_app_secret', ''));
        $this->messenger_habilitado = (bool) Setting::get('messenger_habilitado', '0');
        $this->messenger_hasToken   = !empty(Setting::get('messenger_page_token', ''));
        $this->instagram_habilitado = (bool) Setting::get('instagram_habilitado', '0');
        $this->mail_hasPassword     = !empty(Setting::get('mail_password', ''));

        foreach (['bsas', 'valle_nqn', 'cordoba', 'mendoza'] as $zona) {
            $key     = 'wa_' . $zona;
            $stored  = Setting::get($key, '');
            $decoded = json_decode($stored, true);
            $this->$key = is_array($decoded) ? $decoded : ($stored ? [$stored] : []);
        }

        // Bot permissions
        $this->botPermisos = BotPermissions::currentState();
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
        $t = json_encode($tema);
        $this->js("document.documentElement.setAttribute('data-theme',$t);localStorage.setItem('verdeo-theme',$t);");
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

    public function verificarMessenger(): void
    {
        $this->messengerTestResult = null;
        $this->messengerTestMsg    = '';
        $token  = Setting::get('messenger_page_token', '');
        $pageId = $this->messenger_page_id ?: Setting::get('messenger_page_id', '');
        if (!$token || !$pageId) {
            $this->messengerTestResult = 'error';
            $this->messengerTestMsg    = 'Configurá el Page ID y el Page Token primero.';
            return;
        }
        try {
            $resp = Http::timeout(8)->get("https://graph.facebook.com/v19.0/{$pageId}", [
                'fields' => 'name,id', 'access_token' => $token,
            ]);
            if ($resp->successful()) {
                $name = $resp->json('name') ?? 'desconocido';
                $this->messengerTestResult = 'ok';
                $this->messengerTestMsg    = "Conexión OK — Página: {$name}";
            } else {
                $this->messengerTestResult = 'error';
                $this->messengerTestMsg    = $resp->json('error.message') ?? "Error {$resp->status()}";
            }
        } catch (\Throwable $e) {
            $this->messengerTestResult = 'error';
            $this->messengerTestMsg    = 'Sin respuesta: ' . $e->getMessage();
        }
    }

    public function verificarInstagram(): void
    {
        $this->instagramTestResult = null;
        $this->instagramTestMsg    = '';
        $token     = Setting::get('messenger_page_token', '');
        $accountId = $this->instagram_account_id ?: Setting::get('instagram_account_id', '');
        if (!$token || !$accountId) {
            $this->instagramTestResult = 'error';
            $this->instagramTestMsg    = 'Configurá el Account ID y el Page Token (sección Messenger) primero.';
            return;
        }
        try {
            $resp = Http::timeout(8)->get("https://graph.facebook.com/v19.0/{$accountId}", [
                'fields' => 'name,username', 'access_token' => $token,
            ]);
            if ($resp->successful()) {
                $username = $resp->json('username') ?? $resp->json('name') ?? 'desconocido';
                $this->instagramTestResult = 'ok';
                $this->instagramTestMsg    = "Conexión OK — @{$username}";
            } else {
                $this->instagramTestResult = 'error';
                $this->instagramTestMsg    = $resp->json('error.message') ?? "Error {$resp->status()}";
            }
        } catch (\Throwable $e) {
            $this->instagramTestResult = 'error';
            $this->instagramTestMsg    = 'Sin respuesta: ' . $e->getMessage();
        }
    }

    public function setMailPreset(string $preset): void
    {
        $this->mail_preset = $preset;
        match ($preset) {
            'gmail'   => [$this->mail_host = 'smtp.gmail.com',        $this->mail_port = '587', $this->mail_encryption = 'tls'],
            'outlook' => [$this->mail_host = 'smtp-mail.outlook.com', $this->mail_port = '587', $this->mail_encryption = 'tls'],
            'resend'  => [$this->mail_host = 'smtp.resend.com',       $this->mail_port = '465', $this->mail_encryption = 'ssl'],
            default   => null,
        };
    }

    public function verificarEmail(): void
    {
        $this->mailTestResult = null;
        $this->mailTestMsg    = '';
        $host = Setting::get('mail_host', $this->mail_host);
        $port = (int) Setting::get('mail_port', $this->mail_port ?: '587');
        if (!$host) {
            $this->mailTestResult = 'error';
            $this->mailTestMsg    = 'Configurá el host SMTP y guardá los ajustes primero.';
            return;
        }
        try {
            $conn = @fsockopen($host, $port, $errno, $errstr, 8);
            if ($conn) {
                fclose($conn);
                $this->mailTestResult = 'ok';
                $this->mailTestMsg    = "Conexión OK — puerto {$port} en {$host} accesible.";
            } else {
                $this->mailTestResult = 'error';
                $this->mailTestMsg    = "Sin respuesta en {$host}:{$port} — {$errstr}";
            }
        } catch (\Throwable $e) {
            $this->mailTestResult = 'error';
            $this->mailTestMsg    = $e->getMessage();
        }
    }

    /**
     * Toggle a single bot capability immediately (no "Guardar" needed).
     * Planificado capabilities are silently ignored.
     */
    public function toggleBotPermiso(string $capability): void
    {
        if (! auth()->user()->isAdmin()) return;

        $new = BotPermissions::toggle($capability);
        $this->botPermisos[$capability] = $new;
    }

    public function guardar(): void
    {
        $rules = [
            'app_nombre'               => 'required|min:2|max:60',
            'timezone'                 => 'required|timezone',
            'chatbot_ia_proveedor'     => 'required|in:claude,gpt,gemini',
            'chatbot_ia_modelo'        => 'required|string|max:80',
            'chatbot_ia_prompt'        => 'nullable|max:2000',
            'chatbot_ia_temperatura'   => 'required|numeric|min:0|max:1',
            'asistente_ia_proveedor'   => 'required|in:claude,gpt,gemini',
            'asistente_ia_modelo'      => 'required|string|max:80',
            'asistente_ia_temperatura' => 'required|numeric|min:0|max:1',
        ];

        // API key requerida si el proveedor la necesita y aún no hay una guardada
        foreach (['chatbot', 'asistente'] as $ctx) {
            $prov  = $ctx === 'chatbot' ? $this->chatbot_ia_proveedor : $this->asistente_ia_proveedor;
            $field = "{$ctx}_ia_api_key";
            if (in_array($prov, ['claude', 'gpt', 'gemini'])) {
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

        // Canales: secure fields
        if (!empty($this->meta_app_secret)) {
            Setting::set('meta_app_secret', $this->meta_app_secret);
            $this->meta_app_secret = '';
        }
        if (!empty($this->messenger_page_token)) {
            Setting::set('messenger_page_token', $this->messenger_page_token);
            $this->messenger_page_token = '';
        }
        $this->meta_hasSecret     = !empty(Setting::get('meta_app_secret', ''));
        $this->messenger_hasToken = !empty(Setting::get('messenger_page_token', ''));

        // Email: guardar contraseña solo si se proporcionó una nueva
        if (!empty($this->mail_password)) {
            Setting::set('mail_password', $this->mail_password);
            $this->mail_password = '';
        }
        $this->mail_hasPassword = !empty(Setting::get('mail_password', ''));

        // Canales: booleans
        Setting::set('messenger_habilitado', $this->messenger_habilitado ? '1' : '0');
        Setting::set('instagram_habilitado', $this->instagram_habilitado ? '1' : '0');

        // Guardar números de WhatsApp como JSON arrays
        foreach (['bsas', 'valle_nqn', 'cordoba', 'mendoza'] as $zona) {
            $key = 'wa_' . $zona;
            Setting::set($key, json_encode(array_values($this->$key)));
        }

        $this->guardado = true;
        $this->dispatch('guardado');
    }

    public function cambiarServidor(string $srv): void
    {
        if (! in_array($srv, ['alice', 'betty'])) return;
        $user = auth()->user();
        if (! ($user->isAdmin() || $user->isResponsableZona())) return;

        session(['db_env' => $srv]);
        config(['database.default' => $srv]);
        DB::setDefaultConnection($srv);
        $this->servidor = $srv;

        $label = $srv === 'betty' ? 'Betty (producción)' : 'Alice (pruebas)';
        $this->sincMensaje = "Servidor cambiado a {$label}.";
        $this->sincError   = false;
        $this->js("window.location.reload();");
    }

    public function pedirConfirmacionSinc(): void
    {
        $this->sincConfirmar = true;
        $this->sincMensaje   = null;
    }

    public function cancelarSinc(): void
    {
        $this->sincConfirmar = false;
    }

    public function guardarSincZonas(): void
    {
        if (! auth()->user()->isAdmin()) return;
        Setting::set('sinc_zonas_ids', json_encode(array_values(array_map('intval', $this->sincZonasIds))));
        $this->sincMensaje = 'Selección de zonas guardada.';
        $this->sincError   = false;
    }

    public function sincronizarABetty(): void
    {
        $user = auth()->user();
        if (! $user->isAdmin()) return;

        $this->sincConfirmar = false;

        try {
            // ── Zonas ──────────────────────────────────────────────────────────
            $zonas = DB::connection('alice')->table('zonas')->get();
            foreach ($zonas as $z) {
                DB::connection('betty')->table('zonas')->updateOrInsert(
                    ['slug' => $z->slug],
                    (array) $z,
                );
            }

            // ── Productos (menus) + Platos ─────────────────────────────────────
            $productos = DB::connection('alice')->table('productos')->get();
            foreach ($productos as $p) {
                DB::connection('betty')->table('productos')->updateOrInsert(
                    ['id' => $p->id],
                    (array) $p,
                );

                $platos = DB::connection('alice')->table('platos')->where('producto_id', $p->id)->get();
                foreach ($platos as $pl) {
                    DB::connection('betty')->table('platos')->updateOrInsert(
                        ['id' => $pl->id],
                        (array) $pl,
                    );
                }
            }

            $nZonas    = count($zonas);
            $nMenus    = count($productos);
            $nPlatos   = DB::connection('alice')->table('platos')->count();
            $this->sincMensaje = "Sincronización completa: {$nZonas} zonas, {$nMenus} menús y {$nPlatos} platos copiados a Betty.";
            $this->sincError   = false;
        } catch (\Throwable $e) {
            $this->sincMensaje = 'Error al sincronizar: ' . $e->getMessage();
            $this->sincError   = true;
        }
    }

}; ?>

<div x-data="{ flash: false }" @guardado.window="flash = true; setTimeout(() => flash = false, 3500)">

    <div x-show="flash" x-transition
         class="mb-6 badge-green px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
        Ajustes guardados correctamente.
    </div>

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

        {{-- ── SERVIDOR ────────────────────────────────────────────────────────── --}}
        @if(auth()->user()->isAdmin() || auth()->user()->isResponsableZona())
        <div class="card">
            <div class="flex items-start gap-3 mb-5" style="border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 14px;">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                     style="background: rgba(78,158,90,0.12); border: 1px solid rgba(78,158,90,0.25);">
                    <svg width="16" height="16" fill="none" stroke="#4e9e5a" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3V6a3 3 0 013-3h13.5a3 3 0 013 3v5.25a3 3 0 01-3 3m-13.5 0v3.375c0 .621.504 1.125 1.125 1.125h11.25c.621 0 1.125-.504 1.125-1.125V14.25"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-2">
                        <h3 class="font-condensed font-bold tracking-wide"
                            style="color: var(--vd-text); letter-spacing: 1px; text-transform: uppercase; font-size: 12px;">
                            Servidor de datos
                        </h3>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                              style="{{ $servidor === 'betty'
                                  ? 'background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3);'
                                  : 'background: rgba(78,158,90,0.15); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.3);' }}">
                            {{ $servidor === 'betty' ? 'BETTY · Producción' : 'ALICE · Pruebas' }}
                        </span>
                    </div>
                    <p class="text-xs mt-0.5" style="color: var(--vd-muted);">
                        Elegí la base de datos activa para esta sesión. Alice es el entorno de pruebas, Betty es producción.
                    </p>
                </div>
            </div>

            {{-- Switch buttons --}}
            <div class="flex gap-3 mb-4">
                <button type="button" wire:click="cambiarServidor('alice')"
                        class="flex-1 flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl text-sm font-semibold transition-all"
                        style="{{ $servidor === 'alice'
                            ? 'background: rgba(78,158,90,0.18); color: #4e9e5a; border: 2px solid rgba(78,158,90,0.5);'
                            : 'background: rgba(0,0,0,0.1); color: var(--vd-muted); border: 2px solid var(--vd-bdr-soft); cursor: pointer;' }}">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5"/>
                    </svg>
                    Alice · Pruebas
                </button>
                <button type="button" wire:click="cambiarServidor('betty')"
                        class="flex-1 flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl text-sm font-semibold transition-all"
                        style="{{ $servidor === 'betty'
                            ? 'background: rgba(239,68,68,0.15); color: #f87171; border: 2px solid rgba(239,68,68,0.4);'
                            : 'background: rgba(0,0,0,0.1); color: var(--vd-muted); border: 2px solid var(--vd-bdr-soft); cursor: pointer;' }}">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 004.5 4.5H18a3.75 3.75 0 001.332-7.257 3 3 0 00-3.758-3.848 5.25 5.25 0 00-10.233 2.33A4.502 4.502 0 002.25 15z"/>
                    </svg>
                    Betty · Producción
                </button>
            </div>

            {{-- Sincronizar (solo admin) --}}
            @if(auth()->user()->isAdmin())
            <div class="pt-4" style="border-top: 1px solid var(--vd-bdr-soft);">
                <p class="text-xs mb-3" style="color: var(--vd-muted);">
                    Copiá zonas y menús desde Alice hacia Betty. Esto no borra datos existentes en Betty.
                </p>

                @if(!$sincConfirmar)
                <button type="button" wire:click="pedirConfirmacionSinc"
                        class="flex items-center gap-2 py-2 px-4 rounded-lg text-sm font-semibold transition-all"
                        style="background: rgba(234,179,8,0.12); color: #facc15; border: 1px solid rgba(234,179,8,0.3);">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
                    </svg>
                    Sincronizar Alice → Betty
                </button>
                @else
                <div class="flex items-center gap-3 p-3 rounded-xl"
                     style="background: rgba(234,179,8,0.08); border: 1px solid rgba(234,179,8,0.25);">
                    <svg width="18" height="18" fill="none" stroke="#facc15" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                    </svg>
                    <span class="text-sm" style="color: #facc15; flex: 1;">
                        ¿Confirmar sincronización Alice → Betty?
                    </span>
                    <button type="button" wire:click="sincronizarABetty"
                            class="py-1.5 px-3 rounded-lg text-sm font-bold"
                            style="background: rgba(234,179,8,0.2); color: #facc15; border: 1px solid rgba(234,179,8,0.4);">
                        Confirmar
                    </button>
                    <button type="button" wire:click="cancelarSinc"
                            class="py-1.5 px-3 rounded-lg text-sm"
                            style="color: var(--vd-muted); border: 1px solid var(--vd-bdr-soft);">
                        Cancelar
                    </button>
                </div>
                @endif
            </div>
            @endif

            {{-- Resultado de sincronización --}}
            @if($sincMensaje)
            <div class="mt-3 px-4 py-3 rounded-xl text-sm"
                 style="{{ $sincError
                     ? 'background: rgba(239,68,68,0.1); color: #f87171; border: 1px solid rgba(239,68,68,0.25);'
                     : 'background: rgba(78,158,90,0.1); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.25);' }}">
                {{ $sincMensaje }}
            </div>
            @endif
        </div>
        @endif

        {{-- ── ZONAS PARA SINCRONIZACIÓN ──────────────────────────────────────── --}}
        @if(auth()->user()->isAdmin())
        @php $todasZonas = \App\Models\Zona::orderBy('nombre')->get(); @endphp
        @if($todasZonas->isNotEmpty())
        <div class="card">
            <div class="flex items-start gap-3 mb-5" style="border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 14px;">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                     style="background: rgba(78,158,90,0.12); border: 1px solid rgba(78,158,90,0.25);">
                    <svg width="16" height="16" fill="none" stroke="#4e9e5a" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-condensed font-bold tracking-wide"
                        style="color: var(--vd-text); letter-spacing: 1px; text-transform: uppercase; font-size: 12px;">
                        Zonas para sincronización
                    </h3>
                    <p class="text-xs mt-0.5" style="color: var(--vd-muted);">
                        Elegí qué zonas reciben los botones "Guardar en Zonas" de Menús y Precios.
                        Sin selección = todas las zonas.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-5">
                @foreach($todasZonas as $zona)
                <label class="flex items-center gap-3 p-3 rounded-xl cursor-pointer transition-all"
                       style="{{ in_array($zona->id, $this->sincZonasIds)
                           ? 'background: rgba(78,158,90,0.10); border: 1px solid rgba(78,158,90,0.3);'
                           : 'background: rgba(0,0,0,0.06); border: 1px solid var(--vd-bdr-soft);' }}">
                    <input type="checkbox"
                           wire:model.live="sincZonasIds"
                           value="{{ $zona->id }}"
                           class="w-4 h-4 rounded accent-green-500"
                           style="accent-color: #4e9e5a;">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold truncate" style="color: var(--vd-text);">{{ $zona->nombre }}</p>
                        @if($zona->alcance)
                        <p class="text-[11px] truncate" style="color: var(--vd-muted);">{{ $zona->alcance }}</p>
                        @endif
                    </div>
                </label>
                @endforeach
            </div>

            <div class="flex items-center justify-between">
                <div class="flex gap-2">
                    <button type="button"
                            wire:click="$set('sincZonasIds', {{ json_encode($todasZonas->pluck('id')->map(fn($id) => (int)$id)->toArray()) }})"
                            class="text-xs px-3 py-1.5 rounded-lg"
                            style="color: var(--vd-muted); border: 1px solid var(--vd-bdr-soft);">
                        Todas
                    </button>
                    <button type="button"
                            wire:click="$set('sincZonasIds', [])"
                            class="text-xs px-3 py-1.5 rounded-lg"
                            style="color: var(--vd-muted); border: 1px solid var(--vd-bdr-soft);">
                        Ninguna
                    </button>
                </div>
                <button type="button" wire:click="guardarSincZonas"
                        class="btn-primary text-xs px-4 py-2">
                    Guardar selección
                </button>
            </div>

            {{-- Resultado --}}
            @if($sincMensaje && !$sincError && str_contains($sincMensaje, 'zonas'))
            <div class="mt-3 px-4 py-3 rounded-xl text-sm"
                 style="background: rgba(78,158,90,0.1); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.25);">
                {{ $sincMensaje }}
            </div>
            @endif
        </div>
        @endif
        @endif

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
                    <button type="button" @click="tab = 'bot'"
                            class="flex-1 flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-sm font-semibold transition-all"
                            :style="tab === 'bot'
                                ? 'background: rgba(168,85,247,0.18); color: #a855f7; border: 1px solid rgba(168,85,247,0.35);'
                                : 'color: var(--vd-muted); border: 1px solid transparent;'">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5"/>
                        </svg>
                        Agente Bot
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
                                'claude'  => ['label' => 'Claude',  'sub' => 'Anthropic', 'color' => '#a855f7', 'char' => 'C'],
                                'gpt'     => ['label' => 'GPT',     'sub' => 'OpenAI',    'color' => '#10b981', 'char' => 'G'],
                                'gemini'  => ['label' => 'Gemini',  'sub' => 'Google',    'color' => '#60a5fa', 'char' => '✦'],
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
                            @else Modelos multimodales de Google. Excelente velocidad y costo. Requiere API key de Google AI Studio. @endif
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
                                <option value="gemini-2.0-flash"      style="background:var(--vd-bg-2);color:var(--vd-text);">Gemini 2.0 Flash ✓</option>
                                <option value="gemini-2.0-flash-lite" style="background:var(--vd-bg-2);color:var(--vd-text);">Gemini 2.0 Flash Lite — muy económico</option>
                                <option value="gemini-1.5-pro"        style="background:var(--vd-bg-2);color:var(--vd-text);">Gemini 1.5 Pro — contexto largo</option>
                                <option value="gemini-1.5-flash"      style="background:var(--vd-bg-2);color:var(--vd-text);">Gemini 1.5 Flash</option>
                            @endif
                        </select>
                        @error('chatbot_ia_modelo') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                    </div>

                    {{-- API Key --}}
                    @if(true)
                    <div class="mb-5" x-data="{ show: false, cambiar: false }">
                        <label class="label">
                            API Key
                            <span class="ml-1 text-xs font-normal" style="color: var(--vd-muted);">
                                ({{ $chatbot_ia_proveedor === 'claude' ? 'console.anthropic.com' : ($chatbot_ia_proveedor === 'gpt' ? 'platform.openai.com' : 'aistudio.google.com') }})
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
                                'claude'  => ['label' => 'Claude',  'sub' => 'Anthropic', 'color' => '#a855f7', 'char' => 'C'],
                                'gpt'     => ['label' => 'GPT',     'sub' => 'OpenAI',    'color' => '#10b981', 'char' => 'G'],
                                'gemini'  => ['label' => 'Gemini',  'sub' => 'Google',    'color' => '#60a5fa', 'char' => '✦'],
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
                            @if($asistente_ia_proveedor === 'claude') Mayor precisión en análisis y generación de contenido interno. Requiere API key de Anthropic.
                            @elseif($asistente_ia_proveedor === 'gpt') Ampliamente adoptado, buen equilibrio velocidad/costo. Requiere API key de OpenAI.
                            @else Modelos multimodales de Google. Excelente velocidad y costo. Requiere API key de Google AI Studio. @endif
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
                                <option value="gemini-2.0-flash"      style="background:var(--vd-bg-2);color:var(--vd-text);">Gemini 2.0 Flash ✓</option>
                                <option value="gemini-2.0-flash-lite" style="background:var(--vd-bg-2);color:var(--vd-text);">Gemini 2.0 Flash Lite — muy económico</option>
                                <option value="gemini-1.5-pro"        style="background:var(--vd-bg-2);color:var(--vd-text);">Gemini 1.5 Pro — contexto largo</option>
                                <option value="gemini-1.5-flash"      style="background:var(--vd-bg-2);color:var(--vd-text);">Gemini 1.5 Flash</option>
                            @endif
                        </select>
                        @error('asistente_ia_modelo') <p class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</p> @enderror
                    </div>

                    {{-- API Key --}}
                    @if(true)
                    <div class="mb-5" x-data="{ show: false, cambiar: false }">
                        <label class="label">
                            API Key
                            <span class="ml-1 text-xs font-normal" style="color: var(--vd-muted);">
                                ({{ $asistente_ia_proveedor === 'claude' ? 'console.anthropic.com' : ($asistente_ia_proveedor === 'gpt' ? 'platform.openai.com' : 'aistudio.google.com') }})
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

                {{-- ── TAB: AGENTE BOT ──────────────────────────────────────────── --}}
                <div x-show="tab === 'bot'" x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">

                    {{-- Descripción --}}
                    <div class="mb-6 p-4 rounded-xl flex items-start gap-3"
                         style="background: rgba(168,85,247,0.07); border: 1px solid rgba(168,85,247,0.2);">
                        <svg width="18" height="18" fill="none" stroke="#a855f7" stroke-width="1.8" viewBox="0 0 24 24" class="flex-shrink-0 mt-0.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-semibold mb-1" style="color: #c084fc;">Permisos del Agente IA</p>
                            <p class="text-xs leading-relaxed" style="color: var(--vd-muted);">
                                Definí qué acciones puede ejecutar el bot de forma autónoma. Los cambios son inmediatos.
                                Las funciones <span class="font-semibold" style="color: var(--vd-muted);">planificadas</span> no están disponibles aún y aparecen deshabilitadas.
                            </p>
                        </div>
                    </div>

                    @php $catalog = BotPermissions::catalog(); @endphp

                    <div class="space-y-6">
                    @foreach($catalog as $groupKey => $group)
                        {{-- Group header --}}
                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <div class="w-6 h-6 rounded-md flex items-center justify-center flex-shrink-0"
                                     style="background: {{ $group['color'] }}22; border: 1px solid {{ $group['color'] }}44;">
                                    <svg width="12" height="12" fill="none" stroke="{{ $group['color'] }}" stroke-width="1.8" viewBox="0 0 24 24">
                                        {!! $group['icon'] !!}
                                    </svg>
                                </div>
                                <span class="text-xs font-bold uppercase tracking-widest"
                                      style="color: {{ $group['color'] }}; letter-spacing: 1.5px;">
                                    {{ $group['label'] }}
                                </span>
                            </div>

                            <div class="space-y-2 ml-0">
                            @foreach($group['items'] as $capKey => $cap)
                                @php
                                    $isPlanificado = $cap['status'] === \App\Services\BotPermissions::STATUS_PLANIFICADO;
                                    $isEnabled     = $botPermisos[$capKey] ?? false;
                                @endphp
                                <div class="flex items-start justify-between gap-4 p-3 rounded-xl transition-all"
                                     style="{{ $isEnabled && !$isPlanificado
                                         ? 'background: rgba(168,85,247,0.07); border: 1px solid rgba(168,85,247,0.2);'
                                         : 'background: rgba(0,0,0,0.05); border: 1px solid var(--vd-bdr-soft);' }}
                                         {{ $isPlanificado ? 'opacity: 0.55;' : '' }}">

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-sm font-semibold" style="color: var(--vd-text);">
                                                {{ $cap['label'] }}
                                            </span>

                                            {{-- Status badge --}}
                                            @if($isPlanificado)
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                                  style="background: rgba(107,114,128,0.15); color: #6b7280; border: 1px solid rgba(107,114,128,0.25);">
                                                Planificado
                                            </span>
                                            @elseif($cap['status'] === \App\Services\BotPermissions::STATUS_REQUIERE)
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                                  style="background: rgba(234,179,8,0.1); color: #facc15; border: 1px solid rgba(234,179,8,0.25);">
                                                Requiere config
                                            </span>
                                            @else
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                                  style="background: rgba(78,158,90,0.1); color: #4e9e5a; border: 1px solid rgba(78,158,90,0.25);">
                                                Operativo
                                            </span>
                                            @endif
                                        </div>

                                        <p class="text-xs mt-0.5" style="color: var(--vd-muted);">
                                            {{ $cap['descripcion'] }}
                                        </p>

                                        @if(!empty($cap['requiere']))
                                        <div class="flex flex-wrap gap-1 mt-1.5">
                                            @foreach($cap['requiere'] as $req)
                                            <span class="text-[10px] px-1.5 py-0.5 rounded font-mono"
                                                  style="background: rgba(0,0,0,0.15); color: var(--vd-muted-2); border: 1px solid var(--vd-bdr-soft);">
                                                {{ $req }}
                                            </span>
                                            @endforeach
                                        </div>
                                        @endif
                                    </div>

                                    {{-- Toggle --}}
                                    <button type="button"
                                            @if(!$isPlanificado) wire:click="toggleBotPermiso('{{ $capKey }}')" @endif
                                            @disabled($isPlanificado)
                                            class="flex-shrink-0 w-11 h-6 rounded-full relative transition-all duration-200 focus:outline-none"
                                            style="{{ $isPlanificado ? 'cursor: not-allowed;' : 'cursor: pointer;' }}
                                                   background: {{ ($isEnabled && !$isPlanificado) ? '#a855f7' : 'rgba(107,114,128,0.3)' }};
                                                   border: 1px solid {{ ($isEnabled && !$isPlanificado) ? '#a855f7' : 'var(--vd-bdr)' }};"
                                            title="{{ $isPlanificado ? 'No disponible aún' : ($isEnabled ? 'Deshabilitar' : 'Habilitar') }}">
                                        <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full transition-transform duration-200 shadow-sm"
                                              style="background: #fff;
                                                     transform: translateX({{ ($isEnabled && !$isPlanificado) ? '20px' : '0px' }});"></span>
                                    </button>
                                </div>
                            @endforeach
                            </div>
                        </div>
                    @endforeach
                    </div>

                    {{-- Bot user info --}}
                    <div class="mt-6 pt-4 flex items-center gap-2" style="border-top: 1px solid var(--vd-bdr-soft);">
                        <svg width="13" height="13" fill="none" stroke="var(--vd-muted-2)" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
                        </svg>
                        <p class="text-xs" style="color: var(--vd-muted-2);">
                            Las acciones del bot se registran bajo el usuario <span class="font-mono">bot@verdeo.com.ar</span> en el log de actividad.
                        </p>
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

        {{-- ── CANALES DE MENSAJERÍA ─────────────────────────────────────────── --}}
        <div class="card" x-data="{ canal: 'messenger' }">

            {{-- Header --}}
            <div class="flex items-start gap-3 mb-5" style="border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 14px;">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                     style="background: rgba(24,119,242,0.12); border: 1px solid rgba(24,119,242,0.25);">
                    <svg width="16" height="16" fill="none" stroke="#1877f2" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-condensed font-bold tracking-wide"
                        style="color: var(--vd-text); letter-spacing: 1px; text-transform: uppercase; font-size: 12px;">
                        Canales de Mensajería
                    </h3>
                    <p class="text-xs mt-0.5" style="color: var(--vd-muted);">
                        Messenger e Instagram usan la misma Meta App. Configurar una vez, activar por canal.
                    </p>
                </div>
            </div>

            {{-- ── Meta App Config (compartida) ── --}}
            <div class="mb-6">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-xs font-condensed font-bold uppercase tracking-widest"
                          style="color: var(--vd-muted); letter-spacing: 1px;">Meta App (compartida)</span>
                    <span class="text-xs px-2 py-0.5 rounded-full"
                          style="background: rgba(24,119,242,0.1); border: 1px solid rgba(24,119,242,0.2); color: #1877f2;">
                        App ID: {{ $wa_meta_app_id ?: '—' }}
                    </span>
                    <span class="text-xs" style="color: var(--vd-muted-2);">· configurado en API de WhatsApp ↑</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    {{-- App Secret --}}
                    <div x-data="{ show: false }">
                        <label class="label">App Secret</label>
                        @if($meta_hasSecret)
                        <div class="flex items-center gap-2">
                            <div class="input flex-1 font-mono text-xs flex items-center" style="color: var(--vd-muted);">
                                ••••••••••••••••
                            </div>
                            <button type="button" @click="$wire.set('meta_hasSecret', false)"
                                    class="btn-secondary text-xs px-3 flex-shrink-0">Cambiar</button>
                        </div>
                        @else
                        <div class="relative">
                            <input :type="show ? 'text' : 'password'" wire:model="meta_app_secret"
                                   class="input font-mono pr-10" placeholder="tu-app-secret-de-meta">
                            <button type="button" @click="show = !show"
                                    class="absolute right-3 top-1/2 -translate-y-1/2" style="color: var(--vd-muted);">
                                <svg x-show="!show" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <svg x-show="show" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                            </button>
                        </div>
                        @endif
                        <p class="text-xs mt-1" style="color: var(--vd-muted-2);">
                            Usado para verificar la firma de los webhooks entrantes.
                        </p>
                    </div>

                    {{-- Verify Token --}}
                    <div>
                        <label class="label">Verify Token</label>
                        <input type="text" wire:model="meta_verify_token" class="input font-mono"
                               placeholder="mi-token-secreto-123">
                        <p class="text-xs mt-1" style="color: var(--vd-muted-2);">
                            Cadena libre que Meta enviará al configurar el webhook para verificarlo.
                        </p>
                    </div>

                </div>

                {{-- Webhook URL --}}
                <div class="mt-4" x-data="{ copied: false }">
                    <label class="label mb-1">
                        URL del Webhook
                        <span class="ml-1 text-xs font-normal" style="color: var(--vd-muted);">
                            · cargar en Meta Developer Console → Webhooks
                        </span>
                    </label>
                    <div class="flex gap-2">
                        <input type="text" readonly
                               value="{{ url('/api/webhook/meta') }}"
                               class="input font-mono text-xs flex-1" style="color: var(--vd-muted);">
                        <button type="button"
                                @click="navigator.clipboard.writeText('{{ url('/api/webhook/meta') }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                class="btn-secondary text-xs px-3 flex-shrink-0 transition-all"
                                :style="copied ? 'color: #4e9e5a;' : ''">
                            <span x-show="!copied">Copiar</span>
                            <span x-show="copied">✓ Copiado</span>
                        </button>
                    </div>
                    <p class="text-xs mt-1" style="color: var(--vd-muted-2);">
                        n8n normaliza el mensaje y lo reenvía a Laravel. Si usás n8n, apuntá el webhook de Meta al endpoint de n8n.
                    </p>
                </div>
            </div>

            {{-- ── Tabs: Messenger / Instagram ── --}}
            <div style="border-top: 1px solid var(--vd-bdr-soft); padding-top: 20px;">

                {{-- Tab pills --}}
                <div class="flex gap-2 mb-5">

                    {{-- Messenger tab --}}
                    <button type="button" @click="canal = 'messenger'"
                            class="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold transition-all"
                            :style="canal === 'messenger'
                                ? 'background: rgba(0,120,255,0.15); color: #0078ff; border: 1px solid rgba(0,120,255,0.4);'
                                : 'background: var(--vd-bg-2); color: var(--vd-muted); border: 1px solid var(--vd-bdr);'">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.36 2 2 6.13 2 11.7c0 2.91 1.19 5.44 3.14 7.17.16.13.26.35.27.56l.05 1.76a.75.75 0 001.05.66l1.96-.87c.17-.07.36-.1.54-.07.9.25 1.9.38 2.99.38 5.64 0 10-4.13 10-9.7S17.64 2 12 2zm6 7.46l-2.93 4.67a1.51 1.51 0 01-2.18.4L10.77 13a.6.6 0 00-.72 0l-2.84 2.16c-.38.29-.88-.17-.63-.58L9.51 9.9a1.51 1.51 0 012.18-.4l2.12 1.53a.6.6 0 00.72 0l2.84-2.16c.38-.29.88.17.63.59z"/>
                        </svg>
                        Messenger
                        @if($messenger_habilitado)
                        <span class="w-1.5 h-1.5 rounded-full inline-block" style="background: #4e9e5a;"></span>
                        @endif
                    </button>

                    {{-- Instagram tab --}}
                    <button type="button" @click="canal = 'instagram'"
                            class="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold transition-all"
                            :style="canal === 'instagram'
                                ? 'background: rgba(225,48,108,0.12); color: #e1306c; border: 1px solid rgba(225,48,108,0.35);'
                                : 'background: var(--vd-bg-2); color: var(--vd-muted); border: 1px solid var(--vd-bdr);'">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>
                        </svg>
                        Instagram
                        @if($instagram_habilitado)
                        <span class="w-1.5 h-1.5 rounded-full inline-block" style="background: #4e9e5a;"></span>
                        @endif
                    </button>

                </div>

                {{-- ── Panel: Messenger ── --}}
                <div x-show="canal === 'messenger'"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0">

                    {{-- Toggle --}}
                    <div class="flex items-center justify-between mb-4 pb-4"
                         style="border-bottom: 1px solid var(--vd-bdr-soft);">
                        <div>
                            <p class="text-sm font-semibold" style="color: var(--vd-text);">Recibir mensajes de Messenger</p>
                            <p class="text-xs mt-0.5" style="color: var(--vd-muted);">
                                Requiere Facebook Page + permisos <code style="font-size:10px;">pages_messaging</code>
                            </p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="messenger_habilitado" class="sr-only peer">
                            <div class="w-10 h-6 rounded-full peer transition-colors duration-200"
                                 style="background: {{ $messenger_habilitado ? 'rgba(78,158,90,0.8)' : 'var(--vd-bdr)' }};"></div>
                            <div class="absolute left-1 top-1 w-4 h-4 rounded-full bg-white shadow transition-transform duration-200"
                                 style="transform: translateX({{ $messenger_habilitado ? '16px' : '0' }})"></div>
                        </label>
                    </div>

                    @if($messenger_habilitado)
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                            {{-- Page ID --}}
                            <div>
                                <label class="label">Facebook Page ID</label>
                                <input type="text" wire:model="messenger_page_id" class="input font-mono"
                                       placeholder="123456789012345">
                                <p class="text-xs mt-1" style="color: var(--vd-muted-2);">
                                    Encontralo en Configuración de tu Página → Acerca de.
                                </p>
                            </div>

                            {{-- Page Access Token --}}
                            <div x-data="{ show: false }">
                                <label class="label">Page Access Token</label>
                                @if($messenger_hasToken)
                                <div class="flex items-center gap-2">
                                    <div class="input flex-1 font-mono text-xs flex items-center" style="color: var(--vd-muted);">
                                        ••••••••••••••••
                                    </div>
                                    <button type="button" @click="$wire.set('messenger_hasToken', false)"
                                            class="btn-secondary text-xs px-3 flex-shrink-0">Cambiar</button>
                                </div>
                                @else
                                <div class="relative">
                                    <input :type="show ? 'text' : 'password'" wire:model="messenger_page_token"
                                           class="input font-mono pr-10" placeholder="EAABwz...">
                                    <button type="button" @click="show = !show"
                                            class="absolute right-3 top-1/2 -translate-y-1/2" style="color: var(--vd-muted);">
                                        <svg x-show="!show" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        <svg x-show="show" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                                    </button>
                                </div>
                                @endif
                                <p class="text-xs mt-1" style="color: var(--vd-muted-2);">Token de larga duración (60 días). No se muestra tras guardar.</p>
                            </div>

                        </div>

                        {{-- Messenger test badge --}}
                        @if($messengerTestResult)
                        <div class="px-4 py-2.5 rounded-xl text-sm font-medium flex items-center gap-2"
                             style="background: {{ $messengerTestResult === 'ok' ? 'rgba(78,158,90,0.12)' : 'rgba(239,68,68,0.10)' }};
                                    border: 1px solid {{ $messengerTestResult === 'ok' ? 'rgba(78,158,90,0.3)' : 'rgba(239,68,68,0.25)' }};
                                    color: {{ $messengerTestResult === 'ok' ? '#4e9e5a' : '#ef4444' }};">
                            @if($messengerTestResult === 'ok')
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            @else
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                            @endif
                            {{ $messengerTestMsg }}
                        </div>
                        @endif

                        <div class="flex items-center gap-3">
                            <button type="button" wire:click="verificarMessenger" wire:loading.attr="disabled"
                                    class="btn-secondary text-sm flex items-center gap-2">
                                <svg wire:loading.remove wire:target="verificarMessenger" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <svg wire:loading wire:target="verificarMessenger" class="animate-spin" width="14" height="14" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                Verificar conexión
                            </button>
                            <p class="text-xs" style="color: var(--vd-muted-2);">
                                Confirma que el Page Token puede leer la página configurada.
                            </p>
                        </div>
                    </div>
                    @else
                    <div class="py-6 text-center">
                        <p class="text-sm" style="color: var(--vd-muted);">Activá Messenger para configurar las credenciales.</p>
                    </div>
                    @endif
                </div>

                {{-- ── Panel: Instagram ── --}}
                <div x-show="canal === 'instagram'"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0">

                    {{-- Toggle --}}
                    <div class="flex items-center justify-between mb-4 pb-4"
                         style="border-bottom: 1px solid var(--vd-bdr-soft);">
                        <div>
                            <p class="text-sm font-semibold" style="color: var(--vd-text);">Recibir mensajes de Instagram</p>
                            <p class="text-xs mt-0.5" style="color: var(--vd-muted);">
                                Requiere cuenta Instagram Business vinculada a Facebook Page · permisos <code style="font-size:10px;">instagram_manage_messages</code>
                            </p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="instagram_habilitado" class="sr-only peer">
                            <div class="w-10 h-6 rounded-full peer transition-colors duration-200"
                                 style="background: {{ $instagram_habilitado ? 'rgba(225,48,108,0.7)' : 'var(--vd-bdr)' }};"></div>
                            <div class="absolute left-1 top-1 w-4 h-4 rounded-full bg-white shadow transition-transform duration-200"
                                 style="transform: translateX({{ $instagram_habilitado ? '16px' : '0' }})"></div>
                        </label>
                    </div>

                    @if($instagram_habilitado)
                    <div class="space-y-4">

                        {{-- Info: token compartido --}}
                        <div class="flex items-start gap-2 px-3 py-2.5 rounded-lg text-xs"
                             style="background: rgba(200,160,48,0.08); border: 1px solid rgba(200,160,48,0.2); color: var(--vd-text-soft);">
                            <svg width="14" height="14" fill="none" stroke="#c8a030" stroke-width="2" viewBox="0 0 24 24" class="flex-shrink-0 mt-0.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
                            </svg>
                            <span>
                                Instagram usa el <strong>Page Access Token de Messenger</strong> y la misma Meta App.
                                Configurá Messenger primero y asegurate de que la cuenta de IG esté vinculada a la Página.
                            </span>
                        </div>

                        {{-- Instagram Business Account ID --}}
                        <div>
                            <label class="label">Instagram Business Account ID</label>
                            <input type="text" wire:model="instagram_account_id" class="input font-mono"
                                   placeholder="17841400123456789">
                            <p class="text-xs mt-1" style="color: var(--vd-muted-2);">
                                Obtenerlo vía Graph API: <code style="font-size:10px;">GET /{page-id}?fields=instagram_business_account</code>
                            </p>
                        </div>

                        {{-- Instagram test badge --}}
                        @if($instagramTestResult)
                        <div class="px-4 py-2.5 rounded-xl text-sm font-medium flex items-center gap-2"
                             style="background: {{ $instagramTestResult === 'ok' ? 'rgba(78,158,90,0.12)' : 'rgba(239,68,68,0.10)' }};
                                    border: 1px solid {{ $instagramTestResult === 'ok' ? 'rgba(78,158,90,0.3)' : 'rgba(239,68,68,0.25)' }};
                                    color: {{ $instagramTestResult === 'ok' ? '#4e9e5a' : '#ef4444' }};">
                            @if($instagramTestResult === 'ok')
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            @else
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                            @endif
                            {{ $instagramTestMsg }}
                        </div>
                        @endif

                        <div class="flex items-center gap-3">
                            <button type="button" wire:click="verificarInstagram" wire:loading.attr="disabled"
                                    class="btn-secondary text-sm flex items-center gap-2">
                                <svg wire:loading.remove wire:target="verificarInstagram" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <svg wire:loading wire:target="verificarInstagram" class="animate-spin" width="14" height="14" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                Verificar conexión
                            </button>
                            <p class="text-xs" style="color: var(--vd-muted-2);">
                                Valida el Account ID contra la Graph API usando el Page Token de Messenger.
                            </p>
                        </div>

                    </div>
                    @else
                    <div class="py-6 text-center">
                        <p class="text-sm" style="color: var(--vd-muted);">Activá Instagram para configurar el Account ID.</p>
                    </div>
                    @endif

                </div>{{-- /instagram panel --}}

            </div>{{-- /tabs --}}

        </div>{{-- /card canales --}}

        {{-- ── CORREO ELECTRÓNICO ──────────────────────────────────────────────── --}}
        <div class="card" x-data="{ show_pass: false }">

            {{-- Header --}}
            <div class="flex items-start gap-3 mb-5" style="border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 14px;">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                     style="background: rgba(59,130,246,0.12); border: 1px solid rgba(59,130,246,0.25);">
                    <svg width="16" height="16" fill="none" stroke="#3b82f6" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-condensed font-bold tracking-wide"
                        style="color: var(--vd-text); letter-spacing: 1px; text-transform: uppercase; font-size: 12px;">
                        Correo electrónico
                    </h3>
                    <p class="text-xs mt-0.5" style="color: var(--vd-muted);">
                        Cuenta SMTP para envío de emails desde el sistema. Usado por Marketing → Email y notificaciones internas.
                    </p>
                </div>
            </div>

            {{-- Proveedor preset --}}
            <div class="mb-5">
                <label class="label mb-2">Proveedor</label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    @foreach([
                        'smtp'    => ['SMTP',    'Configuración manual',      '#6b7280', 'S'],
                        'gmail'   => ['Gmail',   'smtp.gmail.com · 587',     '#ea4335', 'G'],
                        'outlook' => ['Outlook', 'smtp-mail.outlook.com · 587','#0078d4','O'],
                        'resend'  => ['Resend',  'smtp.resend.com · 465',    '#3b82f6', 'R'],
                    ] as $preset => [$label, $sub, $color, $char])
                    <button type="button" wire:click="setMailPreset('{{ $preset }}')"
                            class="flex flex-col items-center gap-2 py-3 px-2 rounded-xl border transition-all"
                            style="{{ $mail_preset === $preset
                                ? 'border-color:'.$color.';background:'.$color.'1a;'
                                : 'border-color:var(--vd-bdr);' }}">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0"
                             style="background: {{ $color }}22; color: {{ $color }}; border: 2px solid {{ $color }}44;">
                            {{ $char }}
                        </div>
                        <div class="text-center">
                            <div class="text-xs font-bold"
                                 style="color: {{ $mail_preset === $preset ? $color : 'var(--vd-text)' }};">{{ $label }}</div>
                            <div class="text-[10px] leading-tight" style="color: var(--vd-muted-2);">{{ $sub }}</div>
                        </div>
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Campos de conexión SMTP --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">

                <div class="sm:col-span-2">
                    <label class="label">Host SMTP</label>
                    <input type="text" wire:model="mail_host" class="input font-mono"
                           placeholder="smtp.tuservidor.com">
                </div>

                <div>
                    <label class="label">Puerto</label>
                    <input type="number" wire:model="mail_port" class="input font-mono"
                           placeholder="587">
                </div>

                <div>
                    <label class="label">Encriptación</label>
                    <select wire:model="mail_encryption" class="input">
                        <option value="tls">TLS (recomendado)</option>
                        <option value="ssl">SSL</option>
                        <option value="">Ninguna</option>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label class="label">Usuario / Email de cuenta</label>
                    <input type="email" wire:model="mail_username" class="input"
                           placeholder="tu@cuenta.com">
                </div>

            </div>

            {{-- Contraseña --}}
            <div class="mb-5">
                <label class="label">Contraseña de aplicación</label>
                @if($mail_hasPassword)
                <div class="flex items-center gap-2">
                    <div class="input flex-1 font-mono text-xs flex items-center" style="color: var(--vd-muted);">
                        ••••••••••••••••
                    </div>
                    <button type="button" wire:click="$set('mail_hasPassword', false)"
                            class="btn-secondary text-xs px-3 flex-shrink-0">Cambiar</button>
                </div>
                @else
                <div class="relative">
                    <input :type="show_pass ? 'text' : 'password'" wire:model="mail_password"
                           class="input font-mono pr-10"
                           placeholder="{{ $mail_preset === 'gmail' ? 'Contraseña de aplicación de 16 dígitos' : 'Contraseña SMTP' }}">
                    <button type="button" @click="show_pass = !show_pass"
                            class="absolute right-3 top-1/2 -translate-y-1/2" style="color: var(--vd-muted);">
                        <svg x-show="!show_pass" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <svg x-show="show_pass" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                    </button>
                </div>
                @if($mail_preset === 'gmail')
                <p class="text-xs mt-1" style="color: var(--vd-muted);">
                    Gmail requiere una <strong>contraseña de aplicación</strong> (no tu contraseña normal). Generala en
                    <span style="color: #3b82f6;">Cuenta Google → Seguridad → Verificación en dos pasos → Contraseñas de aplicación</span>.
                </p>
                @endif
                @endif
            </div>

            {{-- Campos de remitente --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                <div>
                    <label class="label">Email remitente (from)</label>
                    <input type="email" wire:model="mail_from_address" class="input"
                           placeholder="noreply@verdeo.com.ar">
                </div>
                <div>
                    <label class="label">Nombre remitente</label>
                    <input type="text" wire:model="mail_from_name" class="input"
                           placeholder="Verdeo">
                </div>
            </div>

            {{-- Resultado de verificación --}}
            @if($mailTestResult)
            <div class="flex items-start gap-2 px-3 py-2.5 rounded-lg text-sm mb-4"
                 style="{{ $mailTestResult === 'ok'
                     ? 'background: rgba(78,158,90,0.1); border: 1px solid rgba(78,158,90,0.3); color: #4e9e5a;'
                     : 'background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.25); color: #fca5a5;' }}">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="flex-shrink-0 mt-0.5">
                    @if($mailTestResult === 'ok')
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    @else
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                    @endif
                </svg>
                {{ $mailTestMsg }}
            </div>
            @endif

            {{-- Verificar --}}
            <div class="flex items-center gap-3 pt-4" style="border-top: 1px solid var(--vd-bdr-soft);">
                <button type="button" wire:click="verificarEmail" wire:loading.attr="disabled"
                        class="btn-secondary text-sm flex items-center gap-2">
                    <svg wire:loading.remove wire:target="verificarEmail" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <svg wire:loading wire:target="verificarEmail" class="animate-spin" width="14" height="14" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Verificar conexión
                </button>
                <p class="text-xs" style="color: var(--vd-muted-2);">
                    Comprueba conectividad TCP al servidor SMTP con las credenciales guardadas.
                </p>
            </div>

        </div>{{-- /card email --}}

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
