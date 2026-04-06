<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Models\ShopCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ShopCategoryController extends Controller
{
    public function index()
    {
        return response()->json(ShopCategory::query()->orderBy('sort_order')->orderBy('name')->get());
    }

    public function show(string $id)
    {
        $category = ShopCategory::query()->where('id', $id)->orWhere('slug', $id)->first();
        if (!$category) {
            return response()->json(['message' => 'Categoria no encontrada'], 404);
        }
        return response()->json($category);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? null, $data['name']);
        return response()->json(ShopCategory::create($data), 201);
    }

    public function update(Request $request, string $id)
    {
        $category = ShopCategory::find($id);
        if (!$category) {
            return response()->json(['message' => 'Categoria no encontrada'], 404);
        }
        $data = $this->validateData($request, true, $category->id);
        if (array_key_exists('name', $data) || array_key_exists('slug', $data)) {
            $data['slug'] = $this->uniqueSlug($data['slug'] ?? $category->slug, $data['name'] ?? $category->name, $category->id);
        }
        $category->update($data);
        return response()->json($category);
    }

    public function destroy(string $id)
    {
        $category = ShopCategory::find($id);
        if (!$category) {
            return response()->json(['message' => 'Categoria no encontrada'], 404);
        }
        $category->delete();
        return response()->json(null, 204);
    }

    private function validateData(Request $request, bool $isUpdate = false, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('shop_categories', 'slug')->ignore($ignoreId)],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('slug', $validated) && $validated['slug'] !== null) {
            $validated['slug'] = Str::slug($validated['slug']);
        }

        return $validated;
    }

    private function uniqueSlug(?string $providedSlug, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($providedSlug ?: $name);
        $slug = $base;
        $counter = 2;

        while (
            ShopCategory::query()
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
