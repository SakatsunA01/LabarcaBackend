<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\TicketOrderApprovedMail;
use App\Mail\TicketOrderPendingMail;
use App\Models\Product;
use App\Models\TicketOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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

        $order = $order->fresh(['event', 'product', 'user']);
        if ($order->status === 'approved' && !$order->email_sent_at && $order->user?->email) {
            Mail::to($order->user->email)->send(new TicketOrderApprovedMail($order));
            $order->email_sent_at = now();
            $order->save();
        }

        return response()->json($order);
    }

    public function sendTicketEmail(string $id)
    {
        $order = TicketOrder::with(['event', 'product', 'user'])->find($id);
        if (!$order) {
            return response()->json(['message' => 'Orden no encontrada'], 404);
        }

        if ($order->status !== 'approved') {
            return response()->json(['message' => 'La orden no esta aprobada.'], 422);
        }

        $email = $order->user?->email;
        if (!$email) {
            return response()->json(['message' => 'El usuario no tiene email.'], 422);
        }

        Mail::to($email)->send(new TicketOrderApprovedMail($order));
        $order->email_sent_at = now();
        $order->save();

        return response()->json(['message' => 'Email enviado.']);
    }

    public function sendPendingEmail(string $id)
    {
        $order = TicketOrder::with(['event', 'product', 'user'])->find($id);
        if (!$order) {
            return response()->json(['message' => 'Orden no encontrada'], 404);
        }

        if ($order->status !== 'pending' || $order->payment_method !== 'mercadopago') {
            return response()->json(['message' => 'La orden no esta pendiente de Mercado Pago.'], 422);
        }

        $email = $order->user?->email;
        if (!$email) {
            return response()->json(['message' => 'El usuario no tiene email.'], 422);
        }

        Mail::to($email)->send(new TicketOrderPendingMail($order));
        $order->pending_email_sent_at = now();
        $order->save();

        return response()->json(['message' => 'Email pendiente enviado.']);
    }

    public function rejectCash(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'rejected_reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $order = TicketOrder::with(['event', 'product', 'user'])->find($id);
        if (!$order) {
            return response()->json(['message' => 'Orden no encontrada'], 404);
        }

        if (!in_array($order->status, ['pending_cash', 'pending'], true)) {
            return response()->json(['message' => 'La orden no se puede rechazar en este estado.'], 422);
        }

        $order->status = 'rejected';
        $order->rejected_at = now();
        $order->rejected_by = $request->user()?->id;
        $order->rejected_reason = $request->input('rejected_reason');
        $order->save();

        return response()->json($order->fresh(['event', 'product', 'user']));
    }
}
