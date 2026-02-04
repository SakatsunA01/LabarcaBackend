<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\TicketOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminTicketOrderController extends Controller
{
    public function index()
    {
        TicketOrder::expirePendingCashOrders();

        $orders = TicketOrder::with(['event', 'product', 'user'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($orders);
    }

    public function show(string $id)
    {
        TicketOrder::expirePendingCashOrders();

        $order = TicketOrder::with(['event', 'product', 'user'])->find($id);
        if (is_null($order)) {
            return response()->json(['message' => 'Orden no encontrada'], 404);
        }

        return response()->json($order);
    }

    public function approveCash(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'coordination_phone' => 'nullable|string|max:60',
            'admin_note' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $order = TicketOrder::with(['event', 'product', 'user'])->find($id);
        if (!$order) {
            return response()->json(['message' => 'Orden no encontrada'], 404);
        }

        if ($order->status === 'approved') {
            return response()->json($order);
        }

        if (!in_array($order->status, ['pending_cash', 'pending'], true)) {
            return response()->json(['message' => 'La orden no se puede aprobar en este estado.'], 422);
        }

        if ($order->status === 'pending_cash' && $order->expires_at && $order->expires_at->isPast()) {
            $order->status = 'expired';
            $order->save();
            return response()->json(['message' => 'La orden en efectivo esta vencida.'], 422);
        }

        try {
            DB::transaction(function () use ($order, $request) {
                $product = Product::where('id', $order->product_id)->lockForUpdate()->first();

                if (!$product || $product->stock < $order->quantity) {
                    throw new \RuntimeException('No hay stock suficiente para aprobar esta orden.');
                }

                $product->stock -= $order->quantity;
                $product->save();

                $order->status = 'approved';
                $order->payment_method = $order->payment_method ?: 'cash';
                $order->approved_at = now();
                $order->approved_by = $request->user()?->id;
                $order->coordination_phone = $request->input('coordination_phone');
                $order->admin_note = $request->input('admin_note');
                $order->save();
            });
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($order->fresh(['event', 'product', 'user']));
    }
}
