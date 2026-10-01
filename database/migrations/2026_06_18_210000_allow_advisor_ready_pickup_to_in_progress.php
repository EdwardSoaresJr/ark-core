<?php

use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusTransition;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusTransitionRole;
use App\Ark\Runtime\Authorization\ArkRole;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $transition = RepairOrderStatusTransition::query()
            ->where('from_status_slug', RepairOrderStatus::ReadyPickup->value)
            ->where('to_status_slug', RepairOrderStatus::InProgress->value)
            ->first();

        if ($transition === null) {
            return;
        }

        foreach ([
            ArkRole::Technician->value,
            ArkRole::Admin->value,
            ArkRole::Advisor->value,
        ] as $role) {
            RepairOrderStatusTransitionRole::query()->firstOrCreate([
                'transition_id' => $transition->id,
                'role' => $role,
            ]);
        }

        app(RepairOrderStatusCatalog::class)->forgetCache();
    }

    public function down(): void
    {
        $transition = RepairOrderStatusTransition::query()
            ->where('from_status_slug', RepairOrderStatus::ReadyPickup->value)
            ->where('to_status_slug', RepairOrderStatus::InProgress->value)
            ->first();

        if ($transition === null) {
            return;
        }

        RepairOrderStatusTransitionRole::query()
            ->where('transition_id', $transition->id)
            ->whereIn('role', [ArkRole::Admin->value, ArkRole::Advisor->value])
            ->delete();

        app(RepairOrderStatusCatalog::class)->forgetCache();
    }
};
