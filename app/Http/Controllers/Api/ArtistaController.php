<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artista;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'imageUrl' => 'nullable|string|max:255',
            'heroImageUrl' => 'nullable|string|max:255',
            'secondaryImageUrl' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string',
            'spotifyEmbedUrl' => 'nullable|string|max:255',
            'youtubeVideoId' => 'nullable|string|max:255',
            'social_instagram' => 'nullable|string|max:255',
            'social_facebook' => 'nullable|string|max:255',
            'social_youtubeChannel' => 'nullable|string|max:255',
            'social_tiktok' => 'nullable|string|max:255',
            'social_spotifyProfile' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $artista = Artista::create($request->all());
        return response()->json($artista, 201);
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

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255', // 'sometimes' para que solo valide si está presente
            'imageUrl' => 'nullable|string|max:255',
            // ... añade el resto de campos con 'sometimes' o 'nullable' según corresponda
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $artista->update($request->all());
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
        $artista->delete();
        return response()->json(null, 204);
    }
}
