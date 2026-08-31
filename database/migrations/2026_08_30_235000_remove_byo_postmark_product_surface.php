<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the stock BYO Postmark product surface restored by
 * 2026_08_30_230000_restore_byo_postmark_and_email_provider.
 *
 * Forward-only: clears shop-owned Postmark credentials and drops the
 * turnkey provider-selection columns. Reply-to identity columns remain
 * for ARK Mail. Does not rewrite earlier migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('shop_settings', 'postmark_token')) {
            DB::table('shop_settings')->update(['postmark_token' => null]);
        }

        if (Schema::hasColumn('shop_settings', 'email_provider')) {
            DB::table('shop_settings')
                ->where('email_provider', 'postmark')
                ->update(['email_provider' => null]);
        }

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

    public function down(): void
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
};
