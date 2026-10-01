<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_settings_id')->nullable()->constrained('shop_settings')->nullOnDelete();
            $table->string('name', 128);
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider', 24);
            $table->string('provider_identifier', 128)->nullable();
            $table->json('capabilities')->nullable();
            $table->string('status', 24)->default('offline');
            $table->timestamps();

            $table->index(['shop_settings_id', 'assigned_user_id'], 'comm_dev_shop_user_idx');
            $table->index(['provider', 'status'], 'comm_dev_provider_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_devices');
    }
};
