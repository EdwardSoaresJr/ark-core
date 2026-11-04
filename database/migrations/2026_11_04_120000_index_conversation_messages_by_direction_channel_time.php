<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The unread-message badge on every staff page asks for recent inbound messages on one channel
 * (UnreadInboundMessageQueue). Without this index the database reads every open conversation's messages.
 */
return new class extends Migration
{
    private const INDEX = 'conv_msg_dir_chan_occurred_idx';

    public function up(): void
    {
        if (Schema::hasIndex('conversation_messages', self::INDEX)) {
            return;
        }

        Schema::table('conversation_messages', function (Blueprint $table): void {
            $table->index(['direction', 'channel', 'occurred_at'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('conversation_messages', self::INDEX)) {
            return;
        }

        Schema::table('conversation_messages', function (Blueprint $table): void {
            $table->dropIndex(self::INDEX);
        });
    }
};
