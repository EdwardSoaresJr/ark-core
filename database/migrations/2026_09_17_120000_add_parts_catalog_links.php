<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_settings', 'nexpart_url')) {
                $table->string('nexpart_url', 255)->nullable();
            }

            if (! Schema::hasColumn('shop_settings', 'nexpart_enabled')) {
                $table->boolean('nexpart_enabled')->default(false);
            }

            if (! Schema::hasColumn('shop_settings', 'parts_catalog_links')) {
                $table->json('parts_catalog_links')->nullable();
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'parts_catalog_links')) {
                $table->json('parts_catalog_links')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('shop_settings', 'nexpart_enabled') ? 'nexpart_enabled' : null,
                Schema::hasColumn('shop_settings', 'nexpart_url') ? 'nexpart_url' : null,
                Schema::hasColumn('shop_settings', 'parts_catalog_links') ? 'parts_catalog_links' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'parts_catalog_links')) {
                $table->dropColumn('parts_catalog_links');
            }
        });
    }
};
