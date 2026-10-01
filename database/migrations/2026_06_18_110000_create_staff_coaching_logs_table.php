<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_coaching_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('call_session_id')->nullable()->constrained('call_sessions')->nullOnDelete();
            $table->foreignId('staff_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('notes');
            $table->timestamp('discussed_at');
            $table->timestamps();

            $table->index(['staff_user_id', 'discussed_at'], 'staff_coach_staff_disc_idx');
            $table->index(['call_session_id', 'discussed_at'], 'staff_coach_call_disc_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_coaching_logs');
    }
};
