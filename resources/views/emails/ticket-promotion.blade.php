@php
  $eventName = $event->nombre ?? 'Evento';
  $productName = $product->name ?? 'Entrada';
  $promoDates = collect($promotions)
    ->map(function ($promo) {
      $start = !empty($promo['starts_at']) ? \Illuminate\Support\Carbon::parse($promo['starts_at'])->format('d/m/Y') : null;
      $end = !empty($promo['ends_at']) ? \Illuminate\Support\Carbon::parse($promo['ends_at'])->format('d/m/Y') : null;
      if ($start && $end) return "{$start} - {$end}";
      if ($start) return "Desde {$start}";
      if ($end) return "Hasta {$end}";
      return null;
    })
    ->filter()
    ->unique()
    ->values();
  $urgency = max(1, (int) floor((int) ($product->stock ?? 0) * 0.2));
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
        <h1 style="margin:0;font-size:20px;">¡No te lo pierdas! Preventa exclusiva para el Ópera</h1>
        <p style="margin:8px 0 0;font-size:13px;color:#e2e8f0;">
          Una oportunidad única de renovación espiritual a través del arte, la música y la presencia de Dios.
        </p>
      </div>

      <div style="padding:24px;">
        <div style="margin:0 0 16px;">
          <p style="margin:0;font-size:12px;color:#94a3b8;letter-spacing:2px;">PROMO ESPECIAL</p>
          <p style="margin:4px 0 0;font-size:15px;font-weight:bold;line-height:1.5;">
            Solo por
            <span style="color:#7b1b2b;font-weight:bold;">
              tiempo de promocion{{ $promoDates->count() ? ' (' . $promoDates->implode(', ') . ')' : '' }}
            </span>,
            consegui tus entradas para
            <span style="color:#0f172a;font-weight:bold;">{{ $eventName }}</span>
            con esta promo especial.
          </p>
          <p style="margin:8px 0 0;font-size:13px;color:#475569;">
            Solo <strong>{{ $urgency }}</strong> lugares disponibles con este beneficio.
          </p>
        </div>

        <div style="margin:0 0 14px;">
          <p style="margin:0;font-size:12px;color:#94a3b8;letter-spacing:2px;">ENTRADA</p>
          <p style="margin:4px 0 0;font-size:14px;font-weight:bold;">{{ $productName }}</p>
        </div>

        <div style="margin:0 0 18px;padding:16px;border:1px solid #e6e9ef;border-radius:14px;background:#f8fafc;">
          <p style="margin:0;font-size:11px;letter-spacing:2px;color:#94a3b8;">PROMOCIONES ACTIVAS</p>
          @foreach ($promotions as $promo)
            @php
              $rawLabel = strtolower($promo['label'] ?? '');
              if (str_contains($rawLabel, 'familia')) {
                $displayLabel = 'Pack Familiar: Iluminen juntos';
              } elseif (str_contains($rawLabel, 'joven')) {
                $displayLabel = 'Pack Jovenes: Energia en grupo';
              } elseif (str_contains($rawLabel, 'iglesia')) {
                $displayLabel = 'Pack Iglesias: Comunidad en unidad';
              } else {
                $displayLabel = $promo['label'] ?? ($promo['buy_qty'] . ' + ' . $promo['free_qty']);
              }
            @endphp
            <div style="margin:10px 0 0;padding:10px;border-radius:10px;background:#ffffff;border:1px solid #e6e9ef;">
              <p style="margin:0;font-size:13px;color:#0f172a;font-weight:bold;">
                {{ $displayLabel }}
              </p>
              <p style="margin:4px 0 0;font-size:12px;color:#64748b;">
                Invitamos nosotros: lleva {{ $promo['buy_qty'] ?? 0 }} y paga {{ max(0, ($promo['buy_qty'] ?? 0) - ($promo['free_qty'] ?? 0)) }}.
              </p>
            </div>
          @endforeach
          @if ($promoDates->count())
            <p style="margin:12px 0 0;font-size:12px;color:#64748b;">
              Vigencia de la promocion:
              @foreach ($promoDates as $date)
                <span style="font-weight:bold;color:#0f172a;">{{ $date }}</span>@if (!$loop->last), @endif
              @endforeach
            </p>
          @endif
        </div>

        <a href="{{ $ctaUrl }}"
          style="display:inline-block;background:#7b1b2b;color:#fff;text-decoration:none;padding:12px 18px;border-radius:10px;font-weight:bold;font-size:13px;">
          ¡Quiero mi entrada promo!
        </a>
      </div>

      <div style="padding:16px 24px;border-top:1px solid #e6e9ef;font-size:11px;color:#64748b;">
        La Barca Music · Gracias por ser parte.
      </div>
    </div>
  </body>
</html>
