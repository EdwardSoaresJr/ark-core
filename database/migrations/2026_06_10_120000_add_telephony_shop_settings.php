<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->boolean('telephony_enabled')->default(false)->after('square_email_pay_enabled');
            $table->string('telephony_inbound_number', 32)->nullable()->after('telephony_enabled');
            $table->string('telephony_forward_to', 32)->nullable()->after('telephony_inbound_number');
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropColumn([
                'telephony_enabled',
                'telephony_inbound_number',
                'telephony_forward_to',
            ]);
        });
    }
};
