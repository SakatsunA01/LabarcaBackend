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
        Schema::table('testimonios_eventos', function (Blueprint $table) {
            $table->boolean('approved')->default(false)->after('comentario');
            $table->string('foto_path')->nullable()->after('approved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('testimonios_eventos', function (Blueprint $table) {
            $table->dropColumn(['approved', 'foto_path']);
        });
    }
};
