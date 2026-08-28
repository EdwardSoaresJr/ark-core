<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advisor_tasks', function (Blueprint $table): void {
            $table->foreignId('assigned_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by_user_id')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
            $table->foreignId('call_session_id')->nullable()->after('vehicle_id')->constrained('call_sessions')->nullOnDelete();
            $table->index(['assigned_user_id', 'completed_at'], 'adv_task_assignee_done');
        });

        Schema::table('station_device_tokens', function (Blueprint $table): void {
            $table->json('glass_config')->nullable()->after('shop_identity');
        });
    }

    public function down(): void
    {
        Schema::table('advisor_tasks', function (Blueprint $table): void {
            $table->dropIndex('adv_task_assignee_done');
            $table->dropConstrainedForeignId('call_session_id');
            $table->dropConstrainedForeignId('completed_by_user_id');
            $table->dropConstrainedForeignId('assigned_user_id');
        });

        Schema::table('station_device_tokens', function (Blueprint $table): void {
            $table->dropColumn('glass_config');
        });
    }
};
