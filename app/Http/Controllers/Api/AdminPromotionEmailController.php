<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\TicketPromotionMail;
use App\Models\Evento;
use App\Models\Product;
use App\Models\TicketOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class AdminPromotionEmailController extends Controller
{
    public function index()
    {
        $events = Evento::with(['generalProduct', 'vipProduct'])->get();

        $payload = $events->flatMap(function ($event) {
            $items = [];
            foreach (['general' => $event->generalProduct, 'vip' => $event->vipProduct] as $type => $product) {
                if (!$product) continue;
                $activePromos = $this->filterActivePromotions($product->promotions);
                if (empty($activePromos)) continue;
                $items[] = [
                    'event_id' => $event->id,
                    'event_name' => $event->nombre,
                    'event_date' => $event->fecha,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'price_ars' => $product->price_ars,
                    'type' => $type,
                    'promotions' => $activePromos,
                ];
            }
            return $items;
        })->values();

        return response()->json($payload);
    }

    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'scope' => 'required|in:all,no_purchase',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $product = Product::find($request->input('product_id'));
        if (!$product) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        $event = Evento::where('general_product_id', $product->id)
            ->orWhere('vip_product_id', $product->id)
            ->first();

        if (!$event) {
            return response()->json(['message' => 'Evento no encontrado para este producto.'], 404);
        }

        $activePromos = $this->filterActivePromotions($product->promotions);
        if (empty($activePromos)) {
            return response()->json(['message' => 'No hay promociones activas.'], 422);
        }

        $scope = $request->input('scope');

        $usersQuery = User::query()->whereNotNull('email');

        if ($scope === 'no_purchase') {
            $usersQuery->whereDoesntHave('ticketOrders', function ($query) use ($event) {
                $query->where('event_id', $event->id)
                    ->whereIn('status', ['approved', 'approved_out_of_stock']);
            });
        }

        $users = $usersQuery->get();

        $sent = 0;
        $skipped = 0;

        foreach ($users as $user) {
            if (!$user->email) {
                $skipped++;
                continue;
            }

            Mail::to($user->email)->send(new TicketPromotionMail($event, $product, $activePromos));
            $sent++;
        }

        return response()->json([
            'message' => 'Envio completado.',
            'sent' => $sent,
            'skipped' => $skipped,
        ]);
    }

    private function filterActivePromotions($promotions): array
    {
        $list = is_array($promotions) ? $promotions : [];
        $now = now();

        return collect($list)
            ->filter(function ($promo) use ($now) {
                if (!is_array($promo)) return false;
                if (($promo['type'] ?? '') !== 'buy_x_get_y') return false;
                if (array_key_exists('is_active', $promo) && !$promo['is_active']) return false;

                $startsAt = $promo['starts_at'] ?? null;
                $endsAt = $promo['ends_at'] ?? null;
                try {
                    if ($startsAt && $now->lt(Carbon::parse($startsAt))) return false;
                } catch (\Throwable $e) {
                    // ignore
                }
                try {
                    if ($endsAt && $now->gt(Carbon::parse($endsAt))) return false;
                } catch (\Throwable $e) {
                    // ignore
                }

                return true;
            })
            ->values()
            ->all();
    }
}
