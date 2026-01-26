<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sorteo;
use App\Models\SorteoParticipant;
use App\Models\TicketOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SorteoController extends Controller
{
    public function index()
    {
        $sorteos = Sorteo::with('ganador')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($sorteos->map(fn (Sorteo $sorteo) => $this->formatSorteo($sorteo)));
    }

    public function publicIndex()
    {
        $sorteos = Sorteo::with('ganador')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($sorteos->map(fn (Sorteo $sorteo) => $this->formatPublicSorteo($sorteo)));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'premio' => 'required|string|max:255',
            'fecha_limite' => 'required|date',
            'descripcion' => 'nullable|string',
            'requisitos' => 'nullable',
            'premio_imagen_url' => 'nullable|image|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $data = $request->except(['premio_imagen_url']);
        $data['requisitos'] = $this->normalizeRequirements($request->input('requisitos'));
        $data['created_by'] = $request->user()?->id;

        if ($request->hasFile('premio_imagen_url')) {
            $path = $request->file('premio_imagen_url')->store('sorteos', 'public');
            $data['premio_imagen_url'] = '/public/storage/' . $path;
        }

        $sorteo = Sorteo::create($data);

        return response()->json($this->formatSorteo($sorteo->load('ganador')), 201);
    }

    public function update(Request $request, Sorteo $sorteo)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|required|string|max:255',
            'premio' => 'sometimes|required|string|max:255',
            'fecha_limite' => 'sometimes|required|date',
            'descripcion' => 'nullable|string',
            'requisitos' => 'nullable',
            'premio_imagen_url' => 'nullable|image|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $data = $request->except(['premio_imagen_url', '_method']);
        if ($request->has('requisitos')) {
            $data['requisitos'] = $this->normalizeRequirements($request->input('requisitos'));
        }

        if ($request->hasFile('premio_imagen_url')) {
            $this->deleteFile($sorteo->premio_imagen_url);
            $path = $request->file('premio_imagen_url')->store('sorteos', 'public');
            $data['premio_imagen_url'] = '/public/storage/' . $path;
        } elseif ($request->has('premio_imagen_url') && $request->input('premio_imagen_url') === '') {
            $this->deleteFile($sorteo->premio_imagen_url);
            $data['premio_imagen_url'] = null;
        }

        $sorteo->update($data);

        return response()->json($this->formatSorteo($sorteo->fresh('ganador')));
    }

    public function destroy(Sorteo $sorteo)
    {
        $this->deleteFile($sorteo->premio_imagen_url);
        $sorteo->delete();

        return response()->json(null, 204);
    }

    public function users(Request $request, Sorteo $sorteo)
    {
        $manualIds = SorteoParticipant::where('sorteo_id', $sorteo->id)
            ->pluck('user_id')
            ->all();
        $manualLookup = array_fill_keys($manualIds, true);

        [$registrationRule, $ticketRule] = $this->extractRules($sorteo);
        $ticketUserLookup = $this->buildTicketUserLookup($sorteo, $ticketRule);
        $eligibleOnly = $request->boolean('eligible_only');

        $users = User::select('id', 'name', 'email', 'created_at')
            ->orderBy('name')
            ->get();

        $result = [];
        foreach ($users as $user) {
            $isParticipant = isset($manualLookup[$user->id]);
            $eligible = $this->isUserEligible($user, $sorteo, $registrationRule, $ticketRule, $ticketUserLookup);
            $isCandidate = $eligible || $isParticipant;

            if ($eligibleOnly && !$isCandidate) {
                continue;
            }

            $result[] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toISOString(),
                'eligible' => $eligible,
                'is_participant' => $isParticipant,
            ];
        }

        return response()->json($result);
    }

    public function addParticipants(Request $request, Sorteo $sorteo)
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $now = now();
        $payload = collect($request->input('user_ids'))
            ->unique()
            ->map(function ($userId) use ($sorteo, $request, $now) {
                return [
                    'sorteo_id' => $sorteo->id,
                    'user_id' => $userId,
                    'is_manual' => true,
                    'added_by' => $request->user()?->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->values()
            ->all();

        if ($payload) {
            SorteoParticipant::upsert($payload, ['sorteo_id', 'user_id'], ['updated_at']);
        }

        return response()->json(['message' => 'Participantes agregados.'], 201);
    }

    public function close(Request $request, Sorteo $sorteo)
    {
        if ($sorteo->estado === 'cerrado') {
            return response()->json(['message' => 'El sorteo ya fue cerrado.'], 422);
        }

        $winner = $this->pickWinner($sorteo);
        if (!$winner) {
            return response()->json(['message' => 'No hay participantes para cerrar el sorteo.'], 422);
        }

        $sorteo->estado = 'cerrado';
        $sorteo->closed_at = now();
        $sorteo->ganador_user_id = $winner->id;
        $sorteo->ganador_snapshot = [
            'name' => $winner->name,
            'email' => $winner->email,
        ];
        $sorteo->save();

        return response()->json([
            'winner' => [
                'id' => $winner->id,
                'name' => $winner->name,
                'email' => $winner->email,
            ],
            'sorteo' => $this->formatSorteo($sorteo->fresh('ganador')),
        ]);
    }

    private function formatSorteo(Sorteo $sorteo): array
    {
        $data = $sorteo->toArray();
        $winner = $sorteo->ganador;
        $snapshot = $sorteo->ganador_snapshot ?? [];
        $data['ganador'] = $winner
            ? ['name' => $winner->name, 'email' => $winner->email]
            : ($snapshot ? ['name' => $snapshot['name'] ?? null, 'email' => $snapshot['email'] ?? null] : null);

        return $data;
    }

    private function formatPublicSorteo(Sorteo $sorteo): array
    {
        $data = $sorteo->toArray();
        $snapshot = $sorteo->ganador_snapshot ?? [];
        $winner = $sorteo->ganador;
        $data['ganador'] = $winner
            ? ['name' => $winner->name]
            : ($snapshot ? ['name' => $snapshot['name'] ?? null] : null);

        $participants = $this->buildPublicParticipants($sorteo);
        $data['participants_preview'] = $participants['preview'];
        $data['participants_count'] = $participants['count'];

        return $data;
    }

    private function normalizeRequirements($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    private function buildPublicParticipants(Sorteo $sorteo, int $limit = 30): array
    {
        $manualIds = SorteoParticipant::where('sorteo_id', $sorteo->id)
            ->pluck('user_id')
            ->all();
        $manualLookup = array_fill_keys($manualIds, true);

        [$registrationRule, $ticketRule] = $this->extractRules($sorteo);
        $ticketUserLookup = $this->buildTicketUserLookup($sorteo, $ticketRule);

        $count = 0;
        $preview = [];

        User::select('id', 'name', 'created_at')
            ->orderBy('id')
            ->chunkById(500, function ($users) use (&$count, &$preview, $limit, $manualLookup, $sorteo, $registrationRule, $ticketRule, $ticketUserLookup) {
                foreach ($users as $user) {
                    $isParticipant = isset($manualLookup[$user->id]);
                    $eligible = $this->isUserEligible($user, $sorteo, $registrationRule, $ticketRule, $ticketUserLookup);
                    if (!$eligible && !$isParticipant) {
                        continue;
                    }
                    $count++;
                    if (count($preview) < $limit) {
                        $preview[] = $user->name;
                    }
                }
            });

        return ['count' => $count, 'preview' => $preview];
    }

    private function extractRules(Sorteo $sorteo): array
    {
        $requirements = $sorteo->requisitos ?? [];
        $registrationRule = null;
        $ticketRule = null;

        foreach ($requirements as $req) {
            if (!is_array($req)) {
                continue;
            }
            if (!$registrationRule && ($req['type'] ?? null) === 'registration_schedule') {
                $registrationRule = $req;
            }
            if (!$ticketRule && ($req['type'] ?? null) === 'ticket_purchase') {
                $ticketRule = $req;
            }
        }

        return [$registrationRule, $ticketRule];
    }

    private function buildTicketUserLookup(Sorteo $sorteo, ?array $ticketRule): array
    {
        if (!$ticketRule) {
            return [];
        }

        $data = $ticketRule['data'] ?? [];
        $mode = $data['mode'] ?? 'any';
        $eventId = $data['event_id'] ?? null;

        $query = TicketOrder::query()
            ->where('status', 'approved')
            ->where('created_at', '<=', $sorteo->fecha_limite)
            ->whereNotNull('user_id');

        if ($mode === 'event' && $eventId) {
            $query->where('event_id', $eventId);
        }

        $ids = $query->distinct()->pluck('user_id')->all();
        return array_fill_keys($ids, true);
    }

    private function isUserEligible(User $user, Sorteo $sorteo, ?array $registrationRule, ?array $ticketRule, array $ticketUserLookup): bool
    {
        if ($user->created_at && $user->created_at->greaterThan($sorteo->fecha_limite)) {
            return false;
        }

        if ($ticketRule && !isset($ticketUserLookup[$user->id])) {
            return false;
        }

        if ($registrationRule) {
            $data = $registrationRule['data'] ?? [];
            $days = $data['days'] ?? [];
            $start = $data['start_time'] ?? null;
            $end = $data['end_time'] ?? null;

            $createdAt = $user->created_at ? Carbon::parse($user->created_at) : null;
            if (!$createdAt) {
                return false;
            }

            if (is_array($days) && count($days) > 0) {
                $dayName = strtolower($createdAt->format('l'));
                if (!in_array($dayName, $days, true)) {
                    return false;
                }
            }

            if ($start && $end) {
                $time = $createdAt->format('H:i');
                if ($start <= $end) {
                    if ($time < $start || $time > $end) {
                        return false;
                    }
                } else {
                    $isInside = ($time >= $start || $time <= $end);
                    if (!$isInside) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    private function pickWinner(Sorteo $sorteo): ?User
    {
        $manualIds = SorteoParticipant::where('sorteo_id', $sorteo->id)
            ->pluck('user_id')
            ->all();
        $manualLookup = array_fill_keys($manualIds, true);

        [$registrationRule, $ticketRule] = $this->extractRules($sorteo);
        $ticketUserLookup = $this->buildTicketUserLookup($sorteo, $ticketRule);

        $winnerId = null;
        $seen = 0;

        if (!empty($manualIds)) {
            foreach ($manualIds as $userId) {
                $seen++;
                if (random_int(1, $seen) === 1) {
                    $winnerId = $userId;
                }
            }
        }

        $query = User::query()->select('id', 'created_at');
        if (!empty($ticketUserLookup)) {
            $query->whereIn('id', array_keys($ticketUserLookup));
        }
        $query->where('created_at', '<=', $sorteo->fecha_limite);

        $query->orderBy('id')
            ->chunkById(500, function ($users) use (&$winnerId, &$seen, $manualLookup, $sorteo, $registrationRule, $ticketRule, $ticketUserLookup) {
                foreach ($users as $user) {
                    if (isset($manualLookup[$user->id])) {
                        continue;
                    }
                    if (!$this->isUserEligible($user, $sorteo, $registrationRule, $ticketRule, $ticketUserLookup)) {
                        continue;
                    }
                    $seen++;
                    if (random_int(1, $seen) === 1) {
                        $winnerId = $user->id;
                    }
                }
            });

        return $winnerId ? User::find($winnerId) : null;
    }

    private function deleteFile(?string $filePath): void
    {
        if (!$filePath) {
            return;
        }

        $path = parse_url($filePath, PHP_URL_PATH);
        if ($path) {
            $path = str_replace('/public/storage/', '', $path);
            Storage::disk('public')->delete($path);
        }
    }
}
