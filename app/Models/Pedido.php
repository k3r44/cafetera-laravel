<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
  
// Campos que permitimos llenar desde el código
    protected $fillable = [
        'user_id', 
        'receta', 
        'metodo', 
        'estado', 
        'error_msg'
    ];

    // Esto convierte automáticamente el JSON de la DB en un Array de PHP
    protected $casts = [
        'receta' => 'array',
];
        }
