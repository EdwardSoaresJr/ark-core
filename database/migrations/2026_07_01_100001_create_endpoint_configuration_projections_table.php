<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('endpoint_configuration_projections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('communication_device_id')
                ->constrained('communication_devices', indexName: 'endpoint_cfg_proj_dev_fk')
                ->cascadeOnDelete();
            $table->string('inputs_fingerprint', 64);
            $table->longText('serialized_config')->nullable();
            $table->string('builder', 32);
            $table->string('format', 32);
            $table->timestamp('generated_at');
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();

            $table->index(
                ['communication_device_id', 'superseded_at'],
                'endpoint_cfg_proj_device_current_idx',
            );
            $table->index('generated_at', 'endpoint_cfg_proj_generated_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('endpoint_configuration_projections');
    }
};
