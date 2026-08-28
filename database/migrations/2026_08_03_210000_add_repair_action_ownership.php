<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_order_work_groups', function (Blueprint $table): void {
            $table->string('owner_type', 32)->default('technician')->after('position');
            $table->unsignedBigInteger('owner_user_id')->nullable()->after('owner_type');
            $table->index(['owner_type', 'owner_user_id'], 'ro_wg_owner_idx');
        });

        // FK after column exists (SQLite-friendly).
        Schema::table('repair_order_work_groups', function (Blueprint $table): void {
            $table->foreign('owner_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::create('repair_action_ownership_events', function (Blueprint $table): void {
            $table->id();
            // Short FK names — default MySQL identifier exceeds 64 chars.
            $table->unsignedBigInteger('repair_order_work_group_id');
            $table->string('event_kind', 32);
            $table->string('from_owner_type', 32)->nullable();
            $table->unsignedBigInteger('from_owner_user_id')->nullable();
            $table->string('to_owner_type', 32);
            $table->unsignedBigInteger('to_owner_user_id')->nullable();
            $table->string('reason', 255)->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['repair_order_work_group_id', 'occurred_at'], 'ra_own_evt_wg_at_idx');
            $table->foreign('repair_order_work_group_id', 'ra_own_evt_wg_fk')
                ->references('id')
                ->on('repair_order_work_groups')
                ->cascadeOnDelete();
            $table->foreign('actor_user_id', 'ra_own_evt_actor_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        $now = now();
        $groups = DB::table('repair_order_work_groups')->get(['id', 'repair_order_concern_id']);

        foreach ($groups as $group) {
            $primaryTechnicianId = DB::table('repair_order_concerns as c')
                ->join('repair_orders as ro', 'ro.id', '=', 'c.repair_order_id')
                ->where('c.id', $group->repair_order_concern_id)
                ->value('ro.assigned_technician_id');

            if ($primaryTechnicianId === null) {
                continue;
            }

            DB::table('repair_order_work_groups')
                ->where('id', $group->id)
                ->update([
                    'owner_type' => 'technician',
                    'owner_user_id' => $primaryTechnicianId,
                ]);

            DB::table('repair_action_ownership_events')->insert([
                'repair_order_work_group_id' => $group->id,
                'event_kind' => 'assigned',
                'from_owner_type' => null,
                'from_owner_user_id' => null,
                'to_owner_type' => 'technician',
                'to_owner_user_id' => $primaryTechnicianId,
                'reason' => 'Migrated from RO Primary Technician',
                'actor_user_id' => null,
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_action_ownership_events');

        Schema::table('repair_order_work_groups', function (Blueprint $table): void {
            $table->dropForeign(['owner_user_id']);
            $table->dropIndex('ro_wg_owner_idx');
            $table->dropColumn(['owner_type', 'owner_user_id']);
        });
    }
};
