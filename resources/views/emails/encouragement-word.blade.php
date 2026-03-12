<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tu Palabra de Animo</title>
</head>
<body style="margin:0;padding:0;background:#f7f6f3;color:#1f2937;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:24px 0;background:#f7f6f3;">
    <tr>
      <td align="center">
        <table role="presentation" width="640" cellspacing="0" cellpadding="0" style="max-width:640px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #ece7df;">
          <tr>
            <td style="padding:26px 30px;background:#0f172a;color:#f9fafb;">
              <p style="margin:0;font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:#d1d5db;">La Barca</p>
              <h1 style="margin:10px 0 0 0;font-size:26px;font-weight:700;line-height:1.2;">Tu Palabra de Animo</h1>
            </td>
          </tr>
          <tr>
            <td style="padding:26px 30px 10px 30px;">
              <p style="margin:0 0 12px 0;font-size:15px;line-height:1.6;">
                Hola {{ $recipientName }}, esta es la palabra que consultaste hoy.
              </p>
              @if(!empty($payload['moodText']))
                <p style="margin:0 0 16px 0;font-size:13px;color:#6b7280;">
                  Estado de animo: <strong style="color:#111827;">{{ $payload['moodText'] }}</strong>
                </p>
              @endif
            </td>
          </tr>
          <tr>
            <td style="padding:0 30px 6px 30px;">
              <p style="margin:0;font-size:13px;letter-spacing:.12em;text-transform:uppercase;color:#b45309;font-weight:700;">
                {{ $payload['verseCitation'] ?? '' }} @if(!empty($payload['verseCitation'])) - RVR1960 @endif
              </p>
              <blockquote style="margin:12px 0 0 0;padding:18px 18px;border-left:4px solid #f59e0b;background:#fffbeb;font-size:22px;line-height:1.35;color:#1f2937;font-style:italic;">
                "{{ $payload['verseText'] ?? '' }}"
              </blockquote>
            </td>
          </tr>
          <tr>
            <td style="padding:20px 30px 0 30px;">
              <p style="margin:0;font-size:16px;line-height:1.75;color:#374151;">
                {!! nl2br(e($payload['initialReflection'] ?? '')) !!}
              </p>
            </td>
          </tr>

          @if(!empty($payload['context']) && is_array($payload['context']))
            <tr>
              <td style="padding:22px 30px 0 30px;">
                <h2 style="margin:0 0 10px 0;font-size:16px;color:#111827;">Contexto historico</h2>
                @if(!empty($payload['context']['authorAndDate']))
                  <p style="margin:0 0 8px 0;font-size:14px;line-height:1.65;"><strong>Origen:</strong> {{ $payload['context']['authorAndDate'] }}</p>
                @endif
                @if(!empty($payload['context']['locationAndSociety']))
                  <p style="margin:0 0 8px 0;font-size:14px;line-height:1.65;"><strong>Sociedad:</strong> {{ $payload['context']['locationAndSociety'] }}</p>
                @endif
                @if(!empty($payload['context']['originalMeaning']))
                  <p style="margin:0 0 8px 0;font-size:14px;line-height:1.65;"><strong>Significado:</strong> {{ $payload['context']['originalMeaning'] }}</p>
                @endif
              </td>
            </tr>
          @endif

          @if(!empty($payload['prayer']))
            <tr>
              <td style="padding:22px 30px 0 30px;">
                <h2 style="margin:0 0 10px 0;font-size:16px;color:#111827;">Oracion sugerida</h2>
                <p style="margin:0;font-size:15px;line-height:1.75;color:#374151;">
                  {!! nl2br(e($payload['prayer'])) !!}
                </p>
              </td>
            </tr>
          @endif

          <tr>
            <td style="padding:24px 30px 28px 30px;">
              @if(!empty($payload['pageUrl']))
                <p style="margin:0 0 14px 0;font-size:13px;color:#6b7280;">
                  Puedes volver a consultarla cuando quieras:
                  <a href="{{ $payload['pageUrl'] }}" style="color:#0369a1;">{{ $payload['pageUrl'] }}</a>
                </p>
              @endif
              <p style="margin:0;font-size:12px;color:#9ca3af;">Este correo fue solicitado desde tu cuenta en La Barca.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>

