<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_article_media', function (Blueprint $table) {
            $table->id();
            $table->string('article_key', 120);
            $table->string('slot', 120);
            $table->string('kind', 20);
            $table->string('storage_path', 255)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->string('youtube_video_id', 20)->nullable();
            $table->string('original_name', 255)->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['article_key', 'slot'], 'learn_media_article_slot');
            $table->index('article_key', 'learn_media_article_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_article_media');
    }
};
