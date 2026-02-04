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
            $table->string('pilar_experiencia_icon', 32)->nullable()->after('pilar_experiencia');
            $table->string('pilar_autoridad_icon', 32)->nullable()->after('pilar_autoridad');
            $table->string('pilar_mensaje_icon', 32)->nullable()->after('pilar_mensaje');
            $table->json('cronograma')->nullable()->after('lineup_artist_ids');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn([
                'pilar_experiencia_icon',
                'pilar_autoridad_icon',
                'pilar_mensaje_icon',
                'cronograma',
            ]);
        });
    }
};
