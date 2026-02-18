@php
  $heroImage = null;
  if (!empty($event?->imagenUrl)) {
    $heroImage = str_starts_with($event->imagenUrl, 'http')
      ? $event->imagenUrl
      : rtrim(env('APP_URL', 'https://api.labarcaministerio.com'), '/') . $event->imagenUrl;
  }

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

  $churchPromo = collect($promotions)->first(function ($promo) {
    $label = strtolower((string) ($promo['label'] ?? ''));
    return str_contains($label, 'iglesia');
  });
  if (!$churchPromo) {
    $churchPromo = collect($promotions)->sortByDesc('buy_qty')->first();
  }

  $churchBuy = (int) ($churchPromo['buy_qty'] ?? 16);
  $churchFree = (int) ($churchPromo['free_qty'] ?? 4);
  $churchTotal = $churchBuy + $churchFree;
@endphp
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Ilumina Argentina en el Opera</title>
  </head>
  <body style="margin:0;background:#f5f6f8;padding:24px;font-family:Arial, sans-serif;color:#0f172a;">
    <div style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:20px;overflow:hidden;border:1px solid #e2e8f0;">
      <div style="background:#0f172a;color:#ffffff;">
        @if($heroImage)
          <img src="{{ $heroImage }}" alt="Ilumina Argentina" style="width:100%;height:220px;object-fit:cover;display:block;" />
        @endif
        <div style="padding:24px;">
          <h1 style="margin:0;font-size:28px;line-height:1.2;">
            Estamos a un paso de algo historico: Ilumina Argentina
          </h1>
          <p style="margin:10px 0 0;font-size:14px;color:#cbd5e1;">
            Una experiencia de fe, musica y unidad que renueva corazones en el emblematico Teatro Opera.
          </p>
        </div>
      </div>

      <div style="padding:24px;">
        <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#334155;">
          Estimados lideres de <strong>{{ $churchName ?: 'su congregacion' }}</strong>@if($pastorRole), {{ $pastorRole }}@endif:
          desde La Barca Music queremos invitarlos a una noche de renovacion espiritual y excelencia artistica el
          <strong>{{ !empty($event?->fecha) ? \Illuminate\Support\Carbon::parse($event->fecha)->format('d/m/Y H:i') : '27/02 19:00' }} hs</strong>.
        </p>

        <div style="display:block;margin:0 0 16px;">
          <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:14px;margin-bottom:8px;">
            <p style="margin:0;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#94a3b8;">Experiencia</p>
            <p style="margin:6px 0 0;font-size:13px;color:#0f172a;">Mucho mas que un concierto... una atmosfera de adoracion.</p>
          </div>
          <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:14px;margin-bottom:8px;">
            <p style="margin:0;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#94a3b8;">Autoridad</p>
            <p style="margin:6px 0 0;font-size:13px;color:#0f172a;">Lideres y referentes de Argentina y el mundo.</p>
          </div>
          <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:14px;">
            <p style="margin:0;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#94a3b8;">Mensaje</p>
            <p style="margin:6px 0 0;font-size:13px;color:#0f172a;">Palabras de fe que inspiran a cada persona.</p>
          </div>
        </div>

        @if(!empty($lineup))
          <div style="margin:0 0 16px;">
            <p style="margin:0 0 10px;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#94a3b8;">Lineup invitado</p>
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
              <tr>
                @foreach(array_slice($lineup, 0, 6) as $artist)
                  <td style="text-align:center;padding:6px;">
                    @if(!empty($artist['image']))
                      <img src="{{ $artist['image'] }}" alt="{{ $artist['name'] }}" style="width:72px;height:72px;border-radius:50%;object-fit:cover;display:block;margin:0 auto 6px;" />
                    @endif
                    <p style="margin:0;font-size:11px;color:#334155;line-height:1.3;">{{ $artist['name'] }}</p>
                  </td>
                  @if(($loop->iteration % 3) === 0 && !$loop->last)
                    </tr><tr>
                  @endif
                @endforeach
              </tr>
            </table>
          </div>
        @endif

        <div style="background:#ecfeff;border:1px solid #99f6e4;border-radius:16px;padding:16px;margin:0 0 18px;">
          <p style="margin:0;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#0f766e;">Pack Iglesias: Comunidad en unidad</p>
          <p style="margin:8px 0 0;font-size:14px;line-height:1.6;color:#0f172a;">
            Al reservar en bloque para su congregacion o grupo de jovenes:
            <strong>comprando {{ $churchBuy }} entradas, reciben {{ $churchFree }} de regalo</strong>
            (total {{ $churchTotal }} lugares), con posibilidad de ubicaciones preferenciales para mantenerse unidos.
          </p>
          @if($promoDates->count())
            <p style="margin:10px 0 0;font-size:12px;color:#334155;">
              Cupos limitados. Vigencia: <strong>{{ $promoDates->implode(', ') }}</strong>
            </p>
          @endif
        </div>

        <div style="text-align:center;">
          <a href="{{ $ctaUrl }}" style="display:inline-block;background:#7a0f25;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:12px;font-size:13px;font-weight:bold;letter-spacing:0.08em;text-transform:uppercase;">
            Solicitar reserva para grupos
          </a>
        </div>

        <div style="margin:18px 0 0;padding:14px;border:1px solid #d1fae5;background:#ecfdf5;border-radius:14px;">
          <p style="margin:0 0 10px;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#047857;font-weight:bold;">
            Soporte por WhatsApp
          </p>
          <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
            <tr>
              <td style="padding:4px;">
                <a href="https://wa.me/5491127768859" style="display:block;text-align:center;background:#22c55e;color:#ffffff;text-decoration:none;padding:10px 8px;border-radius:10px;font-size:11px;font-weight:bold;letter-spacing:.06em;text-transform:uppercase;">
                  Leonel
                </a>
              </td>
              <td style="padding:4px;">
                <a href="https://wa.me/5491130229504" style="display:block;text-align:center;background:#22c55e;color:#ffffff;text-decoration:none;padding:10px 8px;border-radius:10px;font-size:11px;font-weight:bold;letter-spacing:.06em;text-transform:uppercase;">
                  Malu
                </a>
              </td>
              <td style="padding:4px;">
                <a href="https://wa.me/5491160259671" style="display:block;text-align:center;background:#22c55e;color:#ffffff;text-decoration:none;padding:10px 8px;border-radius:10px;font-size:11px;font-weight:bold;letter-spacing:.06em;text-transform:uppercase;">
                  Sergio
                </a>
              </td>
            </tr>
          </table>
        </div>
      </div>
    </div>
  </body>
</html>
