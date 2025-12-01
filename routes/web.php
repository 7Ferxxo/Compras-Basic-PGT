<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FacturaController;

Route::get('/', function () {
    return view('inicio');
});

Route::get('/dashboard.html', function () {
    return view('dashboard');
});

Route::post('/crear-factura', [FacturaController::class, 'guardar']);
Route::get('/get-recibos', [FacturaController::class, 'obtenerRecibos']);
Route::get('/api/cliente/{casillero}', [FacturaController::class, 'buscarCliente']);