<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TicketOrder;
use App\Support\TicketVerificationToken;
use Illuminate\Http\Request;

class TicketOrderController extends Controller
{
    public function index(Request $request)
    {
        TicketOrder::expirePendingCashOrders();

        $user = $request->user();
        $orders = TicketOrder::with(['event', 'product'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        $orders->each(function (TicketOrder $order) {
            if ($order->status === 'approved') {
                $order->setAttribute('verification_token', TicketVerificationToken::make($order));
            }
        });

        return response()->json($orders);
    }

    public function show(Request $request, string $id)
    {
        TicketOrder::expirePendingCashOrders();

        $user = $request->user();
        $order = TicketOrder::with(['event', 'product'])
            ->where('user_id', $user->id)
            ->find($id);

        if (is_null($order)) {
            return response()->json(['message' => 'Orden no encontrada'], 404);
        }

        if ($order->status === 'approved') {
            $order->setAttribute('verification_token', TicketVerificationToken::make($order));
        }

        return response()->json($order);
    }
}
