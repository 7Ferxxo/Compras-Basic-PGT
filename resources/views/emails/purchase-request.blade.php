<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <title>Confirmaci&oacute;n de solicitud de compra</title>
  </head>
  <body style="margin:0; padding:24px; background-color:#f8fafc; font-family:Arial, Helvetica, sans-serif; color:#0f172a;">
    <div style="max-width:620px; margin:0 auto; background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden;">
      <div style="background:#0b3b66; color:#ffffff; padding:18px 24px;">
        <h1 style="margin:0; font-size:20px; font-weight:700;">Solicitud recibida</h1>
      </div>

      <div style="padding:24px;">
        <p style="margin:0 0 12px; font-size:15px; line-height:1.6;">
          Hola <strong>{{ $request->client_name }}</strong>,
        </p>

        <p style="margin:0 0 16px; font-size:15px; line-height:1.6; color:#334155;">
          Adjuntamos el comprobante de tu solicitud de compra <strong>{{ $request->code }}</strong> en formato PDF.
          Gracias por preferirnos.
        </p>

        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; margin:0 0 18px;">
          <table cellpadding="6" cellspacing="0" style="width:100%; border-collapse:collapse; font-size:14px;">
            <tr>
              <td style="width:42%; color:#475569; font-weight:700;">C&oacute;digo</td>
              <td style="color:#0f172a;">{{ $request->code }}</td>
            </tr>
            <tr>
              <td style="color:#475569; font-weight:700;">Estado</td>
              <td>
                <span style="display:inline-block; padding:4px 8px; border-radius:999px; background:#fef3c7; color:#92400e; font-size:12px; font-weight:700;">CREADA</span>
              </td>
            </tr>
            <tr>
              <td style="color:#475569; font-weight:700;">Casillero</td>
              <td style="color:#0f172a;">{{ $request->client_code }}</td>
            </tr>
            <tr>
              <td style="color:#475569; font-weight:700;">Tienda</td>
              <td style="color:#0f172a;">{{ $storeName }}</td>
            </tr>
            <tr>
              <td style="color:#475569; font-weight:700;">Monto</td>
              <td style="color:#0f172a; font-weight:700;">B/. {{ number_format((float) $request->quoted_total, 2, '.', '') }}</td>
            </tr>
          </table>
        </div>

        <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:14px 16px; margin:0 0 18px;">
          <p style="margin:0 0 8px; color:#1e3a8a; font-size:13px; font-weight:700;">Pr&oacute;ximos pasos</p>
          <p style="margin:0; color:#1e40af; font-size:13px; line-height:1.7;">
            Revisaremos tu solicitud y te notificaremos cuando avance a la siguiente etapa.
            Tambi&eacute;n podr&aacute;s darle seguimiento desde tu panel de compras.
          </p>
        </div>

        <p style="margin:0; color:#64748b; font-size:13px; line-height:1.7;">
          Si tienes alguna consulta, estamos para ayudarte.
        </p>
      </div>

      <div style="border-top:1px solid #e2e8f0; padding:14px 24px; background:#f8fafc; text-align:center; color:#64748b; font-size:12px;">
        PGT Logistics | Sistema de Compras BASIC
      </div>
    </div>
  </body>
</html>
