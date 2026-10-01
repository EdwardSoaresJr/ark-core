<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_sessions', function (Blueprint $table): void {
            $table->timestamp('coaching_follow_up_at')->nullable()->after('analyzed_at');
            $table->index('coaching_follow_up_at', 'call_sess_coach_fu_idx');
        });
    }

    public function down(): void
    {
        Schema::table('call_sessions', function (Blueprint $table): void {
            $table->dropIndex('call_sess_coach_fu_idx');
            $table->dropColumn('coaching_follow_up_at');
        });
    }
};
