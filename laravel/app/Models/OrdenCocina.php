<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * OrdenCocina — batch de preparación para una ciudad.
 *
 * Flujo:
 *   activa     → cocina está preparando los pedidos incluidos
 *   completada → todos los pedidos están lista_para_entrega o cancelados
 *
 * Número: COC-YYYYMMDD-NNN  (secuencial por día)
 */
class OrdenCocina extends Model
{
    use LogsActivity;

    protected array $logCampos = ['estado', 'ciudad', 'asignado_a', 'notas'];

    protected $table = 'ordenes_cocina';

    protected $fillable = [
        'numero', 'ciudad', 'creado_por', 'asignado_a', 'estado', 'notas',
    ];

    public static array $estados = [
        'activa'     => 'Activa',
        'completada' => 'Completada',
    ];

    /* ── Relaciones ──────────────────────────────────────────────────────────── */

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function asignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }

    public function ordenes(): HasMany
    {
        return $this->hasMany(Orden::class, 'orden_cocina_id');
    }

    /* ── Helpers ─────────────────────────────────────────────────────────────── */

    public static function generarNumero(): string
    {
        $fecha = today()->format('Ymd');
        $count = static::whereDate('created_at', today())->count() + 1;
        return 'COC-' . $fecha . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Cierra el batch si todos sus pedidos están en lista_para_entrega o cancelada.
     */
    public function checkCompletada(): void
    {
        $pendiente = $this->ordenes()
            ->whereNotIn('estado', ['lista_para_entrega', 'cancelada'])
            ->exists();

        if (! $pendiente) {
            $this->update(['estado' => 'completada']);
        }
    }

    public function estadoLabel(): string
    {
        return static::$estados[$this->estado] ?? $this->estado;
    }
}
