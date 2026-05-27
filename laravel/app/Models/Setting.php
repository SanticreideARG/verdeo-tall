<?php

namespace App\Models;

use App\Services\BotPermissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting:{$key}", 3600, fn() =>
            static::find($key)?->value ?? $default
        );
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting:{$key}");
    }

    public static function defaults(): array
    {
        return [
            'app_nombre'               => 'Verdeo',
            'timezone'                 => 'America/Argentina/Buenos_Aires',
            // Chatbot IA (WhatsApp)
            'chatbot_ia_proveedor'     => 'claude',
            'chatbot_ia_modelo'        => 'claude-haiku-4-5-20251001',
            'chatbot_ia_api_key'       => '',
            'chatbot_ia_prompt'        => 'Sos un asistente de Verdeo, una empresa de comida saludable. Respondé preguntas sobre pedidos, menús y entregas de forma amigable y concisa. No inventes información.',
            'chatbot_ia_temperatura'   => '0.7',
            // Asistente interno (panel)
            'asistente_ia_proveedor'   => 'claude',
            'asistente_ia_modelo'      => 'claude-haiku-4-5-20251001',
            'asistente_ia_api_key'     => '',
            'asistente_ia_temperatura' => '0.5',
            // WhatsApp
            'wa_bsas'                  => '5491158393179',
            'wa_valle_nqn'             => '5492995493102',
            'wa_cordoba'               => '5493513007925',
            'wa_mendoza'               => '5492615117163',
        ]

        // Bot capabilities: all off by default (keys prefixed with 'bot_')
        + array_combine(
            array_map(fn($k) => 'bot_' . $k, array_keys(BotPermissions::defaults())),
            array_fill(0, count(BotPermissions::defaults()), '0')
        );
    }
}
