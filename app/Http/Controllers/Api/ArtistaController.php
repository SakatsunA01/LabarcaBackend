<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artista;
use App\Models\ArtistCategory;
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
        $artistas = Artista::with('categories')->orderBy('name')->get();
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

        $data = $request->except(['imageUrl', 'heroImageUrl', 'secondaryImageUrl', 'category_ids']);

        $categoryIds = $this->parseCategoryIds($request->input('category_ids'));
        if (is_null($categoryIds)) {
            return response()->json(['category_ids' => ['Formato invalido para category_ids']], 400);
        }
        if (!$this->categoriesExist($categoryIds)) {
            return response()->json(['category_ids' => ['Algunas categorias no existen']], 400);
        }

        // Handle image uploads
        $data['imageUrl'] = $this->handleImageUpload($request, 'imageUrl');
        $data['heroImageUrl'] = $this->handleImageUpload($request, 'heroImageUrl');
        $data['secondaryImageUrl'] = $this->handleImageUpload($request, 'secondaryImageUrl');

        $artista = Artista::create($data);
        $artista->categories()->sync($categoryIds);

        return response()->json($artista->load('categories'), 201);
    }

    private function validationRules($isUpdate = false)
    {
        $sometimes = $isUpdate ? 'sometimes|' : '';
        return [
            'name' => 'required|string|max:255',
            'imageUrl' => $sometimes . 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10240',
            'heroImageUrl' => $sometimes . 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10240',
            'secondaryImageUrl' => $sometimes . 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10240',
            'vinculacion' => $sometimes . 'nullable|in:interno,colaborador',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string',
            'spotifyEmbedUrl' => 'nullable|string|max:255',
            'youtubeVideoId' => 'nullable|string|max:255',
            'social_instagram' => 'nullable|string|max:255',
            'social_facebook' => 'nullable|string|max:255',
            'social_youtubeChannel' => 'nullable|string|max:255',
            'social_tiktok' => 'nullable|string|max:255',
            'social_spotifyProfile' => 'nullable|string|max:255',
            'category_ids' => 'nullable',
        ];
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $artista = Artista::with('categories')->find($id);
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

        $validator = Validator::make($request->all(), $this->validationRules(true));
        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $data = $request->except(['imageUrl', 'heroImageUrl', 'secondaryImageUrl', '_method', 'category_ids']);

        $syncCategories = false;
        $categoryIds = [];
        if ($request->has('category_ids')) {
            $parsedCategoryIds = $this->parseCategoryIds($request->input('category_ids'));
            if (is_null($parsedCategoryIds)) {
                return response()->json(['category_ids' => ['Formato invalido para category_ids']], 400);
            }
            if (!$this->categoriesExist($parsedCategoryIds)) {
                return response()->json(['category_ids' => ['Algunas categorias no existen']], 400);
            }
            $syncCategories = true;
            $categoryIds = $parsedCategoryIds;
        }

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
        if ($syncCategories) {
            $artista->categories()->sync($categoryIds);
        }

        return response()->json($artista->load('categories'));
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

            return '/public/storage/' . $path;
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

    private function parseCategoryIds($value): ?array
    {
        if (is_null($value) || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                return null;
            }
            $value = $decoded;
        }

        if (!is_array($value)) {
            return null;
        }

        return array_values(
            array_unique(
                array_filter(
                    array_map(fn ($id) => (int) $id, $value),
                    fn ($id) => $id > 0
                )
            )
        );
    }

    private function categoriesExist(array $ids): bool
    {
        if (empty($ids)) {
            return true;
        }

        return ArtistCategory::whereIn('id', $ids)->count() === count($ids);
    }
}
