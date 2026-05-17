<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'mensajes'          => 'array',
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

    public function usuarioVinculado(): HasOne
    {
        return $this->hasOne(User::class, 'whatsapp', 'telefono');
    }

    public function zonaLabel(): string
    {
        return static::zonas()[$this->zona] ?? ucfirst($this->zona);
    }

    public static function zonas(): array
    {
        return [
            'bsas'      => 'Buenos Aires',
            'valle_nqn' => 'Valle NQN / Roca',
            'cordoba'   => 'Córdoba',
            'mendoza'   => 'Mendoza',
        ];
    }

    public static function estadosConv(): array
    {
        return ['abierta' => 'Abierta', 'cerrada' => 'Cerrada', 'esperando' => 'Esperando'];
    }
}
