<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_devices', function (Blueprint $table) {
            if (! Schema::hasColumn('communication_devices', 'workstation_id')) {
                $table->foreignId('workstation_id')
                    ->nullable()
                    ->after('shop_settings_id')
                    ->constrained('workstations')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('communication_devices', 'model')) {
                $table->string('model', 64)->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('communication_devices', function (Blueprint $table) {
            if (Schema::hasColumn('communication_devices', 'workstation_id')) {
                $table->dropConstrainedForeignId('workstation_id');
            }

            if (Schema::hasColumn('communication_devices', 'model')) {
                $table->dropColumn('model');
            }
        });
    }
};
