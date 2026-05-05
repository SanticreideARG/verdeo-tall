<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenItem extends Model
{
    protected $fillable = ['orden_id', 'producto_id', 'cantidad', 'precio_unitario', 'subtotal'];

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
