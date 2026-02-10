<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light only">
    <title>Solicitud de compra</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f4f7fb;
            font-family: "Segoe UI", Arial, sans-serif;
            color: #0f172a;
        }
        .wrapper {
            width: 100%;
            background-color: #f4f7fb;
            padding: 24px 12px 32px;
        }
        .container {
            max-width: 640px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid #e6edf7;
        }
        .header {
            background: linear-gradient(135deg, #0b3a6b, #0b4aa2);
            color: #ffffff;
            padding: 18px 22px;
        }
        .logo {
            height: 32px;
            display: block;
        }
        .logo-wrap {
            display: inline-block;
            background: #ffffff;
            border-radius: 8px;
            padding: 6px 8px;
            line-height: 0;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
        }
        .title {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.2px;
        }
        .subtitle {
            margin: 6px 0 0;
            font-size: 12px;
            opacity: 0.9;
        }
        .content {
            padding: 22px 24px 6px;
        }
        .lead {
            margin: 0 0 18px;
            font-size: 14px;
            color: #4b5563;
            line-height: 1.5;
        }
        .card {
            background: #f8fafc;
            border: 1px solid #e6edf7;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 18px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table td {
            padding: 8px 0;
            font-size: 13px;
        }
        .label {
            font-weight: 600;
            color: #475569;
            width: 40%;
        }
        .value {
            color: #0f172a;
            font-weight: 600;
        }
        .footer {
            padding: 14px 24px 20px;
            border-top: 1px solid #e6edf7;
            font-size: 11px;
            color: #6b7280;
            text-align: center;
            line-height: 1.6;
        }
        .brand {
            font-weight: 700;
            color: #0b3a6b;
        }
        @media (max-width: 480px) {
            .content, .header, .footer { padding-left: 16px; padding-right: 16px; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="left" valign="middle">
                            <span class="logo-wrap">
                                <img src="{{ $logoUrl ?? 'https://www.pgtlogistics.com/assetsAuth/img/logoNew.png' }}" class="logo" alt="PGT Logistics">
                            </span>
                        </td>
                        <td align="right" valign="middle">
                            <div class="title">Solicitud de compra recibida</div>
                            <div class="subtitle">Nueva solicitud registrada</div>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="content">
                <p class="lead">Tu solicitud fue registrada con exito. Puedes consultar los detalles a continuacion.</p>

                <div class="card">
                    <table class="table" role="presentation" cellpadding="0" cellspacing="0">
                        <tr>
                            <td class="label">Codigo</td>
                            <td class="value">{{ $request->code }}</td>
                        </tr>
                        <tr>
                            <td class="label">Mensaje</td>
                            <td class="value">Muchas gracias por su compra</td>
                        </tr>
                        <tr>
                            <td class="label">Cliente</td>
                            <td class="value">{{ $request->client_name }}</td>
                        </tr>
                        <tr>
                            <td class="label">Casillero</td>
                            <td class="value">{{ $request->client_code }}</td>
                        </tr>
                        <tr>
                            <td class="label">Tienda</td>
                            <td class="value">{{ $storeName }}</td>
                        </tr>
                        <tr>
                            <td class="label">Monto</td>
                            <td class="value">B/. {{ number_format((float) $request->quoted_total, 2, '.', '') }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="footer">
                <div class="brand">PGT Logistics - Sistema de Compras BASIC</div>
                Si tienes alguna pregunta, contactanos.
            </div>
        </div>
    </div>
</body>
</html>
