<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventManualBuyer;
use App\Models\Evento;
use App\Models\TicketOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminEventBuyersController extends Controller
{
    /**
     * Returns merged buyer list: approved ticket orders + manual buyers.
     */
    public function index(Evento $evento)
    {
        // Ticket orders (approved or pending_cash)
        $orders = TicketOrder::where('event_id', $evento->id)
            ->whereIn('status', ['approved', 'pending_cash'])
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($order) {
                $name  = $order->user?->name  ?? $order->guest_name  ?? 'Sin nombre';
                $email = $order->user?->email ?? $order->guest_email ?? null;
                return [
                    'id'         => 'order-' . $order->id,
                    'source'     => 'ticket',
                    'name'       => $name,
                    'email'      => $email,
                    'phone'      => null,
                    'notes'      => $order->admin_note,
                    'status'     => $order->status,
                    'quantity'   => $order->quantity,
                    'created_at' => $order->created_at?->toIso8601String(),
                    'user_id'    => $order->user_id,
                ];
            });

        // Manual buyers
        $manual = EventManualBuyer::where('event_id', $evento->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($b) {
                return [
                    'id'         => 'manual-' . $b->id,
                    'source'     => 'manual',
                    'name'       => $b->name,
                    'email'      => $b->email,
                    'phone'      => $b->phone,
                    'notes'      => $b->notes,
                    'status'     => 'manual',
                    'quantity'   => 1,
                    'created_at' => $b->created_at?->toIso8601String(),
                    'user_id'    => null,
                    'raw_id'     => $b->id,
                ];
            });

        return response()->json([
            'evento'  => ['id' => $evento->id, 'nombre' => $evento->nombre, 'fecha' => $evento->fecha],
            'buyers'  => $orders->merge($manual)->values(),
            'totals'  => [
                'ticket'  => $orders->count(),
                'manual'  => $manual->count(),
            ],
        ]);
    }

    public function store(Request $request, Evento $evento)
    {
        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:60',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $buyer = EventManualBuyer::create([
            'event_id'   => $evento->id,
            'name'       => $request->input('name'),
            'email'      => $request->input('email'),
            'phone'      => $request->input('phone'),
            'notes'      => $request->input('notes'),
            'created_by' => $request->user()?->id,
        ]);

        return response()->json([
            'id'         => 'manual-' . $buyer->id,
            'source'     => 'manual',
            'name'       => $buyer->name,
            'email'      => $buyer->email,
            'phone'      => $buyer->phone,
            'notes'      => $buyer->notes,
            'status'     => 'manual',
            'quantity'   => 1,
            'created_at' => $buyer->created_at?->toIso8601String(),
            'user_id'    => null,
            'raw_id'     => $buyer->id,
        ], 201);
    }

    public function destroy(Request $request, Evento $evento, EventManualBuyer $buyer)
    {
        if ((int) $buyer->event_id !== $evento->id) {
            return response()->json(['message' => 'No encontrado.'], 404);
        }
        $buyer->delete();
        return response()->json(null, 204);
    }
}
