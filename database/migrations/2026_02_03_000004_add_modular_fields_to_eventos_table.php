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
            $table->boolean('countdown_enabled')->default(true)->after('imagenUrl');
            $table->string('countdown_title')->nullable()->after('countdown_enabled');
            $table->string('countdown_subtitle')->nullable()->after('countdown_title');
            $table->text('pilar_experiencia')->nullable()->after('countdown_subtitle');
            $table->text('pilar_autoridad')->nullable()->after('pilar_experiencia');
            $table->text('pilar_mensaje')->nullable()->after('pilar_autoridad');
            $table->json('lineup_artist_ids')->nullable()->after('pilar_mensaje');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn([
                'countdown_enabled',
                'countdown_title',
                'countdown_subtitle',
                'pilar_experiencia',
                'pilar_autoridad',
                'pilar_mensaje',
                'lineup_artist_ids',
            ]);
        });
    }
};
