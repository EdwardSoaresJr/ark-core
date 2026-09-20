<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restore shop-owned Postmark credentials and an explicit email provider choice.
 * Forward-only: does not rewrite 2026_08_30_170000_drop_byo_postmark_credentials.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_settings', 'email_provider')) {
                $table->string('email_provider', 32)->nullable()->after('postmark_reply_to_name');
            }
            if (! Schema::hasColumn('shop_settings', 'postmark_token')) {
                $table->text('postmark_token')->nullable()->after('email_provider');
            }
            if (! Schema::hasColumn('shop_settings', 'postmark_message_stream_id')) {
                $table->string('postmark_message_stream_id', 64)->nullable()->after('postmark_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('shop_settings', 'postmark_message_stream_id')) {
                $table->dropColumn('postmark_message_stream_id');
            }
            if (Schema::hasColumn('shop_settings', 'postmark_token')) {
                $table->dropColumn('postmark_token');
            }
            if (Schema::hasColumn('shop_settings', 'email_provider')) {
                $table->dropColumn('email_provider');
            }
        });
    }
};
