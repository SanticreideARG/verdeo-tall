<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActividadLog extends Model
{
    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $table = 'actividad_logs';

    protected $fillable = [
        'user_id',
        'accion',
        'modelo',
        'modelo_id',
        'descripcion',
        'cambios',
        'ip',
    ];

    protected $casts = [
        'cambios'    => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Registrar una acción en el log de actividad.
     *
     * @param string   $accion
     * @param string   $modelo
     * @param int|null $modeloId
     * @param string   $descripcion
     * @param array    $cambios
     * @param int|null $actorId  Fuerza un user_id específico (útil para acciones del bot en jobs/console).
     *                           Si se provee, bypasea la guard de runningInConsole y auth.
     */
    public static function registrar(
        string   $accion,
        string   $modelo,
        int|null $modeloId,
        string   $descripcion,
        array    $cambios = [],
        ?int     $actorId = null,
    ): void {
        // Si hay actorId explícito (bot u otro sistema), siempre registrar
        if ($actorId !== null) {
            static::create([
                'user_id'     => $actorId,
                'accion'      => $accion,
                'modelo'      => $modelo,
                'modelo_id'   => $modeloId,
                'descripcion' => $descripcion,
                'cambios'     => empty($cambios) ? null : $cambios,
                'ip'          => app()->runningInConsole() ? '0.0.0.0' : request()->ip(),
            ]);
            return;
        }

        // Flujo normal: solo en contexto web con usuario autenticado
        if (! app()->runningInConsole() && auth()->check()) {
            static::create([
                'user_id'     => auth()->id(),
                'accion'      => $accion,
                'modelo'      => $modelo,
                'modelo_id'   => $modeloId,
                'descripcion' => $descripcion,
                'cambios'     => empty($cambios) ? null : $cambios,
                'ip'          => request()->ip(),
            ]);
        }
    }
}
