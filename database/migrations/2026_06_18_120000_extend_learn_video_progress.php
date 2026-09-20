<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('learn_video_progress', 'video_key')) {
            Schema::table('learn_video_progress', function (Blueprint $table) {
                $table->string('video_key', 64)->default('main')->after('article_key');
                $table->unsignedInteger('watched_seconds')->default(0)->after('percent_watched');
                $table->boolean('completed')->default(false)->after('watched_seconds');
                $table->unsignedInteger('last_position_seconds')->default(0)->after('completed');
            });
        }

        $indexNames = collect(Schema::getIndexes('learn_video_progress'))->pluck('name');

        if ($indexNames->contains('learn_vid_user_article') && ! $indexNames->contains('learn_vid_user_article_key')) {
            Schema::table('learn_video_progress', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropUnique('learn_vid_user_article');
                $table->unique(['user_id', 'article_key', 'video_key'], 'learn_vid_user_article_key');
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        $indexNames = collect(Schema::getIndexes('learn_video_progress'))->pluck('name');

        if ($indexNames->contains('learn_vid_user_article_key')) {
            Schema::table('learn_video_progress', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropUnique('learn_vid_user_article_key');
                $table->unique(['user_id', 'article_key'], 'learn_vid_user_article');
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('learn_video_progress', 'video_key')) {
            Schema::table('learn_video_progress', function (Blueprint $table) {
                $table->dropColumn(['video_key', 'watched_seconds', 'completed', 'last_position_seconds']);
            });
        }
    }
};
