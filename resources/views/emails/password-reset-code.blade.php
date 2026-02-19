<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Recuperar contrasena</title>
  </head>
  <body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f3f4f6;">
      <tr>
        <td align="center" style="padding:24px 12px;">
          <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
            <tr>
              <td style="background:#0f172a;padding:20px;color:#fff;">
                <h1 style="margin:0;font-size:22px;">Cambio de contrasena</h1>
                <p style="margin:8px 0 0;font-size:13px;color:#cbd5e1;">Usa este codigo para validar tu identidad.</p>
              </td>
            </tr>
            <tr>
              <td style="padding:22px;">
                <p style="margin:0 0 12px;font-size:14px;color:#374151;">
                  Hola {{ $user->name ?? 'usuario' }}, recibimos una solicitud para cambiar tu contrasena.
                </p>
                <p style="margin:0 0 10px;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#9ca3af;">Codigo de verificacion</p>
                <p style="margin:0 0 18px;font-size:36px;font-weight:800;letter-spacing:6px;color:#7a2233;">
                  {{ $code }}
                </p>
                <p style="margin:0 0 18px;font-size:13px;color:#6b7280;">
                  Este codigo vence en {{ $ttlMinutes }} minutos.
                </p>
                <a href="{{ $resetUrl }}"
                  style="display:inline-block;background:#7a2233;color:#fff;text-decoration:none;padding:10px 16px;border-radius:999px;font-size:12px;font-weight:700;letter-spacing:0.15em;text-transform:uppercase;">
                  Ir a recuperar contrasena
                </a>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
