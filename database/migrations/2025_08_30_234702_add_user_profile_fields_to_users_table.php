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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->boolean('belongs_to_church')->default(false)->after('phone');
            $table->string('church_name')->nullable()->after('belongs_to_church');
            $table->string('pastor_name')->nullable()->after('church_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'belongs_to_church', 'church_name', 'pastor_name']);
        });
    }
};