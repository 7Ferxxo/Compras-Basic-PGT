<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <title>Factura</title>
    <style>
      body {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 12px;
        color: #2b2b2b;
        margin: 0;
        padding: 0;
      }
      .container {
        width: 90%;
        margin: 0 auto;
        padding: 26px 0 18px 0;
      }
      .brand {
        width: 140px;
      }
      .header-table {
        width: 100%;
        margin-bottom: 12px;
      }
      .title {
        text-align: right;
        color: #0b3a6b;
        font-weight: bold;
        font-size: 20px;
        letter-spacing: 0.3px;
      }
      .meta {
        text-align: right;
        font-size: 12px;
        color: #3b3b3b;
      }
      .client-box {
        margin-top: 10px;
        background: #f2f2f2;
        padding: 10px 12px;
        border-left: 4px solid #0b3a6b;
        width: 62%;
      }
      .divider {
        border-top: 2px solid #0b3a6b;
        margin: 14px 0 10px 0;
      }
      .items-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 8px;
      }
      .items-table th {
        background: #0b3a6b;
        color: #ffffff;
        text-align: left;
        padding: 8px 10px;
        font-size: 12px;
      }
      .items-table td {
        padding: 10px;
        border-bottom: 1px solid #e0e0e0;
        font-size: 12px;
      }
      .text-right {
        text-align: right;
      }
      .totals {
        width: 52%;
        margin: 18px 0 0 auto;
        border-collapse: collapse;
      }
      .totals td {
        padding: 4px 0;
        text-align: right;
        font-size: 12px;
      }
      .totals .line {
        border-top: 2px solid #0b3a6b;
      }
      .grand-total {
        font-weight: bold;
        color: #0b3a6b;
        font-size: 13px;
      }
      .footer {
        margin-top: 28px;
        text-align: center;
        font-size: 10px;
        color: #7a7a7a;
        border-top: 1px solid #e0e0e0;
        padding-top: 10px;
      }
    </style>
  </head>
  <body>
    @php
      $subtotal = (float) ($request->quoted_total ?? 0);
      $comision = (float) ($request->american_card_charge ?? 0);
      $residencial = (float) ($request->residential_charge ?? 0);
      $total = $subtotal + $comision + $residencial;
      $sucursal = '-';
      if (!empty($request->notes) && str_contains($request->notes, 'Sucursal:')) {
        $firstLine = strtok($request->notes, "\n");
        $sucursal = trim(str_replace('Sucursal:', '', $firstLine));
      }
    @endphp

    <div class="container">
      <table class="header-table" cellpadding="0" cellspacing="0">
        <tr>
          <td width="60%" valign="top">
            <img src="{{ public_path('imagenes/logo.png') }}" class="brand" alt="PGT Logistics">

            <div class="client-box">
              <strong>CLIENTE:</strong><br>
              {{ $request->client_name }}<br>
              <small>{{ $request->account_email ?: '-' }}</small><br>
              Casillero: <strong>{{ $request->client_code }}</strong>
            </div>
          </td>
          <td width="40%" valign="top">
            <div class="title">FACTURA</div>
            <div class="meta">
              <strong>No. {{ $request->code }}</strong><br>
              Fecha: {{ optional($request->created_at)->format('d/m/Y') }}<br>
              Sucursal: {{ $sucursal }}<br>
              Pago: {{ $request->payment_method ?? '-' }}
            </div>
          </td>
        </tr>
      </table>

      <div class="divider"></div>

      <table class="items-table" cellpadding="0" cellspacing="0">
        <thead>
          <tr>
            <th width="50%">TIENDA</th>
            <th width="25%" class="text-right">PRECIO</th>
            <th width="25%" class="text-right">TOTAL</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>{{ $storeName }}</td>
            <td class="text-right">B/. {{ number_format($subtotal, 2, '.', '') }}</td>
            <td class="text-right">B/. {{ number_format($total, 2, '.', '') }}</td>
          </tr>
        </tbody>
      </table>

      <table class="totals" cellpadding="0" cellspacing="0">
        <tr>
          <td>Precio del artículo:</td>
          <td>B/. {{ number_format($subtotal, 2, '.', '') }}</td>
        </tr>
        <tr>
          <td>Comisión:</td>
          <td>B/. {{ number_format($comision, 2, '.', '') }}</td>
        </tr>
        <tr>
          <td class="line grand-total">TOTAL A PAGAR:</td>
          <td class="line grand-total">B/. {{ number_format($total, 2, '.', '') }}</td>
        </tr>
      </table>

      <div class="footer">
        <strong>Gracias por su preferencia</strong><br>
        PGT LOGISTICS GROUP S.A. | RUC: 155713237-2-2021 DV: 21<br>
        Tel: 399-4305 | cobros.panamagt@gmail.com
      </div>
    </div>
  </body>
</html>
