<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Recibo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;

class FacturaController extends Controller
{
    public function guardar(Request $request)
    {
        try {
            $request->validate([
                'cliente'       => 'required',
                'casillero'     => 'required',
                'email_cliente' => 'required|email',
                'sucursal'      => 'required',
                'fecha'         => 'required',
                'metodo_pago'   => 'required',
                'items'         => 'required|array',
            ]);

            $subtotal = 0;
            $concepto = "";

            foreach ($request->items as $item) {
                if (isset($item['precio'])) {
                    $subtotal += $item['precio'];
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
            $recibo->cliente       = $request->cliente;
            $recibo->casillero     = $request->casillero;
            $recibo->sucursal      = $request->sucursal;
            $recibo->monto         = $total;
            $recibo->concepto      = $concepto;
            $recibo->metodo_pago   = $request->metodo_pago;
            $recibo->fecha         = $request->fecha;
            $recibo->email_cliente = $request->email_cliente;
            $recibo->pdf_filename  = 'generando...';
            $recibo->save();

            $nombreArchivo = 'recibo-' . $recibo->id . '-' . time() . '.pdf';
            $pdf = Pdf::loadView('pdf.recibo', compact('recibo', 'subtotal', 'itbms'));
            
            $rutaCarpeta = public_path('facturas_pdf');
            if (!file_exists($rutaCarpeta)) {
                mkdir($rutaCarpeta, 0755, true);
            }
            
            $rutaCompleta = $rutaCarpeta . '/' . $nombreArchivo;
            $pdf->save($rutaCompleta);

            $recibo->pdf_filename = $nombreArchivo;
            $recibo->save();

            try {
                $data = ['cliente' => $recibo->cliente];
                
                Mail::send([], $data, function ($message) use ($recibo, $rutaCompleta) {
                    $message->to($recibo->email_cliente, $recibo->cliente)
                            ->subject('Nuevo Recibo de Compra - PGT Logistics')
                            ->html("<p>Hola <strong>{$recibo->cliente}</strong>,</p><p>Adjunto encontrarás tu recibo de compra.</p><p>Gracias por preferirnos.</p>");

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
            $cliente = Recibo::where('casillero', $casillero)
                             ->orderBy('id', 'desc')
                             ->first();

            if ($cliente) {
                return response()->json([
                    'cliente' => $cliente->cliente,
                    'email_cliente' => $cliente->email_cliente
                ]);
            } else {
                return response()->json(['message' => 'Cliente nuevo'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al buscar'], 500);
        }
    }
}