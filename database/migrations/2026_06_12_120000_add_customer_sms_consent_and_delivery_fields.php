<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('sms_consent_status', 24)->default('subscribed')->after('phone');
            $table->timestamp('sms_consent_at')->nullable()->after('sms_consent_status');
            $table->string('last_sms_delivery_status', 32)->nullable()->after('sms_consent_at');
            $table->timestamp('last_sms_delivered_at')->nullable()->after('last_sms_delivery_status');
            $table->timestamp('last_sms_failed_at')->nullable()->after('last_sms_delivered_at');
            $table->string('last_sms_error_code', 16)->nullable()->after('last_sms_failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'sms_consent_status',
                'sms_consent_at',
                'last_sms_delivery_status',
                'last_sms_delivered_at',
                'last_sms_failed_at',
                'last_sms_error_code',
            ]);
        });
    }
};
