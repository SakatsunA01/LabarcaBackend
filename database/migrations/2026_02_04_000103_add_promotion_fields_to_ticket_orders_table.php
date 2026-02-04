<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('paid_quantity')->nullable()->after('quantity');
            $table->unsignedTinyInteger('bonus_quantity')->default(0)->after('paid_quantity');
            $table->json('promotion_snapshot')->nullable()->after('bonus_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_orders', function (Blueprint $table) {
            $table->dropColumn(['paid_quantity', 'bonus_quantity', 'promotion_snapshot']);
        });
    }
};
