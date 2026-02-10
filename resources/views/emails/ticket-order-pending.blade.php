@php
  $eventName = $order->event->nombre ?? 'Evento';
  $ticketName = $order->product->name ?? 'Entrada';
  $quantity = $order->quantity ?? 1;
@endphp
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Compra pendiente</title>
  </head>
  <body style="margin:0;background:#f5f6f8;padding:24px;font-family:Arial, sans-serif;color:#0b0b0b;">
    <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e6e9ef;">
      <div style="background:#0f172a;color:#ffffff;padding:24px;">
        <h1 style="margin:0;font-size:20px;">Tu compra esta pendiente</h1>
        <p style="margin:8px 0 0;font-size:13px;color:#e2e8f0;">
          Tenemos registrada tu orden, pero el pago aun no fue confirmado.
        </p>
      </div>

      <div style="padding:24px;">
        <p style="margin:0 0 12px;font-size:14px;color:#334155;">
          Podes completar el pago desde el siguiente boton:
        </p>

        <a href="{{ $pendingUrl }}"
          style="display:inline-block;background:#7b1b2b;color:#fff;text-decoration:none;padding:12px 18px;border-radius:10px;font-weight:bold;font-size:13px;">
          Completar pago
        </a>

        <div style="margin:20px 0;padding:14px;border:1px solid #e6e9ef;border-radius:12px;background:#f8fafc;">
          <p style="margin:0;font-size:11px;letter-spacing:2px;color:#94a3b8;">DETALLE</p>
          <p style="margin:6px 0 0;font-size:14px;font-weight:bold;">{{ $eventName }}</p>
          <p style="margin:6px 0 0;font-size:13px;color:#475569;">Entrada: {{ $ticketName }}</p>
          <p style="margin:6px 0 0;font-size:13px;color:#475569;">Cantidad: {{ $quantity }}</p>
        </div>

        <p style="margin:16px 0 8px;font-size:13px;color:#475569;">
          Si necesitas ayuda, podes escribirnos por WhatsApp:
        </p>
        <ul style="margin:0;padding-left:18px;font-size:13px;color:#0f172a;">
          <li><a href="https://wa.me/5491127768859" style="color:#0f172a;text-decoration:underline;">Leonel · 11 2776 8859</a></li>
          <li><a href="https://wa.me/5491130229504" style="color:#0f172a;text-decoration:underline;">Malu · 11 3022 9504</a></li>
          <li><a href="https://wa.me/5491160259671" style="color:#0f172a;text-decoration:underline;">Sergio · 11 6025 9671</a></li>
        </ul>
      </div>

      <div style="padding:16px 24px;border-top:1px solid #e6e9ef;font-size:11px;color:#64748b;">
        La Barca Music · Si ya pagaste, ignora este mensaje.
      </div>
    </div>
  </body>
</html>
