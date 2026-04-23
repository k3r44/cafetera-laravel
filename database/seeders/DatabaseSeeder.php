<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
{
    // Bebidas con lógica Sí/No
    \App\Models\Bebida::create([
        'nombre' => 'Americano',
        'receta' => ['cafe' => true, 'agua' => true, 'leche' => false, 'Cocoa'=>false],
        'disponible' => true
    ]);

    \App\Models\Bebida::create([
        'nombre' => 'Americano con Leche',
        'receta' => ['cafe' => true, 'agua' => true, 'leche' => true,'Cocoa'=>false ],
        'disponible' => true
    ]);

    \App\Models\Bebida::create([
        'nombre' => 'Mocha latte',
        'receta' => ['cafe' => true, 'agua' => true, 'leche' => true,'Cocoa'=>true ],
        'disponible' => true
    ]);
    \App\Models\Bebida::create([
        'nombre' => 'Cocoa',
        'receta' => ['cafe' => false, 'agua' => true, 'leche' => true,'Cocoa'=>true ],
        'disponible' => true
    ]);
    
   // 2. Crear los usuarios de los ingenieros
    $usuarios = ['Ingeniero1', 'Ingeniero2', 'Admin'];
    foreach ($usuarios as $u) {
        \App\Models\User::create([
            'name' => $u,
            'username' => strtolower($u),
            'password' => bcrypt('cafetera123'),
            'automatizacion_activa' => false,
            'cuenta_activa' => true
        ]);
    }

    // 3. Crear el usuario Jefe (Hector) y su Token
    $user = \App\Models\User::create([
        'name' => 'Hector',
        'username' => 'hector_admin',
        'password' => bcrypt('admin123'),
        'automatizacion_activa' => true,
    ]);

    $token = $user->createToken('token-esp32')->plainTextToken;

    $this->command->info("--- TOKEN ESP32 GENERADO ---");
    $this->command->warn($token);
    $this->command->info("----------------------------");
}



}


