<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Entrada digital</title>
    <style>
      body { margin: 0; padding: 0; background: #f3f4f6; font-family: Arial, sans-serif; color: #111827; }
      .wrapper { width: 100%; background: #f3f4f6; padding: 32px 16px; }
      .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 12px 30px rgba(0,0,0,0.08); }
      .header { background: #0f172a; padding: 24px; color: #fff; }
      .header h1 { margin: 0; font-size: 22px; }
      .content { padding: 24px; }
      .label { font-size: 12px; letter-spacing: 0.12em; text-transform: uppercase; color: #6b7280; margin-bottom: 4px; }
      .value { font-size: 16px; font-weight: 600; margin-bottom: 16px; }
      .ticket { background: #f9fafb; border: 1px solid #e5e7eb; padding: 16px; border-radius: 12px; }
      .qr { margin-top: 16px; text-align: center; }
      .footer { padding: 18px 24px; font-size: 12px; color: #6b7280; }
      .badge { display: inline-block; background: #d1fae5; color: #065f46; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
    </style>
  </head>
  <body>
    <div class="wrapper">
      <div class="card">
        <div class="header">
          <h1>Tu entrada digital está lista</h1>
          <p style="margin: 8px 0 0; color: #e2e8f0;">Gracias por tu compra en La Barca Music.</p>
        </div>
        <div class="content">
          <div class="label">Evento</div>
          <div class="value">{{ $event?->nombre ?? 'Evento' }}</div>

          <div class="label">Fecha</div>
          <div class="value">{{ $event?->fecha ?? 'Por confirmar' }}</div>

          <div class="label">Entrada</div>
          <div class="value">{{ $product?->name ?? 'Entrada' }}</div>

          <div class="label">Cantidad</div>
          <div class="value">{{ $order->quantity }}</div>

          <div class="ticket">
            <div class="label">Codigo de validacion</div>
            <div class="value">{{ $order->mp_payment_id ?? $order->id }}</div>
            <span class="badge">Presentalo junto al QR</span>
          </div>

          @if($order->verification_token)
            <div class="qr">
              <p style="margin: 12px 0 0; font-size: 12px; color: #6b7280;">
                Podés ver el QR de la entrada en tu perfil dentro del sitio.
              </p>
            </div>
          @endif
        </div>
        <div class="footer">
          Si tenés dudas, respondé este correo o contactanos por WhatsApp desde la web.
        </div>
      </div>
    </div>
  </body>
</html>
