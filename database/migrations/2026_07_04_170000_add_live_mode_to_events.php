<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('live_mode_started_at')->nullable()->after('kiosk_last_heartbeat_at');
            $table->timestamp('live_mode_ended_at')->nullable()->after('live_mode_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['live_mode_started_at', 'live_mode_ended_at']);
        });
    }
};
