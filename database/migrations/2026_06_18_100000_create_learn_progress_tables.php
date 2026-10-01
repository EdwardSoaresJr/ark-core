<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('article_key', 120);
            $table->unsignedInteger('active_seconds')->default(0);
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'article_key'], 'learn_sess_user_article');
            $table->index(['user_id', 'updated_at'], 'learn_sess_user_updated');
        });

        Schema::create('learn_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('article_key', 120);
            $table->string('checkpoint_key', 64);
            $table->unsignedSmallInteger('checkpoint_index')->default(0);
            $table->unsignedInteger('active_seconds_at_reach')->default(0);
            $table->timestamp('reached_at');
            $table->timestamps();

            $table->unique(['user_id', 'article_key', 'checkpoint_key'], 'learn_cp_user_article_key');
            $table->index(['user_id', 'article_key'], 'learn_cp_user_article');
        });

        Schema::create('learn_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('article_key', 120);
            $table->unsignedSmallInteger('catalog_version')->default(1);
            $table->unsignedSmallInteger('article_version')->default(1);
            $table->unsignedInteger('active_seconds')->default(0);
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->unique(['user_id', 'article_key'], 'learn_done_user_article');
            $table->index(['completed_at'], 'learn_done_completed');
        });

        Schema::create('learn_video_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('article_key', 120);
            $table->string('video_key', 64)->default('main');
            $table->unsignedTinyInteger('percent_watched')->default(0);
            $table->unsignedInteger('watched_seconds')->default(0);
            $table->boolean('completed')->default(false);
            $table->unsignedInteger('last_position_seconds')->default(0);
            $table->json('watched_ranges')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'article_key', 'video_key'], 'learn_vid_user_article_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_video_progress');
        Schema::dropIfExists('learn_completions');
        Schema::dropIfExists('learn_checkpoints');
        Schema::dropIfExists('learn_sessions');
    }
};
