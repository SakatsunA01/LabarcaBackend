<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminMediaController extends Controller
{
    // Todos los archivos con filtro opcional por user_id
    public function index(Request $request)
    {
        $query = MediaFile::with(['category', 'user:id,name,email'])->latest();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('category_id')) {
            $query->where('media_category_id', $request->category_id);
        }

        return response()->json($query->get());
    }

    // Toggle is_downloadable
    public function toggleDownloadable(MediaFile $mediaFile)
    {
        $mediaFile->update(['is_downloadable' => !$mediaFile->is_downloadable]);
        return response()->json($mediaFile->load(['category', 'user:id,name,email']));
    }

    // Eliminar desde admin
    public function destroy(MediaFile $mediaFile)
    {
        Storage::disk($mediaFile->disk)->delete($mediaFile->file_path);
        $mediaFile->delete();
        return response()->json(null, 204);
    }
}
