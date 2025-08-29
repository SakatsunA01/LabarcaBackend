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
        Schema::create('artistas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('imageUrl');
            $table->string('heroImageUrl');
            $table->string('secondaryImageUrl')->nullable();
            $table->string('color')->nullable();
            $table->text('description')->nullable();
            $table->string('spotifyEmbedUrl')->nullable();
            $table->string('youtubeVideoId')->nullable();
            $table->string('social_instagram')->nullable();
            $table->string('social_facebook')->nullable();
            $table->string('social_youtubeChannel')->nullable();
            $table->string('social_tiktok')->nullable();
            $table->string('social_spotifyProfile')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artistas');
    }
};
