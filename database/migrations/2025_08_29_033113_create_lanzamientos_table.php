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
        Schema::create('lanzamientos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->foreignId('artista_id')->constrained('artistas')->onDelete('cascade');
            $table->date('fecha_lanzamiento');
            $table->string('cover_image_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lanzamientos');
    }
};