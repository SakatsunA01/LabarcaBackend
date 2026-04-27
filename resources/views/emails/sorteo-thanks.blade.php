<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gracias por participar</title>
</head>
<body style="margin:0;padding:0;background:#f5efe6;font-family:Georgia,serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5efe6;padding:40px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;max-width:600px;">
        <!-- Header -->
        <tr>
          <td style="background:#1a1a1a;padding:32px 40px;text-align:center;">
            <p style="margin:0;color:#c9a96e;font-size:11px;letter-spacing:0.4em;text-transform:uppercase;font-family:Arial,sans-serif;">Ministerio La Barca</p>
            <h1 style="margin:8px 0 0;color:#ffffff;font-size:24px;font-style:italic;">Gracias por participar</h1>
          </td>
        </tr>
        <!-- Bible quote -->
        <tr>
          <td style="background:#faf6f0;padding:24px 40px;text-align:center;border-bottom:1px solid #e8ddd0;">
            <p style="margin:0;font-size:15px;color:#5a4a3a;font-style:italic;">"Jehová te bendiga, y te guarde."</p>
            <p style="margin:6px 0 0;font-size:11px;color:#9a8a7a;font-family:Arial,sans-serif;letter-spacing:0.15em;">Números 6:24 · Reina-Valera 1960</p>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style="padding:36px 40px;">
            @if($user)
            <p style="margin:0 0 16px;font-size:15px;color:#2a2a2a;">Querido/a <strong>{{ $user->name }}</strong>,</p>
            @endif
            <p style="margin:0 0 20px;font-size:15px;color:#3a3a3a;line-height:1.7;">
              Gracias por tu confianza y por acompañarnos. Es un privilegio tenerte como parte de esta familia. Tu participación en <strong>{{ $sorteo->nombre }}</strong> nos llena de gratitud.
            </p>
            @if($sorteo->premio_imagen_url)
            <div style="text-align:center;margin:24px 0;">
              <img src="{{ (str_starts_with($sorteo->premio_imagen_url, 'http') ? '' : config('app.url')) . $sorteo->premio_imagen_url }}" alt="{{ $sorteo->nombre }}" style="max-width:280px;border-radius:10px;border:1px solid #e8ddd0;">
            </div>
            @endif
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#faf6f0;border-radius:8px;margin:20px 0;overflow:hidden;">
              <tr><td style="padding:20px 24px;">
                <p style="margin:0 0 6px;font-size:10px;text-transform:uppercase;letter-spacing:0.3em;color:#9a8a7a;font-family:Arial,sans-serif;">Premio en juego</p>
                <p style="margin:0;font-size:18px;color:#1a1a1a;font-style:italic;">{{ $sorteo->premio }}</p>
              </td></tr>
            </table>
            @if(!empty($sorteo->requisitos))
            <p style="margin:20px 0 10px;font-size:11px;text-transform:uppercase;letter-spacing:0.3em;color:#9a8a7a;font-family:Arial,sans-serif;">Requisitos de participación</p>
            <ul style="margin:0;padding:0 0 0 20px;color:#3a3a3a;font-size:14px;line-height:2;">
              @foreach($sorteo->requisitos as $req)
                @php
                  $type = $req['type'] ?? '';
                  $data = $req['data'] ?? [];
                  if ($type === 'registrado') $label = 'Registrado en la web';
                  elseif ($type === 'custom_text') $label = $data['text'] ?? 'Requisito';
                  elseif ($type === 'ticket_purchase') $label = 'Compra de entrada';
                  elseif ($type === 'registration_schedule') $label = 'Registro en horario';
                  elseif ($type === 'open_link') $label = $data['title'] ?? 'Seguir en redes';
                  else $label = $type;
                @endphp
                <li>{{ $label }}</li>
              @endforeach
            </ul>
            @endif
            <p style="margin:28px 0 0;font-size:15px;color:#3a3a3a;line-height:1.7;">
              Que Dios multiplique su gracia en tu vida. Seguimos creyendo y adorando juntos.
            </p>
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style="background:#1a1a1a;padding:24px 40px;text-align:center;">
            <p style="margin:0;color:#c9a96e;font-size:13px;font-style:italic;">Ministerio La Barca</p>
            <p style="margin:6px 0 0;color:#666;font-size:11px;font-family:Arial,sans-serif;letter-spacing:0.1em;">labarcaministerio.com</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
