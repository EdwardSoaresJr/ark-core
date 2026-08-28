<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_order_work_groups', function (Blueprint $table): void {
            $table->string('status', 32)->default('pending')->after('owner_user_id');
            $table->text('latest_update')->nullable()->after('status');
            $table->index(['status', 'owner_user_id'], 'ro_wg_status_owner_idx');
        });
    }

    public function down(): void
    {
        Schema::table('repair_order_work_groups', function (Blueprint $table): void {
            $table->dropIndex('ro_wg_status_owner_idx');
            $table->dropColumn(['status', 'latest_update']);
        });
    }
};
