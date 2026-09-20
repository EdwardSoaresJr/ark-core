<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telephony_extensions', function (Blueprint $table) {
            if (! Schema::hasColumn('telephony_extensions', 'mobile_device_id')) {
                $table->foreignId('mobile_device_id')
                    ->nullable()
                    ->constrained('mobile_devices')
                    ->nullOnDelete();
            }
        });

        Schema::table('telephony_extensions', function (Blueprint $table) {
            if (! Schema::hasIndex('telephony_extensions', 'tel_ext_mobile_device_unique')) {
                $table->unique('mobile_device_id', 'tel_ext_mobile_device_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('telephony_extensions', function (Blueprint $table) {
            if (Schema::hasIndex('telephony_extensions', 'tel_ext_mobile_device_unique')) {
                $table->dropUnique('tel_ext_mobile_device_unique');
            }

            if (Schema::hasColumn('telephony_extensions', 'mobile_device_id')) {
                $table->dropConstrainedForeignId('mobile_device_id');
            }
        });
    }
};
