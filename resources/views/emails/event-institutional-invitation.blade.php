@php
  $heroImage = null;
  if (!empty($event?->imagenUrl)) {
    $heroImage = str_starts_with($event->imagenUrl, 'http')
      ? $event->imagenUrl
      : rtrim(env('APP_URL', 'https://api.labarcaministerio.com'), '/') . $event->imagenUrl;
  }

  $fallbackArtists = [
    ['name' => 'Constanza Quiroga', 'image' => null],
    ['name' => 'Franco Maronati', 'image' => null],
    ['name' => 'Priscila Matiesco', 'image' => null],
    ['name' => 'Gabi Ledesma', 'image' => null],
    ['name' => 'Rafael Prancatelli', 'image' => null],
    ['name' => 'Luciano Scatena', 'image' => null],
  ];
  $artists = collect($lineup)->take(6)->values()->all();
  if (count($artists) < 6) {
    $artists = $fallbackArtists;
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
@endphp
<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Invitacion institucional</title>
</head>
<body style="margin:0;padding:0;background-color:#0f0f10;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
    Una noche de unidad y adoracion en el corazon de Buenos Aires. Su iglesia es parte de este hito.
  </div>

  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#0f0f10;">
    <tr>
      <td align="center" style="padding:20px 10px;">
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;background-color:#121212;border:1px solid #2a2a2a;border-radius:14px;overflow:hidden;">
          <tr>
            <td style="padding:0;">
              @if($heroImage)
                <img src="{{ $heroImage }}" alt="Ilumina Argentina en el Teatro Opera" width="600" height="300" style="display:block;width:100%;max-width:600px;height:auto;border:0;outline:none;text-decoration:none;">
              @else
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#1f1f1f;">
                  <tr>
                    <td align="center" style="padding:120px 20px;color:#f5f0e6;font-family:Helvetica,Arial,sans-serif;font-size:24px;font-weight:700;">
                      Ilumina Argentina
                    </td>
                  </tr>
                </table>
              @endif
            </td>
          </tr>

          <tr>
            <td style="padding:26px 26px 12px 26px;font-family:Helvetica,Arial,sans-serif;color:#f5f0e6;">
              <h1 style="margin:0;font-size:30px;line-height:1.2;font-weight:800;color:#f5f0e6;">
                Estamos a un paso de algo historico: Ilumina Argentina
              </h1>
            </td>
          </tr>

          <tr>
            <td style="padding:0 26px 20px 26px;font-family:Helvetica,Arial,sans-serif;color:#ddd6c7;">
              <p style="margin:0;font-size:16px;line-height:1.6;">
                Estimados lideres de <strong style="color:#ffffff;">{{ $churchName ?: 'nombre_iglesia' }}</strong>,
                hay momentos que marcan la historia de nuestra adoracion. El 27 de febrero, el Teatro Opera se viste de gala para recibir a Ilumina Argentina.
                Mas que un concierto, es una convocatoria para que el cuerpo de Cristo se una en un solo sonido de renovacion.
              </p>
            </td>
          </tr>

          <tr>
            <td style="padding:0 20px 16px 20px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                @for($row = 0; $row < 2; $row++)
                  <tr>
                    @for($col = 0; $col < 3; $col++)
                      @php $idx = ($row * 3) + $col; $artist = $artists[$idx] ?? null; @endphp
                      <td width="33.33%" align="center" valign="top" style="padding:10px 6px;">
                        @if(!empty($artist['image']))
                          <img src="{{ $artist['image'] }}" alt="{{ $artist['name'] }}" width="100" height="100" style="display:block;width:100px;height:100px;border-radius:50%;object-fit:cover;border:2px solid #d4af37;">
                        @else
                          <table role="presentation" width="100" height="100" cellspacing="0" cellpadding="0" border="0" style="width:100px;height:100px;border-radius:50%;border:2px solid #d4af37;background:#1c1c1c;">
                            <tr>
                              <td align="center" style="font-family:Helvetica,Arial,sans-serif;font-size:11px;color:#d4af37;font-weight:700;padding:6px;">
                                Artista
                              </td>
                            </tr>
                          </table>
                        @endif
                        <p style="margin:8px 0 0 0;font-family:Helvetica,Arial,sans-serif;font-size:12px;line-height:1.4;color:#f5f0e6;font-weight:700;">
                          {{ $artist['name'] ?? '' }}
                        </p>
                      </td>
                    @endfor
                  </tr>
                @endfor
              </table>
            </td>
          </tr>

          <tr>
            <td style="padding:0 26px 20px 26px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#191919;border:1px solid #3a2f1a;border-radius:14px;">
                <tr>
                  <td style="padding:18px 18px 8px 18px;font-family:Helvetica,Arial,sans-serif;">
                    <p style="margin:0;font-size:12px;line-height:1.4;letter-spacing:1.2px;text-transform:uppercase;color:#d4af37;font-weight:800;">
                      Pack Comunidad: 16 + 4
                    </p>
                  </td>
                </tr>
                <tr>
                  <td style="padding:0 18px 16px 18px;font-family:Helvetica,Arial,sans-serif;">
                    <p style="margin:0;font-size:14px;line-height:1.7;color:#e7e0d2;">
                      Entendemos que la bendicion es mayor cuando se vive en grupo.
                      Al reservar un bloque de <strong style="color:#ffffff;">16 entradas</strong> para su congregacion,
                      reciben <strong style="color:#ffffff;">4 entradas adicionales sin cargo</strong> para su equipo de liderazgo o servicio.
                      Las ubicaciones se asignan por orden de llegada.
                    </p>
                    @if($promoDates->count())
                      <p style="margin:10px 0 0 0;font-size:12px;line-height:1.5;color:#cbbfa5;">
                        Vigencia del beneficio: <strong>{{ $promoDates->implode(', ') }}</strong>
                      </p>
                    @endif
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td align="center" style="padding:0 26px 22px 26px;">
              <a href="{{ $ctaUrl }}" style="display:inline-block;background:#7a0f25;color:#ffffff;text-decoration:none;font-family:Helvetica,Arial,sans-serif;font-size:14px;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;padding:14px 22px;border-radius:10px;">
                Solicitar reserva institucional
              </a>
            </td>
          </tr>

          <tr>
            <td style="padding:0 26px 24px 26px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#102015;border:1px solid #1f4f30;border-radius:12px;">
                <tr>
                  <td style="padding:12px 12px 6px 12px;font-family:Helvetica,Arial,sans-serif;font-size:11px;color:#90d3a8;text-transform:uppercase;letter-spacing:1px;font-weight:700;">
                    Contacto por WhatsApp
                  </td>
                </tr>
                <tr>
                  <td style="padding:0 8px 12px 8px;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                      <tr>
                        <td style="padding:4px;">
                          <a href="https://wa.me/5491127768859" style="display:block;text-align:center;background:#22c55e;color:#ffffff;text-decoration:none;padding:10px 6px;border-radius:8px;font-family:Helvetica,Arial,sans-serif;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;">Leonel 11 2776-8859</a>
                        </td>
                        <td style="padding:4px;">
                          <a href="https://wa.me/5491130229504" style="display:block;text-align:center;background:#22c55e;color:#ffffff;text-decoration:none;padding:10px 6px;border-radius:8px;font-family:Helvetica,Arial,sans-serif;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;">Malu 11 3022-9504</a>
                        </td>
                        <td style="padding:4px;">
                          <a href="https://wa.me/5491160259671" style="display:block;text-align:center;background:#22c55e;color:#ffffff;text-decoration:none;padding:10px 6px;border-radius:8px;font-family:Helvetica,Arial,sans-serif;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;">Sergio 11 6025-9671</a>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
