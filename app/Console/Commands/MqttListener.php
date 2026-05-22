<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

#[Signature('mqtt:listen')]
#[Description('Escucha reportes del ESP32 en tiempo real mediante MQTT')]
class MqttListener extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando el escuchador de MQTT en la máquina virtual...');
        
        try {
            // Conectamos al broker local
            $mqtt = new MqttClient('127.0.0.1', 1883, 'laravel_listener');
            $mqtt->connect();

            // Nos suscribimos al canal donde el ESP32 reportará
            $mqtt->subscribe('cafetera/reportar', function ($topic, $message) {
                $this->info("Mensaje MQTT recibido en {$topic}: " . $message);
                
                $data = json_decode($message, true);
                if (!isset($data['status'])) return;

                $status = $data['status'];
                $orden  = Cache::get('orden_actual');

                // CASO 1: Botón físico (No hay orden en caché)
                if (!$orden && $status === 'finalizado') {
                    $bebidaId = $data['bebida_id'] ?? 1;
                    DB::table('pedidos')->insert([
                        'user_id'    => 1,
                        'bebida_id'  => $bebidaId,
                        'metodo'     => 'manual',
                        'estado'     => 'completado',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $this->warn('Pedido físico registrado exitosamente vía MQTT.');
                }

                // CASO 2: Pedido desde la App
                if ($orden && $status === 'finalizado') {
                    DB::table('pedidos')->insert([
                        'user_id'    => $orden['user_id'],
                        'bebida_id'  => $orden['bebida_id'],
                        'metodo'     => $orden['metodo'], // Será 'app'
                        'estado'     => 'completado',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    Cache::forget('orden_actual');
                    $this->info('Pedido de la App guardado en BD y Caché limpia vía MQTT.');
                }
            }, 0);

            // Bucle infinito para que Laravel no se duerma y siga escuchando
            $mqtt->loop(true);

        } catch (\Exception $e) {
            $this->error('Error fatal en el servicio MQTT: ' . $e->getMessage());
        }
    }
}
