<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->json('pickup_points')->nullable()->after('lineup_artist_ids');
            $table->string('cash_whatsapp_url', 500)->nullable()->after('pickup_points');
            $table->text('cash_instructions')->nullable()->after('cash_whatsapp_url');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn(['pickup_points', 'cash_whatsapp_url', 'cash_instructions']);
        });
    }
};
