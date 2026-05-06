<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Orden extends Model
{
    protected $table = 'ordenes';

    protected $fillable = ['numero', 'user_id', 'estado', 'zona', 'notas', 'total'];

    protected $casts = [
        'total' => 'decimal:2',
    ];

    public static array $estados = [
        'pendiente'          => 'Pendiente',
        'aprobada'           => 'Aprobada',
        'lista_para_entrega' => 'Lista p/ entregar',
        'entregada'          => 'Entregada',
        'cancelada'          => 'Cancelada',
    ];

    public static array $estadoBadge = [
        'pendiente'          => 'badge-yellow',
        'aprobada'           => 'badge-blue',
        'lista_para_entrega' => 'badge-green',
        'entregada'          => 'badge-gray',
        'cancelada'          => 'badge-red',
    ];

    public static function generarNumero(): string
    {
        $year = now()->year;
        $count = static::whereYear('created_at', $year)->count() + 1;
        return sprintf('ORD-%d-%04d', $year, $count);
    }

    public function recalcularTotal(): void
    {
        $this->update(['total' => $this->items()->sum('subtotal')]);
    }

    public function estadoLabel(): string
    {
        return static::$estados[$this->estado] ?? $this->estado;
    }

    public function estadoBadgeClass(): string
    {
        return static::$estadoBadge[$this->estado] ?? 'badge-gray';
    }

    public function cliente()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items()
    {
        return $this->hasMany(OrdenItem::class);
    }
}
