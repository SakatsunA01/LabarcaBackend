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
        Schema::table('posts', function (Blueprint $table) {
            $table->string('titulo');
            $table->text('contenido');
            $table->string('url_imagen')->nullable();
            $table->string('autor')->nullable();
            $table->timestamp('fecha_publicacion')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['titulo', 'contenido', 'url_imagen', 'autor', 'fecha_publicacion']);
        });
    }
};
