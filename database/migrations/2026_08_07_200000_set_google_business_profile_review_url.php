<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Point shop google_reviews_url at the Google Business Profile review form.
 * Settings remains the authority — this only migrates known legacy destinations.
 */
return new class extends Migration
{
    private const NEW_REVIEW_URL = '';

    /** @var list<string> */
    private const LEGACY_REVIEW_URLS = [
        'https://www.google.com/maps/search/?api=1&query=Demo+Auto+Repair',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('shop_settings') || ! Schema::hasColumn('shop_settings', 'public_surface_settings')) {
            return;
        }

        $rows = DB::table('shop_settings')->select(['id', 'public_surface_settings'])->get();

        foreach ($rows as $row) {
            $stored = $row->public_surface_settings;

            if (is_string($stored)) {
                $settings = json_decode($stored, true);
            } elseif (is_array($stored)) {
                $settings = $stored;
            } else {
                $settings = null;
            }

            if (! is_array($settings)) {
                $settings = [];
            }

            $current = trim((string) ($settings['google_reviews_url'] ?? ''));

            if ($current !== '' && ! in_array($current, self::LEGACY_REVIEW_URLS, true)) {
                continue;
            }

            $settings['google_reviews_url'] = self::NEW_REVIEW_URL;

            DB::table('shop_settings')
                ->where('id', $row->id)
                ->update([
                    'public_surface_settings' => json_encode($settings),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Intentionally empty — review destination is shop configuration, not reversible schema.
    }
};
