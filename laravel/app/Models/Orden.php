<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Orden extends Model
{
    use LogsActivity;

    protected array $logCampos = ['estado', 'zona', 'notas', 'total'];
    protected $table = 'ordenes';

    public const INGRESA_POR = [
        'whatsapp' => 'WhatsApp',
        'mail'     => 'Mail',
    ];

    protected $fillable = [
        'numero', 'user_id', 'estado', 'zona', 'notas', 'total',
        'ingresa_por', 'direccion', 'latitud', 'longitud',
    ];

    protected $casts = [
        'total'    => 'decimal:2',
        'latitud'  => 'float',
        'longitud' => 'float',
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

    public static function generarNumero(int $userId): string
    {
        $numCliente  = str_pad(User::find($userId)?->numero_cliente ?? 0, 6, '0', STR_PAD_LEFT);
        $numOrden    = str_pad(static::where('user_id', $userId)->count() + 1, 6, '0', STR_PAD_LEFT);
        return "{$numCliente}-{$numOrden}";
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
