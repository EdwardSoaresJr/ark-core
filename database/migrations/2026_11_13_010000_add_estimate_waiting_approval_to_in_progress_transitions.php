<?php

use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusDefinition;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusTransition;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusTransitionRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Advisors can move Building Estimate / Waiting Approval straight to In Progress.
     * Technician assignment (and other gates) still block the move until satisfied.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ro_statuses') || ! Schema::hasTable('ro_status_transitions')) {
            return;
        }

        if (! RepairOrderStatusDefinition::query()->exists()) {
            return;
        }

        $edges = [
            ['estimate', 'in_progress', ['technician', 'admin', 'advisor']],
            ['waiting_approval', 'in_progress', ['technician', 'admin', 'advisor']],
        ];

        foreach ($edges as [$from, $to, $roles]) {
            $row = RepairOrderStatusTransition::query()->updateOrCreate(
                [
                    'from_status_slug' => $from,
                    'to_status_slug' => $to,
                ],
                [
                    'active' => true,
                ],
            );

            $row->roles()->delete();

            foreach ($roles as $role) {
                RepairOrderStatusTransitionRole::query()->create([
                    'transition_id' => $row->id,
                    'role' => $role,
                ]);
            }
        }

        app(RepairOrderStatusCatalog::class)->forgetCache();
    }

    public function down(): void
    {
        if (! Schema::hasTable('ro_status_transitions')) {
            return;
        }

        RepairOrderStatusTransition::query()
            ->where(function ($query): void {
                $query->where(function ($inner): void {
                    $inner->where('from_status_slug', 'estimate')
                        ->where('to_status_slug', 'in_progress');
                })->orWhere(function ($inner): void {
                    $inner->where('from_status_slug', 'waiting_approval')
                        ->where('to_status_slug', 'in_progress');
                });
            })
            ->each(function (RepairOrderStatusTransition $row): void {
                $row->roles()->delete();
                $row->delete();
            });

        if (app()->bound(RepairOrderStatusCatalog::class)) {
            app(RepairOrderStatusCatalog::class)->forgetCache();
        }
    }
};
