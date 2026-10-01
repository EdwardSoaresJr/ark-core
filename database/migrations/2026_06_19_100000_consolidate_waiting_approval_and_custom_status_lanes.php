<?php

use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusDefinition;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusTransition;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusTransitionRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ro_statuses', function (Blueprint $table): void {
            $table->string('advisor_lane_key', 32)->nullable()->after('dashboard_group_name');
        });

        DB::table('repair_orders')
            ->where('status', 'awaiting_approval')
            ->update(['status' => RepairOrderStatus::WaitingApproval->value]);

        if (Schema::hasTable('shop_settings')) {
            DB::table('shop_settings')
                ->where('default_estimate_state', 'awaiting_approval')
                ->update(['default_estimate_state' => RepairOrderStatus::WaitingApproval->value]);
        }

        $laneKeys = [
            'draft' => null,
            'estimate' => null,
            'waiting_approval' => 'waiting_approval',
            'approved' => 'shop_floor',
            'waiting_parts' => 'waiting_parts',
            'ready_for_work' => 'shop_floor',
            'in_progress' => 'shop_floor',
            'quality_check' => 'quality_check',
            'completed' => 'ready_pickup',
            'invoiced' => 'ready_pickup',
            'ready_pickup' => 'ready_pickup',
            'closed' => null,
            'awaiting_approval' => 'waiting_approval',
        ];

        foreach ($laneKeys as $slug => $laneKey) {
            RepairOrderStatusDefinition::query()
                ->where('slug', $slug)
                ->update(['advisor_lane_key' => $laneKey]);
        }

        RepairOrderStatusTransition::query()
            ->where(function ($query): void {
                $query->where('from_status_slug', 'awaiting_approval')
                    ->orWhere('to_status_slug', 'awaiting_approval');
            })
            ->delete();

        RepairOrderStatusDefinition::query()
            ->where('slug', 'awaiting_approval')
            ->delete();

        app(RepairOrderStatusCatalog::class)->forgetCache();
    }

    public function down(): void
    {
        RepairOrderStatusDefinition::query()->updateOrCreate(
            ['slug' => 'awaiting_approval'],
            [
                'name' => 'Awaiting Approval',
                'is_system' => true,
                'dashboard_group_slug' => 'new_arrivals_intake',
                'dashboard_group_name' => 'Estimates',
                'advisor_lane_key' => 'waiting_approval',
                'show_on_advisor_board' => true,
                'show_on_technician_board' => false,
                'is_terminal' => false,
                'requires_variant' => false,
                'enforce_standard_close_rules' => false,
                'active' => true,
                'sort_order' => 12,
            ],
        );

        Schema::table('ro_statuses', function (Blueprint $table): void {
            $table->dropColumn('advisor_lane_key');
        });

        app(RepairOrderStatusCatalog::class)->forgetCache();
    }
};
