<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('contact_surface', 24);
            $table->string('contact_address', 191);
            $table->string('status', 24)->default('open');
            $table->timestamps();

            $table->unique(['contact_surface', 'contact_address'], 'conv_surface_addr_unique');
            $table->index('status', 'conv_status_idx');
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('participant_type', 24);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('display_name', 255)->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'participant_type'], 'conv_part_type_idx');
        });

        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_participant_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 32);
            $table->string('direction', 16);
            $table->text('body');
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'occurred_at'], 'conv_msg_occurred_idx');
        });

        Schema::create('conversation_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('linkable_type', 191);
            $table->unsignedBigInteger('linkable_id');
            $table->timestamps();

            $table->unique(
                ['conversation_id', 'linkable_type', 'linkable_id'],
                'conv_link_unique',
            );
            $table->index(['linkable_type', 'linkable_id'], 'conv_link_target_idx');
        });

        if (Schema::hasTable('communication_events')) {
            Schema::table('communication_events', function (Blueprint $table): void {
                $table->foreign('conversation_message_id', 'comm_events_conv_msg_fk')
                    ->references('id')
                    ->on('conversation_messages')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('communication_events')) {
            Schema::table('communication_events', function (Blueprint $table): void {
                $table->dropForeign('comm_events_conv_msg_fk');
            });
        }

        Schema::dropIfExists('conversation_links');
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
