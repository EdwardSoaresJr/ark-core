<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_settings', 'twilio_account_sid')) {
                if (Schema::hasColumn('shop_settings', 'telephony_call_flow')) {
                    $table->string('twilio_account_sid', 64)->nullable()->after('telephony_call_flow');
                } else {
                    $table->string('twilio_account_sid', 64)->nullable();
                }
            }

            if (! Schema::hasColumn('shop_settings', 'twilio_auth_token')) {
                $table->text('twilio_auth_token')->nullable()->after('twilio_account_sid');
            }

            if (! Schema::hasColumn('shop_settings', 'square_application_id')) {
                $table->string('square_application_id', 128)->nullable()->after('square_email_pay_enabled');
            }

            if (! Schema::hasColumn('shop_settings', 'square_access_token')) {
                $table->text('square_access_token')->nullable()->after('square_application_id');
            }

            if (! Schema::hasColumn('shop_settings', 'square_location_id')) {
                $table->string('square_location_id', 64)->nullable()->after('square_access_token');
            }

            if (! Schema::hasColumn('shop_settings', 'square_webhook_signature_key')) {
                $table->text('square_webhook_signature_key')->nullable()->after('square_location_id');
            }

            if (! Schema::hasColumn('shop_settings', 'square_environment')) {
                $table->string('square_environment', 16)->nullable()->after('square_webhook_signature_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('shop_settings', 'twilio_account_sid') ? 'twilio_account_sid' : null,
                Schema::hasColumn('shop_settings', 'twilio_auth_token') ? 'twilio_auth_token' : null,
                Schema::hasColumn('shop_settings', 'square_application_id') ? 'square_application_id' : null,
                Schema::hasColumn('shop_settings', 'square_access_token') ? 'square_access_token' : null,
                Schema::hasColumn('shop_settings', 'square_location_id') ? 'square_location_id' : null,
                Schema::hasColumn('shop_settings', 'square_webhook_signature_key') ? 'square_webhook_signature_key' : null,
                Schema::hasColumn('shop_settings', 'square_environment') ? 'square_environment' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
