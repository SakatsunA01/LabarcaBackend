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
        Schema::create('sorteos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('premio');
            $table->string('premio_imagen_url')->nullable();
            $table->text('descripcion')->nullable();
            $table->timestamp('fecha_limite');
            $table->string('estado')->default('activo');
            $table->json('requisitos')->nullable();
            $table->foreignId('ganador_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('ganador_snapshot')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['estado', 'fecha_limite']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sorteos');
    }
};
