<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromoVideo;
use App\Support\PromoVideoToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PromoVideoController extends Controller
{
    public function showPublic()
    {
        $promo = PromoVideo::query()->where('is_active', true)->latest()->first();
        if (!$promo || !$promo->video_path) {
            return response()->json(['message' => 'No hay video promocional disponible.'], 404);
        }

        $token = PromoVideoToken::make($promo->video_path, 900);

        return response()->json([
            'title' => $promo->title,
            'description' => $promo->description,
            'stream_url' => url('/api/promo-video/stream?token=' . $token),
        ]);
    }

    public function stream(Request $request)
    {
        $token = $request->query('token');
        if (!$token) {
            return response()->json(['message' => 'Token requerido.'], 400);
        }

        $payload = PromoVideoToken::parse($token);
        if (!$payload || empty($payload['path']) || empty($payload['exp'])) {
            return response()->json(['message' => 'Token invalido.'], 401);
        }

        if (now()->timestamp > (int) $payload['exp']) {
            return response()->json(['message' => 'Token expirado.'], 401);
        }

        $relativePath = $this->normalizePath($payload['path']);
        $disk = $this->resolveDisk($relativePath);
        if (!$disk) {
            return response()->json(['message' => 'Video no encontrado.'], 404);
        }

        $filePath = $disk->path($relativePath);
        $fileSize = filesize($filePath);
        $mimeType = mime_content_type($filePath) ?: 'video/mp4';

        $range = $request->header('Range');
        $start = 0;
        $end = $fileSize - 1;
        $status = 200;

        if ($range && preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {
            $status = 206;
            if ($matches[1] !== '') {
                $start = (int) $matches[1];
            }
            if ($matches[2] !== '') {
                $end = (int) $matches[2];
            }
            if ($end > $fileSize - 1) {
                $end = $fileSize - 1;
            }
            if ($start > $end) {
                return response()->json(['message' => 'Rango invalido.'], 416);
            }
        }

        $length = $end - $start + 1;

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Length' => $length,
            'Accept-Ranges' => 'bytes',
        ];

        if ($status === 206) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$fileSize}";
        }

        return response()->stream(function () use ($filePath, $start, $length) {
            $chunk = 8192;
            $handle = fopen($filePath, 'rb');
            fseek($handle, $start);
            $bytesRemaining = $length;
            while ($bytesRemaining > 0 && !feof($handle)) {
                $read = min($chunk, $bytesRemaining);
                $data = fread($handle, $read);
                if ($data === false) {
                    break;
                }
                echo $data;
                $bytesRemaining -= $read;
            }
            fclose($handle);
        }, $status, $headers);
    }

    public function showAdmin()
    {
        $promo = PromoVideo::query()->latest()->first();
        if (!$promo) {
            return response()->json(null);
        }

        $token = $promo->video_path ? PromoVideoToken::make($promo->video_path, 900) : null;
        return response()->json([
            'id' => $promo->id,
            'title' => $promo->title,
            'description' => $promo->description,
            'video_path' => $promo->video_path,
            'is_active' => $promo->is_active,
            'stream_url' => $token ? url('/api/promo-video/stream?token=' . $token) : null,
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:204800'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $promo = PromoVideo::query()->latest()->first();
        if (!$promo) {
            $promo = new PromoVideo();
            $promo->created_by = $request->user()->id ?? null;
        }

        $data = $request->only(['title', 'description', 'is_active']);
        $data['updated_by'] = $request->user()->id ?? null;

        if ($request->hasFile('video')) {
            if ($promo->video_path) {
                $oldPath = $this->normalizePath($promo->video_path);
                Storage::disk('local')->delete($oldPath);
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('video')->store('promo_videos', 'local');
            $data['video_path'] = $path;
        }

        $promo->fill($data);
        $promo->save();

        $token = $promo->video_path ? PromoVideoToken::make($promo->video_path, 900) : null;

        return response()->json([
            'message' => 'Video promocional actualizado.',
            'promo' => [
                'id' => $promo->id,
                'title' => $promo->title,
                'description' => $promo->description,
                'video_path' => $promo->video_path,
                'is_active' => $promo->is_active,
                'stream_url' => $token ? url('/api/promo-video/stream?token=' . $token) : null,
            ],
        ]);
    }

    private function normalizePath(string $path): string
    {
        $normalized = str_replace('/public/storage/', '', $path);
        return ltrim($normalized, '/');
    }

    private function resolveDisk(string $relativePath): ?\Illuminate\Contracts\Filesystem\Filesystem
    {
        if (Storage::disk('local')->exists($relativePath)) {
            return Storage::disk('local');
        }
        if (Storage::disk('public')->exists($relativePath)) {
            return Storage::disk('public');
        }
        return null;
    }
}
