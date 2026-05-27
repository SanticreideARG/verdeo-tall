<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['nombre', 'descripcion', 'tipo', 'activo', 'orden', 'precio_250kcal', 'precio_400kcal'];

    protected $casts = [
        'activo'        => 'boolean',
        'precio_250kcal' => 'decimal:2',
        'precio_400kcal' => 'decimal:2',
    ];

    public static function tipos(): array
    {
        return [
            'keto'        => 'Menú Nuevo Keto',
            'anti_age'    => 'Menú Anti-Age',
            'vegetariano' => 'Menú Vegetariano',
            'real'        => 'Menú Real',
            'intuitivo'   => 'Menú Intuitivo',
        ];
    }

    public static function tiposDescripcion(): array
    {
        return [
            'keto'        => 'Sin harinas ni cereales',
            'anti_age'    => 'Ingredientes anti inflamatorios y anti oxidantes',
            'vegetariano' => 'Sin carnes · Con harina integral orgánica y queso',
            'real'        => 'Sin ultraprocesados ni procesados',
            'intuitivo'   => 'Una alimentación consciente, variada y saludable, sin restricciones. Sin sal.',
        ];
    }

    public static function tiposBadge(): array
    {
        return [
            'keto'        => 'badge-blue',
            'anti_age'    => 'badge-green',
            'vegetariano' => 'badge-green',
            'real'        => 'badge-yellow',
            'intuitivo'   => 'badge-gray',
        ];
    }

    public function tipoLabel(): string
    {
        return self::tipos()[$this->tipo] ?? $this->tipo;
    }

    public function tipoBadge(): string
    {
        return self::tiposBadge()[$this->tipo] ?? 'badge-gray';
    }

    public function esIntuitivo(): bool
    {
        return $this->tipo === 'intuitivo';
    }

    public function platos()
    {
        return $this->hasMany(Plato::class)->orderBy('orden');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function ordenItems()
    {
        return $this->hasMany(OrdenItem::class);
    }

    public function tieneOrdenes(): bool
    {
        return $this->ordenItems()->exists();
    }
}
