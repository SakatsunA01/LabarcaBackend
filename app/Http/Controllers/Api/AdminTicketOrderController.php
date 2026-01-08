<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TicketOrder;

class AdminTicketOrderController extends Controller
{
    public function index()
    {
        $orders = TicketOrder::with(['event', 'product', 'user'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($orders);
    }

    public function show(string $id)
    {
        $order = TicketOrder::with(['event', 'product', 'user'])->find($id);
        if (is_null($order)) {
            return response()->json(['message' => 'Orden no encontrada'], 404);
        }

        return response()->json($order);
    }
}
