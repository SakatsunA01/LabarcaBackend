<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ShopShippingService
{
    public function quote(string $destinationAddress, ?string $fallbackDestination = null): array
    {
        $apiKey = config('services.openrouteservice.api_key');
        $originAddress = trim((string) config('services.shop.origin_address', 'Alsina 5651, Billinghurst, Buenos Aires, Argentina'));
        $ratePerKm = (float) config('services.shop.shipping_rate_per_km', 800);

        if (!$apiKey) {
            throw new RuntimeException('OpenRouteService no esta configurado para calcular envios.');
        }

        $originCoords = $this->geocodeAddressCandidates([$originAddress], $apiKey);
        $destinationCandidates = array_values(array_filter([$destinationAddress, $fallbackDestination]));
        $destinationCoords = $this->geocodeAddressCandidates($destinationCandidates, $apiKey);

        $response = Http::timeout(25)->get('https://api.openrouteservice.org/v2/directions/driving-car', [
            'api_key' => $apiKey,
            'start' => $originCoords['lon'] . ',' . $originCoords['lat'],
            'end' => $destinationCoords['lon'] . ',' . $destinationCoords['lat'],
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('No se pudo consultar OpenRouteService para calcular el envio.');
        }

        $payload = $response->json();
        $summary = $payload['features'][0]['properties']['summary'] ?? null;
        if (!is_array($summary)) {
            throw new RuntimeException('OpenRouteService no pudo resolver la distancia.');
        }

        $meters = (int) ($summary['distance'] ?? 0);
        if ($meters <= 0) {
            throw new RuntimeException('La distancia calculada no es valida.');
        }

        $distanceKm = round($meters / 1000, 2);
        $shippingCost = round($distanceKm * $ratePerKm, 2);

        return [
            'origin_address' => $originAddress,
            'destination_address' => $destinationAddress,
            'distance_meters' => $meters,
            'distance_km' => $distanceKm,
            'distance_text' => $this->formatDistance($distanceKm),
            'duration_text' => $this->formatDuration((int) ($summary['duration'] ?? 0)),
            'rate_per_km' => $ratePerKm,
            'shipping_cost_ars' => $shippingCost,
            'currency' => 'ARS',
        ];
    }

    private function geocodeAddressCandidates(array $addresses, string $apiKey): array
    {
        foreach ($addresses as $address) {
            $address = trim((string) $address);
            if ($address === '') {
                continue;
            }

            $queries = [
                [
                    'api_key' => $apiKey,
                    'text' => $address,
                    'size' => 1,
                    'boundary.country' => 'AR',
                ],
                [
                    'api_key' => $apiKey,
                    'text' => $address . ', Argentina',
                    'size' => 1,
                ],
            ];

            foreach ($queries as $query) {
                $response = Http::timeout(25)->get('https://api.openrouteservice.org/geocode/search', $query);
                if (!$response->successful()) {
                    continue;
                }

                $payload = $response->json();
                $feature = $payload['features'][0] ?? null;
                $coords = $feature['geometry']['coordinates'] ?? null;
                if (is_array($coords) && count($coords) >= 2) {
                    return [
                        'lon' => $coords[0],
                        'lat' => $coords[1],
                    ];
                }
            }
        }

        throw new RuntimeException('No se pudo geocodificar la direccion para calcular el envio.');
    }

    private function formatDistance(float $distanceKm): string
    {
        if ($distanceKm < 1) {
            return (int) round($distanceKm * 1000) . ' m';
        }

        return number_format($distanceKm, 1, ',', '.') . ' km';
    }

    private function formatDuration(int $durationSeconds): ?string
    {
        if ($durationSeconds <= 0) {
            return null;
        }

        $minutes = (int) round($durationSeconds / 60);
        if ($minutes < 60) {
            return $minutes . ' min';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;
        if ($remainingMinutes === 0) {
            return $hours . ' h';
        }

        return $hours . ' h ' . $remainingMinutes . ' min';
    }
}
