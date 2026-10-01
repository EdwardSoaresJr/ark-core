<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_devices', function (Blueprint $table): void {
            $table->string('fcm_token', 512)->nullable()->after('app_version');
            $table->index(['user_id', 'fcm_token'], 'mobile_devices_user_fcm_idx');
        });
    }

    public function down(): void
    {
        Schema::table('mobile_devices', function (Blueprint $table): void {
            $table->dropIndex('mobile_devices_user_fcm_idx');
            $table->dropColumn('fcm_token');
        });
    }
};
