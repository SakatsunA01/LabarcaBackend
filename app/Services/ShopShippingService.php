<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ShopShippingService
{
    public function quote(string $destinationAddress): array
    {
        $apiKey = config('services.google_maps.api_key');
        $originAddress = trim((string) config('services.shop.origin_address', 'Alsina 5651, Billinghurst, Buenos Aires, Argentina'));
        $ratePerKm = (float) config('services.shop.shipping_rate_per_km', 800);

        if (!$apiKey) {
            throw new RuntimeException('Google Maps no esta configurado para calcular envios.');
        }

        $response = Http::timeout(25)->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
            'origins' => $originAddress,
            'destinations' => $destinationAddress,
            'units' => 'metric',
            'mode' => 'driving',
            'language' => 'es-419',
            'key' => $apiKey,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('No se pudo consultar Google Maps para calcular el envio.');
        }

        $payload = $response->json();
        if (($payload['status'] ?? null) !== 'OK') {
            throw new RuntimeException($payload['error_message'] ?? 'Google Maps no pudo resolver la distancia.');
        }

        $element = $payload['rows'][0]['elements'][0] ?? null;
        if (!is_array($element) || ($element['status'] ?? null) !== 'OK') {
            throw new RuntimeException('No se pudo calcular el envio para la direccion indicada.');
        }

        $meters = (int) ($element['distance']['value'] ?? 0);
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
            'distance_text' => $element['distance']['text'] ?? null,
            'duration_text' => $element['duration']['text'] ?? null,
            'rate_per_km' => $ratePerKm,
            'shipping_cost_ars' => $shippingCost,
            'currency' => 'ARS',
        ];
    }
}
