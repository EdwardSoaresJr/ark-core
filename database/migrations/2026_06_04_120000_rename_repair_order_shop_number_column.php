<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('repair_orders', 'legacy_arksms_repair_order_id')) {
            return;
        }

        Schema::table('repair_orders', function (Blueprint $table) {
            $table->dropUnique('ro_legacy_ark_ro_unique');
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('repair_orders', function (Blueprint $table) {
                $table->renameColumn('legacy_arksms_repair_order_id', 'repair_order_id');
            });
        } else {
            DB::statement('ALTER TABLE repair_orders CHANGE legacy_arksms_repair_order_id repair_order_id BIGINT UNSIGNED NULL');
        }

        DB::table('repair_orders')
            ->whereNull('repair_order_id')
            ->update(['repair_order_id' => DB::raw('id')]);

        Schema::table('repair_orders', function (Blueprint $table) {
            $table->unique('repair_order_id', 'ro_shop_number_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('repair_orders', 'repair_order_id')) {
            return;
        }

        Schema::table('repair_orders', function (Blueprint $table) {
            $table->dropUnique('ro_shop_number_unique');
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('repair_orders', function (Blueprint $table) {
                $table->renameColumn('repair_order_id', 'legacy_arksms_repair_order_id');
            });
        } else {
            DB::statement('ALTER TABLE repair_orders CHANGE repair_order_id legacy_arksms_repair_order_id BIGINT UNSIGNED NULL');
        }

        Schema::table('repair_orders', function (Blueprint $table) {
            $table->unique('legacy_arksms_repair_order_id', 'ro_legacy_ark_ro_unique');
        });
    }
};
