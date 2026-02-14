@php
  $eventName = $event->nombre ?? 'Evento';
  $productName = $product->name ?? 'Entrada';
@endphp
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Promocion activa</title>
  </head>
  <body style="margin:0;background:#f5f6f8;padding:24px;font-family:Arial, sans-serif;color:#0b0b0b;">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e6e9ef;">
      <div style="background:#0f172a;color:#ffffff;padding:24px;">
        <h1 style="margin:0;font-size:20px;">Promocion activa</h1>
        <p style="margin:8px 0 0;font-size:13px;color:#e2e8f0;">
          Tenemos una promo disponible para tu proximo encuentro.
        </p>
      </div>

      <div style="padding:24px;">
        <div style="margin:0 0 14px;">
          <p style="margin:0;font-size:12px;color:#94a3b8;letter-spacing:2px;">EVENTO</p>
          <p style="margin:4px 0 0;font-size:16px;font-weight:bold;">{{ $eventName }}</p>
        </div>

        <div style="margin:0 0 14px;">
          <p style="margin:0;font-size:12px;color:#94a3b8;letter-spacing:2px;">ENTRADA</p>
          <p style="margin:4px 0 0;font-size:14px;font-weight:bold;">{{ $productName }}</p>
        </div>

        <div style="margin:0 0 18px;padding:14px;border:1px solid #e6e9ef;border-radius:12px;background:#f8fafc;">
          <p style="margin:0;font-size:11px;letter-spacing:2px;color:#94a3b8;">PROMOCIONES</p>
          @foreach ($promotions as $promo)
            <p style="margin:8px 0 0;font-size:13px;color:#0f172a;font-weight:bold;">
              {{ $promo['label'] ?? ($promo['buy_qty'] . ' + ' . $promo['free_qty']) }}
            </p>
          @endforeach
        </div>

        <a href="{{ $ctaUrl }}"
          style="display:inline-block;background:#7b1b2b;color:#fff;text-decoration:none;padding:12px 18px;border-radius:10px;font-weight:bold;font-size:13px;">
          Ver promocion y comprar
        </a>
      </div>

      <div style="padding:16px 24px;border-top:1px solid #e6e9ef;font-size:11px;color:#64748b;">
        La Barca Music · Gracias por ser parte.
      </div>
    </div>
  </body>
</html>
