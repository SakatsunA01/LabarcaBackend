<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Models\ShopOrder;
use App\Models\ShopProduct;
use App\Models\ShopProductVariant;
use App\Models\ShopPromotion;
use App\Mail\ShopOrderConfirmedMail;
use App\Services\ShopShippingService;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ShopCheckoutController extends Controller
{
    public function createPreference(Request $request, ShopShippingService $shippingService)
    {
        $validator = Validator::make($request->all(), [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:shop_products,id'],
            'items.*.variant_id' => ['nullable', 'exists:shop_product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'guest_name' => ['nullable', 'string', 'max:255'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer.phone' => ['nullable', 'string', 'max:50'],
            'customer.address_line' => ['nullable', 'string', 'max:255'],
            'customer.city' => ['nullable', 'string', 'max:255'],
            'customer.province' => ['nullable', 'string', 'max:255'],
            'customer.postal_code' => ['nullable', 'string', 'max:50'],
            'delivery_method' => ['required', 'in:shipping,pickup,envio,retiro_a_coordinar'],
            'pickup_note' => ['nullable', 'string', 'max:255'],
            'shipping_address_line1' => ['required_if:delivery_method,shipping,envio', 'nullable', 'string', 'max:255'],
            'shipping_address_line2' => ['nullable', 'string', 'max:255'],
            'shipping_city' => ['required_if:delivery_method,shipping,envio', 'nullable', 'string', 'max:255'],
            'shipping_state' => ['nullable', 'string', 'max:255'],
            'shipping_postal_code' => ['nullable', 'string', 'max:50'],
            'shipping_country' => ['nullable', 'string', 'max:100'],
            'shipping_full_address' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();
        $payload['delivery_method'] = match ($payload['delivery_method']) {
            'envio' => 'shipping',
            'retiro_a_coordinar' => 'pickup',
            default => $payload['delivery_method'],
        };
        $payload['customer_name'] = $payload['customer_name'] ?? $payload['guest_name'] ?? null;
        $payload['customer_email'] = $payload['customer_email'] ?? $payload['guest_email'] ?? null;
        $payload['customer_phone'] = $payload['customer_phone'] ?? data_get($payload, 'customer.phone');
        $payload['shipping_address_line1'] = $payload['shipping_address_line1'] ?? data_get($payload, 'customer.address_line');
        $payload['shipping_city'] = $payload['shipping_city'] ?? data_get($payload, 'customer.city');
        $payload['shipping_state'] = $payload['shipping_state'] ?? data_get($payload, 'customer.province');
        $payload['shipping_postal_code'] = $payload['shipping_postal_code'] ?? data_get($payload, 'customer.postal_code');
        $payload['shipping_country'] = $payload['shipping_country'] ?? 'Argentina';
        $itemsPayload = $payload['items'];

        $products = ShopProduct::query()
            ->with([
                'variants' => fn ($query) => $query->where('is_active', true),
                'promotions.products',
                'category',
                'type',
            ])
            ->whereIn('id', collect($itemsPayload)->pluck('product_id')->all())
            ->get()
            ->keyBy('id');

        if ($products->count() !== count(array_unique(collect($itemsPayload)->pluck('product_id')->all()))) {
            return response()->json(['message' => 'Uno o mas productos no existen.'], 404);
        }

        $shippingQuote = null;
        if ($payload['delivery_method'] === 'shipping') {
            $destination = trim((string) ($payload['shipping_full_address'] ?? $this->buildAddress($payload)));
            if ($destination === '') {
                return response()->json(['message' => 'La direccion es obligatoria para calcular el envio.'], 422);
            }

            try {
                $shippingQuote = $shippingService->quote($destination);
            } catch (\Throwable $exception) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }
        }

        $cart = [];
        foreach ($itemsPayload as $itemPayload) {
            $product = $products->get((int) $itemPayload['product_id']);
            if (!$product || !$product->is_active) {
                return response()->json(['message' => 'Uno o mas productos no estan disponibles.'], 422);
            }

            $quantity = (int) $itemPayload['quantity'];
            $variant = null;
            if (!empty($itemPayload['variant_id'])) {
                $variant = $product->variants->firstWhere('id', (int) $itemPayload['variant_id']);
                if (!$variant || !$variant->is_active) {
                    return response()->json(['message' => 'Una de las variantes seleccionadas no existe o no esta activa.'], 422);
                }
            } elseif ($product->variants->isNotEmpty()) {
                return response()->json(['message' => 'Selecciona una variante para este producto.'], 422);
            }

            $unitPrice = $variant ? (float) $variant->price_ars : (float) $product->price_ars;
            $availableStock = $variant ? (int) $variant->stock : (int) $product->stock;
            if ($availableStock < $quantity) {
                return response()->json(['message' => 'No hay stock suficiente para uno de los productos.'], 422);
            }

            $lineSubtotal = round($unitPrice * $quantity, 2);
            $linePromotion = $this->resolveBestLinePromotion($product, $quantity, $unitPrice);
            $lineDiscount = (float) ($linePromotion['discount_ars'] ?? 0);
            $lineTotal = max(0, round($lineSubtotal - $lineDiscount, 2));

            $cart[] = [
                'product' => $product,
                'variant' => $variant,
                'quantity' => $quantity,
                'unit_price_ars' => $unitPrice,
                'subtotal_ars' => $lineSubtotal,
                'discount_ars' => $lineDiscount,
                'line_total_ars' => $lineTotal,
                'promotion_snapshot' => $linePromotion['promotion'] ?? null,
            ];
        }

        $comboDiscount = $this->resolveComboDiscount($cart);
        $subtotal = array_sum(array_column($cart, 'subtotal_ars'));
        $lineDiscountTotal = array_sum(array_column($cart, 'discount_ars'));
        $discountTotal = round($lineDiscountTotal + ($comboDiscount['discount_ars'] ?? 0), 2);
        $shippingCost = (float) ($shippingQuote['shipping_cost_ars'] ?? 0);
        $total = round(max(0, $subtotal - $discountTotal + $shippingCost), 2);

        $customerName = trim((string) ($payload['customer_name'] ?? ''));
        $customerEmail = strtolower(trim((string) ($payload['customer_email'] ?? '')));
        $customerPhone = trim((string) ($payload['customer_phone'] ?? ''));

        if (Auth::check() && !$customerName) {
            $customerName = Auth::user()->name;
        }
        if (Auth::check() && !$customerEmail) {
            $customerEmail = Auth::user()->email;
        }

        if ($customerName === '' || $customerEmail === '') {
            return response()->json(['message' => 'Completa nombre y email para continuar.'], 422);
        }

        $accessToken = config('services.mercadopago.shop_access_token') ?: config('services.mercadopago.access_token');
        if (!$accessToken) {
            return response()->json(['message' => 'Mercado Pago no esta configurado para la tienda.'], 500);
        }

        $order = DB::transaction(function () use (
            $payload,
            $cart,
            $comboDiscount,
            $subtotal,
            $discountTotal,
            $shippingQuote,
            $shippingCost,
            $total,
            $customerName,
            $customerEmail,
            $customerPhone
        ) {
            $order = ShopOrder::create([
                'user_id' => Auth::id(),
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_phone' => $customerPhone ?: null,
                'delivery_method' => $payload['delivery_method'],
                'pickup_note' => $payload['pickup_note'] ?? null,
                'shipping_address_line1' => $payload['shipping_address_line1'] ?? null,
                'shipping_address_line2' => $payload['shipping_address_line2'] ?? null,
                'shipping_city' => $payload['shipping_city'] ?? null,
                'shipping_state' => $payload['shipping_state'] ?? null,
                'shipping_postal_code' => $payload['shipping_postal_code'] ?? null,
                'shipping_country' => $payload['shipping_country'] ?? null,
                'shipping_distance_km' => $shippingQuote['distance_km'] ?? null,
                'shipping_rate_per_km' => $shippingQuote['rate_per_km'] ?? config('services.shop.shipping_rate_per_km', 800),
                'shipping_cost_ars' => $shippingCost,
                'shipping_quote_snapshot' => $shippingQuote,
                'subtotal_ars' => $subtotal,
                'discount_ars' => $discountTotal,
                'total_ars' => $total,
                'currency' => 'ARS',
                'promotion_snapshot' => [
                    'line_promotions' => array_values(array_filter(array_column($cart, 'promotion_snapshot'))),
                    'combo_promotion' => $comboDiscount['promotion'] ?? null,
                ],
                'payment_method' => 'mercadopago',
                'status' => 'pending_payment',
            ]);

            foreach ($cart as $line) {
                $order->items()->create([
                    'shop_product_id' => $line['product']->id,
                    'shop_product_variant_id' => $line['variant']?->id,
                    'name_snapshot' => $line['variant']?->label ? $line['product']->name . ' - ' . $line['variant']->label : $line['product']->name,
                    'sku_snapshot' => $line['variant']?->sku,
                    'quantity' => $line['quantity'],
                    'unit_price_ars' => $line['unit_price_ars'],
                    'discount_ars' => $line['discount_ars'],
                    'line_total_ars' => $line['line_total_ars'],
                    'product_snapshot' => [
                        'id' => $line['product']->id,
                        'slug' => $line['product']->slug,
                        'name' => $line['product']->name,
                        'price_ars' => (float) $line['product']->price_ars,
                    ],
                    'variant_snapshot' => $line['variant'] ? [
                        'id' => $line['variant']->id,
                        'sku' => $line['variant']->sku,
                        'label' => $line['variant']->label,
                        'color' => $line['variant']->color,
                        'size' => $line['variant']->size,
                    ] : null,
                    'promotion_snapshot' => $line['promotion_snapshot'],
                ]);
            }

            return $order;
        });

        $preferencePayload = [
            'items' => [
                [
                    'title' => 'Compra tienda La Barca',
                    'quantity' => 1,
                    'unit_price' => (float) $total,
                    'currency_id' => 'ARS',
                ],
            ],
            'external_reference' => (string) $order->id,
            'metadata' => [
                'order_id' => $order->id,
                'delivery_method' => $order->delivery_method,
                'shipping_cost_ars' => $order->shipping_cost_ars,
                'shipping_distance_km' => $order->shipping_distance_km,
                'total_ars' => $order->total_ars,
            ],
            'auto_return' => 'approved',
            'back_urls' => [
                'success' => $this->buildBackUrl(config('services.mercadopago.shop_success_url') ?: config('services.mercadopago.success_url'), $order->id),
                'failure' => $this->buildBackUrl(config('services.mercadopago.shop_failure_url') ?: config('services.mercadopago.failure_url'), $order->id),
                'pending' => $this->buildBackUrl(config('services.mercadopago.shop_pending_url') ?: config('services.mercadopago.pending_url'), $order->id),
            ],
            'notification_url' => config('services.mercadopago.shop_notification_url') ?: config('services.mercadopago.notification_url'),
        ];

        try {
            $client = new Client([
                'base_uri' => 'https://api.mercadopago.com',
                'timeout' => 20,
            ]);

            $response = $client->post('/checkout/preferences', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $preferencePayload,
            ]);

            $data = json_decode((string) $response->getBody(), true);
            $order->mp_preference_id = $data['id'] ?? null;
            $order->mp_checkout_url = $data['init_point'] ?? $data['sandbox_init_point'] ?? null;
            $order->save();

            $useSandbox = config('app.env') !== 'production' && !empty($data['sandbox_init_point']);

            return response()->json([
                'order_id' => $order->id,
                'subtotal_ars' => (float) $order->subtotal_ars,
                'discount_ars' => (float) $order->discount_ars,
                'shipping_cost_ars' => (float) $order->shipping_cost_ars,
                'total_ars' => (float) $order->total_ars,
                'shipping_quote' => $shippingQuote,
                'init_point' => $useSandbox ? $data['sandbox_init_point'] : $data['init_point'],
            ]);
        } catch (\Throwable $exception) {
            Log::error('Error creando preferencia de Mercado Pago para shop', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'No se pudo crear la preferencia de pago de la tienda.'], 500);
        }
    }

    public function handleWebhook(Request $request)
    {
        $paymentId = $request->input('data.id') ?? $request->input('id');
        if (!$paymentId) {
            return response()->json(['message' => 'Sin id de pago.'], 200);
        }

        $accessToken = config('services.mercadopago.shop_access_token') ?: config('services.mercadopago.access_token');
        if (!$accessToken) {
            return response()->json(['message' => 'Mercado Pago no esta configurado para la tienda.'], 200);
        }

        try {
            $client = new Client([
                'base_uri' => 'https://api.mercadopago.com',
                'timeout' => 20,
            ]);

            $response = $client->get("/v1/payments/{$paymentId}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);

            $paymentData = json_decode((string) $response->getBody(), true);
        } catch (\Throwable $exception) {
            Log::error('Error consultando pago de shop en Mercado Pago', [
                'payment_id' => $paymentId,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'No se pudo validar el pago.'], 200);
        }

        $orderId = $paymentData['external_reference'] ?? ($paymentData['metadata']['order_id'] ?? null);
        if (!$orderId) {
            return response()->json(['message' => 'Sin referencia de orden.'], 200);
        }

        $order = ShopOrder::find($orderId);
        if (!$order) {
            return response()->json(['message' => 'Orden no encontrada.'], 200);
        }

        $status = $paymentData['status'] ?? 'unknown';

        $wasPaid = $order->status === 'paid';

        DB::transaction(function () use ($order, $status, $paymentId) {
            $order->mp_payment_id = (string) $paymentId;

            if ($status === 'approved' && $order->status !== 'paid') {
                $items = $order->items()->with(['product', 'variant'])->lockForUpdate()->get();
                foreach ($items as $item) {
                    if ($item->variant) {
                        if ($item->variant->stock < $item->quantity) {
                            $order->status = 'approved_out_of_stock';
                            $order->save();
                            return;
                        }

                        $item->variant->decrement('stock', $item->quantity);
                        continue;
                    }

                    if ($item->product && $item->product->stock < $item->quantity) {
                        $order->status = 'approved_out_of_stock';
                        $order->save();
                        return;
                    }

                    if ($item->product) {
                        $item->product->decrement('stock', $item->quantity);
                    }
                }

                $order->status = 'paid';
            } else {
                $order->status = match ($status) {
                    'approved' => 'paid',
                    'pending' => 'pending_payment',
                    'rejected', 'cancelled' => 'payment_failed',
                    default => $status,
                };
            }

            $order->save();
        });

        if (!$wasPaid && $order->status === 'paid' && $order->customer_email) {
            try {
                $order->loadMissing('items');
                Mail::to($order->customer_email)->send(new ShopOrderConfirmedMail($order));
            } catch (\Throwable $exception) {
                Log::warning('No se pudo enviar email de compra de shop', [
                    'order_id' => $order->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return response()->json(['message' => 'OK'], 200);
    }

    private function resolveBestLinePromotion(ShopProduct $product, int $quantity, float $unitPrice): array
    {
        $bestDiscount = 0.0;
        $bestPromotion = null;
        $now = now();

        foreach ($product->promotions as $promotion) {
            if (!$promotion->isActiveNow($now)) {
                continue;
            }

            if ($promotion->promotion_type === 'percent_off' && $promotion->discount_percent !== null) {
                $discount = round($unitPrice * $quantity * ((float) $promotion->discount_percent / 100), 2);
                if ($discount > $bestDiscount) {
                    $bestDiscount = $discount;
                    $bestPromotion = $this->promotionSnapshot($promotion, $discount);
                }
            }

            if ($promotion->promotion_type === 'amount_off' && $promotion->discount_amount_ars !== null) {
                $discount = round((float) $promotion->discount_amount_ars * $quantity, 2);
                if ($discount > $bestDiscount) {
                    $bestDiscount = $discount;
                    $bestPromotion = $this->promotionSnapshot($promotion, $discount);
                }
            }

            if ($promotion->promotion_type === 'buy_x_get_y' && $promotion->buy_qty && $promotion->get_qty) {
                $groups = intdiv($quantity, (int) $promotion->buy_qty);
                $freeUnits = $groups * (int) $promotion->get_qty;
                $discount = round($freeUnits * $unitPrice, 2);
                if ($discount > $bestDiscount) {
                    $bestDiscount = $discount;
                    $bestPromotion = $this->promotionSnapshot($promotion, $discount, [
                        'buy_qty' => (int) $promotion->buy_qty,
                        'get_qty' => (int) $promotion->get_qty,
                    ]);
                }
            }
        }

        return [
            'discount_ars' => $bestDiscount,
            'promotion' => $bestPromotion,
        ];
    }

    private function resolveComboDiscount(array $cart): array
    {
        $cartQuantities = [];
        foreach ($cart as $line) {
            $cartQuantities[$line['product']->id] = ($cartQuantities[$line['product']->id] ?? 0) + $line['quantity'];
        }

        $bestDiscount = 0.0;
        $bestPromotion = null;
        $now = now();

        foreach (ShopPromotion::query()->with('products')->where('promotion_type', 'combo')->get() as $promotion) {
            if (!$promotion->isActiveNow($now) || $promotion->products->isEmpty()) {
                continue;
            }

            $occurrences = null;
            foreach ($promotion->products as $promoProduct) {
                $requiredQty = max(1, (int) ($promoProduct->pivot->required_quantity ?? 1));
                $availableQty = (int) ($cartQuantities[$promoProduct->id] ?? 0);
                $fit = intdiv($availableQty, $requiredQty);
                $occurrences = $occurrences === null ? $fit : min($occurrences, $fit);
            }

            if (!$occurrences || $occurrences <= 0) {
                continue;
            }

            $comboDiscount = 0.0;
            if ($promotion->discount_amount_ars !== null) {
                $comboDiscount = (float) $promotion->discount_amount_ars * $occurrences;
            } elseif ($promotion->combo_price_ars !== null) {
                $normalTotal = 0.0;
                foreach ($promotion->products as $promoProduct) {
                    $requiredQty = max(1, (int) ($promoProduct->pivot->required_quantity ?? 1));
                    $line = $this->findCartLineByProductId($cart, $promoProduct->id);
                    if (!$line) {
                        continue;
                    }
                    $normalTotal += (float) $line['unit_price_ars'] * $requiredQty;
                }
                $comboDiscount = max(0, ($normalTotal - (float) $promotion->combo_price_ars) * $occurrences);
            } elseif ($promotion->discount_percent !== null) {
                $normalTotal = 0.0;
                foreach ($promotion->products as $promoProduct) {
                    $requiredQty = max(1, (int) ($promoProduct->pivot->required_quantity ?? 1));
                    $line = $this->findCartLineByProductId($cart, $promoProduct->id);
                    if (!$line) {
                        continue;
                    }
                    $normalTotal += (float) $line['unit_price_ars'] * $requiredQty;
                }
                $comboDiscount = round($normalTotal * ((float) $promotion->discount_percent / 100) * $occurrences, 2);
            }

            if ($comboDiscount > $bestDiscount) {
                $bestDiscount = $comboDiscount;
                $bestPromotion = $this->promotionSnapshot($promotion, $comboDiscount, [
                    'occurrences' => $occurrences,
                    'products' => $promotion->products->map(fn (ShopProduct $product) => [
                        'id' => $product->id,
                        'required_quantity' => (int) ($product->pivot->required_quantity ?? 1),
                    ])->values()->all(),
                ]);
            }
        }

        return [
            'discount_ars' => $bestDiscount,
            'promotion' => $bestPromotion,
        ];
    }

    private function buildBackUrl(?string $baseUrl, int $orderId): string
    {
        $url = $baseUrl ?: 'https://labarcaministerio.com/pago/success';
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . 'source=shop&order_id=' . $orderId;
    }

    private function findCartLineByProductId(array $cart, int $productId): ?array
    {
        foreach ($cart as $line) {
            if ((int) $line['product']->id === $productId) {
                return $line;
            }
        }

        return null;
    }

    private function promotionSnapshot(ShopPromotion $promotion, float $discount, array $extra = []): array
    {
        return array_merge([
            'id' => $promotion->id,
            'slug' => $promotion->slug,
            'name' => $promotion->name,
            'promotion_type' => $promotion->promotion_type,
            'discount_ars' => round($discount, 2),
        ], $extra);
    }

    private function buildAddress(array $data): string
    {
        return collect([
            $data['shipping_address_line1'] ?? null,
            $data['shipping_address_line2'] ?? null,
            $data['shipping_city'] ?? null,
            $data['shipping_state'] ?? null,
            $data['shipping_postal_code'] ?? null,
            $data['shipping_country'] ?? null,
        ])->filter()->implode(', ');
    }
}
