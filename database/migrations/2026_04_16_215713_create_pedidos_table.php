<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('bebida_id')->constrained('bebidas'); 
            // AQUÍ ESTÁ LA MAGIA: Agregamos 'app' a la lista VIP
            $table->enum('metodo', ['manual', 'voz', 'ia', 'app']);
            $table->enum('estado', ['completado', 'error'])->default('completado');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};