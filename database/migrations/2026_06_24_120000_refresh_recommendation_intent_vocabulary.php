<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $map = [
            'safety_drivability' => 'immediate_attention',
            'maintenance_due' => 'maintenance',
            'future_repair' => 'plan_soon',
            'monitor' => 'information_only',
            'diagnostic_recommendation' => 'diagnostic',
        ];

        foreach ($map as $from => $to) {
            DB::table('repair_order_concerns')
                ->where('recommendation_intent', $from)
                ->update(['recommendation_intent' => $to]);

            DB::table('shop_settings')
                ->where('default_recommendation_intent', $from)
                ->update(['default_recommendation_intent' => $to]);
        }

        DB::table('shop_settings')
            ->whereNull('default_recommendation_intent')
            ->orWhere('default_recommendation_intent', '')
            ->update(['default_recommendation_intent' => 'maintenance']);
    }

    public function down(): void
    {
        $map = [
            'immediate_attention' => 'safety_drivability',
            'maintenance' => 'maintenance_due',
            'plan_soon' => 'future_repair',
            'information_only' => 'monitor',
            'diagnostic' => 'diagnostic_recommendation',
            'repair_verification' => 'diagnostic_recommendation',
        ];

        foreach ($map as $from => $to) {
            DB::table('repair_order_concerns')
                ->where('recommendation_intent', $from)
                ->update(['recommendation_intent' => $to]);

            DB::table('shop_settings')
                ->where('default_recommendation_intent', $from)
                ->update(['default_recommendation_intent' => $to]);
        }
    }
};
