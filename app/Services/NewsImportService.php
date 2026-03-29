<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;

class NewsImportService
{
    public function getCandidates(): array
    {
        $candidates = [];
        $issues = [];
        $existingByUrl = $this->buildExistingByUrlMap();
        $existingBySignature = $this->buildExistingBySignatureMap();

        foreach ($this->getSources() as $source) {
            try {
                foreach ($this->fetchFeedItems($source) as $item) {
                    $candidate = $this->normalizeFeedItem($source, $item);
                    $signature = $this->makeExistingSignature($candidate['titulo'], $candidate['source_published_at']);

                    if (
                        ($candidate['url_origen'] && isset($existingByUrl[$candidate['url_origen']]))
                        || isset($existingBySignature[$signature])
                    ) {
                        continue;
                    }

                    $candidates[] = $candidate;
                    if ($candidate['url_origen']) {
                        $existingByUrl[$candidate['url_origen']] = true;
                    }
                    $existingBySignature[$signature] = true;
                }
            } catch (\Throwable $exception) {
                $issues[] = [
                    'source_key' => $source['key'],
                    'source_name' => $source['name'],
                    'status' => 'source_fetch_error',
                    'message' => $exception->getMessage(),
                ];
            }
        }

        usort($candidates, function (array $left, array $right) {
            return [$right['source_published_at'], $left['source_name'], $left['titulo']]
                <=> [$left['source_published_at'], $right['source_name'], $right['titulo']];
        });

        return [
            'candidates' => array_values($candidates),
            'issues' => $issues,
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

                if ($this->postExists($validated['url_origen'], $validated['titulo'], $validated['source_published_at'])) {
                    $skipped[] = [
                        'candidate_id' => $validated['candidate_id'],
                        'titulo' => $validated['titulo'],
                        'reason' => 'already_exists',
                    ];
                    continue;
                }

                $imagePath = null;
                $warnings = $validated['warnings'];

                if ($validated['image_remote_url']) {
                    try {
                        $imagePath = $this->downloadImage(
                            $validated['image_remote_url'],
                            $validated['source_name'],
                            $validated['titulo']
                        );
                    } catch (\Throwable $exception) {
                        $warnings[] = 'image_download_failed';
                    }
                }

                $post = Post::create([
                    'titulo' => $validated['titulo'],
                    'contenido' => $validated['contenido'],
                    'url_imagen' => $imagePath,
                    'autor' => $validated['autor'] ?: $validated['source_name'],
                    'fecha_publicacion' => now(),
                    'fuente' => $validated['source_name'],
                    'url_origen' => $validated['url_origen'],
                    'origen_importado' => true,
                ]);

                $created[] = [
                    'candidate_id' => $validated['candidate_id'],
                    'id' => $post->id,
                    'titulo' => $post->titulo,
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

    private function getSources(): array
    {
        return [
            [
                'key' => 'christian_today_main',
                'name' => 'Christian Today',
                'feed_url' => 'https://www.christiantoday.com/rss.xml',
            ],
            [
                'key' => 'christian_today_world',
                'name' => 'Christian Today World',
                'feed_url' => 'https://www.christiantoday.com/world?format=xml',
            ],
            [
                'key' => 'christian_today_church',
                'name' => 'Christian Today Church',
                'feed_url' => 'https://www.christiantoday.com/church?format=xml',
            ],
            [
                'key' => 'charisma_news',
                'name' => 'Charisma News',
                'feed_url' => 'https://charismanews.com/feed/',
            ],
        ];
    }

    private function fetchFeedItems(array $source): array
    {
        $body = Http::timeout(20)
            ->accept('application/rss+xml, application/xml, text/xml')
            ->get($source['feed_url'])
            ->throw()
            ->body();

        $xml = $this->parseXml($body);

        if (isset($xml->channel->item)) {
            return iterator_to_array($xml->channel->item);
        }

        if (isset($xml->entry)) {
            return iterator_to_array($xml->entry);
        }

        throw new RuntimeException('La fuente no devolvio items RSS validos.');
    }

    private function normalizeFeedItem(array $source, SimpleXMLElement $item): array
    {
        $title = $this->sanitizeText((string) ($item->title ?? 'Sin titulo'));
        $link = $this->extractItemLink($item);
        $publishedAt = $this->normalizePublishedAt((string) ($item->pubDate ?? $item->published ?? $item->updated ?? ''));
        $author = $this->extractAuthor($item);
        $content = $this->extractContent($item);
        $imageUrl = $this->extractImageUrl($item);
        $warnings = [];

        if (!$imageUrl) {
            $warnings[] = 'missing_image';
        }

        if (Str::length($content) < 120) {
            $warnings[] = 'short_content';
        }

        return [
            'candidate_id' => sha1(implode('|', [$source['key'], $link, $publishedAt, $title])),
            'selected' => true,
            'source_key' => $source['key'],
            'source_name' => $source['name'],
            'titulo' => $title,
            'contenido' => $content,
            'autor' => $author,
            'url_origen' => $link,
            'image_remote_url' => $imageUrl,
            'image_preview_url' => $imageUrl,
            'source_published_at' => $publishedAt,
            'status' => empty($warnings) ? 'ready' : 'warning',
            'warnings' => $warnings,
        ];
    }

    private function parseXml(string $xml): SimpleXMLElement
    {
        libxml_use_internal_errors(true);
        $parsed = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA);
        libxml_clear_errors();

        if (!$parsed) {
            throw new RuntimeException('No se pudo interpretar el XML de la fuente.');
        }

        return $parsed;
    }

    private function extractItemLink(SimpleXMLElement $item): ?string
    {
        if (!empty((string) $item->link)) {
            return trim((string) $item->link);
        }

        foreach ($item->link ?? [] as $link) {
            $attributes = $link->attributes();
            if (!empty($attributes['href'])) {
                return trim((string) $attributes['href']);
            }
        }

        return null;
    }

    private function extractAuthor(SimpleXMLElement $item): ?string
    {
        $author = $this->sanitizeText((string) ($item->author ?? ''));
        if ($author !== '') {
            return $author;
        }

        $namespaces = $item->getNamespaces(true);
        if (!empty($namespaces['dc'])) {
            $dc = $item->children($namespaces['dc']);
            $dcAuthor = $this->sanitizeText((string) ($dc->creator ?? ''));
            if ($dcAuthor !== '') {
                return $dcAuthor;
            }
        }

        return null;
    }

    private function extractContent(SimpleXMLElement $item): string
    {
        $namespaces = $item->getNamespaces(true);
        $content = '';

        if (!empty($namespaces['content'])) {
            $contentNode = $item->children($namespaces['content']);
            $content = (string) ($contentNode->encoded ?? '');
        }

        if ($content === '') {
            $content = (string) ($item->description ?? $item->summary ?? '');
        }

        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content ?? '') ?? '';
        $content = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $content) ?? '';
        $content = strip_tags($content);
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = preg_replace('/\s+/', ' ', $content) ?? '';
        $content = trim($content);

        if ($content === '') {
            $content = 'Nota importada desde ' . $this->sanitizeText((string) ($item->title ?? 'la fuente original')) . '.';
        }

        return Str::limit($content, 6000, '...');
    }

    private function extractImageUrl(SimpleXMLElement $item): ?string
    {
        if (isset($item->enclosure)) {
            foreach ($item->enclosure as $enclosure) {
                $attributes = $enclosure->attributes();
                $type = (string) ($attributes['type'] ?? '');
                $url = (string) ($attributes['url'] ?? '');

                if ($url && Str::startsWith($type, 'image/')) {
                    return $url;
                }
            }
        }

        $namespaces = $item->getNamespaces(true);
        if (!empty($namespaces['media'])) {
            $media = $item->children($namespaces['media']);

            foreach (['content', 'thumbnail'] as $nodeName) {
                if (!isset($media->{$nodeName})) {
                    continue;
                }

                foreach ($media->{$nodeName} as $mediaNode) {
                    $attributes = $mediaNode->attributes();
                    $url = (string) ($attributes['url'] ?? '');

                    if ($url) {
                        return $url;
                    }
                }
            }
        }

        $description = (string) ($item->description ?? '');
        if ($description && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $description, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function normalizePublishedAt(string $value): string
    {
        if (trim($value) === '') {
            return now()->toDateString();
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $exception) {
            return now()->toDateString();
        }
    }

    private function sanitizeText(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return trim($value);
    }

    private function downloadImage(string $url, string $sourceName, string $title): string
    {
        $response = Http::timeout(20)->get($url)->throw();
        $extension = $this->detectImageExtension($response->header('Content-Type'));
        $filename = Str::slug($sourceName . '-' . $title) . '-' . Str::random(8) . '.' . $extension;
        $path = "posts_images/imported/{$filename}";

        Storage::disk('public')->put($path, $response->body());

        return '/public/storage/' . $path;
    }

    private function detectImageExtension(?string $contentType): string
    {
        return match ($contentType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            default => 'jpg',
        };
    }

    private function buildExistingByUrlMap(): array
    {
        return Post::query()
            ->whereNotNull('url_origen')
            ->whereRaw("TRIM(url_origen) <> ''")
            ->pluck('id', 'url_origen')
            ->all();
    }

    private function buildExistingBySignatureMap(): array
    {
        return Post::query()
            ->whereNotNull('fecha_publicacion')
            ->get(['titulo', 'fecha_publicacion'])
            ->mapWithKeys(function (Post $post) {
                return [
                    $this->makeExistingSignature(
                        $post->titulo,
                        optional($post->fecha_publicacion)->format('Y-m-d') ?? (string) $post->fecha_publicacion
                    ) => true,
                ];
            })
            ->all();
    }

    private function postExists(?string $url, string $title, string $publishedAt): bool
    {
        if ($url) {
            $byUrl = Post::query()->where('url_origen', $url)->exists();
            if ($byUrl) {
                return true;
            }
        }

        return Post::query()
            ->whereRaw('LOWER(titulo) = ?', [Str::lower($title)])
            ->whereDate('fecha_publicacion', $publishedAt)
            ->exists();
    }

    private function makeExistingSignature(string $title, string $publishedAt): string
    {
        return implode('|', [Str::lower(trim($title)), $publishedAt]);
    }

    private function validateCandidatePayload(array $candidate): array
    {
        $required = ['candidate_id', 'source_name', 'titulo', 'contenido', 'url_origen', 'source_published_at'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $candidate) || $candidate[$field] === null || $candidate[$field] === '') {
                throw new RuntimeException("Falta el campo {$field}.");
            }
        }

        return [
            'candidate_id' => (string) $candidate['candidate_id'],
            'source_name' => (string) $candidate['source_name'],
            'titulo' => Str::limit(trim((string) $candidate['titulo']), 255, ''),
            'contenido' => trim((string) $candidate['contenido']),
            'autor' => isset($candidate['autor']) ? trim((string) $candidate['autor']) : null,
            'url_origen' => trim((string) $candidate['url_origen']),
            'image_remote_url' => $candidate['image_remote_url'] ?? null,
            'source_published_at' => (string) $candidate['source_published_at'],
            'warnings' => is_array($candidate['warnings'] ?? null) ? $candidate['warnings'] : [],
        ];
    }
}
