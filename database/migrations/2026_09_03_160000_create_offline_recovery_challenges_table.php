<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_recovery_challenges', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->uuid('installation_uuid');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->uuid('consumed_jti')->nullable();
            $table->timestamps();

            $table->index(['installation_uuid', 'expires_at'], 'offline_recovery_install_exp_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_recovery_challenges');
    }
};
