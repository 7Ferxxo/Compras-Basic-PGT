<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Facturacion\FacturaController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Compras\PurchaseRequestsController;
use App\Http\Controllers\Compras\StatsController;
use App\Http\Controllers\Compras\StoresController;

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/health', HealthController::class);

    Route::get('/cliente/{casillero}', [FacturaController::class, 'buscarCliente'])->middleware('throttle:30,1');
    Route::get('/stores', [StoresController::class, 'index']);
    Route::get('/stats', [StatsController::class, 'index']);
    Route::get('/purchase-requests', [PurchaseRequestsController::class, 'index']);
    Route::get('/purchase-requests/{id}', [PurchaseRequestsController::class, 'show']);

    Route::middleware('compras.token')->group(function () {
        Route::post('/purchase-requests/receipt/{reciboId}/attachments', [PurchaseRequestsController::class, 'attachFromReceipt']);
        Route::patch('/purchase-requests/{id}/status', [PurchaseRequestsController::class, 'patchStatus']);
        Route::post('/purchase-requests/{id}/send', [PurchaseRequestsController::class, 'sendToSupervisor']);
        Route::post('/purchase-requests/{id}/attachments', [PurchaseRequestsController::class, 'uploadAttachment']);
    });

    Route::post('/purchase-requests/webhook/recibo-pagado', [PurchaseRequestsController::class, 'createFromInvoiceWebhook']);
    Route::post('/purchase-requests', [PurchaseRequestsController::class, 'store']);
    Route::post('/purchase-requests/{id}/resend-receipt', [PurchaseRequestsController::class, 'resendReceipt']);
});
