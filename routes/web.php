<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Facturacion\FacturaController;
use App\Http\Controllers\Compras\PurchaseRequestsController;
use App\Http\Controllers\Compras\StatsController;
use App\Http\Controllers\Compras\StoresController;

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
Route::get('/api/cliente/{casillero}', [FacturaController::class, 'buscarCliente']);
Route::get('/recibos/{id}/pdf', [FacturaController::class, 'verPdf']);

Route::get('/api/health', function () {
    try {
        DB::connection()->getPdo();
        return response()->json([
            'ok' => true,
            'service' => 'api',
            'time' => now()->toISOString(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'ok' => false,
            'code' => 'db_unreachable',
            'message' => 'No hay conexion a la base de datos',
        ], 503);
    }
});

Route::get('/api/stats', [StatsController::class, 'index']);
Route::get('/api/stores', [StoresController::class, 'index']);
Route::get('/api/purchase-requests', [PurchaseRequestsController::class, 'index']);
Route::get('/api/purchase-requests/{id}', [PurchaseRequestsController::class, 'show']);
Route::post('/api/purchase-requests/webhook/recibo-pagado', [PurchaseRequestsController::class, 'createFromInvoiceWebhook']);
Route::post('/api/purchase-requests/receipt/{reciboId}/attachments', [PurchaseRequestsController::class, 'attachFromReceipt']);
Route::post('/api/purchase-requests', [PurchaseRequestsController::class, 'store']);
Route::patch('/api/purchase-requests/{id}/status', [PurchaseRequestsController::class, 'patchStatus']);
Route::post('/api/purchase-requests/{id}/send', [PurchaseRequestsController::class, 'sendToSupervisor']);
Route::post('/api/purchase-requests/{id}/attachments', [PurchaseRequestsController::class, 'uploadAttachment']);
