<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_order_concerns', function (Blueprint $table) {
            $table->string('recommendation_intent', 32)->default('maintenance_due')->after('billing_posture');
        });

        DB::table('repair_order_concerns')->update([
            'recommendation_intent' => DB::raw("CASE priority
                WHEN 'high' THEN 'safety_drivability'
                WHEN 'low' THEN 'monitor'
                ELSE 'maintenance_due'
            END"),
        ]);

        Schema::table('repair_order_concerns', function (Blueprint $table) {
            $table->dropIndex('ro_concerns_ro_priority_idx');
            $table->dropColumn('priority');
            $table->index(['repair_order_id', 'recommendation_intent'], 'ro_concerns_ro_intent_idx');
        });

        Schema::table('shop_settings', function (Blueprint $table) {
            $table->string('default_recommendation_intent', 32)->default('maintenance_due')->after('default_concern_priority');
        });

        DB::table('shop_settings')->update([
            'default_recommendation_intent' => DB::raw("CASE default_concern_priority
                WHEN 'high' THEN 'safety_drivability'
                WHEN 'low' THEN 'monitor'
                ELSE 'maintenance_due'
            END"),
        ]);

        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropColumn('default_concern_priority');
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->string('default_concern_priority', 32)->default('normal')->after('estimate_validity_days');
        });

        DB::table('shop_settings')->update([
            'default_concern_priority' => DB::raw("CASE default_recommendation_intent
                WHEN 'safety_drivability' THEN 'high'
                WHEN 'monitor' THEN 'low'
                ELSE 'normal'
            END"),
        ]);

        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropColumn('default_recommendation_intent');
        });

        Schema::table('repair_order_concerns', function (Blueprint $table) {
            $table->string('priority', 32)->default('normal')->after('billing_posture');
        });

        DB::table('repair_order_concerns')->update([
            'priority' => DB::raw("CASE recommendation_intent
                WHEN 'safety_drivability' THEN 'high'
                WHEN 'monitor' THEN 'low'
                ELSE 'normal'
            END"),
        ]);

        Schema::table('repair_order_concerns', function (Blueprint $table) {
            $table->dropIndex('ro_concerns_ro_intent_idx');
            $table->dropColumn('recommendation_intent');
            $table->index(['repair_order_id', 'priority'], 'ro_concerns_ro_priority_idx');
        });
    }
};
