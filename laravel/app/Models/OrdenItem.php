<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenItem extends Model
{
    public const TAMANOS = ['250g' => '250g', '400g' => '400g'];

    public const FORMAS_PAGO = [
        'no_definido'  => 'No definido',
        'en_destino'   => 'En Destino',
        'transferencia' => 'Transferencia',
    ];

    protected $fillable = ['orden_id', 'producto_id', 'tamano', 'forma_pago', 'cantidad', 'precio_unitario', 'subtotal'];

    protected $casts = [
        'cantidad'       => 'decimal:3',
        'precio_unitario' => 'decimal:2',
        'subtotal'       => 'decimal:2',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function orden()
    {
        return $this->belongsTo(Orden::class);
    }
}
