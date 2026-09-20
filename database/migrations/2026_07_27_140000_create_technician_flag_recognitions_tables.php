<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_flag_recognitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_order_id')->constrained('repair_orders')->cascadeOnDelete();
            $table->foreignId('repair_order_concern_id')->constrained('repair_order_concerns')->cascadeOnDelete();
            $table->foreignId('technician_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('recognized_at');
            $table->decimal('flag_hours_total', 8, 2);
            $table->unsignedBigInteger('source_operational_event_id')->nullable();
            $table->string('recognition_policy', 64);
            $table->unsignedSmallInteger('recognition_policy_version');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('technician_attribution_source', 64);
            $table->timestamps();

            $table->index(['technician_user_id', 'recognized_at'], 'tfr_tech_recognized_idx');
            $table->index(['repair_order_concern_id', 'recognized_at'], 'tfr_concern_recognized_idx');
            $table->foreign('source_operational_event_id', 'tfr_source_event_fk')
                ->references('id')
                ->on('operational_events')
                ->nullOnDelete();
        });

        Schema::create('technician_flag_recognition_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_flag_recognition_id')
                ->constrained('technician_flag_recognitions', 'id', 'tfrl_recognition_fk')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('repair_order_line_id');
            $table->string('description', 500);
            $table->string('line_type', 16);
            $table->decimal('flag_hours', 8, 2);
            $table->unsignedBigInteger('operation_id')->nullable();
            $table->timestamps();

            // A labor line may be recognized at most once — reopen + complete cannot duplicate.
            $table->unique('repair_order_line_id', 'tfrl_line_unique');
            $table->foreign('repair_order_line_id', 'tfrl_line_fk')
                ->references('id')
                ->on('repair_order_lines')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_flag_recognition_lines');
        Schema::dropIfExists('technician_flag_recognitions');
    }
};
