<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_sms_intelligence_slices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->date('activity_date');
            $table->timestamp('last_message_at');
            $table->unsignedSmallInteger('message_count')->default(0);
            $table->unsignedSmallInteger('inbound_count')->default(0);
            $table->unsignedSmallInteger('outbound_count')->default(0);
            $table->longText('transcript')->nullable();
            $table->string('analysis_status', 24)->nullable();
            $table->longText('analysis_json')->nullable();
            $table->string('analysis_error', 512)->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamp('coaching_follow_up_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'activity_date'], 'sms_intel_slice_day_unique');
            $table->index('analysis_status', 'sms_intel_analysis_status_idx');
            $table->index('last_message_at', 'sms_intel_last_msg_idx');
            $table->index('coaching_follow_up_at', 'sms_intel_coach_fu_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_sms_intelligence_slices');
    }
};
