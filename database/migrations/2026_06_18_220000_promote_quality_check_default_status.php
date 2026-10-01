<?php

use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusDefinition;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusTransition;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusTransitionRole;
use App\Ark\Runtime\Authorization\ArkRole;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        RepairOrderStatusDefinition::query()
            ->where('slug', RepairOrderStatus::QualityCheck->value)
            ->update([
                'dashboard_group_slug' => 'work_in_progress',
                'dashboard_group_name' => 'Work in progress',
                'show_on_advisor_board' => true,
                'show_on_technician_board' => true,
                'active' => true,
            ]);

        foreach ([
            [RepairOrderStatus::InProgress->value, RepairOrderStatus::QualityCheck->value],
            [RepairOrderStatus::QualityCheck->value, RepairOrderStatus::InProgress->value],
        ] as [$from, $to]) {
            $transition = RepairOrderStatusTransition::query()
                ->where('from_status_slug', $from)
                ->where('to_status_slug', $to)
                ->first();

            if ($transition === null) {
                continue;
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
        }

        app(RepairOrderStatusCatalog::class)->forgetCache();
    }

    public function down(): void
    {
        RepairOrderStatusDefinition::query()
            ->where('slug', RepairOrderStatus::QualityCheck->value)
            ->update([
                'dashboard_group_slug' => 'finalizing-and-pickup',
                'dashboard_group_name' => 'Finalizing & pickup',
            ]);

        foreach ([
            [RepairOrderStatus::InProgress->value, RepairOrderStatus::QualityCheck->value],
            [RepairOrderStatus::QualityCheck->value, RepairOrderStatus::InProgress->value],
        ] as [$from, $to]) {
            $transition = RepairOrderStatusTransition::query()
                ->where('from_status_slug', $from)
                ->where('to_status_slug', $to)
                ->first();

            if ($transition === null) {
                continue;
            }

            RepairOrderStatusTransitionRole::query()
                ->where('transition_id', $transition->id)
                ->whereIn('role', [ArkRole::Admin->value, ArkRole::Advisor->value])
                ->delete();
        }

        app(RepairOrderStatusCatalog::class)->forgetCache();
    }
};
