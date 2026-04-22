<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artista;
use App\Models\MediaCategory;
use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaFileController extends Controller
{
    // Categorías (público)
    public function categories()
    {
        return response()->json(MediaCategory::orderBy('name')->get());
    }

    // Archivos del artista autenticado
    public function index(Request $request)
    {
        $files = MediaFile::with('category')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($files);
    }

    // Archivos públicos de un artista (para clientes)
    public function artistaFiles(Artista $artista)
    {
        if (!$artista->user_id) {
            return response()->json([]);
        }

        $files = MediaFile::with('category')
            ->where('user_id', $artista->user_id)
            ->latest()
            ->get();

        return response()->json($files);
    }

    // Upload
    public function store(Request $request)
    {
        $request->validate([
            'file'              => 'required|file|max:512000', // 500 MB
            'title'             => 'required|string|max:255',
            'media_category_id' => 'required|exists:media_categories,id',
        ]);

        $file = $request->file('file');
        $userId = $request->user()->id;
        $extension = $file->getClientOriginalExtension();
        $path = "media/{$userId}/" . Str::uuid() . ".{$extension}";

        Storage::disk('public')->put($path, file_get_contents($file));

        $media = MediaFile::create([
            'user_id'           => $userId,
            'media_category_id' => $request->media_category_id,
            'title'             => $request->title,
            'file_path'         => $path,
            'file_name'         => $file->getClientOriginalName(),
            'mime_type'         => $file->getMimeType(),
            'file_size'         => $file->getSize(),
            'is_downloadable'   => true,
            'disk'              => 'public',
        ]);

        return response()->json($media->load('category'), 201);
    }

    // Editar titulo y categoría
    public function update(Request $request, MediaFile $mediaFile)
    {
        $user = $request->user()->load('roles');

        if ($mediaFile->user_id !== $user->id && !$user->admin_sn && !$user->canAccessAdmin()) {
            return response()->json(['message' => 'Sin permiso.'], 403);
        }

        $request->validate([
            'title'             => 'sometimes|required|string|max:255',
            'media_category_id' => 'sometimes|required|exists:media_categories,id',
        ]);

        $mediaFile->update($request->only('title', 'media_category_id'));

        return response()->json($mediaFile->load('category'));
    }

    // Eliminar
    public function destroy(Request $request, MediaFile $mediaFile)
    {
        $user = $request->user()->load('roles');

        if ($mediaFile->user_id !== $user->id && !$user->admin_sn && !$user->canAccessAdmin()) {
            return response()->json(['message' => 'Sin permiso.'], 403);
        }

        Storage::disk($mediaFile->disk)->delete($mediaFile->file_path);
        $mediaFile->delete();

        return response()->json(null, 204);
    }

    // Descarga
    public function download(Request $request, MediaFile $mediaFile)
    {
        $user = $request->user()->load('roles');
        $isOwner = $mediaFile->user_id === $user->id;
        $isAdmin = $user->admin_sn || $user->canAccessAdmin();

        if (!$mediaFile->is_downloadable && !$isOwner && !$isAdmin) {
            return response()->json(['message' => 'Descarga no permitida.'], 403);
        }

        $fullPath = Storage::disk($mediaFile->disk)->path($mediaFile->file_path);

        return response()->download($fullPath, $mediaFile->file_name);
    }
}
