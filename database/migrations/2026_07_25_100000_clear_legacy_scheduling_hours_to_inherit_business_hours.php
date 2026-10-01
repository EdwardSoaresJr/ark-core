<?php

use App\Ark\Operations\Appointments\SchedulingHours;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Null legacy seeded scheduling_hours so staff booking inherits Business Hours.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shop_settings') || ! Schema::hasColumn('shop_settings', 'scheduling_hours')) {
            return;
        }

        $rows = DB::table('shop_settings')->select(['id', 'scheduling_hours'])->get();

        foreach ($rows as $row) {
            $stored = $row->scheduling_hours;

            if (is_string($stored)) {
                $decoded = json_decode($stored, true);
                $stored = is_array($decoded) ? $decoded : null;
            }

            if (! is_array($stored)) {
                continue;
            }

            if (! SchedulingHours::matchesLegacySeed($stored)) {
                continue;
            }

            DB::table('shop_settings')
                ->where('id', $row->id)
                ->update(['scheduling_hours' => null]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('shop_settings') || ! Schema::hasColumn('shop_settings', 'scheduling_hours')) {
            return;
        }

        DB::table('shop_settings')
            ->whereNull('scheduling_hours')
            ->update([
                'scheduling_hours' => json_encode(SchedulingHours::defaultWeekly()),
            ]);
    }
};
