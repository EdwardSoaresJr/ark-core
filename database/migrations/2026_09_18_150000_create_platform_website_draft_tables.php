<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform Website P1 — draft authority. Publications exist for P2; never marked current here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_sites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_shop_id')->unique()->constrained('platform_shops')->cascadeOnDelete();
            $table->string('public_host', 255)->nullable();
            $table->string('writing_authority', 32)->default('core_live');
            $table->string('acknowledged_core_hash', 64)->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->boolean('management_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('website_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_site_id')->unique()->constrained('website_sites')->cascadeOnDelete();
            $table->json('document');
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('website_draft_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_draft_id')->constrained('website_drafts')->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->json('document');
            $table->string('action', 64);
            $table->string('core_hash', 64)->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['website_draft_id', 'revision'], 'website_draft_rev_unique');
            $table->index(['website_draft_id', 'created_at'], 'website_draft_rev_created_idx');
        });

        Schema::create('website_publications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_site_id')->constrained('website_sites')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('document');
            $table->string('content_hash', 64);
            $table->boolean('is_current')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('source_draft_revision')->nullable();
            $table->timestamps();

            $table->unique(['website_site_id', 'version'], 'website_pub_version_unique');
            $table->index(['website_site_id', 'is_current'], 'website_pub_current_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_publications');
        Schema::dropIfExists('website_draft_revisions');
        Schema::dropIfExists('website_drafts');
        Schema::dropIfExists('website_sites');
    }
};
