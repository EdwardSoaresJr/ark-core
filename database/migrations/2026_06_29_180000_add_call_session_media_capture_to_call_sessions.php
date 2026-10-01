<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_sessions', function (Blueprint $table): void {
            $table->string('recording_capture_status', 16)->nullable()->after('recording_duration_seconds');
            $table->string('voicemail_capture_status', 16)->nullable()->after('voicemail_duration_seconds');
            $table->text('recording_capture_error')->nullable()->after('recording_capture_status');
            $table->text('voicemail_capture_error')->nullable()->after('voicemail_capture_status');
            $table->json('recording_media_metadata')->nullable()->after('recording_capture_error');
            $table->json('voicemail_media_metadata')->nullable()->after('voicemail_capture_error');
        });
    }

    public function down(): void
    {
        Schema::table('call_sessions', function (Blueprint $table): void {
            $table->dropColumn([
                'recording_capture_status',
                'voicemail_capture_status',
                'recording_capture_error',
                'voicemail_capture_error',
                'recording_media_metadata',
                'voicemail_media_metadata',
            ]);
        });
    }
};
