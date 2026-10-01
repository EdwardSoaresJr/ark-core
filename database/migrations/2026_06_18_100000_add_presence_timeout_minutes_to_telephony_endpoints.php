<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telephony_endpoints', function (Blueprint $table) {
            $table->unsignedSmallInteger('presence_timeout_minutes')
                ->default(30)
                ->after('ring_delay_seconds');
        });

        $flow = json_decode((string) DB::table('shop_settings')->value('telephony_call_flow'), true);
        $globalPresence = max(5, min(240, (int) ($flow['presence_timeout_minutes'] ?? 30)));

        DB::table('telephony_endpoints')->update([
            'presence_timeout_minutes' => $globalPresence,
        ]);
    }

    public function down(): void
    {
        Schema::table('telephony_endpoints', function (Blueprint $table) {
            $table->dropColumn('presence_timeout_minutes');
        });
    }
};
