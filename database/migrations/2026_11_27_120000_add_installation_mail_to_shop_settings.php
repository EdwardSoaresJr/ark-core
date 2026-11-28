<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_settings', 'installation_mail_enabled')) {
                $table->boolean('installation_mail_enabled')->default(false);
            }
            if (! Schema::hasColumn('shop_settings', 'installation_mail_mailer')) {
                $table->string('installation_mail_mailer', 32)->nullable();
            }
            if (! Schema::hasColumn('shop_settings', 'installation_mail_host')) {
                $table->string('installation_mail_host', 255)->nullable();
            }
            if (! Schema::hasColumn('shop_settings', 'installation_mail_port')) {
                $table->unsignedSmallInteger('installation_mail_port')->nullable();
            }
            if (! Schema::hasColumn('shop_settings', 'installation_mail_encryption')) {
                $table->string('installation_mail_encryption', 16)->nullable();
            }
            if (! Schema::hasColumn('shop_settings', 'installation_mail_username')) {
                $table->string('installation_mail_username', 255)->nullable();
            }
            if (! Schema::hasColumn('shop_settings', 'installation_mail_password')) {
                $table->text('installation_mail_password')->nullable();
            }
            if (! Schema::hasColumn('shop_settings', 'installation_mail_from_address')) {
                $table->string('installation_mail_from_address', 255)->nullable();
            }
            if (! Schema::hasColumn('shop_settings', 'installation_mail_from_name')) {
                $table->string('installation_mail_from_name', 255)->nullable();
            }
            if (! Schema::hasColumn('shop_settings', 'installation_mail_reply_to')) {
                $table->string('installation_mail_reply_to', 255)->nullable();
            }
            if (! Schema::hasColumn('shop_settings', 'installation_mail_password_set_at')) {
                $table->timestamp('installation_mail_password_set_at')->nullable();
            }
            if (! Schema::hasColumn('shop_settings', 'installation_mail_last_tested_at')) {
                $table->timestamp('installation_mail_last_tested_at')->nullable();
            }
            if (! Schema::hasColumn('shop_settings', 'installation_mail_last_test_status')) {
                $table->string('installation_mail_last_test_status', 64)->nullable();
            }
            if (! Schema::hasColumn('shop_settings', 'installation_mail_last_error')) {
                $table->text('installation_mail_last_error')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'installation_mail_enabled',
                'installation_mail_mailer',
                'installation_mail_host',
                'installation_mail_port',
                'installation_mail_encryption',
                'installation_mail_username',
                'installation_mail_password',
                'installation_mail_from_address',
                'installation_mail_from_name',
                'installation_mail_reply_to',
                'installation_mail_password_set_at',
                'installation_mail_last_tested_at',
                'installation_mail_last_test_status',
                'installation_mail_last_error',
            ]);
        });
    }
};
