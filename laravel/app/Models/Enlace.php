<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Enlace extends Model
{
    use LogsActivity;

    protected array $logCampos = ['titulo', 'url', 'descripcion', 'categoria', 'activo'];
    protected $fillable = ['titulo', 'url', 'descripcion', 'categoria', 'orden', 'activo', 'clicks'];
    protected $casts = ['activo' => 'boolean'];

    public function scopeActivos($query) { return $query->where('activo', true); }

    public function faviconDomain(): string
    {
        return parse_url($this->url, PHP_URL_HOST) ?? '';
    }

    public function incrementarClicks(): void { $this->increment('clicks'); }
}
