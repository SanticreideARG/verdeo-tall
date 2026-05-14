<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enlace extends Model
{
    protected $fillable = ['titulo', 'url', 'descripcion', 'categoria', 'orden', 'activo', 'clicks'];
    protected $casts = ['activo' => 'boolean'];

    public function scopeActivos($query) { return $query->where('activo', true); }

    public function faviconDomain(): string
    {
        return parse_url($this->url, PHP_URL_HOST) ?? '';
    }

    public function incrementarClicks(): void { $this->increment('clicks'); }
}
