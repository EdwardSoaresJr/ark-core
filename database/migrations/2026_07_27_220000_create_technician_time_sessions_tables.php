<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_time_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('clocked_in_at');
            $table->timestamp('clocked_out_at')->nullable();
            $table->foreignId('clocked_in_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('clocked_out_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('open');
            $table->timestamps();

            $table->index(['user_id', 'status'], 'tts_user_status_idx');
            $table->index(['user_id', 'clocked_in_at'], 'tts_user_clocked_in_idx');
            $table->index(['status'], 'tts_status_idx');
        });

        Schema::create('technician_time_session_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_time_session_id')
                ->constrained('technician_time_sessions', 'id', 'ttsc_session_fk')
                ->cascadeOnDelete();
            $table->string('field', 32);
            $table->string('from_value', 64)->nullable();
            $table->string('to_value', 64)->nullable();
            $table->text('reason');
            $table->foreignId('corrected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('corrected_at');
            $table->timestamps();

            $table->index(['technician_time_session_id'], 'ttsc_session_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_time_session_corrections');
        Schema::dropIfExists('technician_time_sessions');
    }
};
