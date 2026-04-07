<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Models\Artista;
use App\Models\Evento;
use App\Models\ShopProduct;
use App\Models\ShopProductMedia;
use App\Models\ShopProductVariant;
use App\Models\ShopPromotion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ShopProductController extends Controller
{
    public function index(Request $request)
    {
        $products = $this->baseQuery($request)
            ->orderByDesc('is_featured')
            ->orderBy('featured_order')
            ->orderByDesc('created_at')
            ->paginate((int) $request->input('per_page', 24));

        $products->getCollection()->transform(fn (ShopProduct $product) => $this->transformProduct($product));

        return response()->json($products);
    }

    public function show(string $id)
    {
        $product = ShopProduct::query()
            ->with($this->relations())
            ->where('id', $id)
            ->orWhere('slug', $id)
            ->first();

        if (!$product || !$product->is_active) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        return response()->json($this->transformProduct($product));
    }

    public function adminIndex()
    {
        $products = ShopProduct::query()
            ->with($this->relations())
            ->orderByDesc('is_featured')
            ->orderBy('featured_order')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ShopProduct $product) => $this->transformProduct($product));

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $validated = $this->validateProduct($request);
        $validated['slug'] = $this->uniqueSlug($validated['slug'] ?? null, $validated['name']);

        $product = ShopProduct::create($validated);
        $this->syncRelations($product, $request);
        $this->syncVariants($product, $request->input('variants'));
        $this->syncPromotions($product, $request->input('promotions'));
        $this->syncMedia($product, $request);

        return response()->json($this->transformProduct($product->fresh($this->relations())), 201);
    }

    public function update(Request $request, string $id)
    {
        $product = ShopProduct::find($id);
        if (!$product) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $validated = $this->validateProduct($request, true, $product->id);
        if (array_key_exists('slug', $validated) || array_key_exists('name', $validated)) {
            $validated['slug'] = $this->uniqueSlug($validated['slug'] ?? $product->slug, $validated['name'] ?? $product->name, $product->id);
        }

        $product->update($validated);

        $this->syncRelations($product, $request);
        if ($request->has('variants')) {
            $this->syncVariants($product, $request->input('variants'));
        }
        if ($request->has('promotions')) {
            $this->syncPromotions($product, $request->input('promotions'));
        }
        if ($request->has('remove_media_ids') || $request->has('media') || $request->hasFile('media_files')) {
            $this->syncMedia($product, $request);
        }

        return response()->json($this->transformProduct($product->fresh($this->relations())));
    }

    public function destroy(string $id)
    {
        $product = ShopProduct::with('media')->find($id);
        if (!$product) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $this->deleteMediaFiles($product);
        $product->delete();

        return response()->json(null, 204);
    }

    private function relations(): array
    {
        return [
            'category',
            'type',
            'variants' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            'media' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            'promotions.products',
            'artists',
            'eventos',
        ];
    }

    private function baseQuery(Request $request)
    {
        $query = ShopProduct::query()
            ->with($this->relations())
            ->where('is_active', true);

        if ($request->filled('category_id')) {
            $query->where('shop_category_id', $request->input('category_id'));
        }

        if ($request->filled('type_id')) {
            $query->where('shop_product_type_id', $request->input('type_id'));
        }

        if ($request->filled('artist_id')) {
            $query->whereHas('artists', fn ($relation) => $relation->where('artistas.id', $request->input('artist_id')));
        }

        if ($request->filled('event_id')) {
            $query->whereHas('eventos', fn ($relation) => $relation->where('eventos.id', $request->input('event_id')));
        }

        if ($request->boolean('featured', false)) {
            $query->where('is_featured', true);
        }

        if ($request->boolean('available_only', false)) {
            $query->where(function ($relation) {
                $relation->where('stock', '>', 0)
                    ->orWhereHas('variants', fn ($variantQuery) => $variantQuery->where('stock', '>', 0)->where('is_active', true));
            });
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($relation) use ($search) {
                $relation->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        return $query;
    }

    private function validateProduct(Request $request, bool $isUpdate = false, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('shop_products', 'slug')->ignore($ignoreId)],
            'excerpt' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price_ars' => ['nullable', 'numeric', 'min:0'],
            'base_price_ars' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'base_stock' => ['nullable', 'integer', 'min:0'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'image_file' => ['nullable', 'file', 'max:20480'],
            'shop_category_id' => ['nullable', 'exists:shop_categories,id'],
            'shop_product_type_id' => ['nullable', 'exists:shop_product_types,id'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'featured' => ['nullable', 'boolean'],
            'featured_order' => ['nullable', 'integer', 'min:0'],
            'track_stock' => ['nullable', 'boolean'],
            'has_variants' => ['nullable', 'boolean'],
            'requires_shipping' => ['nullable', 'boolean'],
            'allow_pickup' => ['nullable', 'boolean'],
            'artist_ids' => ['nullable'],
            'event_ids' => ['nullable'],
            'variants' => ['nullable'],
            'promotions' => ['nullable'],
            'remove_media_ids' => ['nullable'],
            'media' => ['nullable'],
            'media_files' => ['nullable'],
            'media_types' => ['nullable'],
            'media_alt_texts' => ['nullable'],
        ]);

        if (array_key_exists('base_price_ars', $validated)) {
            $validated['price_ars'] = $validated['base_price_ars'];
        }

        if (array_key_exists('base_stock', $validated)) {
            $validated['stock'] = $validated['base_stock'];
        }

        if (array_key_exists('featured', $validated)) {
            $validated['is_featured'] = $validated['featured'];
        }

        if (!$isUpdate && !array_key_exists('price_ars', $validated)) {
            $validated['price_ars'] = 0;
        }

        if (!$isUpdate && !array_key_exists('stock', $validated)) {
            $validated['stock'] = 0;
        }

        if (array_key_exists('slug', $validated) && $validated['slug'] !== null) {
            $validated['slug'] = Str::slug($validated['slug']);
        }

        if ($request->hasFile('image_file')) {
            $validated['image_url'] = $this->storeUpload($request->file('image_file'), 'shop/products');
        }

        unset(
            $validated['image_file'],
            $validated['base_price_ars'],
            $validated['base_stock'],
            $validated['featured'],
            $validated['excerpt'],
            $validated['has_variants'],
            $validated['requires_shipping'],
            $validated['allow_pickup'],
            $validated['artist_ids'],
            $validated['event_ids'],
            $validated['variants'],
            $validated['promotions'],
            $validated['remove_media_ids'],
            $validated['media'],
            $validated['media_files'],
            $validated['media_types'],
            $validated['media_alt_texts']
        );

        return $validated;
    }

    private function syncRelations(ShopProduct $product, Request $request): void
    {
        if ($request->has('artist_ids')) {
            $product->artists()->sync($this->normalizeIds($request->input('artist_ids')));
        }

        if ($request->has('event_ids')) {
            $product->eventos()->sync($this->normalizeIds($request->input('event_ids')));
        }
    }

    private function syncVariants(ShopProduct $product, mixed $payload): void
    {
        $variants = $this->parseJsonArray($payload);
        if ($variants === null) {
            return;
        }

        $keepIds = [];
        foreach ($variants as $item) {
            if (!is_array($item)) {
                continue;
            }

            $data = [
                'label' => trim((string) ($item['label'] ?? $item['name'] ?? '')),
                'color' => $item['color'] ?? null,
                'size' => $item['size'] ?? null,
                'price_ars' => isset($item['price_ars']) ? (float) $item['price_ars'] : (float) $product->price_ars,
                'stock' => (int) ($item['stock'] ?? 0),
                'is_active' => array_key_exists('is_active', $item) ? (bool) $item['is_active'] : true,
                'sort_order' => (int) ($item['sort_order'] ?? 0),
                'sku' => $item['sku'] ?? null,
            ];

            if (!empty($item['id'])) {
                $variant = ShopProductVariant::where('shop_product_id', $product->id)->where('id', $item['id'])->first();
                if ($variant) {
                    $variant->update($data);
                    $keepIds[] = $variant->id;
                    continue;
                }
            }

            $variant = $product->variants()->create($data);
            $keepIds[] = $variant->id;
        }

        if ($keepIds) {
            $product->variants()->whereNotIn('id', $keepIds)->delete();
        } else {
            $product->variants()->delete();
        }
    }

    private function syncPromotions(ShopProduct $product, mixed $payload): void
    {
        $promotions = $this->parseJsonArray($payload);
        if ($promotions === null) {
            return;
        }

        $syncIds = [];

        foreach ($promotions as $item) {
            if (!is_array($item) || empty($item['name'])) {
                continue;
            }

            $type = $item['promotion_type'] ?? $item['type'] ?? null;
            if (!$type) {
                continue;
            }

            $normalizedType = match ($type) {
                'percentage_off' => 'percent_off',
                'fixed_off' => 'amount_off',
                default => $type,
            };

            $data = [
                'slug' => $this->uniquePromotionSlug($item['slug'] ?? null, (string) $item['name'], !empty($item['id']) ? (int) $item['id'] : null),
                'name' => trim((string) $item['name']),
                'description' => $item['description'] ?? null,
                'promotion_type' => $normalizedType,
                'discount_percent' => $normalizedType === 'percent_off' ? ($item['discount_percent'] ?? $item['value'] ?? null) : null,
                'discount_amount_ars' => $normalizedType === 'amount_off' ? ($item['discount_amount_ars'] ?? $item['value'] ?? null) : null,
                'combo_price_ars' => $item['combo_price_ars'] ?? null,
                'buy_qty' => $item['buy_qty'] ?? null,
                'get_qty' => $item['get_qty'] ?? $item['free_qty'] ?? null,
                'is_active' => array_key_exists('is_active', $item) ? (bool) $item['is_active'] : true,
                'starts_at' => $item['starts_at'] ?? null,
                'ends_at' => $item['ends_at'] ?? null,
            ];

            $promotion = !empty($item['id']) ? ShopPromotion::find((int) $item['id']) : null;
            if ($promotion) {
                $promotion->update($data);
            } else {
                $promotion = ShopPromotion::create($data);
            }

            $syncPayload = [$product->id => ['required_quantity' => 1]];

            if ($normalizedType === 'combo') {
                $comboProductIds = collect($item['combo_product_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values();

                foreach ($comboProductIds as $comboProductId) {
                    $syncPayload[$comboProductId] = ['required_quantity' => 1];
                }
            }

            $promotion->products()->sync($syncPayload);
            $syncIds[] = $promotion->id;
        }

        if ($syncIds) {
            $product->promotions()->whereNotIn('shop_promotions.id', $syncIds)->detach();
        } else {
            $product->promotions()->detach();
        }
    }

    private function syncMedia(ShopProduct $product, Request $request): void
    {
        $removeMediaIds = $this->normalizeIds($request->input('remove_media_ids'));
        if ($removeMediaIds) {
            $mediaToRemove = $product->media()->whereIn('id', $removeMediaIds)->get();
            foreach ($mediaToRemove as $media) {
                $this->deleteUpload($media->url);
                if ($media->thumbnail_url) {
                    $this->deleteUpload($media->thumbnail_url);
                }
            }
            $product->media()->whereIn('id', $removeMediaIds)->delete();
        }

        if ($request->has('media')) {
            $mediaItems = $this->parseJsonArray($request->input('media')) ?? [];
            foreach ($mediaItems as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $url = trim((string) ($item['url'] ?? ''));
                if ($url === '') {
                    continue;
                }

                $product->media()->updateOrCreate(
                    ['id' => $item['id'] ?? null],
                    [
                        'media_type' => $item['media_type'] ?? $item['type'] ?? 'image',
                        'url' => $url,
                        'thumbnail_url' => $item['thumbnail_url'] ?? null,
                        'alt_text' => $item['alt_text'] ?? null,
                        'sort_order' => (int) ($item['sort_order'] ?? 0),
                        'is_primary' => array_key_exists('is_primary', $item) ? (bool) $item['is_primary'] : (bool) ($item['is_featured'] ?? false),
                    ]
                );
            }
        }

        if ($request->hasFile('media_files')) {
            $files = $request->file('media_files');
            if (!is_array($files)) {
                $files = [$files];
            }

            $types = $this->normalizeIndexedValues($request->input('media_types'));
            $alts = $this->normalizeIndexedValues($request->input('media_alt_texts'));
            $existingCount = (int) $product->media()->count();

            foreach ($files as $index => $file) {
                $mediaType = $types[$index] ?? (str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image');
                $storedPath = $this->storeUpload($file, 'shop/products/media');

                $product->media()->create([
                    'media_type' => $mediaType === 'video' ? 'video' : 'image',
                    'url' => $storedPath,
                    'thumbnail_url' => null,
                    'alt_text' => $alts[$index] ?? null,
                    'sort_order' => $existingCount + $index,
                    'is_primary' => $existingCount === 0 && $index === 0,
                ]);
            }
        }
    }

    private function deleteMediaFiles(ShopProduct $product): void
    {
        foreach ($product->media as $media) {
            $this->deleteUpload($media->url);
            if ($media->thumbnail_url) {
                $this->deleteUpload($media->thumbnail_url);
            }
        }
    }

    private function transformProduct(ShopProduct $product): array
    {
        $product->loadMissing($this->relations());

        $variants = $product->variants->map(fn (ShopProductVariant $variant) => [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'label' => $variant->label,
            'name' => $variant->label,
            'color' => $variant->color,
            'size' => $variant->size,
            'price_ars' => (float) $variant->price_ars,
            'stock' => $variant->stock,
            'is_active' => (bool) $variant->is_active,
            'sort_order' => $variant->sort_order,
        ])->values();

        $media = $product->media->map(fn (ShopProductMedia $item) => [
            'id' => $item->id,
            'media_type' => $item->media_type,
            'type' => $item->media_type,
            'url' => $this->normalizeStorageUrl($item->url),
            'thumbnail_url' => $this->normalizeStorageUrl($item->thumbnail_url),
            'alt_text' => $item->alt_text,
            'sort_order' => $item->sort_order,
            'is_primary' => (bool) $item->is_primary,
            'is_featured' => (bool) $item->is_primary,
        ])->values();

        $promotions = $product->promotions->map(function (ShopPromotion $promotion) {
            $typeAlias = match ($promotion->promotion_type) {
                'percent_off' => 'percentage_off',
                'amount_off' => 'fixed_off',
                default => $promotion->promotion_type,
            };

            return [
                'id' => $promotion->id,
                'slug' => $promotion->slug,
                'name' => $promotion->name,
                'type' => $typeAlias,
                'promotion_type' => $promotion->promotion_type,
                'value' => $promotion->discount_percent ?? $promotion->discount_amount_ars,
                'discount_percent' => $promotion->discount_percent !== null ? (float) $promotion->discount_percent : null,
                'discount_amount_ars' => $promotion->discount_amount_ars !== null ? (float) $promotion->discount_amount_ars : null,
                'combo_price_ars' => $promotion->combo_price_ars !== null ? (float) $promotion->combo_price_ars : null,
                'buy_qty' => $promotion->buy_qty,
                'get_qty' => $promotion->get_qty,
                'free_qty' => $promotion->get_qty,
                'is_active' => (bool) $promotion->is_active,
                'starts_at' => $promotion->starts_at?->toISOString(),
                'ends_at' => $promotion->ends_at?->toISOString(),
                'products' => $promotion->products->map(fn (ShopProduct $promotionProduct) => [
                    'id' => $promotionProduct->id,
                    'required_quantity' => (int) ($promotionProduct->pivot->required_quantity ?? 1),
                ])->values(),
                'combo_product_ids' => $promotion->products->pluck('id')->values(),
            ];
        })->values();

        $priceFromVariants = $variants->filter(fn ($variant) => $variant['is_active'])->pluck('price_ars');
        $startingPrice = $priceFromVariants->isNotEmpty()
            ? (float) $priceFromVariants->min()
            : (float) $product->price_ars;

        $availableStock = $variants->isNotEmpty()
            ? (int) $variants->where('is_active', true)->sum('stock')
            : (int) $product->stock;

        $primaryMedia = $media->firstWhere('is_primary', true) ?: $media->first();
        $primaryImage = $product->image_url ?: ($primaryMedia['url'] ?? null);
        $primaryImage = $this->normalizeStorageUrl($primaryImage);
        $eventCollection = $product->eventos->map(fn (Evento $event) => [
            'id' => $event->id,
            'nombre' => $event->nombre,
            'fecha' => $event->fecha,
            'imagenUrl' => $event->imagenUrl,
        ])->values();

        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'excerpt' => null,
            'description' => $product->description,
            'price_ars' => (float) $product->price_ars,
            'base_price_ars' => (float) $product->price_ars,
            'starting_price_ars' => $startingPrice,
            'display_price_ars' => $startingPrice,
            'stock' => (int) $product->stock,
            'base_stock' => (int) $product->stock,
            'available_stock' => $availableStock,
            'has_variants' => $variants->isNotEmpty(),
            'image_url' => $this->normalizeStorageUrl($product->image_url),
            'primary_image_url' => $primaryImage,
            'is_active' => (bool) $product->is_active,
            'is_featured' => (bool) $product->is_featured,
            'featured' => (bool) $product->is_featured,
            'featured_order' => (int) $product->featured_order,
            'track_stock' => (bool) $product->track_stock,
            'requires_shipping' => true,
            'allow_pickup' => true,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'slug' => $product->category->slug,
                'name' => $product->category->name,
            ] : null,
            'type' => $product->type ? [
                'id' => $product->type->id,
                'slug' => $product->type->slug,
                'name' => $product->type->name,
                'variant_config' => $product->type->variant_config,
            ] : null,
            'product_type' => $product->type ? [
                'id' => $product->type->id,
                'slug' => $product->type->slug,
                'name' => $product->type->name,
                'variant_config' => $product->type->variant_config,
            ] : null,
            'variants' => $variants,
            'media' => $media,
            'promotions' => $promotions,
            'all_promotions' => $promotions,
            'artists' => $product->artists->map(fn (Artista $artist) => [
                'id' => $artist->id,
                'name' => $artist->name,
                'imageUrl' => $artist->imageUrl,
                'heroImageUrl' => $artist->heroImageUrl,
                'color' => $artist->color,
            ])->values(),
            'events' => $eventCollection,
            'eventos' => $eventCollection,
        ];
    }

    private function normalizeStorageUrl(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        $normalized = Str::startsWith($value, '/') ? $value : '/' . $value;

        if (Str::startsWith($normalized, ['/storage/', '/public/storage/'])) {
            return $normalized;
        }

        if (Str::startsWith($normalized, ['/public/', '/storage/'])) {
            return $normalized;
        }

        return '/public/storage' . $normalized;
    }

    private function parseJsonArray(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
            return null;
        }

        return is_array($value) ? $value : null;
    }

    private function normalizeIds(mixed $value): array
    {
        $items = $this->parseJsonArray($value);
        if ($items === null) {
            if (is_array($value)) {
                $items = $value;
            } elseif (is_numeric($value)) {
                $items = [(int) $value];
            } else {
                return [];
            }
        }

        return collect($items)
            ->map(fn ($item) => is_array($item) ? (int) ($item['id'] ?? $item['value'] ?? 0) : (int) $item)
            ->filter(fn ($item) => $item > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeIndexedValues(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        $decoded = $this->parseJsonArray($value);
        return $decoded ? array_values($decoded) : [];
    }

    private function storeUpload($file, string $directory): string
    {
        return Storage::disk('public')->put($directory, $file);
    }

    private function deleteUpload(?string $path): void
    {
        if (!$path || Str::startsWith($path, ['http://', 'https://'])) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    private function uniqueSlug(?string $providedSlug, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($providedSlug ?: $name);
        $slug = $base;
        $counter = 2;

        while (
            ShopProduct::query()
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function uniquePromotionSlug(?string $providedSlug, string $name, ?int $ignoreId = null): string
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
