<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_session_id')->unique()->constrained('call_sessions')->cascadeOnDelete();
            $table->foreignId('advisor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('composite_score')->nullable();
            $table->unsignedSmallInteger('coaching_opportunity_weight')->default(0);
            $table->json('strengths');
            $table->json('opportunities');
            $table->json('dimension_scores')->nullable();
            $table->timestamp('reviewed_at');
            $table->string('source', 32)->default('ai_analysis');
            $table->timestamps();

            $table->index(['reviewed_at', 'composite_score'], 'comm_reviews_day_score_idx');
            $table->index(['reviewed_at', 'coaching_opportunity_weight'], 'comm_reviews_day_coach_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_reviews');
    }
};
