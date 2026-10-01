<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fresh public installs only. Not loaded when ARK_PRESERVE_WEBSITE_GROWTH_SCHEMA=true.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['leads', 'conversations', 'repair_orders'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'growth_session_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('growth_session_id');
            });
        }

        Schema::disableForeignKeyConstraints();

        foreach ([
            'growth_touchpoints',
            'growth_last_touches',
            'growth_attributions',
            'growth_events',
            'growth_opportunities',
            'growth_generated_common_problems',
            'growth_sync_tasks',
            'growth_location_metrics',
            'growth_search_queries',
            'growth_landing_page_metrics',
            'growth_index_coverage',
            'growth_core_web_vitals',
            'growth_redirects',
            'growth_sessions',
            'growth_contents',
            'public_surface_events',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();

        if (Schema::hasTable('shop_settings')) {
            $dropColumns = array_values(array_filter([
                Schema::hasColumn('shop_settings', 'growth_google_service_account') ? 'growth_google_service_account' : null,
                Schema::hasColumn('shop_settings', 'growth_integrations') ? 'growth_integrations' : null,
                Schema::hasColumn('shop_settings', 'public_surface_settings') ? 'public_surface_settings' : null,
            ]));

            if ($dropColumns !== []) {
                Schema::table('shop_settings', function (Blueprint $table) use ($dropColumns): void {
                    $table->dropColumn($dropColumns);
                });
            }
        }
    }

    public function down(): void
    {
        //
    }
};
