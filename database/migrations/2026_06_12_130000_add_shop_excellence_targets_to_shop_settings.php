<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->json('shop_excellence_targets')->nullable()->after('shop_overhead_per_hour_cents');
        });

        $legacyPath = base_path(config('shop-excellence.targets_path', 'docs/shop-excellence/demo-shop/targets.php'));

        if (! is_readable($legacyPath)) {
            return;
        }

        $parsed = require $legacyPath;

        if (! is_array($parsed) || $parsed === []) {
            return;
        }

        DB::table('shop_settings')->limit(1)->update([
            'shop_excellence_targets' => json_encode($parsed),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropColumn('shop_excellence_targets');
        });
    }
};
