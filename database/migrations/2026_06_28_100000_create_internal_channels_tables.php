<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120);
            $table->string('description', 500)->nullable();
            $table->boolean('is_private')->default(false);
            $table->timestamps();

            $table->unique('slug', 'internal_channels_slug_unique');
        });

        Schema::create('internal_channel_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internal_channel_id')->constrained('internal_channels')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32)->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['internal_channel_id', 'user_id'], 'internal_channel_member_unique');
        });

        Schema::create('internal_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internal_channel_id')->constrained('internal_channels')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['internal_channel_id', 'created_at'], 'internal_msg_channel_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_messages');
        Schema::dropIfExists('internal_channel_members');
        Schema::dropIfExists('internal_channels');
    }
};
