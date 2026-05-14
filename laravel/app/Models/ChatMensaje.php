<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMensaje extends Model
{
    protected $fillable = [
        'from_user_id', 'to_user_id', 'tipo',
        'contenido', 'latitud', 'longitud', 'direccion',
        'archivo', 'leido_at',
    ];

    protected $casts = [
        'leido_at'  => 'datetime',
        'latitud'   => 'float',
        'longitud'  => 'float',
    ];

    public function from(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /** Unread count for a given user */
    public static function noLeidosPara(int $userId): int
    {
        return static::where('to_user_id', $userId)->whereNull('leido_at')->count();
    }
}
