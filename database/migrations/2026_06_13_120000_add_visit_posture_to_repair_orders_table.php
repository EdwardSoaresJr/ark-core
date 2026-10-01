<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            $table->boolean('tow_incoming')->default(false)->after('concern_summary');
            $table->boolean('waiting_here')->default(false)->after('tow_incoming');
            $table->boolean('drop_off')->default(false)->after('waiting_here');
            $table->boolean('needs_shuttle')->default(false)->after('drop_off');
            $table->boolean('warranty')->default(false)->after('needs_shuttle');
            $table->boolean('fleet')->default(false)->after('warranty');
            $table->boolean('appointment')->default(false)->after('fleet');
        });

        if (! Schema::hasTable('encounters')) {
            return;
        }

        $pairs = DB::table('repair_orders')
            ->whereNotNull('encounter_id')
            ->pluck('encounter_id', 'id');

        foreach ($pairs as $repairOrderId => $encounterId) {
            $encounter = DB::table('encounters')->where('id', $encounterId)->first();

            if ($encounter === null) {
                continue;
            }

            DB::table('repair_orders')->where('id', $repairOrderId)->update([
                'tow_incoming' => (bool) $encounter->tow_incoming,
                'waiting_here' => (bool) $encounter->waiting_here,
                'drop_off' => (bool) $encounter->drop_off,
                'needs_shuttle' => (bool) $encounter->needs_shuttle,
                'warranty' => (bool) $encounter->warranty,
                'fleet' => (bool) $encounter->fleet,
                'appointment' => (bool) $encounter->appointment,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            $table->dropColumn([
                'tow_incoming',
                'waiting_here',
                'drop_off',
                'needs_shuttle',
                'warranty',
                'fleet',
                'appointment',
            ]);
        });
    }
};
