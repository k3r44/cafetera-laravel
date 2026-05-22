<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/cafetera/configurar-wifi', function () {
    return view('cafetera.config_wifi');
});