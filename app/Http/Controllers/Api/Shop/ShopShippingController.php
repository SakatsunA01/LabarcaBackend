<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Services\ShopShippingService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ShopShippingController extends Controller
{
    public function quote(Request $request, ShopShippingService $shippingService)
    {
        $validated = $request->validate([
            'delivery_method' => ['required', 'in:shipping,pickup,envio,retiro_a_coordinar'],
            'address_line1' => ['required_if:delivery_method,shipping,envio', 'nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required_if:delivery_method,shipping,envio', 'nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:100'],
            'full_address' => ['nullable', 'string', 'max:500'],
        ]);

        if (in_array($validated['delivery_method'], ['pickup', 'retiro_a_coordinar'], true)) {
            return response()->json([
                'delivery_method' => 'pickup',
                'shipping_cost_ars' => 0,
                'distance_km' => 0,
                'rate_per_km' => (float) config('services.shop.shipping_rate_per_km', 800),
                'origin_address' => config('services.shop.origin_address', 'Alsina 5651, Billinghurst, Buenos Aires, Argentina'),
                'destination_address' => null,
                'currency' => 'ARS',
                'pickup_note' => 'Retiro a coordinar',
            ]);
        }

        $destination = trim((string) ($validated['full_address'] ?? $this->buildAddress($validated)));
        if ($destination === '') {
            return response()->json(['message' => 'La direccion es obligatoria para calcular el envio.'], 422);
        }

        try {
            return response()->json([
                'delivery_method' => 'shipping',
                ...$shippingService->quote($destination),
            ]);
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    private function buildAddress(array $data): string
    {
        return collect([
            Arr::get($data, 'address_line1'),
            Arr::get($data, 'address_line2'),
            Arr::get($data, 'city'),
            Arr::get($data, 'state'),
            Arr::get($data, 'postal_code'),
            Arr::get($data, 'country'),
        ])->filter()->implode(', ');
    }
}
