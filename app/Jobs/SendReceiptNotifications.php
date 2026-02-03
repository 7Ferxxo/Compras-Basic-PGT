<?php

namespace App\Jobs;

use App\Models\Recibo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendReceiptNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $reciboId, public array $webhookMeta = [])
    {
    }

    public function handle(): void
    {
        $recibo = Recibo::query()->find($this->reciboId);
        if (!$recibo) return;

        $monto = (float) $recibo->monto;
        $itbmsRate = 0.0;
        if ($recibo->metodo_pago === 'Yappy') {
            $itbmsRate = 0.02;
        } elseif ($recibo->metodo_pago === 'Tarjeta') {
            $itbmsRate = 0.03;
        }
        $subtotal = $itbmsRate > 0 ? ($monto / (1 + $itbmsRate)) : $monto;
        $itbms = $monto - $subtotal;

        $rutaCompleta = null;
        if ($recibo->pdf_filename) {
            $rutaCompleta = public_path('facturas_pdf/' . $recibo->pdf_filename);
            if (!is_file($rutaCompleta)) {
                $rutaCompleta = null;
            }
        }

        try {
            $data = [
                'recibo' => $recibo,
                'subtotal' => $subtotal,
                'itbms' => $itbms,
                'logoUrl' => asset('imagenes/logo.png'),
            ];

            Mail::send('emails.recibo', $data, function ($message) use ($recibo, $rutaCompleta) {
                $message->to($recibo->email_cliente, $recibo->cliente)
                        ->subject('Nuevo Recibo de Compra - PGT Logistics');

                if ($rutaCompleta) {
                    $message->attach($rutaCompleta);
                }
            });
        } catch (\Exception $e) {
            Log::error('Error enviando correo de recibo', [
                'error' => $e->getMessage(),
                'id_recibo' => $recibo->id,
            ]);
        }

        $webhookUrl = config('services.compras.webhook_url');
        if (!$webhookUrl) return;

        try {
            $payload = [
                'origen' => 'FACTURADOR_LARAVEL',
                'id_recibo' => $recibo->id,
                'cliente' => $recibo->cliente,
                'casillero' => $recibo->casillero,
                'email_cliente' => $recibo->email_cliente,
                'tipo_servicio' => $this->webhookMeta['tipo_servicio'] ?? null,
                'link_producto' => $this->webhookMeta['link_producto'] ?? null,
                'descripcion_compra' => $recibo->concepto,
                'monto_pagado' => $recibo->monto,
                'subtotal' => $subtotal,
                'comision' => $itbms,
                'metodo_pago' => $recibo->metodo_pago,
                'fecha_pago' => now()->toDateTimeString(),
                'evidencia_pago_url' => $recibo->pdf_filename
                    ? asset('facturas_pdf/' . $recibo->pdf_filename)
                    : null,
            ];

            $timeout = (int) config('services.compras.timeout_seconds', 5);
            $http = Http::timeout($timeout)->acceptJson();

            $token = config('services.compras.webhook_token');
            if ($token) {
                $http = $http->withHeaders(['X-Webhook-Token' => $token]);
            }

            $response = $http->post($webhookUrl, $payload);
            if (!$response->successful()) {
                Log::warning('Webhook a Compras respondio con error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'id_recibo' => $recibo->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error enviando webhook a Compras', [
                'error' => $e->getMessage(),
                'id_recibo' => $recibo->id,
            ]);
        }
    }
}
