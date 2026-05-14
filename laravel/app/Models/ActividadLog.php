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

    public static function registrar(string $accion, string $modelo, int|null $modeloId, string $descripcion, array $cambios = []): void
    {
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
