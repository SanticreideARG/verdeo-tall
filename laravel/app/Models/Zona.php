<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Zona extends Model
{
    use LogsActivity;
    protected $fillable = [
        'slug', 'nombre', 'ciudad', 'alcance', 'caracteristica',
        'responsable_id', 'precio_400kcal', 'precio_250kcal',
        'menus_semanales', 'activa', 'whatsapp', 'modelo_ia', 'foto',
    ];

    protected $casts = [
        'menus_semanales' => 'array',
        'activa'          => 'boolean',
        'precio_400kcal'  => 'integer',
        'precio_250kcal'  => 'integer',
    ];

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public static function generateSlug(string $nombre): string
    {
        $base = Str::slug($nombre, '_');
        $slug = $base;
        $i    = 2;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base . '_' . $i++;
        }
        return $slug;
    }

    public function precioFormateado(string $tamano): string
    {
        $val = $tamano === '400kcal' ? $this->precio_400kcal : $this->precio_250kcal;
        return $val ? '$ ' . number_format($val, 0, ',', '.') : '—';
    }

    public function fotoUrl(): ?string
    {
        return $this->foto ? Storage::url($this->foto) : null;
    }

    public function deleteFoto(): void
    {
        if ($this->foto) {
            Storage::disk('public')->delete($this->foto);
        }
    }
}
