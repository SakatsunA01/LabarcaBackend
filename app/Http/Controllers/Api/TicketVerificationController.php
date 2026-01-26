<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TicketOrder;
use App\Models\TicketOrderCheckin;
use App\Support\TicketVerificationToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TicketVerificationController extends Controller
{
    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'consume_quantity' => 'nullable|integer|min:1',
            'event_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = TicketVerificationToken::parse($request->input('token'));
        if (!$payload) {
            return response()->json(['message' => 'Token invalido.'], 422);
        }

        $order = TicketOrder::with(['event', 'product', 'user'])
            ->find($payload['order_id'] ?? null);

        if (!$order) {
            return response()->json(['message' => 'Orden no encontrada.'], 404);
        }

        if (!empty($payload['mp_payment_id']) && $order->mp_payment_id !== $payload['mp_payment_id']) {
            return response()->json(['message' => 'Token no corresponde a la orden.'], 422);
        }

        if (isset($payload['quantity']) && (int) $payload['quantity'] !== (int) $order->quantity) {
            return response()->json(['message' => 'Token desactualizado.'], 422);
        }

        if ($request->filled('event_id') && (int) $request->input('event_id') !== (int) $order->event_id) {
            return response()->json(['message' => 'La orden no pertenece a este evento.'], 422);
        }

        if ($order->status !== 'approved') {
            return response()->json(['message' => 'La orden no esta aprobada.'], 422);
        }

        $used = (int) TicketOrderCheckin::where('ticket_order_id', $order->id)->sum('quantity');
        $remaining = max(0, (int) $order->quantity - $used);

        $consume = (int) $request->input('consume_quantity', 0);
        if ($consume > 0) {
            if ($consume > $remaining) {
                return response()->json([
                    'message' => 'No hay entradas suficientes disponibles.',
                    'remaining' => $remaining,
                ], 422);
            }

            TicketOrderCheckin::create([
                'ticket_order_id' => $order->id,
                'quantity' => $consume,
                'checked_by' => $request->user()?->id,
                'checked_at' => now(),
            ]);

            $used += $consume;
            $remaining = max(0, (int) $order->quantity - $used);
        }

        return response()->json([
            'order' => [
                'id' => $order->id,
                'mp_payment_id' => $order->mp_payment_id,
                'quantity' => $order->quantity,
                'status' => $order->status,
                'event' => $order->event ? [
                    'id' => $order->event->id,
                    'nombre' => $order->event->nombre,
                    'fecha' => $order->event->fecha,
                ] : null,
                'product' => $order->product ? [
                    'id' => $order->product->id,
                    'name' => $order->product->name,
                ] : null,
                'user' => $order->user ? [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                ] : null,
            ],
            'used' => $used,
            'remaining' => $remaining,
        ]);
    }
}
