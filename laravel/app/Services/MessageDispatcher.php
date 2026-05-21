<?php

namespace App\Services;

use App\Models\Conversacion;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MessageDispatcher
{
    /**
     * Envía un mensaje al canal correcto según la conversación.
     */
    public function enviar(Conversacion $conv, string $mensaje): bool
    {
        return match($conv->canal ?? 'whatsapp') {
            'messenger' => $this->enviarMessenger($conv, $mensaje),
            'instagram' => $this->enviarInstagram($conv, $mensaje),
            default     => $this->enviarWhatsApp($conv, $mensaje),
        };
    }

    private function enviarWhatsApp(Conversacion $conv, string $mensaje): bool
    {
        $proveedor = Setting::get('wa_proveedor', 'evolution');

        if ($proveedor === 'evolution') {
            $url    = rtrim(Setting::get('wa_evolution_url', ''), '/');
            $apiKey = Setting::get('wa_evolution_key', '');
            $inst   = Setting::get('wa_evolution_inst_' . $conv->zona, '');

            if (!$url || !$inst || !$conv->telefono) return false;

            try {
                $resp = Http::timeout(10)
                    ->withHeaders(['apikey' => $apiKey])
                    ->post("{$url}/message/sendText/{$inst}", [
                        'number'  => $conv->telefono,
                        'textMessage' => ['text' => $mensaje],
                    ]);
                return $resp->successful();
            } catch (\Throwable $e) {
                Log::error('MessageDispatcher WA Evolution error', ['error' => $e->getMessage()]);
                return false;
            }
        }

        // Meta WhatsApp Business API
        $token   = Setting::get('wa_meta_token', '');
        $phoneId = Setting::get('wa_meta_phone_' . $conv->zona, '');
        if (!$token || !$phoneId || !$conv->telefono) return false;

        try {
            $resp = Http::timeout(10)
                ->withToken($token)
                ->post("https://graph.facebook.com/v19.0/{$phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to'                => $conv->telefono,
                    'type'              => 'text',
                    'text'              => ['body' => $mensaje],
                ]);
            return $resp->successful();
        } catch (\Throwable $e) {
            Log::error('MessageDispatcher WA Meta error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function enviarMessenger(Conversacion $conv, string $mensaje): bool
    {
        $token  = Setting::get('messenger_page_token', '');
        $pageId = Setting::get('messenger_page_id', '');

        if (!$token || !$pageId || !$conv->canal_id) return false;

        try {
            $resp = Http::timeout(10)->post(
                "https://graph.facebook.com/v19.0/{$pageId}/messages",
                [
                    'recipient'    => ['id' => $conv->canal_id],
                    'message'      => ['text' => $mensaje],
                    'access_token' => $token,
                ]
            );
            return $resp->successful();
        } catch (\Throwable $e) {
            Log::error('MessageDispatcher Messenger error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function enviarInstagram(Conversacion $conv, string $mensaje): bool
    {
        $token     = Setting::get('messenger_page_token', '');  // token compartido
        $accountId = Setting::get('instagram_account_id', '');

        if (!$token || !$accountId || !$conv->canal_id) return false;

        try {
            $resp = Http::timeout(10)->post(
                "https://graph.facebook.com/v19.0/{$accountId}/messages",
                [
                    'recipient'    => ['id' => $conv->canal_id],
                    'message'      => ['text' => $mensaje],
                    'access_token' => $token,
                ]
            );
            return $resp->successful();
        } catch (\Throwable $e) {
            Log::error('MessageDispatcher Instagram error', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
