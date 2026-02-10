<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Facturacion\FacturaController;
use App\Http\Controllers\Compras\PurchaseRequestsController;

Route::redirect('/', '/compras');

Route::redirect('/compras', '/compras/pages/panel-compras/panel-compras.html');

Route::view('/facturador', 'inicio')->name('facturador');

Route::view('/dashboard', 'dashboard')->name('dashboard');

Route::redirect('/dashboard.html', '/dashboard');

Route::post('/crear-factura', [FacturaController::class, 'guardar'])->middleware('throttle:20,1');
Route::get('/get-recibos', [FacturaController::class, 'obtenerRecibos'])->middleware('facturacion.token');
Route::get('/recibos/{id}/pdf', [FacturaController::class, 'verPdf'])->middleware('facturacion.token');

Route::get('/purchase-requests/{id}/pdf', [PurchaseRequestsController::class, 'receiptPdf'])->middleware('facturacion.token');
