<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Facturacion\FacturaController;

Route::redirect('/', '/compras');

Route::redirect('/compras', '/compras/pages/panel-compras/panel-compras.html');

Route::get('/facturador', function () {
    return view('inicio');
})->name('facturador');

Route::get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');

Route::redirect('/dashboard.html', '/dashboard');

Route::post('/crear-factura', [FacturaController::class, 'guardar']);
Route::get('/get-recibos', [FacturaController::class, 'obtenerRecibos']);
Route::get('/recibos/{id}/pdf', [FacturaController::class, 'verPdf']);
