<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artista;
use App\Models\Evento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class EventoController extends Controller
{
    /**
     * Muestra una lista de todos los eventos, ordenados por fecha.
     */
    public function index()
    {
        // Ordenar por fecha más próxima primero
        $eventos = Evento::orderBy('fecha', 'asc')->get();
        return response()->json($eventos);
    }

    /**
     * Almacena un nuevo evento en la base de datos.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->validationRules());

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $data = $request->except(['imagenUrl']);
        $lineupArtistIds = $this->parseLineupArtistIds($request->input('lineup_artist_ids'));
        if (is_null($lineupArtistIds)) {
            return response()->json(['lineup_artist_ids' => ['Formato invalido para lineup_artist_ids']], 400);
        }
        if (!$this->lineupArtistsExist($lineupArtistIds)) {
            return response()->json(['lineup_artist_ids' => ['Algunos artistas seleccionados no existen']], 400);
        }
        $data['lineup_artist_ids'] = $lineupArtistIds;
        $cronograma = $this->parseCronograma($request->input('cronograma'));
        if (is_null($cronograma)) {
            return response()->json(['cronograma' => ['Formato invalido para cronograma']], 400);
        }
        $data['cronograma'] = $cronograma;
        $pickupPoints = $this->parsePickupPoints($request->input('pickup_points'));
        if (is_null($pickupPoints)) {
            return response()->json(['pickup_points' => ['Formato invalido para pickup_points']], 400);
        }
        $data['pickup_points'] = $pickupPoints;
        $data['countdown_enabled'] = $request->boolean('countdown_enabled', true);

        if ($request->hasFile('imagenUrl')) {
            $data['imagenUrl'] = $this->handleImageUpload($request, 'imagenUrl');
        }

        $evento = Evento::create($data);
        return response()->json($evento, 201);
    }

    /**
     * Muestra un evento específico con sus relaciones.
     */
    public function show(string $id)
    {
        $evento = Evento::with(['testimonios', 'galeria', 'generalProduct', 'vipProduct'])->find($id); // Cargar relaciones
        if (is_null($evento)) {
            return response()->json(['message' => 'Evento no encontrado'], 404);
        }

        $payload = $evento->toArray();
        $payload['countdown_settings'] = [
            'active' => (bool) ($evento->countdown_enabled ?? true),
            'title' => $evento->countdown_title ?: 'Cuenta regresiva',
            'subtitle' => $evento->countdown_subtitle ?: 'Empieza en',
        ];
        $payload['bento_pillars'] = [
            'experiencia' => $evento->pilar_experiencia ?: '',
            'autoridad' => $evento->pilar_autoridad ?: '',
            'mensaje' => $evento->pilar_mensaje ?: '',
            'icon_experiencia' => $evento->pilar_experiencia_icon ?: '✨',
            'icon_autoridad' => $evento->pilar_autoridad_icon ?: '🏛️',
            'icon_mensaje' => $evento->pilar_mensaje_icon ?: '💬',
        ];
        $payload['lineup'] = $this->resolveLineupArtists($evento->lineup_artist_ids ?? []);
        $payload['cronograma'] = collect($evento->cronograma ?? [])->map(function ($item) {
            return [
                'hora' => $item['hora'] ?? null,
                'actividad' => $item['actividad'] ?? null,
                'titulo' => $item['actividad'] ?? null,
            ];
        })->values()->all();
        $payload['artistas'] = $payload['lineup'];
        $payload['cash_settings'] = [
            'whatsapp_url' => $evento->cash_whatsapp_url,
            'instructions' => $evento->cash_instructions,
            'pickup_points' => $evento->pickup_points ?? [],
        ];

        return response()->json($payload);
    }

    /**
     * Actualiza un evento existente en la base de datos.
     */
    public function update(Request $request, string $id)
    {
        $evento = Evento::find($id);
        if (is_null($evento)) {
            return response()->json(['message' => 'Evento no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), $this->validationRules(true));

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $data = $request->except(['imagenUrl', '_method']);
        if ($request->has('lineup_artist_ids')) {
            $lineupArtistIds = $this->parseLineupArtistIds($request->input('lineup_artist_ids'));
            if (is_null($lineupArtistIds)) {
                return response()->json(['lineup_artist_ids' => ['Formato invalido para lineup_artist_ids']], 400);
            }
            if (!$this->lineupArtistsExist($lineupArtistIds)) {
                return response()->json(['lineup_artist_ids' => ['Algunos artistas seleccionados no existen']], 400);
            }
            $data['lineup_artist_ids'] = $lineupArtistIds;
        }
        if ($request->has('countdown_enabled')) {
            $data['countdown_enabled'] = $request->boolean('countdown_enabled');
        }
        if ($request->has('cronograma')) {
            $cronograma = $this->parseCronograma($request->input('cronograma'));
            if (is_null($cronograma)) {
                return response()->json(['cronograma' => ['Formato invalido para cronograma']], 400);
            }
            $data['cronograma'] = $cronograma;
        }
        if ($request->has('pickup_points')) {
            $pickupPoints = $this->parsePickupPoints($request->input('pickup_points'));
            if (is_null($pickupPoints)) {
                return response()->json(['pickup_points' => ['Formato invalido para pickup_points']], 400);
            }
            $data['pickup_points'] = $pickupPoints;
        }

        if ($request->hasFile('imagenUrl')) {
            $data['imagenUrl'] = $this->handleImageUpload($request, 'imagenUrl', $evento->imagenUrl);
        }

        $evento->update($data);
        return response()->json($evento);
    }

    /**
     * Elimina un evento de la base de datos.
     */
    public function destroy(string $id)
    {
        $evento = Evento::find($id);
        if (is_null($evento)) {
            return response()->json(['message' => 'Evento no encontrado'], 404);
        }

        // Eliminar la imagen asociada del almacenamiento
        if ($evento->imagenUrl) {
            $this->deleteImage($evento->imagenUrl);
        }

        // Opcional: Eliminar testimonios y galería relacionados si no se usa ON DELETE CASCADE
        // $evento->testimonios()->delete();
        // $evento->galeria()->delete();
        $evento->delete();
        return response()->json(null, 204);
    }

    /**
     * Define las reglas de validación para crear y actualizar eventos.
     */
    private function validationRules($isUpdate = false)
    {
        $sometimes = $isUpdate ? 'sometimes|' : '';
        return [
            'nombre' => $sometimes . 'required|string|max:255',
            'fecha' => $sometimes . 'required|date',
            'link_compra' => 'nullable|url|max:255',
            'general_product_id' => 'nullable|exists:products,id',
            'vip_product_id' => 'nullable|exists:products,id|different:general_product_id',
            'descripcion' => 'nullable|string',
            'lugar' => 'nullable|string|max:255',
            'imagenUrl' => $sometimes . 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10240',
            'countdown_enabled' => 'nullable|boolean',
            'countdown_title' => 'nullable|string|max:120',
            'countdown_subtitle' => 'nullable|string|max:255',
            'pilar_experiencia' => 'nullable|string',
            'pilar_autoridad' => 'nullable|string',
            'pilar_mensaje' => 'nullable|string',
            'pilar_experiencia_icon' => 'nullable|string|max:32',
            'pilar_autoridad_icon' => 'nullable|string|max:32',
            'pilar_mensaje_icon' => 'nullable|string|max:32',
            'cronograma' => 'nullable',
            'lineup_artist_ids' => 'nullable',
            'pickup_points' => 'nullable',
            'cash_whatsapp_url' => 'nullable|url|max:500',
            'cash_instructions' => 'nullable|string',
        ];
    }

    private function parseLineupArtistIds($value): ?array
    {
        if (is_null($value) || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                return null;
            }
            $value = $decoded;
        }

        if (!is_array($value)) {
            return null;
        }

        return array_values(
            array_unique(
                array_filter(
                    array_map(fn ($id) => (int) $id, $value),
                    fn ($id) => $id > 0
                )
            )
        );
    }

    private function lineupArtistsExist(array $artistIds): bool
    {
        if (empty($artistIds)) {
            return true;
        }

        return Artista::whereIn('id', $artistIds)->count() === count($artistIds);
    }

    private function resolveLineupArtists(array $artistIds): array
    {
        if (empty($artistIds)) {
            return [];
        }

        $artists = Artista::whereIn('id', $artistIds)->get()->keyBy('id');

        return collect($artistIds)
            ->map(function ($artistId) use ($artists) {
                $artist = $artists->get($artistId);
                if (!$artist) {
                    return null;
                }

                return [
                    'id' => $artist->id,
                    'name' => $artist->name,
                    'role' => null,
                    'summary' => $artist->description,
                    'image' => $artist->imageUrl,
                    'spotifyUrl' => $artist->social_spotifyProfile ?: $artist->spotifyEmbedUrl,
                    'youtubeUrl' => $artist->social_youtubeChannel ?: $artist->youtubeVideoId,
                    'followers' => $artist->followers_count ?? $artist->followers ?? null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function parseCronograma($value): ?array
    {
        if (is_null($value) || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                return null;
            }
            $value = $decoded;
        }

        if (!is_array($value)) {
            return null;
        }

        return collect($value)
            ->map(function ($item) {
                if (!is_array($item)) {
                    return null;
                }

                return [
                    'hora' => isset($item['hora']) ? trim((string) $item['hora']) : '',
                    'actividad' => isset($item['actividad'])
                        ? trim((string) $item['actividad'])
                        : trim((string) ($item['titulo'] ?? '')),
                ];
            })
            ->filter(fn ($item) => $item && ($item['hora'] !== '' || $item['actividad'] !== ''))
            ->values()
            ->all();
    }

    private function parsePickupPoints($value): ?array
    {
        if (is_null($value) || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                return null;
            }
            $value = $decoded;
        }

        if (!is_array($value)) {
            return null;
        }

        return collect($value)
            ->map(function ($item) {
                if (!is_array($item)) {
                    return null;
                }

                $name = trim((string) ($item['name'] ?? $item['nombre'] ?? ''));
                $mapUrl = trim((string) ($item['map_url'] ?? $item['url'] ?? ''));

                if ($name === '' && $mapUrl === '') {
                    return null;
                }

                return [
                    'name' => $name,
                    'map_url' => $mapUrl,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Maneja la subida de una imagen, la guarda y elimina la anterior si existe.
     */
    private function handleImageUpload(Request $request, $fieldName, $oldImagePath = null)
    {
        if ($request->hasFile($fieldName)) {
            // Eliminar la imagen anterior si existe
            if ($oldImagePath) {
                $this->deleteImage($oldImagePath);
            }

            $path = $request->file($fieldName)->store('eventos', 'public');
            return '/public/storage/' . $path;
        }

        // Si no se sube un archivo nuevo durante una actualización, se mantiene la ruta antigua.
        // Para la creación, esto devolverá null correctamente si no hay archivo.
        return $oldImagePath;
    }

    /**
     * Elimina un archivo de imagen del disco público.
     */
    private function deleteImage($imagePath)
    {
        if (!$imagePath) {
            return;
        }
        $path = parse_url($imagePath, PHP_URL_PATH);
        if ($path) {
            $path = str_replace('/public/storage/', '', $path);
            Storage::disk('public')->delete($path);
        }
    }
}
