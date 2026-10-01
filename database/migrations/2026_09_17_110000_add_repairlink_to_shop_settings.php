<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_settings', 'repairlink_url')) {
                $table->string('repairlink_url', 255)->nullable();
            }

            if (! Schema::hasColumn('shop_settings', 'repairlink_enabled')) {
                $table->boolean('repairlink_enabled')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('shop_settings', 'repairlink_enabled') ? 'repairlink_enabled' : null,
                Schema::hasColumn('shop_settings', 'repairlink_url') ? 'repairlink_url' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
