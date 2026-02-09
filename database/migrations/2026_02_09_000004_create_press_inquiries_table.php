<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('press_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->date('birth_date');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('media_station');
            $table->string('media_position');
            $table->boolean('belongs_to_church')->default(false);
            $table->string('program_time');
            $table->json('social_links')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('press_inquiries');
    }
};
