<?php

namespace App\Ark\Operations\LaborGuides\Rte;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class RteLaborGuideAvailability
{
    private const CACHE_KEY = 'rte:labor-guide:available';

    public function available(): bool
    {
        if (config('rte-labor-guide.enabled') === false) {
            return false;
        }

        return Cache::remember(self::CACHE_KEY, now()->addHour(), function (): bool {
            if (! Schema::hasTable('rte_lab')) {
                return false;
            }

            return DB::table('rte_lab')->limit(1)->exists();
        });
    }

    public static function forgetCachedState(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
