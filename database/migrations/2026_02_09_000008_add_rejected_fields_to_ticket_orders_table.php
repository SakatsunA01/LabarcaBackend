<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_orders', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('email_sent_at');
            $table->unsignedBigInteger('rejected_by')->nullable()->after('rejected_at');
            $table->string('rejected_reason')->nullable()->after('rejected_by');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_orders', function (Blueprint $table) {
            $table->dropColumn(['rejected_at', 'rejected_by', 'rejected_reason']);
        });
    }
};
