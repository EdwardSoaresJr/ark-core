<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('shop_settings', 'shop_timezone')) {
            Schema::table('shop_settings', function (Blueprint $table): void {
                $table->string('shop_timezone', 64)->nullable()->after('shop_name');
            });
        }

        if (Schema::hasColumn('shop_settings', 'telephony_call_flow')) {
            foreach (DB::table('shop_settings')->get(['id', 'telephony_call_flow']) as $row) {
                $timezone = null;
                $flow = json_decode((string) ($row->telephony_call_flow ?? ''), true);

                if (is_array($flow) && filled($flow['timezone'] ?? null)) {
                    $timezone = trim((string) $flow['timezone']);
                }

                if ($timezone === '' || $timezone === null) {
                    $timezone = 'America/Denver';
                }

                DB::table('shop_settings')->where('id', $row->id)->update([
                    'shop_timezone' => $timezone,
                ]);
            }
        } elseif (DB::table('shop_settings')->exists()) {
            DB::table('shop_settings')->update([
                'shop_timezone' => 'America/Denver',
            ]);
        }

        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->string('shop_timezone', 64)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->dropColumn('shop_timezone');
        });
    }
};
