<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PhpMqtt\Client\MqttClient;

/*
|--------------------------------------------------------------------------
| API Cafetera: Flujo App Web + MQTT (Simplificado)
|--------------------------------------------------------------------------
*/

// 1. POST /v1/pedir (Desde el Frontend)
Route::post('/v1/pedir', function (Request $request) {
    if (Cache::has('orden_actual')) {
        return response()->json(['error' => 'Cafetera ocupada'], 429);
    }

    // Buscamos la bebida en la base de datos
    $bebida = DB::table('bebidas')
                ->where('nombre', $request->input('metodo'))
                ->first();

    if (!$bebida) {
        return response()->json(['error' => 'Bebida no encontrada: ' . $request->input('metodo')], 404);
    }

    // Guardamos la orden en caché (solo para bloquear la cafetera temporalmente y guardarla al final)
    $orden = [
        'user_id'   => $request->user()->id,
        'bebida_id' => $bebida->id,
        'nombre'    => $bebida->nombre,
        'metodo'    => 'app' // Fijo para ti, ya que es la app web
    ];

    Cache::put('orden_actual', $orden, now()->addMinutes(10));

    //  EL PUENTE MQTT: Disparamos la orden a Mosquitto 
    try {
        $mqtt = new \PhpMqtt\Client\MqttClient('127.0.0.1', 1883, 'laravel_publisher_pedidos');
        $mqtt->connect();
        
        // PUBLICAMOS SOLO EL ID (La lógica de ingredientes se hace en el ESP32)
        $payload = [
            'bebida_id' => $bebida->id,
            'nombre'    => $bebida->nombre
        ];

        $mqtt->publish('tempus/ordenes', json_encode($payload), 0);
        $mqtt->disconnect();
        
    } catch (\Exception $e) { 
        Cache::forget('orden_actual'); 
        return response()->json(['error' => 'Broker MQTT desconectado, revisa el servidor'], 500); 
    }

    return response()->json(['status' => 'success', 'message' => 'Preparando ' . $bebida->nombre]);
})->middleware('auth:sanctum');


// 2. GET /v1/cafetera/orden (Consulta del ESP32 por si falla MQTT)
Route::get('/v1/cafetera/orden', function () {
    $orden = Cache::get('orden_actual');
    
    if (!$orden) {
        return response()->json(['comando' => 0]);
    }

    // Enviamos solo el ID de la bebida, sin los booleanos
    return response()->json([
        'comando'   => 1,
        'bebida_id' => $orden['bebida_id'],
        'nombre'    => $orden['nombre']
    ]);
});


// 3. POST /v1/cafetera/reportar (Cierre del proceso)
Route::post('/v1/cafetera/reportar', function (Request $request) {
    $status = $request->input('status'); 
    $orden  = Cache::get('orden_actual');

    // --- CASO 1: Pedido Dinámico por Asistente de Voz / IA (No hay nada en Redis) ---
    if (!$orden) {
        if ($status === 'finalizado') {
            
            // Recolección dinámica de datos desde el payload
            $bebidaId = $request->input('bebida_id');
            $nombreBebida = $request->input('nombre');
            
            // Si la IA manda el nombre en vez del ID, la API lo busca sola en la BD
            if (!$bebidaId && $nombreBebida) {
                $bebidaDB = DB::table('bebidas')->where('nombre', $nombreBebida)->first();
                if ($bebidaDB) {
                    $bebidaId = $bebidaDB->id;
                }
            }

            // Validación de seguridad: Asegurar que haya una bebida para guardar
            if (!$bebidaId) {
                return response()->json(['error' => 'No se proporciono una bebida valida para guardar'], 400);
            }

            // Inserción dinámica (toma lo que venga o usa los valores por defecto)
            DB::table('pedidos')->insert([
                'user_id'    => $request->input('user_id', 1), // Defecto: usuario 1 (admin)
                'bebida_id'  => $bebidaId,
                'metodo'     => $request->input('metodo', 'voz'), // Defecto: 'voz'
                'estado'     => 'completado',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            try {
                $mqtt = new \PhpMqtt\Client\MqttClient('127.0.0.1', 1883, 'laravel_reportes');
                $mqtt->connect();
                $mqtt->publish('cafetera/estado', json_encode(['evento' => 'voz_finalizado', 'mensaje' => 'Cafetera Libre']), 0);
                $mqtt->disconnect();
            } catch (\Exception $e) { /* Ignorar error de broker */ }

            return response()->json(['res' => 'Pedido dinamico (Voz/IA) registrado con exito']);
        }
    }

    // --- CASO 2: Pedido de la App Web (Sí hay datos en Redis) ---
    if ($orden && $status === 'finalizado') {
        DB::table('pedidos')->insert([
            'user_id'    => $orden['user_id'],
            'bebida_id'  => $orden['bebida_id'],
            'metodo'     => $orden['metodo'], // Tomará 'app'
            'estado'     => 'completado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Limpiamos el caché para liberar la cafetera
        Cache::forget('orden_actual'); 

        try {
            $mqtt = new \PhpMqtt\Client\MqttClient('127.0.0.1', 1883, 'laravel_reportes');
            $mqtt->connect();
            $mqtt->publish('cafetera/estado', json_encode(['evento' => 'app_finalizado', 'mensaje' => 'Cafetera Libre']), 0);
            $mqtt->disconnect();
        } catch (\Exception $e) { /* Ignorar si Mosquitto no responde */ }

        return response()->json(['res' => 'Pedido de la App guardado y Redis limpio']);
    }

    // Si mandan basura o un estatus que no corresponde
    return response()->json(['res' => 'Proceso ignorado o error de estatus'], 400);
});

// 4. Rutas de Historial y Login
Route::get('/v1/historial', function () {
    return response()->json(DB::table('historial_detallado')->get());
});

Route::post('/v1/login', function (Request $request) {
    $request->validate([
        'username' => 'required',
        'password' => 'required',
    ]);

    $user = User::where('username', $request->username)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'Usuario o contraseña incorrectos'], 401);
    }
    
    if (!$user->cuenta_activa) {
        return response()->json(['message' => 'Esta cuenta de cafetera está desactivada'], 403);
    }

    $user->tokens()->delete();
    $token = $user->createToken('token_cafetera')->plainTextToken;

    return response()->json([
        'status'   => 'success',
        'token'    => $token,
        'user'     => $user->name,
        'username' => $user->username
    ]);
});

Route::get('/v1/mis-pedidos', function(Request $request){
    $pedidos = DB::table('pedidos')
        ->join('bebidas','pedidos.bebida_id', '=', 'bebidas.id')
        ->where('pedidos.user_id', $request->user()->id) 
        ->select('pedidos.*', 'bebidas.nombre as nombre_bebida')
        ->orderBy('pedidos.created_at','desc')
        ->get();
    return response()->json($pedidos);
})->middleware('auth:sanctum');