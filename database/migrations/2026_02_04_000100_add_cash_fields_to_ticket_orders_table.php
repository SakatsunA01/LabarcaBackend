<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_orders', function (Blueprint $table) {
            $table->string('payment_method', 20)->default('mercadopago')->after('currency');
            $table->timestamp('expires_at')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('expires_at');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->string('coordination_phone', 60)->nullable()->after('approved_by');
            $table->text('admin_note')->nullable()->after('coordination_phone');
            $table->string('pickup_point_name', 255)->nullable()->after('admin_note');
            $table->string('pickup_point_map_url', 500)->nullable()->after('pickup_point_name');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'payment_method',
                'expires_at',
                'approved_at',
                'coordination_phone',
                'admin_note',
                'pickup_point_name',
                'pickup_point_map_url',
            ]);
        });
    }
};
