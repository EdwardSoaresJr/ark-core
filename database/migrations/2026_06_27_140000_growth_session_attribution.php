<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('visitor_id')->index('growth_sessions_visitor_idx');
            $table->string('laravel_session_id', 128)->nullable()->unique('growth_sessions_laravel_sess_uq');
            $table->timestamp('started_at');
            $table->string('first_landing_page')->nullable();
            $table->string('first_referrer')->nullable();
            $table->string('first_search_query')->nullable();
            $table->string('first_campaign')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('device')->nullable();
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('started_at', 'growth_sessions_started_idx');
        });

        Schema::create('growth_touchpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('growth_session_id')->constrained('growth_sessions')->cascadeOnDelete();
            $table->string('type', 64);
            $table->string('path')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['growth_session_id', 'recorded_at'], 'growth_touchpoints_session_time_idx');
        });

        Schema::create('growth_last_touches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('growth_session_id')->unique('growth_last_touch_session_uq')->constrained('growth_sessions')->cascadeOnDelete();
            $table->string('landing_page')->nullable();
            $table->string('referrer')->nullable();
            $table->string('campaign')->nullable();
            $table->string('search_query')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->timestamp('touched_at');
            $table->timestamps();
        });

        Schema::table('leads', function (Blueprint $table): void {
            $table->foreignId('growth_session_id')->nullable()->after('uuid')->constrained('growth_sessions')->nullOnDelete();
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->foreignId('growth_session_id')->nullable()->after('id')->constrained('growth_sessions')->nullOnDelete();
        });

        Schema::table('repair_orders', function (Blueprint $table): void {
            $table->foreignId('growth_session_id')->nullable()->after('id')->constrained('growth_sessions')->nullOnDelete();
        });

        Schema::table('growth_events', function (Blueprint $table): void {
            $table->foreignId('growth_session_id')->nullable()->after('id')->constrained('growth_sessions')->nullOnDelete();
        });

        Schema::table('growth_attributions', function (Blueprint $table): void {
            $table->foreignId('growth_session_id')->nullable()->after('id')->constrained('growth_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('growth_attributions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('growth_session_id');
        });

        Schema::table('growth_events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('growth_session_id');
        });

        Schema::table('repair_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('growth_session_id');
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('growth_session_id');
        });

        Schema::table('leads', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('growth_session_id');
        });

        Schema::dropIfExists('growth_last_touches');
        Schema::dropIfExists('growth_touchpoints');
        Schema::dropIfExists('growth_sessions');
    }
};
