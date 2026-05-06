<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plato extends Model
{
    protected $fillable = ['producto_id', 'nombre', 'orden', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function menu()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
