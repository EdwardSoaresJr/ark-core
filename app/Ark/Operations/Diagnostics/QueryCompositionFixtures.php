<?php

namespace App\Ark\Operations\Diagnostics;

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Approvals\ApprovalType;
use App\Ark\Operations\Communications\CommunicationEventRecorder;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Support\Carbon;

final class QueryCompositionFixtures
{
    public static function representativeRepairOrder(): RepairOrder
    {
        $customer = Customer::query()->create([
            'first_name' => 'Composition',
            'last_name' => 'Customer',
            'phone' => '7195559090',
            'email' => 'composition.customer@example.test',
        ]);

        $vehicle = Vehicle::query()->create([
            'customer_id' => $customer->id,
            'plate' => 'COMP1',
            'year' => 2018,
            'make' => 'Toyota',
            'model' => 'Camry',
        ]);

        $repairOrder = RepairOrder::query()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => RepairOrderStatus::WaitingApproval,
            'concern_summary' => 'Brakes pulse · customer wants estimate review.',
            'opened_at' => Carbon::parse('2026-06-01 08:30:00'),
        ]);

        $approvedConcern = RepairOrderConcern::query()->create([
            'repair_order_id' => $repairOrder->id,
            'summary' => 'Front brake service',
            'disposition' => RepairOrderConcernDisposition::Approved,
            'recommendation_intent' => 'immediate_attention',
            'position' => 1,
        ]);

        $recommendedConcern = RepairOrderConcern::query()->create([
            'repair_order_id' => $repairOrder->id,
            'summary' => 'Rear brake inspection',
            'disposition' => RepairOrderConcernDisposition::Recommended,
            'recommendation_intent' => 'maintenance',
            'position' => 2,
        ]);

        RepairOrderLine::query()->create([
            'repair_order_id' => $repairOrder->id,
            'repair_order_concern_id' => $approvedConcern->id,
            'type' => RepairOrderLineType::Labor,
            'description' => 'Front brake pad replacement',
            'quantity' => '1.50',
            'unit_price_cents' => 14500,
            'position' => 1,
        ]);

        RepairOrderLine::query()->create([
            'repair_order_id' => $repairOrder->id,
            'repair_order_concern_id' => $approvedConcern->id,
            'type' => RepairOrderLineType::Part,
            'description' => 'Front brake pads',
            'quantity' => '1.00',
            'unit_price_cents' => 9800,
            'procurement_state' => PartProcurementState::Ordered,
            'position' => 2,
        ]);

        RepairOrderLine::query()->create([
            'repair_order_id' => $repairOrder->id,
            'repair_order_concern_id' => $recommendedConcern->id,
            'type' => RepairOrderLineType::Labor,
            'description' => 'Inspect rear brakes',
            'quantity' => '0.50',
            'unit_price_cents' => 14500,
            'position' => 3,
        ]);

        app(EstimateTotalsCalculator::class)->recalculateRepairOrder(
            $repairOrder->fresh(['customer', 'concerns', 'lines.concern']),
        );

        $recorder = app(CommunicationEventRecorder::class);
        $recorder->record(
            $repairOrder,
            OperationalCommunicationType::EstimateSent,
            OperationalCommunicationChannel::Email,
            OperationalCommunicationDirection::Outbound,
            'Estimate emailed to customer.',
            occurredAt: Carbon::parse('2026-06-01 09:00:00'),
        );
        $recorder->record(
            $repairOrder,
            OperationalCommunicationType::EstimateViewed,
            OperationalCommunicationChannel::Website,
            OperationalCommunicationDirection::Inbound,
            'Customer viewed estimate.',
            occurredAt: Carbon::parse('2026-06-01 10:15:00'),
        );

        ApprovalEvent::query()->create([
            'visit_id' => $repairOrder->id,
            'estimate_snapshot_reference' => 'composition-estimate',
            'approval_type' => ApprovalType::Repair,
            'approved_amount_cents' => 31500,
            'source' => ApprovalSource::InPerson,
            'approved_by' => 'Composition Customer',
            'approved_at' => Carbon::parse('2026-06-01 11:00:00'),
        ]);

        app(OperationalEventRecorder::class)->record(
            OperationalEventName::PartOrdered,
            $repairOrder,
            payload: ['line_id' => 2],
        );

        app(OperationalEventRecorder::class)->record(
            OperationalEventName::RepairOrderLifecycleChanged,
            $repairOrder,
            payload: ['from_status' => RepairOrderStatus::Estimate->value, 'to_status' => RepairOrderStatus::WaitingApproval->value],
        );

        return $repairOrder->fresh(['customer', 'vehicle', 'concerns.lines', 'lines.concern']);
    }
}
