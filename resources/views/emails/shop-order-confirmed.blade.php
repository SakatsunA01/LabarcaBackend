<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Compra confirmada</title>
    <style>
      body { margin: 0; padding: 0; background: #f3f4f6; font-family: Arial, sans-serif; color: #111827; }
      .wrapper { width: 100%; background: #f3f4f6; padding: 32px 16px; }
      .card { max-width: 640px; margin: 0 auto; background: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 16px 40px rgba(0,0,0,0.08); }
      .hero { background: #0b1120; color: #fff; padding: 24px; }
      .hero p { margin: 0; font-size: 11px; letter-spacing: 0.3em; text-transform: uppercase; color: #d1d5db; }
      .hero h1 { margin: 10px 0 0; font-size: 22px; }
      .content { padding: 24px; }
      .label { font-size: 11px; letter-spacing: 0.2em; text-transform: uppercase; color: #6b7280; margin-bottom: 6px; }
      .value { font-size: 16px; font-weight: 600; margin-bottom: 18px; color: #0f172a; }
      .list { background: #f8fafc; border: 1px solid #e2e8f0; padding: 16px; border-radius: 16px; }
      .item { display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 8px; }
      .item:last-child { margin-bottom: 0; }
      .footer { padding: 18px 24px; font-size: 12px; color: #6b7280; }
    </style>
  </head>
  <body>
    <div class="wrapper">
      <div class="card">
        <div class="hero">
          <p>Compra confirmada</p>
          <h1>Gracias por tu compra</h1>
        </div>
        <div class="content">
          <div class="label">Orden</div>
          <div class="value">#{{ $order->id }}</div>

          <div class="label">Entrega</div>
          <div class="value">
            {{ $order->delivery_method === 'shipping' ? 'Envio por kilometro' : 'Retiro a coordinar' }}
          </div>

          @if($order->delivery_method === 'shipping')
            <div class="label">Direccion</div>
            <div class="value">
              {{ collect([$order->shipping_address_line1, $order->shipping_city, $order->shipping_state, $order->shipping_postal_code])->filter()->implode(', ') }}
            </div>
          @endif

          <div class="label">Items</div>
          <div class="list">
            @foreach($items as $item)
              <div class="item">
                <span>{{ $item->name_snapshot }}</span>
                <span>x{{ $item->quantity }}</span>
              </div>
            @endforeach
          </div>

          <div class="label" style="margin-top: 18px;">Total</div>
          <div class="value">$ {{ number_format((float) $order->total_ars, 2, ',', '.') }}</div>
        </div>
        <div class="footer">
          Si necesitas coordinar el envio o retiro, responder este correo es suficiente. Gracias por apoyar el ministerio.
        </div>
      </div>
    </div>
  </body>
</html>
