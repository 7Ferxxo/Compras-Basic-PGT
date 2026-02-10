<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facturacion\StoreReciboRequest;
use App\Jobs\SendReceiptNotifications;
use App\Models\Recibo;
use App\Models\PurchaseRequest;
use App\Services\Compras\PurchaseRequestService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class FacturaController extends Controller
{
    private function configurePdfRuntime(): void
    {
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '0');
        @ini_set('max_input_time', '0');
        @set_time_limit(0);
    }

    public function verPdf(int $id)
    {
        $this->configurePdfRuntime();
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
        $pdfBytes = null;

        if (!file_exists($rutaCompleta) && $recibo->pdf_blob) {
            file_put_contents($rutaCompleta, $recibo->pdf_blob);
        }

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
            $pdfBytes = $pdf->output();
            $pdf->save($rutaCompleta);

            if (!$recibo->pdf_blob) {
                $recibo->pdf_blob = $pdfBytes;
                $recibo->save();
            }
        } elseif (!$recibo->pdf_blob && is_file($rutaCompleta)) {
            $pdfBytes = file_get_contents($rutaCompleta);
            $recibo->pdf_blob = $pdfBytes;
            $recibo->save();
        }

        if ($recibo->pdf_filename !== $nombreArchivo) {
            $recibo->pdf_filename = $nombreArchivo;
            if ($pdfBytes !== null) {
                $recibo->pdf_blob = $pdfBytes;
            }
            $recibo->save();
        }

        return response()->file($rutaCompleta);
    }

    public function guardar(StoreReciboRequest $request)
    {
        $this->configurePdfRuntime();
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
            $pdfBytes = $pdf->output();
            
            $rutaCarpeta = public_path('facturas_pdf');
            if (!is_dir($rutaCarpeta)) {
                mkdir($rutaCarpeta, 0755, true);
            }
            
            $rutaCompleta = $rutaCarpeta . '/' . $nombreArchivo;
            file_put_contents($rutaCompleta, $pdfBytes);

            $recibo->pdf_filename = $nombreArchivo;
            $recibo->pdf_blob = $pdfBytes;
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

                try {
                    SendReceiptNotifications::dispatchSync($recibo->id, [
                        'tipo_servicio' => $tipoServicio,
                        'link_producto' => $request->input('link_producto'),
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error encolando notificaciones de recibo', [
                        'error' => $e->getMessage(),
                        'id_recibo' => $recibo->id,
                    ]);
                }
            }
            if (!empty($recibo->email_cliente) && $tipoServicio !== 'BASIC') {
                try {
                    SendReceiptNotifications::dispatchSync($recibo->id, [
                        'tipo_servicio' => $tipoServicio,
                        'link_producto' => $request->input('link_producto'),
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error enviando correo de recibo (sync)', [
                        'error' => $e->getMessage(),
                        'id_recibo' => $recibo->id,
                    ]);
                }
            }

            return response()->json([
                'message'   => 'Recibo generado y correo enviado!',
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
            $recibos = Recibo::orderBy('id', 'desc')->get()->map(function (Recibo $r) {
                return [
                    'id' => $r->id,
                    'cliente' => $r->cliente,
                    'casillero' => $r->casillero,
                    'email_cliente' => $r->email_cliente,
                    'sucursal' => $r->sucursal,
                    'monto' => $r->monto,
                    'fecha' => $r->fecha,
                    'metodo_pago' => $r->metodo_pago,
                    'pdf_url' => url('/recibos/' . $r->id . '/pdf'),
                    'source' => 'facturacion',
                ];
            });

            $purchaseReceipts = DB::table('purchase_requests as pr')
                ->whereNotNull('pr.receipt_sent_at')
                ->orderByDesc('pr.id')
                ->get()
                ->map(function ($row) {
                    $notes = (string) ($row->notes ?? '');
                    $sucursal = null;
                    if (preg_match('/Sucursal:\s*([^\n\r]+)/i', $notes, $m)) {
                        $sucursal = trim((string) $m[1]);
                    }

                    $monto = (float) ($row->quoted_total ?? 0)
                        + (float) ($row->residential_charge ?? 0)
                        + (float) ($row->american_card_charge ?? 0);

                    return [
                        'id' => 'PR-' . $row->id,
                        'cliente' => $row->client_name,
                        'casillero' => $row->client_code,
                        'email_cliente' => $row->account_email,
                        'sucursal' => $sucursal ?: '-',
                        'monto' => $monto,
                        'fecha' => $row->receipt_sent_at ?: $row->created_at,
                        'metodo_pago' => $row->payment_method ?: '-',
                        'pdf_url' => url('/purchase-requests/' . $row->id . '/pdf'),
                        'source' => 'compras',
                    ];
                });

            $items = $recibos->concat($purchaseReceipts)->sortByDesc(function ($item) {
                try {
                    return strtotime((string) ($item['fecha'] ?? '')) ?: 0;
                } catch (\Throwable $e) {
                    return 0;
                }
            })->values();

            return response()->json($items);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al cargar recibos'], 500);
        }
    }

    public function buscarCliente($casillero)
    {
        try {
            $casillero = trim((string) $casillero);
            $casilleroNorm = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $casillero));

            $crmLookupUrl = (string) config('services.crm.lookup_url', '');
            if ($crmLookupUrl !== '') {
                try {
                    $url = str_contains($crmLookupUrl, '{casillero}')
                        ? str_replace('{casillero}', urlencode($casillero), $crmLookupUrl)
                        : rtrim($crmLookupUrl, '/') . '/' . urlencode($casillero);

                    $timeout = (int) config('services.crm.timeout_seconds', 5);
                    $http = Http::timeout($timeout)->acceptJson();

                    $token = (string) config('services.crm.token', '');
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
                    Log::warning('Excepcion consultando CRM por casillero', [
                        'error' => $e->getMessage(),
                        'casillero' => $casillero,
                    ]);
                }
            }

            $cliente = Recibo::where('casillero', $casillero)
                ->orWhereRaw("REPLACE(REPLACE(UPPER(casillero), '-', ''), ' ', '') = ?", [$casilleroNorm])
                ->orderBy('id', 'desc')
                ->first();

            if ($cliente) {
                return response()->json([
                    'cliente' => $cliente->cliente,
                    'email_cliente' => $cliente->email_cliente,
                    'source' => 'recibos',
                ]);
            }

            $fromPurchase = PurchaseRequest::query()
                ->where('client_code', $casillero)
                ->orWhereRaw("REPLACE(REPLACE(UPPER(client_code), '-', ''), ' ', '') = ?", [$casilleroNorm])
                ->orderByDesc('id')
                ->first();

            if ($fromPurchase) {
                return response()->json([
                    'cliente' => $fromPurchase->client_name,
                    'email_cliente' => $fromPurchase->account_email,
                    'source' => 'compras',
                ]);
            }

            return response()->json(['message' => 'Cliente nuevo'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al buscar'], 500);
        }
    }

    public function buscarClientePorEmail($email)
    {
        try {
            $email = strtolower(trim((string) $email));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return response()->json(['message' => 'Email invalido'], 422);
            }

            $cliente = Recibo::whereRaw('lower(email_cliente) = ?', [$email])
                ->orderBy('id', 'desc')
                ->first();

            if ($cliente) {
                return response()->json([
                    'cliente' => $cliente->cliente,
                    'email_cliente' => $cliente->email_cliente,
                    'casillero' => $cliente->casillero,
                    'source' => 'recibos',
                ]);
            }

            return response()->json(['message' => 'Cliente no encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al buscar'], 500);
        }
    }
}
