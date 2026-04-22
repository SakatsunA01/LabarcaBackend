<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GeminiNewsGeneratorService
{
    public function generateCandidates(string $date, int $count = 6): array
    {
        $apiKey = (string) config('services.gemini.api_key');
        if ($apiKey === '') {
            return [
                'candidates' => [],
                'issues' => [[
                    'source_key' => 'gemini_ai',
                    'source_name' => 'Gemini AI',
                    'status' => 'missing_api_key',
                    'message' => 'No se encontró la clave GEMINI_API_KEY en la configuración del servidor.',
                ]],
            ];
        }

        $model = config('services.gemini.model');
        $apiBaseUrl = rtrim((string) config('services.gemini.api_base_url'), '/');

        try {
            $dateFormatted = Carbon::parse($date)->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
        } catch (\Throwable) {
            $dateFormatted = $date;
        }

        $prompt = <<<PROMPT
Eres el editor de noticias de La Barca Ministerio, un ministerio cristiano pentecostal de Argentina.

Usando Google Search, busca {$count} noticias reales del movimiento cristiano pentecostal y carismático, preferentemente del {$dateFormatted} o de los días recientes. Las noticias deben ser:
- Alentadoras, edificantes y esperanzadoras
- Sobre eventos reales: avivamientos, milagros, conferencias, crecimiento de iglesias, testimonios, misiones, adoración, discipulado, evangelismo
- Evitar noticias sobre escándalos, política, violencia o controversias
- En español neutro (traducir si la fuente está en otro idioma)
- Priorizar noticias del movimiento pentecostal y carismático latinoamericano o global

Devuelve SOLO un JSON válido con esta estructura exacta, sin markdown ni texto adicional:
{"noticias":[{"titulo":"Título en español","contenido":"Contenido completo de 200 a 500 palabras en español, informativo y alentador.","autor":"Nombre del autor si está disponible, sino null","fuente":"Nombre del medio o ministerio","url_origen":"URL exacta si está disponible, sino null","fecha_publicacion":"YYYY-MM-DD"}]}
PROMPT;

        try {
            $response = Http::timeout(60)
                ->acceptJson()
                ->post("{$apiBaseUrl}/models/{$model}:generateContent?key={$apiKey}", [
                    'tools' => [['google_search' => (object) []]],
                    'contents' => [[
                        'parts' => [['text' => $prompt]],
                    ]],
                ])
                ->throw()
                ->json();
        } catch (\Throwable $e) {
            return [
                'candidates' => [],
                'issues' => [[
                    'source_key' => 'gemini_ai',
                    'source_name' => 'Gemini AI',
                    'status' => 'api_error',
                    'message' => 'Error al llamar a la API de Gemini: ' . $e->getMessage(),
                ]],
            ];
        }

        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!$text) {
            return [
                'candidates' => [],
                'issues' => [[
                    'source_key' => 'gemini_ai',
                    'source_name' => 'Gemini AI',
                    'status' => 'empty_response',
                    'message' => 'Gemini no devolvió contenido. Revisá la clave API y el modelo configurado.',
                ]],
            ];
        }

        $decoded = $this->decodeJsonPayload($text);
        if (!is_array($decoded) || empty($decoded['noticias'])) {
            return [
                'candidates' => [],
                'issues' => [[
                    'source_key' => 'gemini_ai',
                    'source_name' => 'Gemini AI',
                    'status' => 'parse_error',
                    'message' => 'No se pudo interpretar la respuesta de Gemini como JSON de noticias.',
                ]],
            ];
        }

        $existingByUrl = $this->buildExistingByUrlMap();
        $candidates = [];

        foreach ($decoded['noticias'] as $item) {
            if (empty($item['titulo']) || empty($item['contenido'])) {
                continue;
            }

            $titulo = Str::limit(trim((string) $item['titulo']), 255, '');
            $contenido = trim((string) $item['contenido']);
            $autor = !empty($item['autor']) ? trim((string) $item['autor']) : null;
            $fuente = !empty($item['fuente']) ? trim((string) $item['fuente']) : 'Gemini AI';
            $urlOrigen = !empty($item['url_origen']) ? trim((string) $item['url_origen']) : null;
            $fechaPublicacion = !empty($item['fecha_publicacion']) ? $item['fecha_publicacion'] : $date;

            if ($urlOrigen && isset($existingByUrl[$urlOrigen])) {
                continue;
            }

            $warnings = ['missing_image'];

            if (Str::length($contenido) < 120) {
                $warnings[] = 'short_content';
            }

            [$hopefulScore, $isHopeful, $hopefulSignals] = $this->evaluateHopefulTone($titulo, $contenido);

            if (!$isHopeful) {
                $warnings[] = 'tone_needs_review';
            }

            $candidateId = sha1(implode('|', ['gemini_ai', $titulo, $fechaPublicacion]));
            $extraWarnings = array_diff($warnings, ['missing_image']);

            $candidates[] = [
                'candidate_id' => $candidateId,
                'selected' => true,
                'source_key' => 'gemini_ai',
                'source_name' => $fuente,
                'source_group' => 'ai_generated',
                'titulo' => $titulo,
                'contenido' => $contenido,
                'autor' => $autor,
                'url_origen' => $urlOrigen,
                'image_remote_url' => null,
                'image_preview_url' => null,
                'source_published_at' => $fechaPublicacion,
                'translation_applied' => true,
                'hopeful_score' => $hopefulScore,
                'is_hopeful' => $isHopeful,
                'hopeful_signals' => $hopefulSignals,
                'is_roster_related' => false,
                'mentioned_artists' => [],
                'relevance_score' => 0,
                'status' => count($extraWarnings) === 0 ? 'ready' : 'warning',
                'warnings' => $warnings,
            ];
        }

        return ['candidates' => $candidates, 'issues' => []];
    }

    private function evaluateHopefulTone(string $title, string $content): array
    {
        $haystack = Str::lower($title . ' ' . $content);

        $positiveKeywords = [
            'avivamiento', 'revival', 'esperanza', 'hope', 'gozo', 'joy', 'alegr', 'celebra',
            'testimonio', 'testimony', 'milagro', 'miracle', 'sanidad', 'healing', 'mision',
            'mission', 'evangelismo', 'evangelism', 'iglesia crece', 'bautismo', 'worship',
            'adoracion', 'jovenes', 'familia', 'conferencia', 'campamento', 'alabanza',
            'comunidad', 'obra social', 'generosidad', 'discipulado', 'discipleship',
        ];

        $negativeKeywords = [
            'escandalo', 'scandal', 'abuso', 'abuse', 'demanda', 'lawsuit', 'guerra', 'war',
            'crimen', 'crime', 'muerte', 'death', 'fraude', 'fraud', 'politica', 'politics',
            'persecucion', 'persecution', 'violencia', 'violence', 'arrest', 'arresto',
            'controversia', 'controversy', 'crisis', 'ataque', 'attack',
        ];

        $positiveHits = array_values(array_unique(array_filter($positiveKeywords, fn ($k) => str_contains($haystack, $k))));
        $negativeHits = array_values(array_unique(array_filter($negativeKeywords, fn ($k) => str_contains($haystack, $k))));
        $score = count($positiveHits) - count($negativeHits);
        $isHopeful = $score >= 1 || (count($positiveHits) >= 2 && count($negativeHits) === 0);

        return [$score, $isHopeful, ['positive' => $positiveHits, 'negative' => $negativeHits]];
    }

    private function decodeJsonPayload(string $text): ?array
    {
        $text = trim($text);

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/is', $text, $matches)) {
            $text = $matches[1];
        } elseif (preg_match('/(\{[\s\S]*\})/s', $text, $matches)) {
            $text = $matches[1];
        }

        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function buildExistingByUrlMap(): array
    {
        return Post::query()
            ->whereNotNull('url_origen')
            ->whereRaw("TRIM(url_origen) <> ''")
            ->pluck('id', 'url_origen')
            ->all();
    }
}
