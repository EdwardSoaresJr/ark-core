<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_settings', 'twilio_api_key_sid')) {
                $table->string('twilio_api_key_sid', 64)->nullable()->after('twilio_auth_token');
            }
            if (! Schema::hasColumn('shop_settings', 'twilio_api_key_secret')) {
                $table->text('twilio_api_key_secret')->nullable()->after('twilio_api_key_sid');
            }
            if (! Schema::hasColumn('shop_settings', 'twilio_voice_twiml_app_sid')) {
                $table->string('twilio_voice_twiml_app_sid', 64)->nullable()->after('twilio_api_key_secret');
            }
            if (! Schema::hasColumn('shop_settings', 'twilio_fcm_credential_sid')) {
                $table->string('twilio_fcm_credential_sid', 64)->nullable()->after('twilio_voice_twiml_app_sid');
            }
            if (! Schema::hasColumn('shop_settings', 'twilio_apns_voip_credential_sid')) {
                $table->string('twilio_apns_voip_credential_sid', 64)->nullable()->after('twilio_fcm_credential_sid');
            }
        });

        Schema::table('mobile_devices', function (Blueprint $table): void {
            if (! Schema::hasColumn('mobile_devices', 'voip_push_token')) {
                $table->string('voip_push_token', 512)->nullable()->after('fcm_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            foreach ([
                'twilio_api_key_sid',
                'twilio_api_key_secret',
                'twilio_voice_twiml_app_sid',
                'twilio_fcm_credential_sid',
                'twilio_apns_voip_credential_sid',
            ] as $column) {
                if (Schema::hasColumn('shop_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('mobile_devices', function (Blueprint $table): void {
            if (Schema::hasColumn('mobile_devices', 'voip_push_token')) {
                $table->dropColumn('voip_push_token');
            }
        });
    }
};
