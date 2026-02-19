<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Entrada digital</title>
    <style>
      body { margin: 0; padding: 0; background: #f3f4f6; font-family: Arial, sans-serif; color: #111827; }
      .wrapper { width: 100%; background: #f3f4f6; padding: 32px 16px; }
      .card { max-width: 640px; margin: 0 auto; background: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 16px 40px rgba(0,0,0,0.08); }
      .hero { background: #0b1120; color: #fff; }
      .hero img { width: 100%; height: 180px; object-fit: cover; display: block; }
      .hero-content { padding: 24px; }
      .hero h1 { margin: 0; font-size: 22px; }
      .hero p { margin: 8px 0 0; color: #cbd5f5; font-size: 13px; }
      .content { padding: 24px; }
      .label { font-size: 11px; letter-spacing: 0.2em; text-transform: uppercase; color: #6b7280; margin-bottom: 6px; }
      .value { font-size: 16px; font-weight: 600; margin-bottom: 18px; color: #0f172a; }
      .ticket { background: #f8fafc; border: 1px solid #e2e8f0; padding: 16px; border-radius: 16px; }
      .badge { display: inline-block; background: #dcfce7; color: #166534; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
      .cta { display: inline-block; margin-top: 16px; background: #7a2233; color: #fff !important; text-decoration: none; padding: 10px 16px; border-radius: 999px; font-size: 12px; font-weight: 700; letter-spacing: 0.2em; text-transform: uppercase; }
      .footer { padding: 18px 24px; font-size: 12px; color: #6b7280; }
    </style>
  </head>
  <body>
    <div class="wrapper">
      <div class="card">
        <div class="hero">
          @if($event?->imagenUrl)
            <img src="{{ url($event->imagenUrl) }}" alt="Evento" />
          @endif
          <div class="hero-content">
            <p style="font-size: 11px; letter-spacing: 0.3em; text-transform: uppercase; color: #d1d5db; margin: 0 0 6px;">
              Entrada digital aprobada
            </p>
            <h1>Tu entrada digital está lista</h1>
            <p>Gracias por tu compra en La Barca Music.</p>
          </div>
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
            <div style="margin-top: 12px;">
              <a class="cta" href="https://labarcaministerio.com/perfil">Ver mi entrada</a>
            </div>
          </div>

          @if($order->generated_login_password && $order->user?->email)
            <div class="ticket" style="margin-top:12px;">
              <div class="label">Acceso a tu cuenta</div>
              <div class="value" style="margin-bottom: 8px;">Email: {{ $order->user->email }}</div>
              <div class="value" style="margin-bottom: 0;">Clave temporal: {{ $order->generated_login_password }}</div>
              <p style="margin: 10px 0 0; font-size: 12px; color: #6b7280;">
                Inicia sesion y cambia tu clave desde tu perfil. Si no recuerdas la clave, usa
                <a href="https://labarcaministerio.com/recuperar-password" style="color:#7a2233;">recuperar contrasena</a>.
              </p>
            </div>
          @endif

          @if($order->verification_token)
            <div class="qr">
              <p style="margin: 12px 0 0; font-size: 12px; color: #6b7280;">
                Podés ver el QR de la entrada en tu perfil dentro del sitio.
              </p>
            </div>
          @endif
        </div>
        <div class="footer">
          Si tenes dudas, responde este correo o contactanos por WhatsApp desde la web.
        </div>
      </div>
    </div>
  </body>
</html>
