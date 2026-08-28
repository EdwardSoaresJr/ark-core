<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->foreignId('repair_order_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->string('recording_url', 512)->nullable()->after('raw_payload');
            $table->string('recording_sid', 64)->nullable()->after('recording_url');
            $table->unsignedSmallInteger('recording_duration_seconds')->nullable()->after('recording_sid');
            $table->string('voicemail_url', 512)->nullable()->after('recording_duration_seconds');
            $table->string('voicemail_sid', 64)->nullable()->after('voicemail_url');
            $table->unsignedSmallInteger('voicemail_duration_seconds')->nullable()->after('voicemail_sid');

            $table->index('repair_order_id', 'call_sess_ro_idx');
        });
    }

    public function down(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('repair_order_id');
            $table->dropColumn([
                'recording_url',
                'recording_sid',
                'recording_duration_seconds',
                'voicemail_url',
                'voicemail_sid',
                'voicemail_duration_seconds',
            ]);
        });
    }
};
