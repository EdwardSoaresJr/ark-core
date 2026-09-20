<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->string('ark_mail_status', 32)->nullable()->after('postmark_message_stream_id');
            $table->string('ark_mail_tenant_public_id', 36)->nullable()->after('ark_mail_status');
            $table->string('ark_mail_from_email', 255)->nullable()->after('ark_mail_tenant_public_id');
            $table->text('ark_mail_credential')->nullable()->after('ark_mail_from_email');
            $table->string('ark_mail_service_url', 255)->nullable()->after('ark_mail_credential');
            $table->timestamp('ark_mail_connected_at')->nullable()->after('ark_mail_service_url');
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'ark_mail_status',
                'ark_mail_tenant_public_id',
                'ark_mail_from_email',
                'ark_mail_credential',
                'ark_mail_service_url',
                'ark_mail_connected_at',
            ]);
        });
    }
};
