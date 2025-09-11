<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artista;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ArtistaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $artistas = Artista::all();
        return response()->json($artistas);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->validationRules());

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $data = $request->except(['imageUrl', 'heroImageUrl', 'secondaryImageUrl']);

        // Manejar la subida de imágenes
        $data['imageUrl'] = $this->handleImageUpload($request, 'imageUrl');
        $data['heroImageUrl'] = $this->handleImageUpload($request, 'heroImageUrl');
        $data['secondaryImageUrl'] = $this->handleImageUpload($request, 'secondaryImageUrl');

        $artista = Artista::create($data);
        return response()->json($artista, 201);
    }

    private function validationRules($isUpdate = false)
    {
        $sometimes = $isUpdate ? 'sometimes|' : '';
        return [
            'name' => 'required|string|max:255',
            'imageUrl' => $sometimes . 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10240',
            'heroImageUrl' => $sometimes . 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10240',
            'secondaryImageUrl' => $sometimes . 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10240',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string',
            'spotifyEmbedUrl' => 'nullable|string|max:255',
            'youtubeVideoId' => 'nullable|string|max:255',
            'social_instagram' => 'nullable|string|max:255',
            'social_facebook' => 'nullable|string|max:255',
            'social_youtubeChannel' => 'nullable|string|max:255',
            'social_tiktok' => 'nullable|string|max:255',
            'social_spotifyProfile' => 'nullable|string|max:255',
        ];
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $artista = Artista::find($id);
        if (is_null($artista)) {
            return response()->json(['message' => 'Artista no encontrado'], 404);
        }
        return response()->json($artista);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $artista = Artista::find($id);
        if (is_null($artista)) {
            return response()->json(['message' => 'Artista no encontrado'], 404);
        }

        $data = $request->except(['imageUrl', 'heroImageUrl', 'secondaryImageUrl', '_method']);

        if ($request->hasFile('imageUrl')) {
            $data['imageUrl'] = $this->handleImageUpload($request, 'imageUrl', $artista->imageUrl);
        }
        if ($request->hasFile('heroImageUrl')) {
            $data['heroImageUrl'] = $this->handleImageUpload($request, 'heroImageUrl', $artista->heroImageUrl);
        }
        if ($request->hasFile('secondaryImageUrl')) {
            $data['secondaryImageUrl'] = $this->handleImageUpload($request, 'secondaryImageUrl', $artista->secondaryImageUrl);
        }

        $artista->update($data);
        return response()->json($artista);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $artista = Artista::find($id);
        if (is_null($artista)) {
            return response()->json(['message' => 'Artista no encontrado'], 404);
        }

        $this->deleteImage($artista->imageUrl);
        $this->deleteImage($artista->heroImageUrl);
        $this->deleteImage($artista->secondaryImageUrl);

        $artista->delete();
        return response()->json(null, 204);
    }

    private function handleImageUpload(Request $request, $fieldName, $oldImagePath = null)
    {
        if ($request->hasFile($fieldName)) {
            if ($oldImagePath) {
                $this->deleteImage($oldImagePath);
            }
            $path = $request->file($fieldName)->store('artistas', 'public');
            return Storage::url($path);
        }
        return $oldImagePath;
    }

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