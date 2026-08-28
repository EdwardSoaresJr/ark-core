<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_device_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('token_prefix', 12);
            $table->string('token_hash', 64)->unique();
            $table->string('shop_identity', 191);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['shop_identity', 'revoked_at'], 'station_tokens_shop_revoked_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_device_tokens');
    }
};
