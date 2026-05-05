<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversacion extends Model
{
    use HasFactory;

    protected $table = 'conversaciones';

    protected $fillable = [
        'zona',
        'telefono',
        'nombre',
        'estado',
        'ultimo_mensaje',
        'ultimo_mensaje_at',
    ];

    protected $casts = [
        'ultimo_mensaje_at' => 'datetime',
    ];

    public function scopeActivas($query)
    {
        return $query->where('estado', 'abierta');
    }

    public function scopeHoy($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeZona($query, string $zona)
    {
        return $query->where('zona', $zona);
    }
}
