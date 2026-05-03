<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sorteo;
use App\Models\SorteoGuestParticipant;
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
        if ($request->has('bendiciones')) {
            $data['bendiciones'] = $this->normalizeRequirements($request->input('bendiciones'));
        }
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
        if ($request->has('bendiciones')) {
            $data['bendiciones'] = $this->normalizeRequirements($request->input('bendiciones'));
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
        // Build lookup from explicit SorteoParticipant records
        $records = SorteoParticipant::where('sorteo_id', $sorteo->id)
            ->with('user:id,name,email,admin_sn,created_at')
            ->get();

        $participantRecords = $records->where('excluded', false)->keyBy('user_id');
        $excludedRecords    = $records->where('excluded', true)->keyBy('user_id');

        [$registrationRule, $ticketRule] = $this->extractRules($sorteo);
        $ticketUserLookup = $this->buildTicketUserLookup($sorteo, $ticketRule);
        $eligibleOnly = $request->boolean('eligible_only');

        // Left panel: explicit participants + excluded
        $leftPanel = [];
        foreach ($records as $rec) {
            if (!$rec->user) continue;
            $leftPanel[$rec->user_id] = [
                'id'             => $rec->user->id,
                'name'           => $rec->user->name,
                'email'          => $rec->user->email,
                'created_at'     => $rec->user->created_at?->toIso8601String(),
                'eligible'       => false,
                'is_participant' => !$rec->excluded,
                'excluded'       => (bool) $rec->excluded,
                'added_by'       => $rec->added_by,
            ];
        }

        // Right panel: all other users (not in sorteo_participants), with eligibility hint
        $allUsers = User::select('id', 'name', 'email', 'created_at', 'admin_sn')
            ->whereNotIn('id', $records->pluck('user_id')->all())
            ->orderBy('name')
            ->get();

        $rightPanel = [];
        foreach ($allUsers as $user) {
            $eligible = $this->isUserEligible($user, $sorteo, $registrationRule, $ticketRule, $ticketUserLookup);

            if ($eligibleOnly && !$eligible) {
                continue;
            }

            $rightPanel[] = [
                'id'             => $user->id,
                'name'           => $user->name,
                'email'          => $user->email,
                'created_at'     => $user->created_at?->toIso8601String(),
                'eligible'       => $eligible,
                'is_participant' => false,
                'excluded'       => false,
                'added_by'       => null,
            ];
        }

        // Fetch guest participants
        $guestRecords = SorteoGuestParticipant::where('sorteo_id', $sorteo->id)->get();
        $guests = $guestRecords->map(fn ($g) => [
            'id'     => $g->id,
            'name'   => $g->name,
            'email'  => $g->email,
            'phone'  => $g->phone,
            'source' => $g->source,
            'notes'  => $g->notes,
        ])->values()->all();

        // For eligible_only (used by close animation), return flat array of names for rolling
        if ($eligibleOnly) {
            $userNames = collect(array_values($leftPanel))->filter(fn ($u) => $u['is_participant'])->pluck('name')->values()->all();
            $guestNames = collect($guests)->pluck('name')->values()->all();
            $allNames = array_merge($userNames, $guestNames);
            return response()->json($allNames);
        }

        return response()->json([
            'users'  => array_merge(array_values($leftPanel), $rightPanel),
            'guests' => $guests,
        ]);
    }

    public function addGuest(Request $request, Sorteo $sorteo)
    {
        $validator = Validator::make($request->all(), [
            'name'   => 'required|string|max:255',
            'email'  => 'nullable|email|max:255',
            'phone'  => 'nullable|string|max:50',
            'source' => 'nullable|string|max:50',
            'notes'  => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $guest = SorteoGuestParticipant::create([
            'sorteo_id' => $sorteo->id,
            'name'      => $request->input('name'),
            'email'     => $request->input('email'),
            'phone'     => $request->input('phone'),
            'source'    => $request->input('source'),
            'notes'     => $request->input('notes'),
            'added_by'  => $request->user()?->id,
        ]);

        return response()->json($guest, 201);
    }

    public function removeGuest(Request $request, Sorteo $sorteo, SorteoGuestParticipant $guest)
    {
        if ($guest->sorteo_id !== $sorteo->id) {
            return response()->json(['message' => 'El invitado no pertenece a este sorteo.'], 422);
        }

        $guest->delete();

        return response()->json(null, 204);
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
                    'excluded' => false,
                    'added_by' => $request->user()?->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->values()
            ->all();

        if ($payload) {
            SorteoParticipant::upsert($payload, ['sorteo_id', 'user_id'], ['is_manual', 'excluded', 'updated_at']);
        }

        return response()->json(['message' => 'Participantes agregados.'], 201);
    }

    public function close(Request $request, Sorteo $sorteo)
    {
        if ($sorteo->estado === 'cerrado') {
            return response()->json(['message' => 'El sorteo ya fue cerrado.'], 422);
        }

        $prizes = $this->getPrizeList($sorteo);
        $winners = [];
        $usedEntries = [];

        foreach ($prizes as $index => $prizeName) {
            $winner = $this->pickWinnerExcluding($sorteo, $usedEntries);
            if (!$winner) break;
            $usedEntries[] = [
                'type' => $winner['type'],
                'id'   => $winner['type'] === 'user' ? $winner['user_id'] : $winner['guest_id'],
            ];
            $winners[] = [
                'position' => $index + 1,
                'prize'    => $prizeName,
                'type'     => $winner['type'],
                'user_id'  => $winner['user_id'],
                'guest_id' => $winner['guest_id'],
                'name'     => $winner['name'],
                'email'    => $winner['email'],
            ];
        }

        if (empty($winners)) {
            return response()->json(['message' => 'No hay participantes para cerrar el sorteo.'], 422);
        }

        $first = $winners[0];
        $sorteo->estado = 'cerrado';
        $sorteo->closed_at = now();
        $sorteo->ganador_user_id = ($first['type'] === 'user') ? $first['user_id'] : null;
        $sorteo->ganador_snapshot = ['name' => $first['name'], 'email' => $first['email']];
        $sorteo->winners = $winners;
        $sorteo->save();

        return response()->json([
            'winners' => $winners,
            'sorteo'  => $this->formatSorteo($sorteo->fresh('ganador')),
        ]);
    }

    public function redraw(Request $request, Sorteo $sorteo)
    {
        $validator = Validator::make($request->all(), [
            'position' => 'required|integer|min:1',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $position = (int) $request->input('position');
        $currentWinners = $sorteo->winners ?? [];

        // Exclude everyone except the slot being re-drawn
        $excludeEntries = collect($currentWinners)
            ->filter(fn ($w) => $w['position'] !== $position)
            ->map(fn ($w) => [
                'type' => $w['type'] ?? 'user',
                'id'   => ($w['type'] ?? 'user') === 'guest' ? $w['guest_id'] : $w['user_id'],
            ])
            ->values()->all();

        $newWinner = $this->pickWinnerExcluding($sorteo, $excludeEntries);
        if (!$newWinner) {
            return response()->json(['message' => 'No hay más participantes disponibles para este premio.'], 422);
        }

        $updatedWinners = collect($currentWinners)->map(function ($w) use ($position, $newWinner) {
            if ((int) $w['position'] === $position) {
                return array_merge($w, [
                    'type'     => $newWinner['type'],
                    'user_id'  => $newWinner['user_id'],
                    'guest_id' => $newWinner['guest_id'],
                    'name'     => $newWinner['name'],
                    'email'    => $newWinner['email'],
                ]);
            }
            return $w;
        })->values()->all();

        if ($position === 1) {
            $sorteo->ganador_user_id = ($newWinner['type'] === 'user') ? $newWinner['user_id'] : null;
            $sorteo->ganador_snapshot = ['name' => $newWinner['name'], 'email' => $newWinner['email']];
        }
        $sorteo->winners = $updatedWinners;
        $sorteo->save();

        return response()->json([
            'winner'  => array_merge(['position' => $position], [
                'type'     => $newWinner['type'],
                'user_id'  => $newWinner['user_id'],
                'guest_id' => $newWinner['guest_id'],
                'name'     => $newWinner['name'],
                'email'    => $newWinner['email'],
            ]),
            'winners' => $updatedWinners,
        ]);
    }

    public function removeParticipant(Request $request, Sorteo $sorteo, User $user)
    {
        SorteoParticipant::updateOrCreate(
            ['sorteo_id' => $sorteo->id, 'user_id' => $user->id],
            ['excluded' => true, 'is_manual' => false, 'added_by' => $request->user()?->id]
        );
        return response()->json(['message' => 'Participante removido.']);
    }

    public function participate(Request $request, Sorteo $sorteo)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'No autorizado.'], 401);
        if ($sorteo->estado === 'cerrado') return response()->json(['message' => 'El sorteo ya cerró.'], 422);

        // Check if already excluded
        $excluded = SorteoParticipant::where('sorteo_id', $sorteo->id)
            ->where('user_id', $user->id)
            ->where('excluded', true)
            ->exists();
        if ($excluded) {
            return response()->json(['message' => 'No podés participar en este sorteo.'], 403);
        }

        // Verify requirements that can be checked server-side
        $requirements = $sorteo->requisitos ?? [];
        foreach ($requirements as $req) {
            $type = $req['type'] ?? null;
            $data = $req['data'] ?? [];

            if ($type === 'ticket_purchase') {
                $mode = $data['mode'] ?? 'any';
                $eventId = $data['event_id'] ?? null;
                $query = \App\Models\TicketOrder::where('status', 'approved')
                    ->where('user_id', $user->id)
                    ->where('created_at', '<=', $sorteo->fecha_limite);
                if ($mode === 'event' && $eventId) {
                    $query->where('event_id', $eventId);
                }
                if (!$query->exists()) {
                    return response()->json(['message' => 'No cumplís el requisito de compra de entrada.'], 422);
                }
            }

            if ($type === 'registration_schedule') {
                $days = $data['days'] ?? [];
                $start = $data['start_time'] ?? null;
                $end = $data['end_time'] ?? null;
                $createdAt = $user->created_at ? \Illuminate\Support\Carbon::parse($user->created_at) : null;

                if (!$createdAt) {
                    return response()->json(['message' => 'No cumplís el requisito de registro en horario.'], 422);
                }
                if (is_array($days) && count($days) > 0) {
                    $dayName = strtolower($createdAt->format('l'));
                    if (!in_array($dayName, $days, true)) {
                        return response()->json(['message' => 'No cumplís el requisito de registro en el día indicado.'], 422);
                    }
                }
                if ($start && $end) {
                    $time = $createdAt->format('H:i');
                    $inRange = ($start <= $end) ? ($time >= $start && $time <= $end) : ($time >= $start || $time <= $end);
                    if (!$inRange) {
                        return response()->json(['message' => 'No cumplís el requisito de registro en el horario indicado.'], 422);
                    }
                }
            }
        }

        SorteoParticipant::updateOrCreate(
            ['sorteo_id' => $sorteo->id, 'user_id' => $user->id],
            ['is_manual' => true, 'excluded' => false, 'added_by' => $user->id]
        );

        return response()->json(['message' => 'Registrado como participante.']);
    }

    public function thankEmailPreview(Request $request, Sorteo $sorteo)
    {
        $participants = $this->buildPublicParticipants($sorteo);
        return response()->json([
            'sorteo_nombre' => $sorteo->nombre,
            'premio' => $sorteo->premio,
            'premio_imagen_url' => $sorteo->premio_imagen_url,
            'requisitos' => $sorteo->requisitos ?? [],
            'participants_count' => $participants['count'],
            'html_preview' => $this->renderThankEmailHtml($sorteo),
        ]);
    }

    public function sendThankEmail(Request $request, Sorteo $sorteo)
    {
        $validator = Validator::make($request->all(), [
            'test_email' => 'nullable|email',
        ]);
        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $testEmail = $request->input('test_email');

        if ($testEmail) {
            \Mail::to($testEmail)->send(new \App\Mail\SorteoThanksEmail($sorteo, null));
            return response()->json(['message' => 'Email de prueba enviado a ' . $testEmail]);
        }

        // Send to all participants
        $manualIds = SorteoParticipant::where('sorteo_id', $sorteo->id)->pluck('user_id')->all();
        $manualLookup = array_fill_keys($manualIds, true);
        [$registrationRule, $ticketRule] = $this->extractRules($sorteo);
        $ticketUserLookup = $this->buildTicketUserLookup($sorteo, $ticketRule);

        $count = 0;
        User::select('id', 'name', 'email', 'created_at')->orderBy('id')
            ->chunkById(200, function ($users) use ($sorteo, $manualLookup, $registrationRule, $ticketRule, $ticketUserLookup, &$count) {
                foreach ($users as $user) {
                    $isParticipant = isset($manualLookup[$user->id]);
                    $eligible = $this->isUserEligible($user, $sorteo, $registrationRule, $ticketRule, $ticketUserLookup);
                    if (!$eligible && !$isParticipant) continue;
                    \Mail::to($user->email)->send(new \App\Mail\SorteoThanksEmail($sorteo, $user));
                    $count++;
                }
            });

        return response()->json(['message' => "Email enviado a {$count} participantes."]);
    }

    private function renderThankEmailHtml(Sorteo $sorteo): string
    {
        return view('emails.sorteo-thanks', ['sorteo' => $sorteo, 'user' => null])->render();
    }

    private function formatSorteo(Sorteo $sorteo): array
    {
        $data = $sorteo->toArray();
        $winner = $sorteo->ganador;
        $snapshot = $sorteo->ganador_snapshot ?? [];
        $data['ganador'] = $winner
            ? ['name' => $winner->name, 'email' => $winner->email]
            : ($snapshot ? ['name' => $snapshot['name'] ?? null, 'email' => $snapshot['email'] ?? null] : null);
        $data['winners'] = $sorteo->winners ?? [];

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

    private function buildExcludedLookup(Sorteo $sorteo): array
    {
        return array_fill_keys(
            SorteoParticipant::where('sorteo_id', $sorteo->id)
                ->where('excluded', true)
                ->pluck('user_id')
                ->all(),
            true
        );
    }

    private function buildPublicParticipants(Sorteo $sorteo, int $limit = 30): array
    {
        $records = SorteoParticipant::where('sorteo_id', $sorteo->id)
            ->where('excluded', false)
            ->with('user:id,name')
            ->get();

        $count = $records->count();
        $preview = $records->take($limit)
            ->map(fn ($r) => $r->user?->name)
            ->filter()
            ->values()
            ->all();

        return ['count' => $count, 'preview' => $preview];
    }

    public function myParticipation(Request $request, Sorteo $sorteo)
    {
        $user = $request->user();
        $record = SorteoParticipant::where('sorteo_id', $sorteo->id)
            ->where('user_id', $user->id)
            ->first();

        return response()->json([
            'is_participant' => $record && !$record->excluded,
            'is_excluded'    => $record && $record->excluded,
        ]);
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
        // Admins are never eligible for sorteos
        if ($user->admin_sn ?? false) {
            return false;
        }

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

    private function getPrizeList(Sorteo $sorteo): array
    {
        $prizes = [$sorteo->premio];
        foreach ($sorteo->bendiciones ?? [] as $b) {
            $prizes[] = is_array($b) ? ($b['nombre'] ?? 'Bendicion') : (string) $b;
        }
        return $prizes;
    }

    private function pickWinnerExcluding(Sorteo $sorteo, array $excludeEntries): ?array
    {
        // Build a set of excluded type+id pairs for fast lookup
        $excludeSet = [];
        foreach ($excludeEntries as $entry) {
            $excludeSet[$entry['type'] . ':' . $entry['id']] = true;
        }

        // Build unified pool: user participants
        $pool = [];
        $userIds = SorteoParticipant::where('sorteo_id', $sorteo->id)
            ->where('excluded', false)
            ->pluck('user_id')
            ->all();
        foreach ($userIds as $userId) {
            if (!isset($excludeSet['user:' . $userId])) {
                $pool[] = ['type' => 'user', 'id' => $userId];
            }
        }

        // Guest participants
        $guests = SorteoGuestParticipant::where('sorteo_id', $sorteo->id)->get();
        foreach ($guests as $g) {
            if (!isset($excludeSet['guest:' . $g->id])) {
                $pool[] = ['type' => 'guest', 'id' => $g->id];
            }
        }

        if (empty($pool)) {
            return null;
        }

        // Reservoir sampling
        $winner = null;
        $seen = 0;
        foreach ($pool as $entry) {
            $seen++;
            if (random_int(1, $seen) === 1) {
                $winner = $entry;
            }
        }

        if (!$winner) return null;

        if ($winner['type'] === 'user') {
            $user = User::find($winner['id']);
            if (!$user) return null;
            return [
                'type'     => 'user',
                'user_id'  => $user->id,
                'guest_id' => null,
                'name'     => $user->name,
                'email'    => $user->email,
            ];
        }

        // Guest winner
        $g = SorteoGuestParticipant::find($winner['id']);
        if (!$g) return null;
        return [
            'type'     => 'guest',
            'user_id'  => null,
            'guest_id' => $g->id,
            'name'     => $g->name,
            'email'    => $g->email,
        ];
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
