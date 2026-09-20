<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_devices', function (Blueprint $table) {
            if (! Schema::hasColumn('communication_devices', 'communication_device_model_id')) {
                $table->foreignId('communication_device_model_id')
                    ->nullable()
                    ->after('shop_settings_id')
                    ->constrained('communication_device_models')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('communication_devices', 'mac_address')) {
                $table->char('mac_address', 12)
                    ->nullable()
                    ->after('model');
            }

            if (! Schema::hasColumn('communication_devices', 'firmware_version')) {
                $table->string('firmware_version', 32)
                    ->nullable()
                    ->after('mac_address');
            }

            if (! Schema::hasColumn('communication_devices', 'is_active')) {
                $table->boolean('is_active')
                    ->default(true)
                    ->after('status');
            }
        });

        Schema::table('communication_devices', function (Blueprint $table) {
            if (! Schema::hasIndex('communication_devices', 'comm_dev_shop_mac_unique')) {
                $table->unique(['shop_settings_id', 'mac_address'], 'comm_dev_shop_mac_unique');
            }

            if (! Schema::hasIndex('communication_devices', 'comm_dev_shop_active_idx')) {
                $table->index(['shop_settings_id', 'is_active'], 'comm_dev_shop_active_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('communication_devices', function (Blueprint $table) {
            if (Schema::hasIndex('communication_devices', 'comm_dev_shop_mac_unique')) {
                $table->dropUnique('comm_dev_shop_mac_unique');
            }

            if (Schema::hasIndex('communication_devices', 'comm_dev_shop_active_idx')) {
                $table->dropIndex('comm_dev_shop_active_idx');
            }

            if (Schema::hasColumn('communication_devices', 'communication_device_model_id')) {
                $table->dropConstrainedForeignId('communication_device_model_id');
            }

            $columns = array_values(array_filter(
                ['mac_address', 'firmware_version', 'is_active'],
                fn (string $column): bool => Schema::hasColumn('communication_devices', $column),
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
