<?php

namespace App\Models;

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
            'chatbot_ia_proveedor'     => 'ollama',
            'chatbot_ia_modelo'        => 'mistral',
            'chatbot_ia_api_key'       => '',
            'chatbot_ia_prompt'        => 'Sos un asistente de Verdeo, una empresa de comida saludable. Respondé preguntas sobre pedidos, menús y entregas de forma amigable y concisa. No inventes información.',
            'chatbot_ia_temperatura'   => '0.7',
            // Asistente interno (panel)
            'asistente_ia_proveedor'   => 'ollama',
            'asistente_ia_modelo'      => 'mistral',
            'asistente_ia_api_key'     => '',
            'asistente_ia_temperatura' => '0.5',
            // WhatsApp
            'wa_bsas'                  => '5491158393179',
            'wa_valle_nqn'             => '5492995493102',
            'wa_cordoba'               => '5493513007925',
            'wa_mendoza'               => '5492615117163',
        ];
    }
}
