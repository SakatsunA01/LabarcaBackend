<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\GaleriaEvento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class GaleriaEventoController extends Controller
{
    public function indexForEvento(string $eventoId)
    {
        if (!Evento::find($eventoId)) {
            return response()->json(['message' => 'Evento no encontrado'], 404);
        }

        $media = GaleriaEvento::where('id_evento', $eventoId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($media);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_evento' => 'required|exists:eventos,id',
            'media_file' => 'required|file|mimetypes:image/jpeg,image/png,image/jpg,image/gif,image/svg+xml,image/webp,video/mp4,video/quicktime,video/webm|max:51200',
            'descripcion' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $data = $request->except(['media_file']);
        [$data['url_imagen'], $data['media_type']] = $this->handleMediaUpload($request, 'media_file');

        $media = GaleriaEvento::create($data);

        return response()->json($media, 201);
    }

    public function show(string $id)
    {
        $media = GaleriaEvento::find($id);
        if (is_null($media)) {
            return response()->json(['message' => 'Media de galeria no encontrada'], 404);
        }

        return response()->json($media);
    }

    public function update(Request $request, string $id)
    {
        $media = GaleriaEvento::find($id);
        if (is_null($media)) {
            return response()->json(['message' => 'Media de galeria no encontrada'], 404);
        }

        $validator = Validator::make($request->all(), [
            'id_evento' => 'sometimes|required|exists:eventos,id',
            'media_file' => 'nullable|file|mimetypes:image/jpeg,image/png,image/jpg,image/gif,image/svg+xml,image/webp,video/mp4,video/quicktime,video/webm|max:51200',
            'descripcion' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $data = $request->except(['media_file', '_method']);

        if ($request->hasFile('media_file')) {
            [$data['url_imagen'], $data['media_type']] = $this->handleMediaUpload($request, 'media_file', $media->url_imagen);
        } elseif (array_key_exists('url_imagen', $request->all()) && ($request->input('url_imagen') === null || $request->input('url_imagen') === '')) {
            $this->deleteMedia($media->url_imagen);
            $data['url_imagen'] = null;
            $data['media_type'] = 'image';
        }

        $media->update($data);

        return response()->json($media);
    }

    public function destroy(string $id)
    {
        $media = GaleriaEvento::find($id);
        if (is_null($media)) {
            return response()->json(['message' => 'Media de galeria no encontrada'], 404);
        }

        if ($media->url_imagen) {
            $this->deleteMedia($media->url_imagen);
        }

        $media->delete();

        return response()->json(null, 204);
    }

    private function handleMediaUpload(Request $request, string $fieldName, ?string $oldMediaPath = null): array
    {
        if (!$request->hasFile($fieldName)) {
            return [$oldMediaPath, 'image'];
        }

        if ($oldMediaPath) {
            $this->deleteMedia($oldMediaPath);
        }

        $file = $request->file($fieldName);
        $mime = $file->getMimeType() ?: '';
        $isVideo = str_starts_with($mime, 'video/');
        $directory = $isVideo ? 'galeria_eventos/videos' : 'galeria_eventos/images';
        $path = $file->store($directory, 'public');

        return ['/public/storage/' . $path, $isVideo ? 'video' : 'image'];
    }

    private function deleteMedia(?string $mediaPath): void
    {
        if (!$mediaPath) {
            return;
        }

        $path = parse_url($mediaPath, PHP_URL_PATH);
        if (!$path) {
            return;
        }

        $path = str_replace('/public/storage/', '', $path);
        Storage::disk('public')->delete($path);
    }
}
