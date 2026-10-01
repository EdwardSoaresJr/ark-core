<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_message_id')->constrained()->cascadeOnDelete();
            $table->string('content_type', 127);
            $table->string('storage_path', 255)->nullable();
            $table->string('provider_url', 512)->nullable();
            $table->string('provider_media_sid', 64)->nullable();
            $table->unsignedInteger('byte_size')->nullable();
            $table->timestamps();

            $table->index('conversation_message_id', 'conv_msg_attach_msg_idx');
        });

        Schema::create('conversation_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_through_at');
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id'], 'conv_read_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_reads');
        Schema::dropIfExists('conversation_message_attachments');
    }
};
