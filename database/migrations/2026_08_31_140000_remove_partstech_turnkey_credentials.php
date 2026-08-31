<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_settings')) {
            DB::table('shop_settings')->update([
                'partstech_base_url' => null,
                'partstech_catalog_path' => null,
                'partstech_username' => null,
                'partstech_api_key' => null,
                'partstech_password' => null,
            ]);

            Schema::table('shop_settings', function (Blueprint $table): void {
                foreach ([
                    'partstech_base_url',
                    'partstech_catalog_path',
                    'partstech_username',
                    'partstech_api_key',
                    'partstech_password',
                ] as $column) {
                    if (Schema::hasColumn('shop_settings', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('users')) {
            DB::table('users')->update([
                'partstech_username' => null,
                'partstech_password' => null,
            ]);

            Schema::table('users', function (Blueprint $table): void {
                if (Schema::hasColumn('users', 'partstech_username')) {
                    $table->dropColumn('partstech_username');
                }

                if (Schema::hasColumn('users', 'partstech_password')) {
                    $table->dropColumn('partstech_password');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shop_settings')) {
            Schema::table('shop_settings', function (Blueprint $table): void {
                if (! Schema::hasColumn('shop_settings', 'partstech_base_url')) {
                    $table->string('partstech_base_url')->nullable();
                }

                if (! Schema::hasColumn('shop_settings', 'partstech_catalog_path')) {
                    $table->string('partstech_catalog_path')->nullable();
                }

                if (! Schema::hasColumn('shop_settings', 'partstech_username')) {
                    $table->string('partstech_username')->nullable();
                }

                if (! Schema::hasColumn('shop_settings', 'partstech_api_key')) {
                    $table->text('partstech_api_key')->nullable();
                }

                if (! Schema::hasColumn('shop_settings', 'partstech_password')) {
                    $table->text('partstech_password')->nullable();
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (! Schema::hasColumn('users', 'partstech_username')) {
                    $table->string('partstech_username')->nullable();
                }

                if (! Schema::hasColumn('users', 'partstech_password')) {
                    $table->text('partstech_password')->nullable();
                }
            });
        }
    }
};
