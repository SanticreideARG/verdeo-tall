<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLink extends Model
{
    protected $fillable = ['user_id', 'titulo', 'url', 'descripcion', 'orden'];

    protected $casts = [
        'orden' => 'integer',
    ];

    /* ── Relaciones ─────────────────────────────────────────────────────── */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /* ── Helpers ────────────────────────────────────────────────────────── */

    /** Devuelve el hostname limpio para mostrar en la UI */
    public function dominio(): string
    {
        $parsed = parse_url($this->url, PHP_URL_HOST);
        return $parsed ? ltrim($parsed, 'www.') : $this->url;
    }

    /** URL del favicon via Google S2 */
    public function faviconUrl(): string
    {
        $host = parse_url($this->url, PHP_URL_HOST) ?? 'example.com';
        return "https://www.google.com/s2/favicons?sz=32&domain={$host}";
    }
}
