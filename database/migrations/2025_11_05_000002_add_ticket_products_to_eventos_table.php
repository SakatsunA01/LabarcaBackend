<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->foreignId('general_product_id')->nullable()->after('link_compra')->constrained('products')->nullOnDelete();
            $table->foreignId('vip_product_id')->nullable()->after('general_product_id')->constrained('products')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropForeign(['general_product_id']);
            $table->dropForeign(['vip_product_id']);
            $table->dropColumn(['general_product_id', 'vip_product_id']);
        });
    }
};
