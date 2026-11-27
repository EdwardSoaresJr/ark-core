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
 *
 * Rollback is intentionally non-destructive: this migration does not record which rows it touched,
 * so `down()` must not rewrite every `light` value back to `secondary` (that would overwrite a
 * later intentional tenant choice). Operators who need the prior platform default can set
 * `ro_statuses.color = secondary` for slug `estimate` and `job_board_lanes.color = secondary`
 * for key `estimates` only where they still want that default, then clear the status catalog caches.
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
        // Non-destructive: no provenance of which rows this migration changed.
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
