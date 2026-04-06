<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Models\ShopProduct;
use App\Models\ShopPromotion;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ShopPromotionController extends Controller
{
    public function index(Request $request)
    {
        $query = ShopPromotion::query()
            ->with(['products' => function ($relation) {
                $relation->select('shop_products.id', 'shop_products.name', 'shop_products.slug', 'shop_products.image_url');
            }])
            ->orderByDesc('created_at');

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        return response()->json(
            $query->get()->map(fn (ShopPromotion $promotion) => $this->transformPromotion($promotion))->values()
        );
    }

    public function show(string $id)
    {
        $promotion = ShopPromotion::query()
            ->with(['products' => function ($relation) {
                $relation->select('shop_products.id', 'shop_products.name', 'shop_products.slug', 'shop_products.image_url');
            }])
            ->where('id', $id)
            ->orWhere('slug', $id)
            ->first();

        if (!$promotion) {
            return response()->json(['message' => 'Promocion no encontrada'], 404);
        }

        return response()->json($this->transformPromotion($promotion));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $products = $this->parseProductsPayload($request->input('products'));
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? null, $data['name']);

        $promotion = ShopPromotion::create($data);
        $this->syncProducts($promotion, $products);

        return response()->json($this->transformPromotion($promotion->fresh(['products'])), 201);
    }

    public function update(Request $request, string $id)
    {
        $promotion = ShopPromotion::find($id);
        if (!$promotion) {
            return response()->json(['message' => 'Promocion no encontrada'], 404);
        }

        $data = $this->validateData($request, true, $promotion->id);
        if (array_key_exists('name', $data) || array_key_exists('slug', $data)) {
            $data['slug'] = $this->uniqueSlug($data['slug'] ?? $promotion->slug, $data['name'] ?? $promotion->name, $promotion->id);
        }

        $promotion->update($data);

        if ($request->has('products')) {
            $this->syncProducts($promotion, $this->parseProductsPayload($request->input('products')));
        }

        return response()->json($this->transformPromotion($promotion->fresh(['products'])));
    }

    public function destroy(string $id)
    {
        $promotion = ShopPromotion::find($id);
        if (!$promotion) {
            return response()->json(['message' => 'Promocion no encontrada'], 404);
        }

        $promotion->delete();
        return response()->json(null, 204);
    }

    private function validateData(Request $request, bool $isUpdate = false, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('shop_promotions', 'slug')->ignore($ignoreId)],
            'description' => ['nullable', 'string'],
            'promotion_type' => ['required', Rule::in(['percent_off', 'amount_off', 'buy_x_get_y', 'combo'])],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount_ars' => ['nullable', 'numeric', 'min:0'],
            'combo_price_ars' => ['nullable', 'numeric', 'min:0'],
            'buy_qty' => ['nullable', 'integer', 'min:1'],
            'get_qty' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'products' => ['nullable'],
        ]);

        if (array_key_exists('slug', $validated) && $validated['slug'] !== null) {
            $validated['slug'] = Str::slug($validated['slug']);
        }

        return $validated;
    }

    private function parseProductsPayload(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (!is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(function ($item) {
                if (is_numeric($item)) {
                    return [
                        'product_id' => (int) $item,
                        'required_quantity' => 1,
                    ];
                }

                if (!is_array($item) || empty($item['product_id'])) {
                    return null;
                }

                return [
                    'product_id' => (int) $item['product_id'],
                    'required_quantity' => max(1, (int) ($item['required_quantity'] ?? 1)),
                ];
            })
            ->filter()
            ->unique('product_id')
            ->values()
            ->all();
    }

    private function syncProducts(ShopPromotion $promotion, array $products): void
    {
        $syncPayload = [];
        foreach ($products as $item) {
            $product = ShopProduct::find($item['product_id']);
            if (!$product) {
                continue;
            }

            $syncPayload[$product->id] = ['required_quantity' => $item['required_quantity'] ?? 1];
        }

        $promotion->products()->sync($syncPayload);
    }

    private function transformPromotion(ShopPromotion $promotion): array
    {
        $promotion->loadMissing('products');

        return [
            'id' => $promotion->id,
            'slug' => $promotion->slug,
            'name' => $promotion->name,
            'description' => $promotion->description,
            'promotion_type' => $promotion->promotion_type,
            'discount_percent' => $promotion->discount_percent !== null ? (float) $promotion->discount_percent : null,
            'discount_amount_ars' => $promotion->discount_amount_ars !== null ? (float) $promotion->discount_amount_ars : null,
            'combo_price_ars' => $promotion->combo_price_ars !== null ? (float) $promotion->combo_price_ars : null,
            'buy_qty' => $promotion->buy_qty,
            'get_qty' => $promotion->get_qty,
            'is_active' => (bool) $promotion->is_active,
            'starts_at' => $promotion->starts_at?->toISOString(),
            'ends_at' => $promotion->ends_at?->toISOString(),
            'is_currently_active' => $promotion->isActiveNow(),
            'products' => $promotion->products->map(fn (ShopProduct $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'image_url' => $product->image_url,
                'required_quantity' => (int) ($product->pivot->required_quantity ?? 1),
            ])->values(),
        ];
    }

    private function uniqueSlug(?string $providedSlug, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($providedSlug ?: $name);
        $slug = $base;
        $counter = 2;

        while (
            ShopPromotion::query()
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
