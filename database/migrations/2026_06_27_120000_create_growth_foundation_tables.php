<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_contents', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique('growth_contents_slug_uq');
            $table->string('template', 64);
            $table->string('title');
            $table->string('path')->unique('growth_contents_path_uq');
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->boolean('indexable')->default(true);
            $table->unsignedTinyInteger('priority')->default(50);
            $table->timestamp('last_crawl_at')->nullable();
            $table->unsignedInteger('search_clicks')->default(0);
            $table->unsignedInteger('search_impressions')->default(0);
            $table->unsignedBigInteger('revenue_cents')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['indexable', 'published_at'], 'growth_contents_indexable_pub_idx');
        });

        Schema::create('growth_redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('from_path')->unique('growth_redirects_from_uq');
            $table->string('to_path')->nullable();
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_wildcard')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('hit_count')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('growth_events', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 64);
            $table->uuid('visitor_id')->nullable();
            $table->foreignId('growth_content_id')->nullable()->constrained('growth_contents')->nullOnDelete();
            $table->string('path')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['type', 'recorded_at'], 'growth_events_type_recorded_idx');
            $table->index(['growth_content_id', 'recorded_at'], 'growth_events_content_recorded_idx');
        });

        Schema::create('growth_attributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repair_order_id')->nullable()->constrained('repair_orders')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->foreignId('growth_content_id')->nullable()->constrained('growth_contents')->nullOnDelete();
            $table->string('source')->nullable();
            $table->string('campaign')->nullable();
            $table->string('landing_page')->nullable();
            $table->string('search_query')->nullable();
            $table->string('referrer')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->unsignedBigInteger('revenue_cents')->default(0);
            $table->unsignedBigInteger('gross_profit_cents')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('repair_order_id', 'growth_attr_ro_uq');
            $table->index('lead_id', 'growth_attr_lead_idx');
            $table->index('growth_content_id', 'growth_attr_content_idx');
        });

        Schema::create('growth_search_queries', function (Blueprint $table): void {
            $table->id();
            $table->string('query');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 8, 4)->default(0);
            $table->decimal('position', 8, 2)->nullable();
            $table->unsignedBigInteger('revenue_cents')->default(0);
            $table->unsignedInteger('repair_order_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['query', 'period_start', 'period_end'], 'growth_search_query_period_uq');
        });

        Schema::create('growth_landing_page_metrics', function (Blueprint $table): void {
            $table->id();
            $table->string('path');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 8, 4)->default(0);
            $table->decimal('position', 8, 2)->nullable();
            $table->unsignedInteger('page_views')->default(0);
            $table->unsignedInteger('appointments')->default(0);
            $table->unsignedBigInteger('revenue_cents')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['path', 'period_start', 'period_end'], 'growth_lpm_path_period_uq');
        });

        Schema::create('growth_index_coverage', function (Blueprint $table): void {
            $table->id();
            $table->string('url');
            $table->string('status', 32);
            $table->timestamp('last_crawl_at')->nullable();
            $table->text('detail')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'last_crawl_at'], 'growth_index_cov_status_idx');
        });

        Schema::create('growth_core_web_vitals', function (Blueprint $table): void {
            $table->id();
            $table->string('url');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('lcp_ms', 10, 2)->nullable();
            $table->decimal('inp_ms', 10, 2)->nullable();
            $table->decimal('cls', 8, 4)->nullable();
            $table->string('lcp_rating', 16)->nullable();
            $table->string('inp_rating', 16)->nullable();
            $table->string('cls_rating', 16)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['url', 'period_start', 'period_end'], 'growth_cwv_url_period_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_core_web_vitals');
        Schema::dropIfExists('growth_index_coverage');
        Schema::dropIfExists('growth_landing_page_metrics');
        Schema::dropIfExists('growth_search_queries');
        Schema::dropIfExists('growth_attributions');
        Schema::dropIfExists('growth_events');
        Schema::dropIfExists('growth_redirects');
        Schema::dropIfExists('growth_contents');
    }
};
