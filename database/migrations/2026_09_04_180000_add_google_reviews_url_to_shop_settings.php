<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Core-owned Google review destination for post-repair review requests.
 * Backfills from legacy public_surface_settings.google_reviews_url when present.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shop_settings')) {
            return;
        }

        if (! Schema::hasColumn('shop_settings', 'google_reviews_url')) {
            Schema::table('shop_settings', function (Blueprint $table): void {
                $table->string('google_reviews_url', 2048)->nullable()->after('email');
            });
        }

        if (! Schema::hasColumn('shop_settings', 'public_surface_settings')) {
            return;
        }

        $rows = DB::table('shop_settings')->select(['id', 'public_surface_settings', 'google_reviews_url'])->get();

        foreach ($rows as $row) {
            if (filled($row->google_reviews_url)) {
                continue;
            }

            $stored = $row->public_surface_settings;
            if ($stored === null || $stored === '') {
                continue;
            }

            $settings = is_string($stored) ? json_decode($stored, true) : $stored;
            if (! is_array($settings)) {
                continue;
            }

            $url = trim((string) ($settings['google_reviews_url'] ?? ''));
            if ($url === '') {
                continue;
            }

            DB::table('shop_settings')->where('id', $row->id)->update([
                'google_reviews_url' => $url,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('shop_settings') || ! Schema::hasColumn('shop_settings', 'google_reviews_url')) {
            return;
        }

        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->dropColumn('google_reviews_url');
        });
    }
};
