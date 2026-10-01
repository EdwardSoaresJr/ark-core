<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_sessions', function (Blueprint $table): void {
            $anchor = Schema::hasColumn('call_sessions', 'voicemail_duration_seconds')
                ? 'voicemail_duration_seconds'
                : (Schema::hasColumn('call_sessions', 'worked_at') ? 'worked_at' : null);

            if (! Schema::hasColumn('call_sessions', 'analysis_status')) {
                if ($anchor !== null) {
                    $table->string('analysis_status', 24)->nullable()->after($anchor);
                } else {
                    $table->string('analysis_status', 24)->nullable();
                }
            }

            if (! Schema::hasColumn('call_sessions', 'transcript')) {
                $table->longText('transcript')->nullable()->after('analysis_status');
            }

            if (! Schema::hasColumn('call_sessions', 'analysis_json')) {
                $table->json('analysis_json')->nullable()->after('transcript');
            }

            if (! Schema::hasColumn('call_sessions', 'analysis_error')) {
                $table->string('analysis_error', 512)->nullable()->after('analysis_json');
            }

            if (! Schema::hasColumn('call_sessions', 'analyzed_at')) {
                $table->timestamp('analyzed_at')->nullable()->after('analysis_error');
            }
        });

        Schema::table('call_sessions', function (Blueprint $table): void {
            if (Schema::hasColumn('call_sessions', 'analysis_status')) {
                $table->index('analysis_status', 'call_sess_analysis_status_idx');
            }

            if (Schema::hasColumn('call_sessions', 'started_at')) {
                $table->index('started_at', 'call_sess_started_at_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->dropIndex('call_sess_analysis_status_idx');
            $table->dropIndex('call_sess_started_at_idx');
            $table->dropColumn([
                'analysis_status',
                'transcript',
                'analysis_json',
                'analysis_error',
                'analyzed_at',
            ]);
        });
    }
};
