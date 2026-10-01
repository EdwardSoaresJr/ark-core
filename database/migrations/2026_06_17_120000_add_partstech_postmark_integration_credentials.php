<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->string('partstech_base_url', 255)->nullable()->after('square_environment');
            $table->string('partstech_catalog_path', 255)->nullable()->after('partstech_base_url');
            $table->string('partstech_username', 128)->nullable()->after('partstech_catalog_path');
            $table->text('partstech_api_key')->nullable()->after('partstech_username');
            $table->text('partstech_password')->nullable()->after('partstech_api_key');
            $table->text('postmark_token')->nullable()->after('partstech_password');
            $table->string('postmark_reply_to', 255)->nullable()->after('postmark_token');
            $table->string('postmark_reply_to_name', 255)->nullable()->after('postmark_reply_to');
            $table->string('postmark_message_stream_id', 64)->nullable()->after('postmark_reply_to_name');
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'partstech_base_url',
                'partstech_catalog_path',
                'partstech_username',
                'partstech_api_key',
                'partstech_password',
                'postmark_token',
                'postmark_reply_to',
                'postmark_reply_to_name',
                'postmark_message_stream_id',
            ]);
        });
    }
};
