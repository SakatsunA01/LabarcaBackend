<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sorteos', function (Blueprint $table) {
            $table->json('winners')->nullable()->after('bendiciones');
        });
    }

    public function down(): void
    {
        Schema::table('sorteos', function (Blueprint $table) {
            $table->dropColumn('winners');
        });
    }
};
