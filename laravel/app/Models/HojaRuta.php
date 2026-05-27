<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * HojaRuta — conjunto de pedidos asignados a un transportista.
 *
 * El transportista accede vía token público (/r/{token}), sin login.
 * El link expira a las 24 hs de su creación.
 *
 * Flujo: activa → en_reparto → completada | cancelada
 */
class HojaRuta extends Model
{
    use LogsActivity;

    protected array $logCampos = ['estado', 'transportista_nombre', 'notas'];

    protected $table = 'hojas_ruta';

    protected $fillable = [
        'numero', 'ciudad', 'token', 'expires_at',
        'creado_por', 'transportista_nombre', 'transportista_telefono',
        'estado', 'notas',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public static array $estados = [
        'activa'     => 'Activa',
        'en_reparto' => 'En reparto',
        'completada' => 'Completada',
        'cancelada'  => 'Cancelada',
    ];

    public static array $estadoBadge = [
        'activa'     => 'badge-yellow',
        'en_reparto' => 'badge-blue',
        'completada' => 'badge-green',
        'cancelada'  => 'badge-red',
    ];

    /* ── Relaciones ──────────────────────────────────────────────────────────── */

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function ordenes(): HasMany
    {
        return $this->hasMany(Orden::class, 'hoja_ruta_id');
    }

    /* ── Helpers ─────────────────────────────────────────────────────────────── */

    public static function generarNumero(): string
    {
        $fecha = today()->format('Ymd');
        $count = static::whereDate('created_at', today())->count() + 1;
        return 'HR-' . $fecha . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    public static function generarToken(): string
    {
        do {
            $token = bin2hex(random_bytes(32));
        } while (static::where('token', $token)->exists());
        return $token;
    }

    public function publicUrl(): string
    {
        return route('entregas.microsite', ['token' => $this->token]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function estadoLabel(): string
    {
        return static::$estados[$this->estado] ?? $this->estado;
    }

    /**
     * Cierra la HR como completada si todos los pedidos están confirmados o cancelados.
     */
    public function checkCompletada(): void
    {
        $sinConfirmar = $this->ordenes()
            ->whereNull('transportista_confirma_at')
            ->where('estado', '!=', 'cancelada')
            ->exists();

        if (! $sinConfirmar) {
            $this->update(['estado' => 'completada']);
        }
    }

    /**
     * Cancela la HR y libera los pedidos (quitan hoja_ruta_id).
     */
    public function cancelar(): void
    {
        $this->ordenes()
            ->whereNull('transportista_confirma_at')
            ->update(['hoja_ruta_id' => null]);

        $this->update(['estado' => 'cancelada']);
    }
}
