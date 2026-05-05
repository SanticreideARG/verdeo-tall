<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'descripcion', 'precio', 'unidad', 'categoria', 'activo'];

    protected $casts = [
        'precio' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public static array $unidades = [
        'kg'     => 'Kilogramo (kg)',
        'g'      => 'Gramo (g)',
        'unidad' => 'Unidad',
        'caja'   => 'Caja',
        'bolsa'  => 'Bolsa',
        'atado'  => 'Atado',
        'docena' => 'Docena',
        'litro'  => 'Litro (L)',
    ];

    public static array $categorias = [
        'Verduras',
        'Frutas',
        'Legumbres',
        'Condimentos',
        'Lácteos',
        'Otros',
    ];

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
