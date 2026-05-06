<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enlace extends Model
{
    protected $fillable = ['titulo', 'url', 'descripcion', 'orden', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
