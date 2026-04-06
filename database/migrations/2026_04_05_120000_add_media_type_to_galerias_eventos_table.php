<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galerias_eventos', function (Blueprint $table) {
            $table->string('media_type', 20)->default('image')->after('url_imagen');
        });

        DB::table('galerias_eventos')->update(['media_type' => 'image']);
    }

    public function down(): void
    {
        Schema::table('galerias_eventos', function (Blueprint $table) {
            $table->dropColumn('media_type');
        });
    }
};
