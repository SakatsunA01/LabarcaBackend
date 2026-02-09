<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('press_inquiries', function (Blueprint $table) {
            $table->json('program_slots')->nullable()->after('pastor_name');
        });
    }

    public function down(): void
    {
        Schema::table('press_inquiries', function (Blueprint $table) {
            $table->dropColumn('program_slots');
        });
    }
};
