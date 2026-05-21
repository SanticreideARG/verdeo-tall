<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversacion extends Model
{
    use HasFactory;

    protected $table = 'conversaciones';

    protected $fillable = [
        'zona',
        'canal',
        'canal_id',
        'telefono',
        'nombre',
        'estado',
        'ultimo_mensaje',
        'ultimo_mensaje_at',
        'mensajes',
    ];

    protected $casts = [
        'ultimo_mensaje_at' => 'datetime',
        'mensajes'          => 'array',
    ];

    public function scopeActivas($query)
    {
        return $query->where('estado', 'abierta');
    }

    public function scopeHoy($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeZona($query, string $zona)
    {
        return $query->where('zona', $zona);
    }

    public function scopeCanal($query, string $canal)
    {
        return $query->where('canal', $canal);
    }

    public function usuarioVinculado(): HasOne
    {
        // Solo aplicable a WhatsApp; Messenger/IG no tienen teléfono vinculado
        return $this->hasOne(User::class, 'whatsapp', 'telefono');
    }

    public function zonaLabel(): string
    {
        return static::zonas()[$this->zona] ?? ucfirst($this->zona);
    }

    public function canalLabel(): string
    {
        return match($this->canal ?? 'whatsapp') {
            'messenger' => 'Messenger',
            'instagram' => 'Instagram',
            default     => 'WhatsApp',
        };
    }

    public function canalColor(): string
    {
        return match($this->canal ?? 'whatsapp') {
            'messenger' => '#0078ff',
            'instagram' => '#e1306c',
            default     => '#25d366',
        };
    }

    // Identificador de contacto según canal
    public function canalIdentifier(): string
    {
        return match($this->canal ?? 'whatsapp') {
            'messenger', 'instagram' => $this->canal_id ?? '—',
            default                  => $this->telefono ? '+' . $this->telefono : '—',
        };
    }

    // URL de apertura rápida del canal (null si no aplica)
    public function canalUrl(): ?string
    {
        return match($this->canal ?? 'whatsapp') {
            'messenger' => $this->canal_id
                ? 'https://www.messenger.com/t/' . $this->canal_id
                : null,
            'instagram' => null,
            default     => $this->telefono
                ? 'https://wa.me/' . $this->telefono
                : null,
        };
    }

    public static function zonas(): array
    {
        return [
            'bsas'      => 'Buenos Aires',
            'valle_nqn' => 'Valle NQN / Roca',
            'cordoba'   => 'Córdoba',
            'mendoza'   => 'Mendoza',
        ];
    }

    public static function canales(): array
    {
        return [
            'whatsapp'  => 'WhatsApp',
            'messenger' => 'Messenger',
            'instagram' => 'Instagram',
        ];
    }

    public static function estadosConv(): array
    {
        return ['abierta' => 'Abierta', 'cerrada' => 'Cerrada', 'esperando' => 'Esperando'];
    }
}
