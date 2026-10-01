<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('engine_display')->nullable()->after('engine');
            $table->string('engine_code', 64)->nullable()->after('engine_display');
            $table->decimal('displacement_liters', 4, 1)->nullable()->after('engine_code');
            $table->string('fuel_type', 32)->nullable()->after('displacement_liters');
            $table->string('aspiration', 32)->nullable()->after('fuel_type');
            $table->string('drivetrain', 32)->nullable()->after('drive');
            $table->string('body_style')->nullable()->after('drivetrain');
            $table->string('manufacturer')->nullable()->after('body_style');
            $table->string('normalized_vehicle_key')->nullable()->after('manufacturer');
            $table->string('vehicle_identity_source', 32)->nullable()->after('normalized_vehicle_key');
            $table->timestamp('vehicle_identity_built_at')->nullable()->after('vehicle_identity_source');

            $table->index('normalized_vehicle_key', 'vehicles_identity_key_idx');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex('vehicles_identity_key_idx');
            $table->dropColumn([
                'engine_display',
                'engine_code',
                'displacement_liters',
                'fuel_type',
                'aspiration',
                'drivetrain',
                'body_style',
                'manufacturer',
                'normalized_vehicle_key',
                'vehicle_identity_source',
                'vehicle_identity_built_at',
            ]);
        });
    }
};
