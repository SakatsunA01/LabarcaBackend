<?php

namespace App\Services;

use App\Models\Artista;
use App\Models\Lanzamiento;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

class SpotifyReleaseImportService
{
    public function getCandidates(): array
    {
        $artists = Artista::query()
            ->orderBy('name')
            ->get(['id', 'name', 'social_spotifyProfile', 'spotifyEmbedUrl']);

        $issues = [];
        $candidates = [];
        $existingByLink = $this->buildExistingByLinkMap();
        $existingBySignature = $this->buildExistingBySignatureMap();
        $summary = [
            'total_artists' => $artists->count(),
            'artists_with_spotify' => 0,
            'artists_without_spotify' => 0,
            'artists_processed' => 0,
            'artists_with_new_releases' => 0,
            'artists_without_new_releases' => 0,
            'releases_scanned' => 0,
            'releases_skipped_existing' => 0,
        ];

        foreach ($artists as $artist) {
            $spotifyArtistId = $this->extractArtistId(
                $artist->social_spotifyProfile,
                $artist->spotifyEmbedUrl
            );

            if (!$spotifyArtistId) {
                $summary['artists_without_spotify']++;
                $issues[] = [
                    'artist_id' => $artist->id,
                    'artist_name' => $artist->name,
                    'status' => 'artist_without_spotify',
                    'message' => 'No se encontro un artist_id valido en Perfil de Spotify ni en URL Spotify Embed.',
                ];
                continue;
            }

            $summary['artists_with_spotify']++;
            $summary['artists_processed']++;

            try {
                $releases = $this->getArtistReleases($spotifyArtistId);
                $artistHasNewReleases = false;

                foreach ($releases as $release) {
                    $summary['releases_scanned']++;
                    $detail = $this->getReleaseDetail($release['id']);
                    $candidate = $this->normalizeReleaseCandidate($artist, $detail);

                    $signature = $this->makeExistingSignature(
                        $candidate['artista_id'],
                        $candidate['titulo'],
                        $candidate['fecha_lanzamiento']
                    );

                    if (
                        ($candidate['spotify_link'] && isset($existingByLink[$candidate['artista_id']][$candidate['spotify_link']]))
                        || isset($existingBySignature[$signature])
                    ) {
                        $summary['releases_skipped_existing']++;
                        continue;
                    }

                    $candidates[] = $candidate;
                    $artistHasNewReleases = true;
                }

                if ($artistHasNewReleases) {
                    $summary['artists_with_new_releases']++;
                } else {
                    $summary['artists_without_new_releases']++;
                    $issues[] = [
                        'artist_id' => $artist->id,
                        'artist_name' => $artist->name,
                        'status' => 'no_new_releases',
                        'message' => 'Se pudo consultar Spotify, pero no se encontraron lanzamientos nuevos para importar.',
                    ];
                }
            } catch (\Throwable $exception) {
                $issues[] = [
                    'artist_id' => $artist->id,
                    'artist_name' => $artist->name,
                    'status' => 'spotify_fetch_error',
                    'message' => $this->normalizeSpotifyErrorMessage($exception),
                ];
            }
        }

        usort($candidates, function (array $left, array $right) {
            return [$left['artista_name'], $right['fecha_lanzamiento'], $left['titulo']]
                <=> [$right['artista_name'], $left['fecha_lanzamiento'], $right['titulo']];
        });

        return [
            'candidates' => array_values($candidates),
            'issues' => $issues,
            'summary' => $summary,
        ];
    }

    public function importCandidates(array $candidates): array
    {
        $created = [];
        $skipped = [];
        $failed = [];

        foreach ($candidates as $candidate) {
            try {
                $validated = $this->validateCandidatePayload($candidate);

                if ($this->releaseExists($validated['artista_id'], $validated['spotify_link'], $validated['titulo'], $validated['fecha_lanzamiento'])) {
                    $skipped[] = [
                        'candidate_id' => $validated['candidate_id'],
                        'titulo' => $validated['titulo'],
                        'reason' => 'already_exists',
                    ];
                    continue;
                }

                $coverPath = null;
                $warnings = $validated['warnings'] ?? [];
                if (!empty($validated['cover_remote_url'])) {
                    try {
                        $coverPath = $this->downloadCoverImage(
                            $validated['cover_remote_url'],
                            $validated['artista_name'],
                            $validated['titulo']
                        );
                    } catch (\Throwable $exception) {
                        $warnings[] = 'cover_download_failed';
                    }
                }

                $lanzamiento = Lanzamiento::create([
                    'titulo' => $validated['titulo'],
                    'artista_id' => $validated['artista_id'],
                    'fecha_lanzamiento' => $validated['fecha_lanzamiento'],
                    'cover_image_url' => $coverPath,
                    'spotify_link' => $validated['spotify_link'],
                    'youtube_link' => null,
                ]);

                foreach ($validated['tracks'] as $track) {
                    $lanzamiento->tracks()->create([
                        'titulo' => $track['titulo'],
                        'duracion' => $track['duracion'],
                    ]);
                }

                $created[] = [
                    'candidate_id' => $validated['candidate_id'],
                    'id' => $lanzamiento->id,
                    'titulo' => $lanzamiento->titulo,
                    'warnings' => array_values(array_unique($warnings)),
                ];
            } catch (\Throwable $exception) {
                $failed[] = [
                    'candidate_id' => $candidate['candidate_id'] ?? null,
                    'titulo' => $candidate['titulo'] ?? null,
                    'reason' => $exception->getMessage(),
                ];
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'failed' => $failed,
            'summary' => [
                'created' => count($created),
                'skipped' => count($skipped),
                'failed' => count($failed),
            ],
        ];
    }

    public function extractArtistId(?string ...$values): ?string
    {
        foreach ($values as $value) {
            $spotifyArtistId = $this->extractArtistIdFromValue($value);
            if ($spotifyArtistId) {
                return $spotifyArtistId;
            }
        }

        return null;
    }

    private function extractArtistIdFromValue(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $value = trim($value);

        if (preg_match('~(?:/artist/|spotify:artist:)([a-zA-Z0-9]+)~', $value, $matches)) {
            return $matches[1];
        }

        if (preg_match('~^[a-zA-Z0-9]{22}$~', $value)) {
            return $value;
        }

        return null;
    }

    private function getArtistReleases(string $spotifyArtistId): array
    {
        $response = $this->spotifyRequest()
            ->get(config('services.spotify.api_base_url') . "/artists/{$spotifyArtistId}/albums", [
                'include_groups' => 'album,single',
                'limit' => 20,
                'market' => 'AR',
            ])
            ->throw()
            ->json();

        return collect($response['items'] ?? [])
            ->unique('id')
            ->values()
            ->all();
    }

    private function getReleaseDetail(string $spotifyReleaseId): array
    {
        return $this->spotifyRequest()
            ->get(config('services.spotify.api_base_url') . "/albums/{$spotifyReleaseId}", [
                'market' => 'AR',
            ])
            ->throw()
            ->json();
    }

    private function normalizeReleaseCandidate(Artista $artist, array $release): array
    {
        $image = collect($release['images'] ?? [])->sortByDesc('width')->first();
        $tracks = collect($release['tracks']['items'] ?? [])
            ->map(function (array $track) {
                return [
                    'titulo' => $track['name'] ?? 'Sin titulo',
                    'duracion' => $this->formatDuration($track['duration_ms'] ?? null),
                ];
            })
            ->values()
            ->all();

        $warnings = [];
        if (!$image || empty($image['url'])) {
            $warnings[] = 'missing_cover';
        }

        return [
            'candidate_id' => sha1(implode('|', [
                $artist->id,
                $release['id'] ?? '',
                $release['external_urls']['spotify'] ?? '',
                $release['release_date'] ?? '',
            ])),
            'selected' => true,
            'artista_id' => $artist->id,
            'artista_name' => $artist->name,
            'titulo' => $release['name'] ?? 'Sin titulo',
            'fecha_lanzamiento' => $this->normalizeReleaseDate($release['release_date'] ?? null, $release['release_date_precision'] ?? null),
            'spotify_link' => $release['external_urls']['spotify'] ?? null,
            'spotify_id' => $release['id'] ?? null,
            'cover_remote_url' => $image['url'] ?? null,
            'cover_preview_url' => $image['url'] ?? null,
            'release_type' => $release['album_type'] ?? null,
            'track_count' => count($tracks),
            'tracks' => $tracks,
            'status' => empty($warnings) ? 'ready' : 'warning',
            'warnings' => $warnings,
        ];
    }

    private function normalizeReleaseDate(?string $releaseDate, ?string $precision): string
    {
        if (!$releaseDate) {
            return now()->toDateString();
        }

        return match ($precision) {
            'year' => "{$releaseDate}-01-01",
            'month' => "{$releaseDate}-01",
            default => $releaseDate,
        };
    }

    private function formatDuration(?int $durationMs): string
    {
        if (!$durationMs || $durationMs < 1) {
            return '0:00';
        }

        $totalSeconds = (int) floor($durationMs / 1000);
        $minutes = (int) floor($totalSeconds / 60);
        $seconds = $totalSeconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    private function downloadCoverImage(string $url, string $artistName, string $releaseTitle): string
    {
        $response = Http::timeout(20)->get($url)->throw();
        $extension = $this->detectImageExtension($response->header('Content-Type'));
        $filename = Str::slug($artistName . '-' . $releaseTitle) . '-' . Str::random(8) . '.' . $extension;
        $path = "lanzamientos/{$filename}";

        Storage::disk('public')->put($path, $response->body());

        return '/public/storage/' . $path;
    }

    private function detectImageExtension(?string $contentType): string
    {
        return match ($contentType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };
    }

    private function spotifyRequest()
    {
        return Http::withToken($this->getAccessToken())
            ->acceptJson()
            ->timeout(20);
    }

    private function getAccessToken(): string
    {
        return Cache::remember('spotify_client_credentials_token', now()->addMinutes(50), function () {
            $clientId = config('services.spotify.client_id');
            $clientSecret = config('services.spotify.client_secret');

            if (!$clientId || !$clientSecret) {
                throw new RuntimeException('Faltan SPOTIFY_CLIENT_ID o SPOTIFY_CLIENT_SECRET.');
            }

            $response = Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->timeout(20)
                ->post(config('services.spotify.accounts_base_url') . '/api/token', [
                    'grant_type' => 'client_credentials',
                ])
                ->throw()
                ->json();

            if (empty($response['access_token'])) {
                throw new RuntimeException('Spotify no devolvio access_token.');
            }

            return $response['access_token'];
        });
    }

    private function buildExistingByLinkMap(): array
    {
        $map = [];
        Lanzamiento::query()
            ->whereNotNull('spotify_link')
            ->whereRaw("TRIM(spotify_link) <> ''")
            ->get(['artista_id', 'spotify_link'])
            ->each(function (Lanzamiento $release) use (&$map) {
                $map[$release->artista_id][$release->spotify_link] = true;
            });

        return $map;
    }

    private function buildExistingBySignatureMap(): array
    {
        return Lanzamiento::query()
            ->get(['artista_id', 'titulo', 'fecha_lanzamiento'])
            ->mapWithKeys(function (Lanzamiento $release) {
                return [
                    $this->makeExistingSignature(
                        $release->artista_id,
                        $release->titulo,
                        optional($release->fecha_lanzamiento)->format('Y-m-d') ?? (string) $release->fecha_lanzamiento
                    ) => true,
                ];
            })
            ->all();
    }

    private function releaseExists(int $artistId, ?string $spotifyLink, string $title, string $releaseDate): bool
    {
        $query = Lanzamiento::query()->where('artista_id', $artistId);

        if ($spotifyLink) {
            $bySpotify = (clone $query)->where('spotify_link', $spotifyLink)->exists();
            if ($bySpotify) {
                return true;
            }
        }

        return $query
            ->whereRaw('LOWER(titulo) = ?', [Str::lower($title)])
            ->whereDate('fecha_lanzamiento', $releaseDate)
            ->exists();
    }

    private function makeExistingSignature(int $artistId, string $title, string $releaseDate): string
    {
        return implode('|', [$artistId, Str::lower(trim($title)), $releaseDate]);
    }

    private function validateCandidatePayload(array $candidate): array
    {
        $required = ['candidate_id', 'artista_id', 'artista_name', 'titulo', 'fecha_lanzamiento'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $candidate) || $candidate[$field] === null || $candidate[$field] === '') {
                throw new RuntimeException("Falta el campo {$field}.");
            }
        }

        $tracks = collect($candidate['tracks'] ?? [])
            ->filter(fn ($track) => !empty($track['titulo']))
            ->map(fn ($track) => [
                'titulo' => $track['titulo'],
                'duracion' => $track['duracion'] ?? '0:00',
            ])
            ->values()
            ->all();

        return [
            'candidate_id' => (string) $candidate['candidate_id'],
            'artista_id' => (int) $candidate['artista_id'],
            'artista_name' => (string) $candidate['artista_name'],
            'titulo' => (string) $candidate['titulo'],
            'fecha_lanzamiento' => (string) $candidate['fecha_lanzamiento'],
            'spotify_link' => $candidate['spotify_link'] ?? null,
            'cover_remote_url' => $candidate['cover_remote_url'] ?? null,
            'tracks' => $tracks,
            'warnings' => is_array($candidate['warnings'] ?? null) ? $candidate['warnings'] : [],
        ];
    }

    private function normalizeSpotifyErrorMessage(\Throwable $exception): string
    {
        if ($exception instanceof RequestException && $exception->response) {
            $status = $exception->response->status();
            $body = $exception->response->json();
            $apiMessage = $body['error']['message'] ?? $body['message'] ?? null;

            if ($status === 403 && is_string($apiMessage) && str_contains(Str::lower($apiMessage), 'premium subscription required')) {
                return 'Spotify bloqueo la app con 403: la cuenta duena de la app necesita Spotify Premium activo.';
            }

            if ($status === 400 && is_string($apiMessage) && str_contains(Str::lower($apiMessage), 'invalid limit')) {
                return 'Spotify rechazo la consulta por limite invalido. Se redujo el limite del importador, vuelve a probar.';
            }

            if (is_string($apiMessage) && $apiMessage !== '') {
                return "Spotify respondio {$status}: {$apiMessage}";
            }
        }

        return $exception->getMessage();
    }
}
