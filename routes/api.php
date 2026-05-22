<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Hash; // Agregado para que no falle el Hash::check del login
use PhpMqtt\Client\MqttClient;
/*
|--------------------------------------------------------------------------
| API Cafetera-IA: Flujo Definitivo
|--------------------------------------------------------------------------
*/

// 1. POST /v1/pedir (Desde el Frontend)
Route::post('/v1/pedir', function (Request $request) {
    if (Cache::has('orden_actual')) {
        return response()->json(['error' => 'Cafetera ocupada'], 429);
    }

    // Buscamos la bebida en la base de datos por el nombre que viene en 'metodo'
    $bebida = DB::table('bebidas')
                ->where('nombre', $request->input('metodo'))
                ->first();

    if (!$bebida) {
        return response()->json(['error' => 'Bebida no encontrada: ' . $request->input('metodo')], 404);
    }

    // Decodificamos la receta (que viene como string JSON de la DB)
    $receta = json_decode($bebida->receta, true);

    $orden = [
        'user_id'   => 1, // Por ahora fijo para pruebas
        'bebida_id' => $bebida->id,
        'nombre'    => $bebida->nombre,
        'metodo'    => 'app', // Ahora sí funcionará porque la BD lo aceptará
        'receta'    => [
            'cafe'  => (bool)($receta['cafe'] ?? false),
            'agua'  => (bool)($receta['agua'] ?? false),
            'leche' => (bool)($receta['leche'] ?? false),
            'cocoa' => (bool)($receta['cocoa'] ?? false),
        ]
    ];

    Cache::put('orden_actual', $orden, now()->addMinutes(10));

    return response()->json(['status' => 'success', 'message' => 'Preparando ' . $bebida->nombre]);
})->middleware('auth:sanctum');

// 2. GET /v1/cafetera/orden (Consulta del ESP32)
Route::get('/v1/cafetera/orden', function () {
    $orden = Cache::get('orden_actual');
    
    if (!$orden) {
        return response()->json(['comando' => 0]);
    }

    return response()->json([
        'comando' => 1,
        'receta'  => $orden['receta'],
        'nombre'  => $orden['nombre']
    ]);
});



// 3. POST /v1/cafetera/reportar (Cierre del proceso - HTTP / Thunder Client)
Route::post('/v1/cafetera/reportar', function (Request $request) {
    $status = $request->input('status'); 
    $orden  = Cache::get('orden_actual');

    // CASO 1: Botón físico (No hay nada en Redis)
    if (!$orden) {
        if ($status === 'finalizado') {
            DB::table('pedidos')->insert([
                'user_id'    => 1, 
                'bebida_id'  => $request->input('bebida_id', 1),
                'metodo'     => 'manual',
                'estado'     => 'completado',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Opcional: Avisar por MQTT a la interfaz web si el botón físico fue presionado
            try {
                $mqtt = new \PhpMqtt\Client\MqttClient('127.0.0.1', 1883, 'laravel_reportes');
                $mqtt->connect();
                $mqtt->publish('cafetera/estado', json_encode(['evento' => 'manual_finalizado']), 0);
                $mqtt->disconnect();
            } catch (\Exception $e) { /* Ignorar error de broker */ }

            return response()->json(['res' => 'Pedido fisico registrado con exito']);
        }
    }

    // CASO 2: Pedido de la App
    if ($orden && $status === 'finalizado') {
        DB::table('pedidos')->insert([
            'user_id'    => $orden['user_id'],
            'bebida_id'  => $orden['bebida_id'],
            'metodo'     => $orden['metodo'], // Inyectará 'app'
            'estado'     => 'completado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::forget('orden_actual'); 

        // NUEVO: Publicar por MQTT que la cafetera ya está libre
        try {
            $mqtt = new \PhpMqtt\Client\MqttClient('127.0.0.1', 1883, 'laravel_reportes');
            $mqtt->connect();
            $mqtt->publish('cafetera/estado', json_encode(['evento' => 'app_finalizado', 'mensaje' => 'Cafetera Libre']), 0);
            $mqtt->disconnect();
        } catch (\Exception $e) { /* Ignorar si Mosquitto no responde */ }

        return response()->json(['res' => 'Pedido de la App guardado y Redis limpio']);
    }

    return response()->json(['res' => 'Proceso ignorado o error de estatus'], 400);
});

Route::get('/v1/historial', function () {
    return response()->json(DB::table('historial_detallado')->get());
});






Route::post('/v1/login', function (Request $request) {
    // Validamos que lleguen los campos correctos
    $request->validate([
        'username' => 'required',
        'password' => 'required',
    ]);

    // Buscamos al usuario por su 'username'
    $user = User::where('username', $request->username)->first();

    // Verificamos credenciales y si la cuenta está activa
    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'Usuario o contraseña incorrectos'], 401);
    }
    
    if (!$user->cuenta_activa) {
        return response()->json(['message' => 'Esta cuenta de cafetera está desactivada'], 403);
    }

    // Limpiamos tokens viejos y creamos el nuevo
    $user->tokens()->delete();
    $token = $user->createToken('token_cafetera')->plainTextToken;

    return response()->json([
        'status' => 'success',
        'token'  => $token,
        'user'   => $user->name,
        'username' => $user->username
    ]);
});

// Obtener pedidos del usuario activo (CORREGIDO)
Route::get('/v1/mis-pedidos', function(Request $request){
    $pedidos = DB::table('pedidos')
        ->join('bebidas','pedidos.bebida_id', '=', 'bebidas.id')
        ->where('pedidos.user_id', $request->user()->id) // Corregido: pedidos.user_id
        ->select('pedidos.*', 'bebidas.nombre as nombre_bebida')
        ->orderBy('pedidos.created_at','desc')
        ->get();
    return response()->json($pedidos);
})->middleware('auth:sanctum');