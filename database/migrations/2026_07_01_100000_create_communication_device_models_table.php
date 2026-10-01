<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_device_models', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64);
            $table->string('manufacturer', 32);
            $table->string('label', 128);
            $table->string('minimum_firmware', 32)->nullable();
            $table->string('recommended_firmware', 32)->nullable();
            $table->string('latest_firmware', 32)->nullable();
            $table->string('builder', 32);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique('slug', 'comm_dev_model_slug_unique');
            $table->index(['enabled', 'builder'], 'comm_dev_model_enabled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_device_models');
    }
};
