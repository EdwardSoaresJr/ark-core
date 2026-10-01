<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telephony_extensions', function (Blueprint $table) {
            if (! Schema::hasColumn('telephony_extensions', 'workstation_id')) {
                $table->foreignId('workstation_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('workstations')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('telephony_extensions', 'communication_device_id')) {
                $table->foreignId('communication_device_id')
                    ->nullable()
                    ->after('workstation_id')
                    ->constrained('communication_devices')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('telephony_extensions', 'secret')) {
                $table->text('secret')
                    ->nullable()
                    ->after('notes');
            }
        });

        Schema::table('telephony_extensions', function (Blueprint $table) {
            if (! Schema::hasIndex('telephony_extensions', 'tel_ext_workstation_enabled_idx')) {
                $table->index(['workstation_id', 'enabled'], 'tel_ext_workstation_enabled_idx');
            }

            if (! Schema::hasIndex('telephony_extensions', 'tel_ext_device_unique')) {
                $table->unique('communication_device_id', 'tel_ext_device_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('telephony_extensions', function (Blueprint $table) {
            if (Schema::hasIndex('telephony_extensions', 'tel_ext_device_unique')) {
                $table->dropUnique('tel_ext_device_unique');
            }

            if (Schema::hasIndex('telephony_extensions', 'tel_ext_workstation_enabled_idx')) {
                $table->dropIndex('tel_ext_workstation_enabled_idx');
            }

            if (Schema::hasColumn('telephony_extensions', 'communication_device_id')) {
                $table->dropConstrainedForeignId('communication_device_id');
            }

            if (Schema::hasColumn('telephony_extensions', 'workstation_id')) {
                $table->dropConstrainedForeignId('workstation_id');
            }

            if (Schema::hasColumn('telephony_extensions', 'secret')) {
                $table->dropColumn('secret');
            }
        });
    }
};
