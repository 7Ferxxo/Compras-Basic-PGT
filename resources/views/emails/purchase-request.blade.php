<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <title>Solicitud de compra</title>
  </head>
  <body style="font-family: Arial, Helvetica, sans-serif; color:#0f172a; background-color:#f8fafc; padding:20px;">
    <div style="max-width:600px; margin:0 auto; background-color:#ffffff; border-radius:8px; padding:30px; box-shadow:0 2px 4px rgba(0,0,0,0.1);">
      <h2 style="margin:0 0 10px; color:#0ea5e9;">✅ Solicitud de compra recibida</h2>
      <p style="margin:0 0 20px; color:#64748b; font-size:14px;">
        Hemos recibido tu solicitud de compra correctamente. Adjunto encontrarás el comprobante en PDF.
      </p>

      <div style="background-color:#f1f5f9; border-radius:6px; padding:20px; margin-bottom:20px;">
        <table cellpadding="8" cellspacing="0" style="border-collapse:collapse; font-size:14px; width:100%;">
          <tr>
            <td style="font-weight:bold; color:#475569; width:40%;">Código</td>
            <td style="color:#0f172a;">{{ $request->code }}</td>
          </tr>
          <tr>
            <td style="font-weight:bold; color:#475569;">Estado</td>
            <td><span style="background-color:#fef3c7; color:#92400e; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:600;">CREADA</span></td>
          </tr>
          <tr>
            <td style="font-weight:bold; color:#475569;">Cliente</td>
            <td style="color:#0f172a;">{{ $request->client_name }}</td>
          </tr>
          <tr>
            <td style="font-weight:bold; color:#475569;">Casillero</td>
            <td style="color:#0f172a;">{{ $request->client_code }}</td>
          </tr>
          <tr>
            <td style="font-weight:bold; color:#475569;">Tienda</td>
            <td style="color:#0f172a;">{{ $storeName }}</td>
          </tr>
          <tr>
            <td style="font-weight:bold; color:#475569;">Monto</td>
            <td style="color:#0f172a; font-weight:600;">B/. {{ number_format((float) $request->quoted_total, 2, '.', '') }}</td>
          </tr>
        </table>
      </div>

      <div style="background-color:#ecfdf5; border-left:4px solid #10b981; padding:15px; margin-bottom:20px; border-radius:4px;">
        <p style="margin:0; font-size:13px; color:#065f46;">
          <strong>Próximos pasos:</strong><br>
          • Revisaremos tu solicitud<br>
          • Te notificaremos cuando esté lista para enviar al supervisor<br>
          • Podrás hacer seguimiento del estado en el panel de compras
        </p>
      </div>

      <hr style="border:none; border-top:1px solid #e2e8f0; margin:20px 0;">

      <p style="margin:0; font-size:12px; color:#94a3b8; text-align:center;">
        PGT Logistics - Sistema de Compras BASIC<br>
        Si tienes alguna pregunta, contáctanos.
      </p>
    </div>
  </body>
</html>
