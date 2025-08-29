<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lanzamiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class LanzamientoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $lanzamientos = Lanzamiento::with('tracks', 'artista')->get(); // Eager load tracks and artista
        return response()->json($lanzamientos);
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

        $data = $request->except(['cover_image_url']);

        $data['cover_image_url'] = $this->handleImageUpload($request, 'cover_image_url');

        $lanzamiento = Lanzamiento::create($data);
        return response()->json($lanzamiento, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $lanzamiento = Lanzamiento::with('tracks')->find($id); // Eager load tracks
        if (is_null($lanzamiento)) {
            return response()->json(['message' => 'Lanzamiento no encontrado'], 404);
        }
        return response()->json($lanzamiento);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $lanzamiento = Lanzamiento::find($id);
        if (is_null($lanzamiento)) {
            return response()->json(['message' => 'Lanzamiento no encontrado'], 404);
        }

        $data = $request->except(['cover_image_url', '_method']);

        if ($request->hasFile('cover_image_url')) {
            $data['cover_image_url'] = $this->handleImageUpload($request, 'cover_image_url', $lanzamiento->cover_image_url);
        }

        $lanzamiento->update($data);
        return response()->json($lanzamiento);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $lanzamiento = Lanzamiento::find($id);
        if (is_null($lanzamiento)) {
            return response()->json(['message' => 'Lanzamiento no encontrado'], 404);
        }
        // Delete associated image if exists
        if ($lanzamiento->cover_image_url) {
            $oldPath = str_replace('/storage', '', $lanzamiento->cover_image_url);
            Storage::disk('public')->delete($oldPath);
        }
        $lanzamiento->delete();
        return response()->json(null, 204);
    }

    private function validationRules($isUpdate = false)
    {
        $sometimes = $isUpdate ? 'sometimes|' : '';
        return [
            'titulo' => 'required|string|max:255',
            'artista_id' => 'required|exists:artistas,id',
            'fecha_lanzamiento' => 'required|date',
            'cover_image_url' => $sometimes . 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10240',
            'youtube_link' => 'nullable|string|max:255',
            'spotify_link' => 'nullable|string|max:255',
        ];
    }

    /**
     * Display the latest resources.
     */
    public function latest()
    {
        // Get the 3 most recent releases, ordered by fecha_lanzamiento
        $lanzamientos = Lanzamiento::with('tracks') // Eager load tracks
                                ->orderBy('fecha_lanzamiento', 'desc')
                                ->take(3) // Changed from 5 to 3
                                ->get();
        return response()->json($lanzamientos);
    }

    private function handleImageUpload(Request $request, $fieldName, $oldImagePath = null)
    {
        if ($request->hasFile($fieldName)) {
            // Eliminar la imagen anterior si existe
            if ($oldImagePath) {
                $oldPath = str_replace('/storage', '', $oldImagePath);
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file($fieldName)->store('lanzamientos', 'public');
            return Storage::url($path);
        }
        return null;
    }
}