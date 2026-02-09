<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light only">
    <title>Recibo de Compra</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f4f7fb;
            font-family: Arial, Helvetica, sans-serif;
            color: #1f2937;
        }
        .wrapper {
            width: 100%;
            background-color: #f4f7fb;
            padding: 24px 12px;
        }
        .container {
            max-width: 640px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e6edf7;
        }
        .header {
            background: #0b3a6b;
            color: #ffffff;
            padding: 20px 24px;
        }
        .header-title {
            font-size: 18px;
            margin: 0;
            font-weight: bold;
            letter-spacing: 0.2px;
        }
        .header-subtitle {
            margin: 6px 0 0 0;
            font-size: 12px;
            opacity: 0.9;
        }
        .content {
            padding: 22px 24px 8px 24px;
        }
        .greeting {
            font-size: 14px;
            margin: 0 0 8px 0;
        }
        .lead {
            font-size: 13px;
            margin: 0 0 16px 0;
            color: #4b5563;
            line-height: 1.5;
        }
        .card {
            border: 1px solid #e6edf7;
            border-radius: 10px;
            padding: 14px;
            margin: 12px 0 18px 0;
            background: #f8fafc;
        }
        .card-label {
            color: #6b7280;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .card-value {
            font-size: 13px;
            margin: 4px 0 10px 0;
            font-weight: bold;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .table th {
            background: #0b3a6b;
            color: #ffffff;
            font-size: 11px;
            text-align: left;
            padding: 10px;
        }
        .table td {
            padding: 10px;
            border-bottom: 1px solid #e6edf7;
            font-size: 12px;
        }
        .text-right {
            text-align: right;
        }
        .totals {
            width: 100%;
            margin-top: 12px;
        }
        .totals td {
            padding: 6px 0;
            font-size: 12px;
        }
        .total-amount {
            font-size: 14px;
            font-weight: bold;
            color: #0b3a6b;
        }
        .note {
            font-size: 11px;
            color: #6b7280;
            line-height: 1.5;
            margin-top: 12px;
        }
        .footer {
            padding: 16px 24px 22px 24px;
            border-top: 1px solid #e6edf7;
            font-size: 11px;
            color: #6b7280;
            text-align: center;
            line-height: 1.6;
        }
        .brand {
            font-weight: bold;
            color: #0b3a6b;
        }
        .logo {
            height: 28px;
        }
        @media (max-width: 480px) {
            .content, .header, .footer {
                padding-left: 16px;
                padding-right: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td valign="middle" align="left">
                            <img src="{{ $logoUrl }}" class="logo" alt="PGT Logistics">
                        </td>
                        <td valign="middle" align="right">
                            <div class="header-title">Recibo de Compra</div>
                            <div class="header-subtitle">No. {{ str_pad($recibo->id, 6, '0', STR_PAD_LEFT) }}</div>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="content">
                <p class="greeting">Hola {{ $recibo->cliente }},</p>
                <p class="lead">
                    Gracias por tu compra. Adjunto encontrarás tu recibo en PDF. A continuación te compartimos un
                    resumen de la transacción.
                </p>

                <div class="card">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td width="50%" valign="top">
                                <div class="card-label">Cliente</div>
                                <div class="card-value">{{ $recibo->cliente }}</div>
                                <div class="card-label">Email</div>
                                <div class="card-value">{{ $recibo->email_cliente }}</div>
                            </td>
                            <td width="50%" valign="top">
                                <div class="card-label">Casillero</div>
                                <div class="card-value">{{ $recibo->casillero }}</div>
                                <div class="card-label">Fecha</div>
                                <div class="card-value">{{ \Carbon\Carbon::parse($recibo->fecha)->format('d/m/Y') }}</div>
                                <div class="card-label">Método de pago</div>
                                <div class="card-value">{{ $recibo->metodo_pago }}</div>
                            </td>
                        </tr>
                    </table>
                </div>

                <table class="table" role="presentation" cellpadding="0" cellspacing="0">
                    <thead>
                        <tr>
                            <th width="55%">Detalle</th>
                            <th width="20%" class="text-right">Precio</th>
                            <th width="25%" class="text-right">Total</th>
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

                <table class="totals" role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td>Subtotal</td>
                        <td class="text-right">B/. {{ number_format($subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Comisión</td>
                        <td class="text-right">B/. {{ number_format($itbms, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="total-amount">Total pagado</td>
                        <td class="text-right total-amount">B/. {{ number_format($recibo->monto, 2) }}</td>
                    </tr>
                </table>

                <p class="note">
                    Si tienes alguna consulta, responde a este correo o contáctanos a través de nuestros canales de atención.
                </p>
            </div>

            <div class="footer">
                <div class="brand">PGT LOGISTICS GROUP S.A.</div>
                RUC: 155713237-2-2021 DV: 21<br>
                Tel: 399-4305 | cobros.panamagt@gmail.com
            </div>
        </div>
    </div>
</body>
</html>
