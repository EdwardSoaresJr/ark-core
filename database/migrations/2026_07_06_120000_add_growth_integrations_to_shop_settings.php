<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_settings', 'growth_integrations')) {
                $table->json('growth_integrations')->nullable()->after('public_surface_settings');
            }

            if (! Schema::hasColumn('shop_settings', 'growth_google_service_account')) {
                $table->text('growth_google_service_account')->nullable()->after('growth_integrations');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('shop_settings', 'growth_google_service_account')) {
                $table->dropColumn('growth_google_service_account');
            }

            if (Schema::hasColumn('shop_settings', 'growth_integrations')) {
                $table->dropColumn('growth_integrations');
            }
        });
    }
};
