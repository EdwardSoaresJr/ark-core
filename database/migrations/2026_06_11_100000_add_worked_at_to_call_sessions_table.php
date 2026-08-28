<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->timestamp('worked_at')->nullable()->after('ended_at');
            $table->index(['worked_at', 'started_at'], 'call_sess_queue_idx');
        });
    }

    public function down(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->dropIndex('call_sess_queue_idx');
            $table->dropColumn('worked_at');
        });
    }
};
