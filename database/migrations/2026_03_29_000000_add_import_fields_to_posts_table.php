<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('fuente')->nullable()->after('autor');
            $table->string('url_origen', 2048)->nullable()->after('fuente');
            $table->boolean('origen_importado')->default(false)->after('url_origen');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['fuente', 'url_origen', 'origen_importado']);
        });
    }
};
