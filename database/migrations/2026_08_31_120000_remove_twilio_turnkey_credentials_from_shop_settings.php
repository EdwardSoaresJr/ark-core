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

        Schema::table('shop_settings', function (Blueprint $table) use ($existing): void {
            $table->dropColumn($existing);
        });
    }

    public function down(): void
    {
        //
    }
};
