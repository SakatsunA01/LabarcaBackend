<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TicketOrder;
use Illuminate\Http\Request;

class TicketOrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $orders = TicketOrder::with(['event', 'product'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($orders);
    }

    public function show(Request $request, string $id)
    {
        $user = $request->user();
        $order = TicketOrder::with(['event', 'product'])
            ->where('user_id', $user->id)
            ->find($id);

        if (is_null($order)) {
            return response()->json(['message' => 'Orden no encontrada'], 404);
        }

        return response()->json($order);
    }
}
