<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('press_inquiries', function (Blueprint $table) {
            $table->string('church_name')->nullable()->after('belongs_to_church');
            $table->string('pastor_name')->nullable()->after('church_name');
        });
    }

    public function down(): void
    {
        Schema::table('press_inquiries', function (Blueprint $table) {
            $table->dropColumn(['church_name', 'pastor_name']);
        });
    }
};
