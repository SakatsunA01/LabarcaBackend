<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shop_product_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('variant_config')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shop_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_category_id')->nullable()->constrained('shop_categories')->nullOnDelete();
            $table->foreignId('shop_product_type_id')->nullable()->constrained('shop_product_types')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price_ars', 10, 2)->default(0);
            $table->unsignedInteger('stock')->default(0);
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('featured_order')->default(0);
            $table->boolean('track_stock')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'is_featured', 'featured_order']);
        });

        Schema::create('shop_product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_product_id')->constrained('shop_products')->cascadeOnDelete();
            $table->string('sku')->nullable();
            $table->string('label')->nullable();
            $table->string('color')->nullable();
            $table->string('size')->nullable();
            $table->decimal('price_ars', 10, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('shop_product_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_product_id')->constrained('shop_products')->cascadeOnDelete();
            $table->string('media_type')->default('image');
            $table->string('url');
            $table->string('thumbnail_url')->nullable();
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['shop_product_id', 'media_type', 'sort_order']);
        });

        Schema::create('shop_promotions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('promotion_type');
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->decimal('discount_amount_ars', 10, 2)->nullable();
            $table->decimal('combo_price_ars', 10, 2)->nullable();
            $table->unsignedInteger('buy_qty')->nullable();
            $table->unsignedInteger('get_qty')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['promotion_type', 'is_active']);
        });

        Schema::create('artist_shop_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained('artistas')->cascadeOnDelete();
            $table->foreignId('shop_product_id')->constrained('shop_products')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['artist_id', 'shop_product_id']);
        });

        Schema::create('evento_shop_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->foreignId('shop_product_id')->constrained('shop_products')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['evento_id', 'shop_product_id']);
        });

        Schema::create('shop_promotion_shop_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_promotion_id')->constrained('shop_promotions')->cascadeOnDelete();
            $table->foreignId('shop_product_id')->constrained('shop_products')->cascadeOnDelete();
            $table->unsignedInteger('required_quantity')->default(1);
            $table->timestamps();
            $table->unique(['shop_promotion_id', 'shop_product_id']);
        });

        Schema::create('shop_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('delivery_method')->default('shipping');
            $table->string('pickup_note')->nullable();
            $table->string('shipping_address_line1')->nullable();
            $table->string('shipping_address_line2')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_state')->nullable();
            $table->string('shipping_postal_code')->nullable();
            $table->string('shipping_country')->nullable();
            $table->decimal('shipping_distance_km', 8, 2)->nullable();
            $table->decimal('shipping_rate_per_km', 10, 2)->nullable();
            $table->decimal('shipping_cost_ars', 10, 2)->default(0);
            $table->json('shipping_quote_snapshot')->nullable();
            $table->decimal('subtotal_ars', 10, 2)->default(0);
            $table->decimal('discount_ars', 10, 2)->default(0);
            $table->decimal('total_ars', 10, 2)->default(0);
            $table->string('currency', 3)->default('ARS');
            $table->json('promotion_snapshot')->nullable();
            $table->string('payment_method')->default('mercadopago');
            $table->string('status')->default('pending_payment');
            $table->string('mp_preference_id')->nullable()->index();
            $table->string('mp_payment_id')->nullable()->index();
            $table->string('mp_checkout_url')->nullable();
            $table->timestamps();

            $table->index(['status', 'delivery_method']);
        });

        Schema::create('shop_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_order_id')->constrained('shop_orders')->cascadeOnDelete();
            $table->foreignId('shop_product_id')->nullable()->constrained('shop_products')->nullOnDelete();
            $table->foreignId('shop_product_variant_id')->nullable()->constrained('shop_product_variants')->nullOnDelete();
            $table->string('name_snapshot');
            $table->string('sku_snapshot')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price_ars', 10, 2);
            $table->decimal('discount_ars', 10, 2)->default(0);
            $table->decimal('line_total_ars', 10, 2);
            $table->json('product_snapshot')->nullable();
            $table->json('variant_snapshot')->nullable();
            $table->json('promotion_snapshot')->nullable();
            $table->timestamps();

            $table->index(['shop_order_id', 'shop_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_order_items');
        Schema::dropIfExists('shop_orders');
        Schema::dropIfExists('shop_promotion_shop_product');
        Schema::dropIfExists('evento_shop_product');
        Schema::dropIfExists('artist_shop_product');
        Schema::dropIfExists('shop_promotions');
        Schema::dropIfExists('shop_product_media');
        Schema::dropIfExists('shop_product_variants');
        Schema::dropIfExists('shop_products');
        Schema::dropIfExists('shop_product_types');
        Schema::dropIfExists('shop_categories');
    }
};
