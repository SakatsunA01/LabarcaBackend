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
                Schema::table('lanzamientos', function (Blueprint $table) {
            $table->string('youtube_link')->nullable()->after('cover_image_url');
            $table->string('spotify_link')->nullable()->after('youtube_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lanzamientos', function (Blueprint $table) {
            $table->dropColumn('youtube_link');
            $table->dropColumn('spotify_link');
        });
    }
};
