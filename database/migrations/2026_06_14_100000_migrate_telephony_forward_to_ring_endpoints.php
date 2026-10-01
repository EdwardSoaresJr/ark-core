<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('telephony_endpoints')) {
            return;
        }

        if (DB::table('telephony_endpoints')->exists()) {
            DB::table('shop_settings')->update(['telephony_forward_to' => null]);

            return;
        }

        $forwardTo = DB::table('shop_settings')->value('telephony_forward_to');

        if (! is_string($forwardTo) || trim($forwardTo) === '') {
            $forwardTo = env('TELEPHONY_FORWARD_TO');
        }

        if (is_string($forwardTo) && trim($forwardTo) !== '') {
            DB::table('telephony_endpoints')->insert([
                'name' => 'Primary cell',
                'type' => 'cell',
                'destination' => trim($forwardTo),
                'enabled' => true,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('shop_settings')->update(['telephony_forward_to' => null]);
    }

    public function down(): void
    {
        // Ring endpoints are authoritative; do not restore env/shop_settings forward targets.
    }
};
