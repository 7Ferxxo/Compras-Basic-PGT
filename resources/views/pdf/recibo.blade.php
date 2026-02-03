<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: 
        }
        .container {
            width: 100%;
            margin: 0 auto;
        }
        .header-table {
            width: 100%;
            margin-bottom: 20px;
            border-bottom: 2px solid 
        }
        .logo {
            width: 120px;
        }
        .info-box {
            background-color: 
            padding: 10px;
            border-left: 5px solid 
        }
        h1 {
            color: 
            margin: 0;
            text-align: right;
        }
        .details-box {
            text-align: right;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .items-table th {
            background-color: 
            color: 
            padding: 8px;
            text-align: left;
        }
        .text-right {
            text-align: right !important;
        }

        .items-table td {
            padding: 10px;
            border-bottom: 1px solid 
        }

        .totals-table {
            width: 60%;
            margin-left: auto;
            margin-top: 20px;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 5px;
            text-align: right;
        }
        .grand-total {
            color: 
            font-weight: bold;
            font-size: 14px;
            border-top: 2px solid 
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 10px;
            color: 
            border-top: 1px solid 
            padding-top: 10px;
        }
    </style>
</head>
<body>

    <div class="container">
        <table class="header-table">
            <tr>
                <td width="60%" valign="top">
                    <img src="{{ public_path('imagenes/logo.png') }}" class="logo"><br><br>

                    <div class="info-box">
                        <strong>CLIENTE:</strong><br>
                        {{ $recibo->cliente }}<br>
                        <small>{{ $recibo->email_cliente }}</small><br>
                        Casillero: <strong>{{ $recibo->casillero }}</strong>
                    </div>
                </td>

                <td width="40%" valign="top" class="details-box">
                    <h1>FACTURA</h1>
                    <br>
                    <strong>No. {{ str_pad($recibo->id, 6, '0', STR_PAD_LEFT) }}</strong><br>
                    Fecha: {{ \Carbon\Carbon::parse($recibo->fecha)->format('d/m/Y') }}<br>
                    Sucursal: {{ $recibo->sucursal }}<br>
                    Pago: {{ $recibo->metodo_pago }}
                </td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th width="50%">TIENDA</th>
                    <th width="25%" class="text-right">PRECIO</th>
                    <th width="25%" class="text-right">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        {{ $recibo->concepto ?: 'Sin tienda especificada' }}
                    </td>
                    <td class="text-right">B/. {{ number_format($recibo->monto, 2) }}</td>
                    <td class="text-right">B/. {{ number_format($recibo->monto, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <table class="totals-table">
            <tr>
                <td>Precio del artículo:</td>
                <td>B/. {{ number_format($subtotal, 2) }}</td>
            </tr>
            <tr>
                <td>Comisión:</td>
                <td>B/. {{ number_format($itbms, 2) }}</td>
            </tr>
            <tr>
                <td class="grand-total">TOTAL A PAGAR:</td>
                <td class="grand-total">B/. {{ number_format($recibo->monto, 2) }}</td>
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
