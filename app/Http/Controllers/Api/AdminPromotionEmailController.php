<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\EventInstitutionalInvitationMail;
use App\Mail\TicketPromotionMail;
use App\Models\Artista;
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
                    'stock' => (int) $product->stock,
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

    public function sendInvitation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|exists:eventos,id',
            'product_id' => 'required|exists:products,id',
            'emails' => 'nullable|array|min:1',
            'emails.*' => 'required|email',
            'recipients' => 'nullable|array|min:1',
            'recipients.*.email' => 'required_with:recipients|email',
            'recipients.*.church_name' => 'nullable|string|max:255',
            'recipients.*.pastor_cargo' => 'nullable|string|max:255',
            'mode' => 'required|in:test,send',
            'test_email' => 'nullable|email',
            'church_name' => 'nullable|string|max:255',
            'pastor_cargo' => 'nullable|string|max:255',
            'subject' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $event = Evento::find($request->input('event_id'));
        $product = Product::find($request->input('product_id'));
        if (!$event || !$product) {
            return response()->json(['message' => 'Evento o producto no encontrado.'], 404);
        }

        $activePromos = $this->filterActivePromotions($product->promotions);
        if (empty($activePromos)) {
            return response()->json(['message' => 'No hay promociones activas para esta entrada.'], 422);
        }

        $lineup = $this->resolveEventLineup($event);
        $churchName = trim((string) $request->input('church_name', ''));
        $pastorCargo = trim((string) $request->input('pastor_cargo', ''));
        $subject = trim((string) $request->input('subject', '')) ?: null;

        $recipientRows = collect($request->input('recipients', []))
            ->map(function ($row) {
                if (!is_array($row)) return null;
                $email = strtolower(trim((string) ($row['email'] ?? '')));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
                return [
                    'email' => $email,
                    'church_name' => trim((string) ($row['church_name'] ?? '')),
                    'pastor_cargo' => trim((string) ($row['pastor_cargo'] ?? '')),
                ];
            })
            ->filter()
            ->values();

        if ($recipientRows->isEmpty()) {
            $emails = collect($request->input('emails', []))
                ->map(fn ($email) => strtolower(trim((string) $email)))
                ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
                ->unique()
                ->values();

            $recipientRows = $emails->map(fn ($email) => [
                'email' => $email,
                'church_name' => '',
                'pastor_cargo' => '',
            ])->values();
        }

        if ($recipientRows->isEmpty()) {
            return response()->json(['message' => 'No hay correos validos para enviar.'], 422);
        }

        $mode = $request->input('mode');
        if ($mode === 'test') {
            $to = strtolower(trim((string) $request->input('test_email', '')));
            if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return response()->json(['message' => 'Ingresa un email de prueba valido.'], 422);
            }

            $sample = $recipientRows->first();
            $sampleChurch = !empty($sample['church_name']) ? $sample['church_name'] : $churchName;
            $samplePastor = !empty($sample['pastor_cargo']) ? $sample['pastor_cargo'] : $pastorCargo;

            Mail::to($to)->send(new EventInstitutionalInvitationMail(
                $event,
                $product,
                $activePromos,
                $lineup,
                $sampleChurch,
                $samplePastor,
                $subject
            ));

            return response()->json([
                'message' => 'Prueba enviada.',
                'sent' => 1,
                'skipped' => 0,
            ]);
        }

        $sent = 0;
        $skipped = 0;
        $dedupedRecipients = $recipientRows
            ->groupBy('email')
            ->map(fn ($group) => $group->first())
            ->values();

        foreach ($dedupedRecipients as $recipient) {
            try {
                $recipientChurch = !empty($recipient['church_name']) ? $recipient['church_name'] : $churchName;
                $recipientPastor = !empty($recipient['pastor_cargo']) ? $recipient['pastor_cargo'] : $pastorCargo;

                Mail::to($recipient['email'])->send(new EventInstitutionalInvitationMail(
                    $event,
                    $product,
                    $activePromos,
                    $lineup,
                    $recipientChurch,
                    $recipientPastor,
                    $subject
                ));
                $sent++;
            } catch (\Throwable $e) {
                $skipped++;
            }
        }

        return response()->json([
            'message' => 'Invitaciones enviadas.',
            'sent' => $sent,
            'skipped' => $skipped,
            'total' => $dedupedRecipients->count(),
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

    private function resolveEventLineup(Evento $event): array
    {
        $ids = is_array($event->lineup_artist_ids) ? $event->lineup_artist_ids : [];
        if (empty($ids)) {
            return [];
        }

        $artists = Artista::whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)
            ->map(function ($artistId) use ($artists) {
                $artist = $artists->get($artistId);
                if (!$artist) return null;
                return [
                    'id' => $artist->id,
                    'name' => $artist->name,
                    'image' => !empty($artist->imageUrl)
                        ? (str_starts_with($artist->imageUrl, 'http')
                            ? $artist->imageUrl
                            : rtrim(env('APP_URL', 'https://api.labarcaministerio.com'), '/') . $artist->imageUrl)
                        : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
