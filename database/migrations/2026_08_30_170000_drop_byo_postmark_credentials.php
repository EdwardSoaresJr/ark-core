<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Official ARK Mail path consolidation: drop shop-held Postmark secrets.
 * Reply-to columns remain (shop customer reply identity for ARK Mail).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('shop_settings', 'postmark_token')) {
                $table->dropColumn('postmark_token');
            }
            if (Schema::hasColumn('shop_settings', 'postmark_message_stream_id')) {
                $table->dropColumn('postmark_message_stream_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_settings', 'postmark_token')) {
                $table->text('postmark_token')->nullable();
            }
            if (! Schema::hasColumn('shop_settings', 'postmark_message_stream_id')) {
                $table->string('postmark_message_stream_id', 64)->nullable();
            }
        });
    }
};
