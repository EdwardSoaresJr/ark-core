<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounters', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique('encounters_uuid_unique');
            $table->text('concern');
            $table->string('callback_name')->nullable();
            $table->string('callback_phone', 32)->nullable();
            $table->string('rough_vehicle')->nullable();
            $table->string('source', 32);
            $table->string('operational_state', 32)->default('new');
            $table->timestamp('last_operational_movement_at')->nullable();
            $table->foreignId('resolved_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('resolved_vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('tow_incoming')->default(false);
            $table->boolean('waiting_here')->default(false);
            $table->boolean('appointment')->default(false);
            $table->timestamps();

            $table->index(['operational_state', 'last_operational_movement_at'], 'encounters_state_movement_idx');
            $table->index('source', 'encounters_source_idx');
            $table->index('resolved_customer_id', 'encounters_customer_idx');
            $table->index('resolved_vehicle_id', 'encounters_vehicle_idx');
            $table->index('created_by', 'encounters_created_by_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounters');
    }
};
