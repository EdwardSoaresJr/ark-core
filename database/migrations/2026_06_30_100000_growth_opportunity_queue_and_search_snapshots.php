<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique('growth_opportunities_key_uq');
            $table->string('action_type', 32);
            $table->string('title');
            $table->text('impact_summary');
            $table->string('effort', 16);
            $table->string('status', 32)->default('discovered');
            $table->unsignedSmallInteger('priority_score')->default(0);
            $table->json('estimated_lift');
            $table->json('evidence');
            $table->foreignId('growth_content_id')->nullable()->constrained('growth_contents')->nullOnDelete();
            $table->string('search_query')->nullable();
            $table->string('landing_path')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('measuring_since')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->json('measurement')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority_score'], 'growth_opp_status_priority_idx');
        });

        Schema::table('growth_search_queries', function (Blueprint $table): void {
            $table->date('report_date')->nullable()->after('query');
        });

        Schema::table('growth_landing_page_metrics', function (Blueprint $table): void {
            $table->date('report_date')->nullable()->after('path');
        });

        Schema::table('growth_search_queries', function (Blueprint $table): void {
            $table->dropUnique('growth_search_query_period_uq');
            $table->unique(['query', 'report_date'], 'growth_sq_query_report_uq');
        });

        Schema::table('growth_landing_page_metrics', function (Blueprint $table): void {
            $table->dropUnique('growth_lpm_path_period_uq');
            $table->unique(['path', 'report_date'], 'growth_lpm_path_report_uq');
        });
    }

    public function down(): void
    {
        Schema::table('growth_landing_page_metrics', function (Blueprint $table): void {
            $table->dropUnique('growth_lpm_path_report_uq');
            $table->unique(['path', 'period_start', 'period_end'], 'growth_lpm_path_period_uq');
            $table->dropColumn('report_date');
        });

        Schema::table('growth_search_queries', function (Blueprint $table): void {
            $table->dropUnique('growth_sq_query_report_uq');
            $table->unique(['query', 'period_start', 'period_end'], 'growth_search_query_period_uq');
            $table->dropColumn('report_date');
        });

        Schema::dropIfExists('growth_opportunities');
    }
};
