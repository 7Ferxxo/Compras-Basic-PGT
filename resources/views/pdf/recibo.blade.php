<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
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
            width: 100%;
            padding: 24px 28px 18px 28px;
        }
        .brand {
            width: 140px;
        }
        .brand-logo {
            width: 150px;
            height: auto;
            display: block;
            margin-bottom: 6px;
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
            width: 60%;
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
            width: 50%;
            margin-left: auto;
            margin-top: 18px;
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
        $logoPath = public_path('imagenes/logo.png');
        $logoDataUri = is_file($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;
    @endphp
    <div class="container">
        <table class="header-table" cellpadding="0" cellspacing="0">
            <tr>
                <td width="60%" valign="top">
                    @if($logoDataUri)
                        <img src="{{ $logoDataUri }}" alt="PGT Logistics" class="brand-logo" />
                    @else
                        <div style="font-weight:700;color:#0b3a6b;font-size:22px;letter-spacing:1px;">PGT LOGISTICS</div>
                    @endif

                    <div class="client-box">
                        <strong>CLIENTE:</strong><br>
                        {{ $recibo->cliente }}<br>
                        <small>{{ $recibo->email_cliente }}</small><br>
                        Casillero: <strong>{{ $recibo->casillero }}</strong>
                    </div>
                </td>
                <td width="40%" valign="top">
                    <div class="title">FACTURA</div>
                    <div class="meta">
                        <strong>No. {{ str_pad($recibo->id, 6, '0', STR_PAD_LEFT) }}</strong><br>
                        Fecha: {{ \Carbon\Carbon::parse($recibo->fecha)->format('d/m/Y') }}<br>
                        Sucursal: {{ $recibo->sucursal }}<br>
                        Pago: {{ $recibo->metodo_pago }}
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
                    <td>{{ $recibo->concepto ?: 'Sin tienda especificada' }}</td>
                    <td class="text-right">B/. {{ number_format($subtotal, 2) }}</td>
                    <td class="text-right">B/. {{ number_format($recibo->monto, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <table class="totals" cellpadding="0" cellspacing="0">
            <tr>
                <td>Precio del articulo:</td>
                <td>B/. {{ number_format($subtotal, 2) }}</td>
            </tr>
            <tr>
                <td>Comision:</td>
                <td>B/. {{ number_format($itbms, 2) }}</td>
            </tr>
            <tr>
                <td class="line grand-total">TOTAL A PAGAR:</td>
                <td class="line grand-total">B/. {{ number_format($recibo->monto, 2) }}</td>
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
