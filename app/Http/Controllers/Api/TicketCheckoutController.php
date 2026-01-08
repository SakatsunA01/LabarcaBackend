<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Product;
use App\Models\TicketOrder;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TicketCheckoutController extends Controller
{
    public function createPreference(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|exists:eventos,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1|max:5',
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

        $quantity = (int) $request->input('quantity');
        if ($product->stock < $quantity) {
            return response()->json(['message' => 'No hay stock suficiente para esta compra.'], 422);
        }

        $accessToken = config('services.mercadopago.access_token');
        if (!$accessToken) {
            return response()->json(['message' => 'Mercado Pago no esta configurado.'], 500);
        }

        $order = TicketOrder::create([
            'event_id' => $event->id,
            'product_id' => $product->id,
            'user_id' => Auth::id(),
            'quantity' => $quantity,
            'unit_price_ars' => $product->price_ars,
            'currency' => 'ARS',
            'status' => 'pending',
        ]);

        $preferencePayload = [
            'items' => [
                [
                    'title' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => (float) $product->price_ars,
                    'currency_id' => 'ARS',
                ],
            ],
            'external_reference' => (string) $order->id,
            'metadata' => [
                'order_id' => $order->id,
                'event_id' => $event->id,
                'product_id' => $product->id,
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

        return response()->json(['message' => 'OK'], 200);
    }
}
