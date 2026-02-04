<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <style>
      body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 12px; color: #0f172a; }
      h1 { font-size: 18px; margin: 0 0 8px; color: #0ea5e9; }
      .muted { color: #64748b; font-size: 11px; }
      .status-badge { background: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 3px; font-weight: bold; font-size: 11px; }
      table { width: 100%; border-collapse: collapse; margin-top: 10px; }
      th, td { border: 1px solid #e2e8f0; padding: 6px; text-align: left; }
      th { background: #f8fafc; font-weight: bold; }
      .next-steps { background: #ecfdf5; border-left: 3px solid #10b981; padding: 10px; margin-top: 15px; }
      .next-steps h3 { margin: 0 0 8px; font-size: 13px; color: #065f46; }
      .next-steps ul { margin: 0; padding-left: 20px; }
      .next-steps li { margin-bottom: 4px; font-size: 11px; color: #065f46; }
    </style>
  </head>
  <body>
    <h1>Comprobante de Solicitud de Compra</h1>
    <div class="muted">Código: {{ $request->code }}</div>
    <div class="muted">Fecha: {{ optional($request->created_at)->format('Y-m-d H:i') }}</div>
    <div style="margin-top: 8px;"><span class="status-badge">ESTADO: CREADA</span></div>

    <table>
      <tr>
        <th>Cliente</th>
        <td>{{ $request->client_name }}</td>
      </tr>
      <tr>
        <th>Casillero</th>
        <td>{{ $request->client_code }}</td>
      </tr>
      <tr>
        <th>Tienda</th>
        <td>{{ $storeName }}</td>
      </tr>
      <tr>
        <th>Método de pago</th>
        <td>{{ $request->payment_method ?? '-' }}</td>
      </tr>
      <tr>
        <th>Precio</th>
        <td>B/. {{ number_format((float) $request->quoted_total, 2, '.', '') }}</td>
      </tr>
      <tr>
        <th>Comisión</th>
        <td>B/. {{ number_format((float) ($request->american_card_charge ?? 0), 2, '.', '') }}</td>
      </tr>
      <tr>
        <th>Cargo residencial</th>
        <td>B/. {{ number_format((float) ($request->residential_charge ?? 0), 2, '.', '') }}</td>
      </tr>
      <tr>
        <th style="background: #e0f2fe;">Total</th>
        <td style="background: #e0f2fe; font-weight: bold;">
          @php
            $total = (float) ($request->quoted_total ?? 0) + (float) ($request->american_card_charge ?? 0) + (float) ($request->residential_charge ?? 0);
          @endphp
          B/. {{ number_format($total, 2, '.', '') }}
        </td>
      </tr>
      <tr>
        <th>Link</th>
        <td style="word-break: break-all; font-size: 10px;">{{ $request->item_link ?: '-' }}</td>
      </tr>
      <tr>
        <th>Descripción</th>
        <td>{{ $request->item_options ?: '-' }}</td>
      </tr>
      <tr>
        <th>Notas</th>
        <td>{{ $request->notes ?: '-' }}</td>
      </tr>
    </table>

    <div class="next-steps">
      <h3>Próximos Pasos</h3>
      <ul>
        <li>Tu solicitud ha sido recibida y está en estado CREADA</li>
        <li>Nuestro equipo revisará la información proporcionada</li>
        <li>Te notificaremos cuando esté lista para enviar al supervisor</li>
        <li>Podrás hacer seguimiento del estado en el panel de compras</li>
      </ul>
    </div>
  </body>
</html>
