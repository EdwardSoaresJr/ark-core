<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_settings', 'parts_catalog_button_colors')) {
                $table->json('parts_catalog_button_colors')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('shop_settings', 'parts_catalog_button_colors')) {
                $table->dropColumn('parts_catalog_button_colors');
            }
        });
    }
};
