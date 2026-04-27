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
                'eligible'       => $eligible,
                'is_participant' => false,
                'excluded'       => false,
                'added_by'       => null,
            ];
        }

        // For eligible_only (used by close animation), return explicit participants
        if ($eligibleOnly) {
            return response()->json(array_values(array_filter($leftPanel, fn ($u) => $u['is_participant'])));
        }

        return response()->json(array_merge(array_values($leftPanel), $rightPanel));
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
            SorteoParticipant::upsert($payload, ['sorteo_id', 'user_id'], ['is_manual', 'excluded', 'updated_at']);
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

    private function pickWinner(Sorteo $sorteo): ?User
    {
        // Winner is picked only from explicit participants (not auto-eligible)
        $participantIds = SorteoParticipant::where('sorteo_id', $sorteo->id)
            ->where('excluded', false)
            ->pluck('user_id')
            ->all();

        if (empty($participantIds)) {
            return null;
        }

        $winnerId = null;
        $seen = 0;
        foreach ($participantIds as $userId) {
            $seen++;
            if (random_int(1, $seen) === 1) {
                $winnerId = $userId;
            }
        }

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
