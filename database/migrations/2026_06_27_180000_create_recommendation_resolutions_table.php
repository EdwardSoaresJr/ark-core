<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendation_resolutions', function (Blueprint $table) {
            $table->id();
            $table->string('recommendation_kind', 64);
            $table->string('aggregate_type', 128);
            $table->unsignedBigInteger('aggregate_id');
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('completion_event', 64);
            $table->string('outcome_label', 255);
            $table->string('title_snapshot', 255);
            $table->timestamp('pressure_since')->nullable();
            $table->timestamp('completed_at');
            $table->unsignedInteger('elapsed_minutes')->nullable();
            $table->timestamps();

            $table->index(['recommendation_kind', 'aggregate_type', 'aggregate_id'], 'rec_resolution_agg');
            $table->index('completed_at', 'rec_resolution_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_resolutions');
    }
};
