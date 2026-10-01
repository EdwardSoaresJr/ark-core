<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_name', 120);
            $table->string('platform', 32);
            $table->string('app_version', 32)->nullable();
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['user_id', 'device_name'], 'mobile_devices_user_device_unique');
            $table->index(['user_id', 'last_seen_at'], 'mobile_devices_user_seen_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_devices');
    }
};
