<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_orders', function (Blueprint $table) {
            $table->timestamp('pending_email_sent_at')->nullable()->after('email_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_orders', function (Blueprint $table) {
            $table->dropColumn('pending_email_sent_at');
        });
    }
};
