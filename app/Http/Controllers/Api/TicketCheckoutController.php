<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\TicketOrderApprovedMail;
use App\Models\Evento;
use App\Models\Product;
use App\Models\TicketOrder;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;

class TicketCheckoutController extends Controller
{
    private const CASH_PENDING_HOURS = 72;

    public function createPreference(Request $request)
    {
        TicketOrder::expirePendingCashOrders();

        $validator = Validator::make($request->all(), [
            'event_id' => 'required|exists:eventos,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1|max:20',
            'payment_method' => 'nullable|in:mercadopago,cash',
            'pickup_point_index' => 'nullable|integer|min:0',
            'guest_name' => 'nullable|string|max:255',
            'guest_email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $event = Evento::find($request->input('event_id'));
        $product = Product::find($request->input('product_id'));

        if (!$event || !$product) {
            return response()->json(['message' => 'Evento o producto no encontrado.'], 404);
        }

        if (!in_array($product->id, [$event->general_product_id, $event->vip_product_id], true)) {
            return response()->json(['message' => 'El producto no pertenece al evento.'], 422);
        }

        if (!$product->is_active) {
            return response()->json(['message' => 'El producto no esta disponible.'], 422);
        }

        $requestedQuantity = (int) $request->input('quantity');
        $promotionData = $this->resolvePromotionForQuantity($product, $requestedQuantity, Auth::id());
        $totalQuantity = (int) $promotionData['total_quantity'];
        $paidQuantity = (int) $promotionData['paid_quantity'];
        $bonusQuantity = (int) $promotionData['bonus_quantity'];
        $promotionSnapshot = $promotionData['promotion'];

        if ($product->stock < $totalQuantity) {
            return response()->json(['message' => 'No hay stock suficiente para esta compra.'], 422);
        }

        $isAuthenticated = Auth::check();
        if (!$isAuthenticated) {
            $guestName = trim((string) $request->input('guest_name'));
            $guestEmail = trim((string) $request->input('guest_email'));
            if ($guestName === '' || $guestEmail === '') {
                return response()->json(['message' => 'Completa nombre y email para continuar.'], 422);
            }
        }

        $paymentMethod = $request->input('payment_method', 'mercadopago');
        $pickupPoints = is_array($event->pickup_points) ? $event->pickup_points : [];
        $pickupPointIndex = $request->input('pickup_point_index');
        $selectedPickupPoint = null;
        if ($pickupPointIndex !== null && isset($pickupPoints[(int) $pickupPointIndex])) {
            $selectedPickupPoint = $pickupPoints[(int) $pickupPointIndex];
        }

        if ($paymentMethod === 'cash') {
            $expiresAt = now()->addHours((int) env('TICKET_CASH_PENDING_HOURS', self::CASH_PENDING_HOURS));
            $order = TicketOrder::create([
                'event_id' => $event->id,
                'product_id' => $product->id,
                'user_id' => Auth::id(),
                'guest_name' => $isAuthenticated ? null : trim((string) $request->input('guest_name')),
                'guest_email' => $isAuthenticated ? null : trim((string) $request->input('guest_email')),
                'quantity' => $totalQuantity,
                'paid_quantity' => $paidQuantity,
                'bonus_quantity' => $bonusQuantity,
                'promotion_snapshot' => $promotionSnapshot,
                'unit_price_ars' => $product->price_ars,
                'currency' => 'ARS',
                'payment_method' => 'cash',
                'status' => 'pending_cash',
                'expires_at' => $expiresAt,
                'pickup_point_name' => $selectedPickupPoint['name'] ?? null,
                'pickup_point_map_url' => $selectedPickupPoint['map_url'] ?? null,
            ]);

            $user = $request->user();
            $message = $this->buildCashWhatsappMessage(
                $user?->name ?: (trim((string) $request->input('guest_name')) ?: 'Usuario'),
                $event->nombre,
                $totalQuantity,
                (int) $order->id,
                $order->pickup_point_name
            );

            return response()->json([
                'order_id' => $order->id,
                'status' => 'pending_cash',
                'paid_quantity' => $paidQuantity,
                'bonus_quantity' => $bonusQuantity,
                'expires_at' => $expiresAt->toISOString(),
                'whatsapp_url' => $event->cash_whatsapp_url,
                'whatsapp_message' => $message,
                'whatsapp_message_url' => $this->buildWhatsAppMessageUrl($event->cash_whatsapp_url, $message),
                'pickup_points' => $pickupPoints,
                'instructions' => $event->cash_instructions,
            ]);
        }

        $accessToken = config('services.mercadopago.access_token');
        if (!$accessToken) {
            return response()->json(['message' => 'Mercado Pago no esta configurado.'], 500);
        }

        $order = TicketOrder::create([
            'event_id' => $event->id,
            'product_id' => $product->id,
            'user_id' => Auth::id(),
            'guest_name' => $isAuthenticated ? null : trim((string) $request->input('guest_name')),
            'guest_email' => $isAuthenticated ? null : trim((string) $request->input('guest_email')),
            'quantity' => $totalQuantity,
            'paid_quantity' => $paidQuantity,
            'bonus_quantity' => $bonusQuantity,
            'promotion_snapshot' => $promotionSnapshot,
            'unit_price_ars' => $product->price_ars,
            'currency' => 'ARS',
            'payment_method' => 'mercadopago',
            'status' => 'pending',
        ]);

        $preferencePayload = [
            'items' => [
                [
                    'title' => $product->name,
                    'quantity' => $paidQuantity,
                    'unit_price' => (float) $product->price_ars,
                    'currency_id' => 'ARS',
                ],
            ],
            'external_reference' => (string) $order->id,
            'metadata' => [
                'order_id' => $order->id,
                'event_id' => $event->id,
                'product_id' => $product->id,
                'paid_quantity' => $paidQuantity,
                'bonus_quantity' => $bonusQuantity,
            ],
            'auto_return' => 'approved',
            'back_urls' => [
                'success' => config('services.mercadopago.success_url'),
                'failure' => config('services.mercadopago.failure_url'),
                'pending' => config('services.mercadopago.pending_url'),
            ],
            'notification_url' => config('services.mercadopago.notification_url'),
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
            $order->save();

            $useSandbox = config('app.env') !== 'production' && !empty($data['sandbox_init_point']);
            return response()->json([
                'order_id' => $order->id,
                'paid_quantity' => $paidQuantity,
                'bonus_quantity' => $bonusQuantity,
                'init_point' => $useSandbox ? $data['sandbox_init_point'] : $data['init_point'],
            ]);
        } catch (\Throwable $exception) {
            Log::error('Error creando preferencia de Mercado Pago', [
                'error' => $exception->getMessage(),
            ]);
            return response()->json(['message' => 'No se pudo crear la preferencia de pago.'], 500);
        }
    }

    public function handleWebhook(Request $request)
    {
        TicketOrder::expirePendingCashOrders();

        $paymentId = $request->input('data.id') ?? $request->input('id');
        if (!$paymentId) {
            return response()->json(['message' => 'Sin id de pago.'], 200);
        }

        $accessToken = config('services.mercadopago.access_token');
        if (!$accessToken) {
            return response()->json(['message' => 'Mercado Pago no esta configurado.'], 200);
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
            Log::error('Error consultando pago en Mercado Pago', [
                'payment_id' => $paymentId,
                'error' => $exception->getMessage(),
            ]);
            return response()->json(['message' => 'No se pudo validar el pago.'], 200);
        }

        $orderId = $paymentData['external_reference'] ?? ($paymentData['metadata']['order_id'] ?? null);
        if (!$orderId) {
            return response()->json(['message' => 'Sin referencia de orden.'], 200);
        }

        $order = TicketOrder::find($orderId);
        if (!$order) {
            return response()->json(['message' => 'Orden no encontrada.'], 200);
        }

        $status = $paymentData['status'] ?? 'unknown';

        DB::transaction(function () use ($order, $status, $paymentId) {
            $order->mp_payment_id = (string) $paymentId;

            if ($status === 'approved' && $order->status !== 'approved') {
                $product = Product::where('id', $order->product_id)->lockForUpdate()->first();
                if ($product && $product->stock >= $order->quantity) {
                    $product->stock -= $order->quantity;
                    $product->save();
                    $order->status = 'approved';
                } else {
                    $order->status = 'approved_out_of_stock';
                }
            } else {
                $order->status = $status;
            }

            $order->save();
        });

        $order->refresh();
        if ($order->status === 'approved' && !$order->email_sent_at) {
            $order->loadMissing(['event', 'product', 'user']);
            $email = $order->user?->email ?: $order->guest_email;
            if ($email) {
                Mail::to($email)->send(new TicketOrderApprovedMail($order));
                $order->email_sent_at = now();
                $order->save();
            }
        }

        return response()->json(['message' => 'OK'], 200);
    }

    private function buildCashWhatsappMessage(
        string $userName,
        string $eventName,
        int $quantity,
        int $orderId,
        ?string $pickupPointName = null
    ): string {
        $pickupText = $pickupPointName ? " Punto de retiro preferido: {$pickupPointName}." : '';
        return "Hola! Quiero coordinar mi entrada en efectivo. Evento: {$eventName}. Cantidad: {$quantity}. Nombre: {$userName}. Orden: #{$orderId}.{$pickupText}";
    }

    private function buildWhatsAppMessageUrl(?string $baseUrl, string $message): ?string
    {
        if (!$baseUrl) {
            return null;
        }

        // The wa.me/qr format does not support prefilled text reliably.
        if (str_contains($baseUrl, '/wa.me/qr/') || str_contains($baseUrl, 'wa.me/qr/')) {
            return null;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        return $baseUrl . $separator . 'text=' . rawurlencode($message);
    }

    private function resolvePromotionForQuantity(Product $product, int $paidQuantity, ?int $userId = null): array
    {
        $promotions = is_array($product->promotions) ? $product->promotions : [];
        $bestPromotion = null;
        $bestBonus = 0;
        $now = now();

        foreach ($promotions as $promotion) {
            if (!is_array($promotion)) continue;
            if (($promotion['type'] ?? '') !== 'buy_x_get_y') continue;
            if (array_key_exists('is_active', $promotion) && !$promotion['is_active']) continue;

            $startsAt = $promotion['starts_at'] ?? null;
            $endsAt = $promotion['ends_at'] ?? null;
            try {
                if ($startsAt && $now->lt(Carbon::parse($startsAt))) {
                    continue;
                }
            } catch (\Throwable $e) {
                // ignore invalid date
            }
            try {
                if ($endsAt && $now->gt(Carbon::parse($endsAt))) {
                    continue;
                }
            } catch (\Throwable $e) {
                // ignore invalid date
            }

            $buyQty = (int) ($promotion['buy_qty'] ?? 0);
            $freeQty = (int) ($promotion['free_qty'] ?? 0);
            if ($buyQty <= 0 || $freeQty <= 0) continue;

            if ($userId && !empty($promotion['id'])) {
                $alreadyUsed = TicketOrder::query()
                    ->where('user_id', $userId)
                    ->whereNotNull('promotion_snapshot')
                    ->where('promotion_snapshot->id', $promotion['id'])
                    ->exists();
                if ($alreadyUsed) {
                    continue;
                }
            }

            // Exact match only: promo applies only when quantity == buy_qty
            $bonus = $paidQuantity === $buyQty ? $freeQty : 0;
            if ($bonus <= 0) continue;

            if ($bonus > $bestBonus) {
                $bestBonus = $bonus;
                $bestPromotion = [
                    'id' => $promotion['id'] ?? null,
                    'type' => 'buy_x_get_y',
                    'buy_qty' => $buyQty,
                    'free_qty' => $freeQty,
                    'label' => $promotion['label'] ?? "{$buyQty} + {$freeQty}",
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ];
            }
        }

        return [
            'paid_quantity' => $paidQuantity,
            'bonus_quantity' => $bestBonus,
            'total_quantity' => $paidQuantity + $bestBonus,
            'promotion' => $bestPromotion,
        ];
    }
}
