<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_devices', function (Blueprint $table): void {
            if (! Schema::hasColumn('mobile_devices', 'voice_ready_at')) {
                $table->timestamp('voice_ready_at')->nullable()->after('last_seen_at');
                $table->index(['user_id', 'voice_ready_at'], 'mobile_devices_user_voice_ready_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mobile_devices', function (Blueprint $table): void {
            if (Schema::hasColumn('mobile_devices', 'voice_ready_at')) {
                $table->dropIndex('mobile_devices_user_voice_ready_idx');
                $table->dropColumn('voice_ready_at');
            }
        });
    }
};
