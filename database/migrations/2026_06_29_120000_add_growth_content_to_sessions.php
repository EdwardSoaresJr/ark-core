<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('growth_sessions', function (Blueprint $table): void {
            $table->foreignId('first_growth_content_id')
                ->nullable()
                ->after('first_campaign')
                ->constrained('growth_contents')
                ->nullOnDelete();
        });

        Schema::table('growth_touchpoints', function (Blueprint $table): void {
            $table->foreignId('growth_content_id')
                ->nullable()
                ->after('growth_session_id')
                ->constrained('growth_contents')
                ->nullOnDelete();

            $table->index(['growth_content_id', 'recorded_at'], 'growth_touchpoints_content_time_idx');
        });
    }

    public function down(): void
    {
        Schema::table('growth_touchpoints', function (Blueprint $table): void {
            $table->dropIndex('growth_touchpoints_content_time_idx');
            $table->dropConstrainedForeignId('growth_content_id');
        });

        Schema::table('growth_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('first_growth_content_id');
        });
    }
};
