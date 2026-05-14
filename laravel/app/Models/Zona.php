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
        'slug', 'nombre', 'alcance', 'caracteristica',
        'responsable_id', 'precio_400g', 'precio_250g',
        'menus_semanales', 'activa', 'whatsapp', 'modelo_ia', 'foto',
    ];

    protected $casts = [
        'menus_semanales' => 'array',
        'activa'          => 'boolean',
        'precio_400g'     => 'integer',
        'precio_250g'     => 'integer',
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
        $val = $tamano === '400g' ? $this->precio_400g : $this->precio_250g;
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
