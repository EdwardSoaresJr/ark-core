<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('call_sessions')) {
            DB::table('call_sessions')
                ->where('provider', 'asterisk')
                ->update(['provider' => 'twilio']);
        }

        if (Schema::hasTable('communication_devices')) {
            DB::table('communication_devices')
                ->where('provider', 'asterisk')
                ->update(['provider' => 'shop_phone']);
        }

        if (Schema::hasTable('shop_settings')) {
            DB::table('shop_settings')->update([
                'telephony_provider' => 'twilio',
            ]);
        }
    }

    public function down(): void
    {
        // Irreversible — Asterisk transport retired.
    }
};
