<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bebida extends Model
{
    protected $fillable = ['nombre', 'receta', 'disponible'];

    // Esto es CLAVE: convierte el JSON de la DB a un array de PHP automáticamente
    protected $casts = [
        'receta' => 'array',
        'disponible' => 'boolean',
    ];
}
