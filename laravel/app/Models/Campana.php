<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Campana extends Model
{
    protected $table = 'campanas';

    protected $fillable = [
        'numero', 'nombre', 'mensaje',
        'filtro_zona', 'filtro_estado',
        'estado',
        'total_destinatarios', 'total_enviados', 'total_fallidos',
        'creado_por', 'lanzada_at',
    ];

    protected $casts = [
        'lanzada_at'          => 'datetime',
        'total_destinatarios' => 'integer',
        'total_enviados'      => 'integer',
        'total_fallidos'      => 'integer',
    ];

    public static array $estados = [
        'borrador'   => 'Borrador',
        'enviando'   => 'Enviando',
        'completada' => 'Completada',
        'cancelada'  => 'Cancelada',
    ];

    /* ── Helpers ──────────────────────────────────────────────────────────── */

    public static function generarNumero(): string
    {
        $fecha = today()->format('Ymd');
        $count = static::whereDate('created_at', today())->count() + 1;
        return 'CAM-' . $fecha . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    public function porcentaje(): int
    {
        if ($this->total_destinatarios === 0) return 0;
        return (int) round(
            ($this->total_enviados + $this->total_fallidos) / $this->total_destinatarios * 100
        );
    }

    public function estaActiva(): bool
    {
        return $this->estado === 'enviando';
    }

    public function checkCompletada(): void
    {
        if ($this->total_destinatarios > 0
            && ($this->total_enviados + $this->total_fallidos) >= $this->total_destinatarios) {
            $this->update(['estado' => 'completada']);
        }
    }

    /* ── Relaciones ───────────────────────────────────────────────────────── */

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }
}
