<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public function enviarMensaje(string $telefono, string $mensaje): bool
    {
        $proveedor = Setting::get('wa_proveedor', 'evolution');

        try {
            if ($proveedor === 'evolution') {
                return $this->enviarEvolution($telefono, $mensaje);
            }
            return $this->enviarMeta($telefono, $mensaje);
        } catch (\Throwable $e) {
            Log::warning('WhatsAppService error: ' . $e->getMessage());
            return false;
        }
    }

    private function enviarEvolution(string $telefono, string $mensaje): bool
    {
        $url      = rtrim(Setting::get('wa_evolution_url', ''), '/');
        $apiKey   = Setting::get('wa_evolution_key', '');
        $instancia = Setting::get('wa_evolution_inst_bsas', ''); // fallback; caller should pass zona

        if (!$url || !$apiKey) return false;

        $resp = Http::timeout(10)
            ->withHeaders(['apikey' => $apiKey])
            ->post("{$url}/message/sendText/{$instancia}", [
                'number'      => $telefono,
                'textMessage' => ['text' => $mensaje],
            ]);

        return $resp->successful();
    }

    public function enviarMensajeZona(string $zona, string $telefono, string $mensaje): bool
    {
        $proveedor = Setting::get('wa_proveedor', 'evolution');

        try {
            if ($proveedor === 'evolution') {
                $url      = rtrim(Setting::get('wa_evolution_url', ''), '/');
                $apiKey   = Setting::get('wa_evolution_key', '');
                $instancia = Setting::get("wa_evolution_inst_{$zona}", '');

                if (!$url || !$apiKey || !$instancia) return false;

                $resp = Http::timeout(10)
                    ->withHeaders(['apikey' => $apiKey])
                    ->post("{$url}/message/sendText/{$instancia}", [
                        'number'      => $telefono,
                        'textMessage' => ['text' => $mensaje],
                    ]);

                return $resp->successful();
            }

            // META
            $phoneId = Setting::get("wa_meta_phone_{$zona}", '');
            $token   = Setting::get('wa_meta_token', '');

            if (!$phoneId || !$token) return false;

            $resp = Http::timeout(10)
                ->withToken($token)
                ->post("https://graph.facebook.com/v19.0/{$phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to'                => $telefono,
                    'type'              => 'text',
                    'text'              => ['body' => $mensaje],
                ]);

            return $resp->successful();
        } catch (\Throwable $e) {
            Log::warning('WhatsAppService zona error: ' . $e->getMessage());
            return false;
        }
    }
}
