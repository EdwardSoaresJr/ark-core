<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shop_settings')) {
            return;
        }

        $columns = [
            'twilio_account_sid',
            'twilio_auth_token',
            'twilio_api_key_sid',
            'twilio_api_key_secret',
            'twilio_voice_twiml_app_sid',
            'twilio_fcm_credential_sid',
            'twilio_apns_voip_credential_sid',
        ];

        $existing = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn('shop_settings', $column),
        ));

        if ($existing === []) {
            return;
        }

        foreach ($existing as $column) {
            \Illuminate\Support\Facades\DB::table('shop_settings')->update([$column => null]);
        }

        Schema::table('shop_settings', function (Blueprint $table) use ($existing): void {
            $table->dropColumn($existing);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shop_settings')) {
            return;
        }

        Schema::table('shop_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_settings', 'twilio_account_sid')) {
                $table->string('twilio_account_sid', 64)->nullable();
            }
            if (! Schema::hasColumn('shop_settings', 'twilio_auth_token')) {
                $table->text('twilio_auth_token')->nullable();
            }
            if (! Schema::hasColumn('shop_settings', 'twilio_api_key_sid')) {
                $table->string('twilio_api_key_sid', 64)->nullable();
            }
            if (! Schema::hasColumn('shop_settings', 'twilio_api_key_secret')) {
                $table->text('twilio_api_key_secret')->nullable();
            }
            if (! Schema::hasColumn('shop_settings', 'twilio_voice_twiml_app_sid')) {
                $table->string('twilio_voice_twiml_app_sid', 64)->nullable();
            }
            if (! Schema::hasColumn('shop_settings', 'twilio_fcm_credential_sid')) {
                $table->string('twilio_fcm_credential_sid', 64)->nullable();
            }
            if (! Schema::hasColumn('shop_settings', 'twilio_apns_voip_credential_sid')) {
                $table->string('twilio_apns_voip_credential_sid', 64)->nullable();
            }
        });
    }
};
