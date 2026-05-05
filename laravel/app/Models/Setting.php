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
            'app_nombre'    => 'Verdeo',
            'ai_modelo'     => 'mistral',
            'timezone'      => 'America/Argentina/Buenos_Aires',
            'wa_bsas'       => '5491158393179',
            'wa_valle_nqn'  => '5492995493102',
            'wa_cordoba'    => '5493513007925',
            'wa_mendoza'    => '5492615117163',
        ];
    }
}
