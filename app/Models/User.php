<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Campos que se pueden llenar de forma masiva.
     */
    protected $fillable = [
        'name',
        'username',
        'password',
        'automatizacion_activa',
        'cuenta_activa',
    ];

    /**
     * Campos que se ocultan en las respuestas JSON.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Definición de tipos de datos.
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'automatizacion_activa' => 'boolean',
            'cuenta_activa' => 'boolean',
        ];
    }
}
