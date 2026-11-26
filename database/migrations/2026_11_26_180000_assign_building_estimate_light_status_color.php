<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give Building Estimate a distinct color from Closed.
 *
 * Platform default was secondary (same visual family as other neutrals). Closed stays dark/gray.
 * Only rewrite rows that are still on the prior platform default so tenant customizations survive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ro_statuses')) {
            DB::table('ro_statuses')
                ->where('slug', 'estimate')
                ->where('color', 'secondary')
                ->update(['color' => 'light']);
        }

        if (Schema::hasTable('job_board_lanes')) {
            DB::table('job_board_lanes')
                ->where('key', 'estimates')
                ->where('color', 'secondary')
                ->update(['color' => 'light']);
        }

        $this->forgetStatusCatalogCaches();
    }

    public function down(): void
    {
        if (Schema::hasTable('ro_statuses')) {
            DB::table('ro_statuses')
                ->where('slug', 'estimate')
                ->where('color', 'light')
                ->update(['color' => 'secondary']);
        }

        if (Schema::hasTable('job_board_lanes')) {
            DB::table('job_board_lanes')
                ->where('key', 'estimates')
                ->where('color', 'light')
                ->update(['color' => 'secondary']);
        }

        $this->forgetStatusCatalogCaches();
    }

    private function forgetStatusCatalogCaches(): void
    {
        Cache::forget('repair_order_status_catalog.v4');
        Cache::forget('repair_order_status_catalog.v2');
        Cache::forget('repair_order_status_catalog.v1');
        Cache::forget('job_board_lanes.v1');
    }
};
