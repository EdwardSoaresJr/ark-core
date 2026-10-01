<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('vin', 17)->nullable();
            $table->string('normalized_vin', 17)->nullable();
            $table->string('plate', 32)->nullable();
            $table->string('plate_state', 32)->nullable();
            $table->string('color')->nullable();
            $table->string('nickname')->nullable();
            $table->string('public_notes')->nullable();
            $table->string('private_notes')->nullable();
            $table->timestamp('insights_built_at')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('trim')->nullable();
            $table->string('engine')->nullable();
            $table->string('transmission')->nullable();
            $table->string('drive')->nullable();
            $table->timestamps();

            $table->index('customer_id', 'vehicles_customer_idx');
            $table->index('vin', 'vehicles_vin_idx');
            $table->index('plate', 'vehicles_plate_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
