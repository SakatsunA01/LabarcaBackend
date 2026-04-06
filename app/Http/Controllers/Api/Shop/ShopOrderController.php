<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Models\ShopOrder;
use Illuminate\Http\Request;

class ShopOrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = ShopOrder::query()
            ->with(['user', 'items.product', 'items.variant'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('delivery_method'), fn ($query) => $query->where('delivery_method', $request->string('delivery_method')))
            ->orderByDesc('created_at')
            ->paginate((int) $request->input('per_page', 20));

        return response()->json($orders);
    }

    public function show(string $id)
    {
        $order = ShopOrder::query()
            ->with(['user', 'items.product', 'items.variant'])
            ->where('id', $id)
            ->orWhere('mp_payment_id', $id)
            ->orWhere('mp_preference_id', $id)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Orden no encontrada'], 404);
        }

        return response()->json($this->transformOrder($order));
    }

    private function transformOrder(ShopOrder $order): array
    {
        return [
            'id' => $order->id,
            'status' => $order->status,
            'delivery_method' => $order->delivery_method,
            'subtotal_ars' => (float) $order->subtotal_ars,
            'discount_ars' => (float) $order->discount_ars,
            'shipping_cost_ars' => (float) $order->shipping_cost_ars,
            'total_ars' => (float) $order->total_ars,
            'currency' => $order->currency,
            'mp_payment_id' => $order->mp_payment_id,
            'mp_preference_id' => $order->mp_preference_id,
            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'product_name' => $item->name_snapshot,
                'name_snapshot' => $item->name_snapshot,
                'quantity' => (int) $item->quantity,
                'unit_price_ars' => (float) $item->unit_price_ars,
                'discount_ars' => (float) $item->discount_ars,
                'line_total_ars' => (float) $item->line_total_ars,
            ])->values(),
        ];
    }
}
