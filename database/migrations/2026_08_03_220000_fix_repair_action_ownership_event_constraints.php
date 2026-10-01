<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production create aborted on MySQL identifier length for the default
 * repair_order_work_group_id foreign key name. Columns/data exist; FKs + index do not.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('repair_action_ownership_events')) {
            return;
        }

        $foreignKeys = collect(Schema::getForeignKeys('repair_action_ownership_events'));
        $foreignNames = $foreignKeys->pluck('name');
        $columnsWithForeign = $foreignKeys
            ->flatMap(fn (array $fk): array => $fk['columns'] ?? [])
            ->unique()
            ->values();

        Schema::table('repair_action_ownership_events', function (Blueprint $table) use ($foreignNames, $columnsWithForeign): void {
            if (! Schema::hasIndex('repair_action_ownership_events', 'ra_own_evt_wg_at_idx')) {
                $table->index(['repair_order_work_group_id', 'occurred_at'], 'ra_own_evt_wg_at_idx');
            }

            if (! $columnsWithForeign->contains('repair_order_work_group_id')
                && ! $foreignNames->contains('ra_own_evt_wg_fk')) {
                $table->foreign('repair_order_work_group_id', 'ra_own_evt_wg_fk')
                    ->references('id')
                    ->on('repair_order_work_groups')
                    ->cascadeOnDelete();
            }

            if (! $columnsWithForeign->contains('actor_user_id')
                && ! $foreignNames->contains('ra_own_evt_actor_fk')) {
                $table->foreign('actor_user_id', 'ra_own_evt_actor_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('repair_action_ownership_events')) {
            return;
        }

        $foreignNames = collect(Schema::getForeignKeys('repair_action_ownership_events'))->pluck('name');

        Schema::table('repair_action_ownership_events', function (Blueprint $table) use ($foreignNames): void {
            if ($foreignNames->contains('ra_own_evt_wg_fk')) {
                $table->dropForeign('ra_own_evt_wg_fk');
            }

            if ($foreignNames->contains('ra_own_evt_actor_fk')) {
                $table->dropForeign('ra_own_evt_actor_fk');
            }

            if (Schema::hasIndex('repair_action_ownership_events', 'ra_own_evt_wg_at_idx')) {
                $table->dropIndex('ra_own_evt_wg_at_idx');
            }
        });
    }
};
