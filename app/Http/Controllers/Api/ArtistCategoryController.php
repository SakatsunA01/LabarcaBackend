<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArtistCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ArtistCategoryController extends Controller
{
    public function index()
    {
        return response()->json(ArtistCategory::orderBy('nombre')->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255|unique:artist_categories,nombre',
            'slug' => 'nullable|string|max:255|unique:artist_categories,slug',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $nombre = trim((string) $request->input('nombre'));
        $slug = trim((string) ($request->input('slug') ?: Str::slug($nombre)));

        $category = ArtistCategory::create([
            'nombre' => $nombre,
            'slug' => $slug,
        ]);

        return response()->json($category, 201);
    }

    public function show(string $id)
    {
        $category = ArtistCategory::find($id);
        if (!$category) {
            return response()->json(['message' => 'Categoria no encontrada'], 404);
        }

        return response()->json($category);
    }

    public function update(Request $request, string $id)
    {
        $category = ArtistCategory::find($id);
        if (!$category) {
            return response()->json(['message' => 'Categoria no encontrada'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255|unique:artist_categories,nombre,' . $id,
            'slug' => 'nullable|string|max:255|unique:artist_categories,slug,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $nombre = trim((string) $request->input('nombre'));
        $slug = trim((string) ($request->input('slug') ?: Str::slug($nombre)));

        $category->update([
            'nombre' => $nombre,
            'slug' => $slug,
        ]);

        return response()->json($category);
    }

    public function destroy(string $id)
    {
        $category = ArtistCategory::find($id);
        if (!$category) {
            return response()->json(['message' => 'Categoria no encontrada'], 404);
        }

        $category->delete();
        return response()->json(null, 204);
    }
}
