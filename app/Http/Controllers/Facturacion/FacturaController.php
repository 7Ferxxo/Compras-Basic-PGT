<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facturacion\StoreReciboRequest;
use App\Models\Recibo;
use App\Services\Compras\PurchaseRequestService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class FacturaController extends Controller
{
    public function verPdf(int $id)
    {
        $recibo = Recibo::query()->findOrFail($id);

        $rutaCarpeta = public_path('facturas_pdf');
        if (!is_dir($rutaCarpeta)) {
            mkdir($rutaCarpeta, 0755, true);
        }

        $nombreArchivo = $recibo->pdf_filename;
        $archivoValido = is_string($nombreArchivo) && $nombreArchivo !== '' && $nombreArchivo !== 'generando...';
        if (!$archivoValido) {
            $nombreArchivo = 'recibo-' . $recibo->id . '-' . time() . '.pdf';
        }

        $rutaCompleta = $rutaCarpeta . '/' . $nombreArchivo;

        if (!file_exists($rutaCompleta)) {
            $monto = (float) $recibo->monto;
            $itbmsRate = 0.0;
            if ($recibo->metodo_pago === 'Yappy') {
                $itbmsRate = 0.02;
            } elseif ($recibo->metodo_pago === 'Tarjeta') {
                $itbmsRate = 0.03;
            }

            $subtotal = $itbmsRate > 0 ? ($monto / (1 + $itbmsRate)) : $monto;
            $itbms = $monto - $subtotal;

            $pdf = Pdf::loadView('pdf.recibo', compact('recibo', 'subtotal', 'itbms'));
            $pdf->save($rutaCompleta);
        }

        if ($recibo->pdf_filename !== $nombreArchivo) {
            $recibo->pdf_filename = $nombreArchivo;
            $recibo->save();
        }

        return response()->file($rutaCompleta);
    }

    public function guardar(StoreReciboRequest $request)
    {
        try {
            $subtotal = 0;
            $concepto = "";

            foreach ($request->input('items', []) as $item) {
                if (isset($item['precio'])) {
                    $subtotal += (float) $item['precio'];
                } else {
                    throw new \Exception("El campo 'precio' es requerido en cada item.");
                }
                $concepto .= isset($item['descripcion']) ? $item['descripcion'] . ", " : "";
            }
            $concepto = rtrim($concepto, ", ");

            $itbms = 0;
            if ($request->metodo_pago === 'Yappy') {
                $itbms = $subtotal * 0.02;
            } elseif ($request->metodo_pago === 'Tarjeta') {
                $itbms = $subtotal * 0.03;
            }

            $total = $subtotal + $itbms;

            $recibo = new Recibo();
            $recibo->cliente       = $request->input('cliente');
            $recibo->casillero     = $request->input('casillero');
            $recibo->sucursal      = $request->input('sucursal');
            $recibo->monto         = $total;
            $recibo->concepto      = $concepto;
            $recibo->metodo_pago   = $request->input('metodo_pago');
            $recibo->fecha         = $request->input('fecha');
            $recibo->email_cliente = $request->input('email_cliente');
            $recibo->pdf_filename  = 'generando...';
            $recibo->save();

            $nombreArchivo = 'recibo-' . $recibo->id . '-' . time() . '.pdf';
            $pdf = Pdf::loadView('pdf.recibo', compact('recibo', 'subtotal', 'itbms'));
            
            $rutaCarpeta = public_path('facturas_pdf');
            if (!is_dir($rutaCarpeta)) {
                mkdir($rutaCarpeta, 0755, true);
            }
            
            $rutaCompleta = $rutaCarpeta . '/' . $nombreArchivo;
            $pdf->save($rutaCompleta);

            $recibo->pdf_filename = $nombreArchivo;
            $recibo->save();

            $tipoServicio = strtoupper((string) $request->input('tipo_servicio', 'OTRO'));
            if ($tipoServicio === 'BASIC') {
                try {
                                                                        
                    $purchaseRequests = app(PurchaseRequestService::class);

                    $purchaseRequests->createFromInvoiceWebhookPayload([
                        'origen' => 'FACTURADOR_LARAVEL',
                        'id_recibo' => $recibo->id,
                        'cliente' => $recibo->cliente,
                        'casillero' => $recibo->casillero,
                        'email_cliente' => $recibo->email_cliente,
                        'tipo_servicio' => $tipoServicio,
                        'link_producto' => $request->input('link_producto'),
                        'descripcion_compra' => $recibo->concepto,
                        'monto_pagado' => $recibo->monto,
                        'subtotal' => $subtotal,
                        'comision' => $itbms,
                        'metodo_pago' => $recibo->metodo_pago,
                        'fecha_pago' => now()->toDateTimeString(),
                        'evidencia_pago_url' => asset('facturas_pdf/' . $nombreArchivo),
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Error creando solicitud interna de Compras desde recibo', [
                        'error' => $e->getMessage(),
                        'id_recibo' => $recibo->id,
                    ]);
                }

                $webhookUrl = config('services.compras.webhook_url');
                if ($webhookUrl) {
                    try {
                        $payload = [
                            'origen' => 'FACTURADOR_LARAVEL',
                            'id_recibo' => $recibo->id,
                            'cliente' => $recibo->cliente,
                            'casillero' => $recibo->casillero,
                            'email_cliente' => $recibo->email_cliente,
                            'tipo_servicio' => $tipoServicio,
                            'link_producto' => $request->input('link_producto'),
                            'descripcion_compra' => $recibo->concepto,
                            'monto_pagado' => $recibo->monto,
                            'subtotal' => $subtotal,
                            'comision' => $itbms,
                            'metodo_pago' => $recibo->metodo_pago,
                            'fecha_pago' => now()->toDateTimeString(),
                            'evidencia_pago_url' => asset('facturas_pdf/' . $nombreArchivo),
                        ];

                        $timeout = (int) (config('services.compras.timeout_seconds') ?? 5);
                        $http = Http::timeout($timeout)->acceptJson();

                        $token = config('services.compras.webhook_token');
                        if ($token) {
                            $http = $http->withHeaders(['X-Webhook-Token' => $token]);
                        }

                        $response = $http->post($webhookUrl, $payload);
                        if (!$response->successful()) {
                            Log::warning('Webhook a Compras respondió con error', [
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
                } else {
                                                                                                          
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

                    $message->attach($rutaCompleta);
                });
            } catch (\Exception $e) {
            }

            return response()->json([
                'message'   => '¡Recibo generado y correo enviado!',
                'id_recibo' => $recibo->id,
                'pdf_url'   => asset('facturas_pdf/' . $nombreArchivo)
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function obtenerRecibos()
    {
        try {
            $recibos = Recibo::orderBy('id', 'desc')->get();
            return response()->json($recibos);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al cargar recibos'], 500);
        }
    }

    public function buscarCliente($casillero)
    {
        try {
            $casillero = trim((string) $casillero);

            $crmLookupUrl = (string) (config('services.crm.lookup_url') ?? '');
            if ($crmLookupUrl !== '') {
                try {
                    $url = str_contains($crmLookupUrl, '{casillero}')
                        ? str_replace('{casillero}', urlencode($casillero), $crmLookupUrl)
                        : rtrim($crmLookupUrl, '/') . '/' . urlencode($casillero);

                    $timeout = (int) (config('services.crm.timeout_seconds') ?? 5);
                    $http = Http::timeout($timeout)->acceptJson();

                    $token = (string) (config('services.crm.token') ?? '');
                    if ($token !== '') {
                        $http = $http->withToken($token);
                    }

                    $response = $http->get($url);

                    if ($response->successful()) {
                        $data = (array) $response->json();

                        $cliente = $data['cliente'] ?? $data['nombre'] ?? $data['name'] ?? null;
                        $email = $data['email_cliente'] ?? $data['email'] ?? null;

                        if ($cliente || $email) {
                            return response()->json([
                                'cliente' => $cliente ?? '',
                                'email_cliente' => $email ?? '',
                                'source' => 'crm',
                            ]);
                        }
                    } elseif ($response->status() !== 404) {
                        Log::warning('Error consultando CRM por casillero', [
                            'status' => $response->status(),
                            'body' => $response->body(),
                            'casillero' => $casillero,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Excepción consultando CRM por casillero', [
                        'error' => $e->getMessage(),
                        'casillero' => $casillero,
                    ]);
                }
            }

            $cliente = Recibo::where('casillero', $casillero)
                ->orderBy('id', 'desc')
                ->first();

            if ($cliente) {
                return response()->json([
                    'cliente' => $cliente->cliente,
                    'email_cliente' => $cliente->email_cliente,
                    'source' => 'recibos',
                ]);
            } else {
                return response()->json(['message' => 'Cliente nuevo'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al buscar'], 500);
        }
    }
}
