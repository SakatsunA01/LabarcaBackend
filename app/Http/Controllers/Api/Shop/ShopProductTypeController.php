<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Models\ShopProductType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ShopProductTypeController extends Controller
{
    public function index()
    {
        return response()->json(ShopProductType::query()->orderBy('sort_order')->orderBy('name')->get());
    }

    public function show(string $id)
    {
        $type = ShopProductType::query()->where('id', $id)->orWhere('slug', $id)->first();
        if (!$type) {
            return response()->json(['message' => 'Tipo no encontrado'], 404);
        }
        return response()->json($type);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? null, $data['name']);
        return response()->json(ShopProductType::create($data), 201);
    }

    public function update(Request $request, string $id)
    {
        $type = ShopProductType::find($id);
        if (!$type) {
            return response()->json(['message' => 'Tipo no encontrado'], 404);
        }
        $data = $this->validateData($request, true, $type->id);
        if (array_key_exists('name', $data) || array_key_exists('slug', $data)) {
            $data['slug'] = $this->uniqueSlug($data['slug'] ?? $type->slug, $data['name'] ?? $type->name, $type->id);
        }
        $type->update($data);
        return response()->json($type);
    }

    public function destroy(string $id)
    {
        $type = ShopProductType::find($id);
        if (!$type) {
            return response()->json(['message' => 'Tipo no encontrado'], 404);
        }
        $type->delete();
        return response()->json(null, 204);
    }

    private function validateData(Request $request, bool $isUpdate = false, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('shop_product_types', 'slug')->ignore($ignoreId)],
            'description' => ['nullable', 'string'],
            'variant_config' => ['nullable'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('slug', $validated) && $validated['slug'] !== null) {
            $validated['slug'] = Str::slug($validated['slug']);
        }

        if (array_key_exists('variant_config', $validated)) {
            $validated['variant_config'] = $this->parseJsonValue($validated['variant_config']);
        }

        return $validated;
    }

    private function parseJsonValue(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : null;
    }

    private function uniqueSlug(?string $providedSlug, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($providedSlug ?: $name);
        $slug = $base;
        $counter = 2;

        while (
            ShopProductType::query()
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
