<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'apellido',
        'email',
        'password',
        'role',
        'whatsapp',
        'ciudad',
        'direccion',
        'zona',
        'foto',
        'numero_cliente',
    ];

    public static function nextNumeroCliente(): int
    {
        return (static::whereNotNull('numero_cliente')->max('numero_cliente') ?? 0) + 1;
    }

    public function nombreCompleto(): string
    {
        return trim($this->name . ' ' . ($this->apellido ?? ''));
    }

    public static function rolesLabels(): array
    {
        return [
            'admin'            => 'Administrador',
            'responsable_zona' => 'Resp. de Zona',
            'colaborador'      => 'Colaborador',
            'cliente'          => 'Cliente',
        ];
    }

    public function rolLabel(): string
    {
        return static::rolesLabels()[$this->role] ?? $this->role;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isResponsableZona(): bool
    {
        return $this->role === 'responsable_zona';
    }

    public function isColaborador(): bool
    {
        return $this->role === 'colaborador';
    }

    public function isCliente(): bool
    {
        return $this->role === 'cliente';
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
}
