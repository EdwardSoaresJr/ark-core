<?php

use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusTransition;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusTransitionRole;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusDefinition;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    /**
     * @return list<array{0: string, 1: string, 2: list<string>}>
     */
    private function retreatTransitions(): array
    {
        return [
            ['ready_pickup', 'completed', ['advisor', 'admin']],
            ['approved', 'waiting_approval', ['advisor', 'admin']],
            ['in_progress', 'ready_for_work', ['advisor', 'admin', 'technician']],
            ['completed', 'quality_check', ['advisor', 'admin']],
            ['completed', 'in_progress', ['advisor', 'admin']],
            ['invoiced', 'completed', ['advisor', 'admin']],
            ['quality_check', 'approved', ['advisor', 'admin']],
            ['quality_check', 'waiting_parts', ['advisor', 'admin']],
        ];
    }

    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('ro_statuses')
            || ! RepairOrderStatusDefinition::query()->exists()) {
            return;
        }

        foreach ($this->retreatTransitions() as [$fromSlug, $toSlug, $roles]) {
            $transition = RepairOrderStatusTransition::query()->updateOrCreate(
                [
                    'from_status_slug' => $fromSlug,
                    'to_status_slug' => $toSlug,
                ],
                ['active' => true],
            );

            RepairOrderStatusTransitionRole::query()
                ->where('transition_id', $transition->id)
                ->delete();

            foreach ($roles as $role) {
                RepairOrderStatusTransitionRole::query()->create([
                    'transition_id' => $transition->id,
                    'role' => $role,
                ]);
            }
        }

        foreach (['repair_order_status_catalog.v4', 'repair_order_status_catalog.v2', 'repair_order_status_catalog.v1'] as $key) {
            Cache::forget($key);
        }
    }

    public function down(): void
    {
        foreach ($this->retreatTransitions() as [$fromSlug, $toSlug]) {
            RepairOrderStatusTransition::query()
                ->where('from_status_slug', $fromSlug)
                ->where('to_status_slug', $toSlug)
                ->delete();
        }

        foreach (['repair_order_status_catalog.v4', 'repair_order_status_catalog.v2', 'repair_order_status_catalog.v1'] as $key) {
            Cache::forget($key);
        }
    }
};
