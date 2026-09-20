<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('owned_by_user_id')
                ->nullable()
                ->after('status')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('waiting_on', 16)->default('shop')->after('owned_by_user_id');
            $table->timestamp('posture_changed_at')->nullable()->after('waiting_on');
            $table->timestamp('resolved_at')->nullable()->after('posture_changed_at');
            $table->unsignedInteger('reopen_count')->default(0)->after('resolved_at');

            $table->index(['status', 'waiting_on'], 'conv_posture_lane_idx');
            $table->index('posture_changed_at', 'conv_posture_age_idx');
        });

        $now = now();

        DB::table('conversations')
            ->where('status', 'open')
            ->update([
                'waiting_on' => 'shop',
                'posture_changed_at' => $now,
            ]);

        DB::table('conversations')
            ->where('status', 'resolved')
            ->update([
                'resolved_at' => DB::raw('COALESCE(updated_at, created_at)'),
                'posture_changed_at' => DB::raw('COALESCE(updated_at, created_at)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conv_posture_lane_idx');
            $table->dropIndex('conv_posture_age_idx');
            $table->dropConstrainedForeignId('owned_by_user_id');
            $table->dropColumn([
                'waiting_on',
                'posture_changed_at',
                'resolved_at',
                'reopen_count',
            ]);
        });
    }
};
