<?php

namespace Tests\Support;

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Commitments\CommitmentStatus;
use App\Ark\Operations\Commitments\CommitmentType;
use App\Ark\Operations\Commitments\OperationalCommitment;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Inspections\Inspection;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Deterministic synthetic Job Board rows for acceptance screenshots/tests.
 * Customers and emails are clearly fake (SYN- prefix). Never mutate real shop data.
 */
final class JobBoardAcceptanceScenario
{
    public const MARKER = 'SYN-JOBBOARD-251';

    /**
     * @return Collection<int, RepairOrder>
     */
    public static function seed(?User $advisor = null): Collection
    {
        self::purge();

        $advisor ??= User::query()->where('email', 'jobboard-review-251@example.test')->first()
            ?? User::factory()->create([
                'name' => 'Job Board Reviewer',
                'email' => 'jobboard-review-251@example.test',
                'password' => Hash::make('Review251!'),
            ]);

        $techAlex = self::technician('Alex Rivera', 'syn.tech.alex.251@example.test');
        $techJordan = self::technician('Jordan Lee', 'syn.tech.jordan.251@example.test');

        $quiet = self::makeRow(
            marker: 'E',
            first: 'SYN',
            last: 'Quiet None',
            vehicle: [2016, 'Chevrolet', 'Malibu'],
            status: RepairOrderStatus::Estimate,
            concern: 'Intermittent no-start cold mornings; battery and starter load test pending.',
            tech: null,
            laborCents: 0,
            promiseAt: null,
            parts: null,
        );

        $estimateOnly = self::makeRow(
            marker: 'A',
            first: 'SYN',
            last: 'Brakes Demo',
            vehicle: [2019, 'Honda', 'CR-V'],
            status: RepairOrderStatus::WaitingApproval,
            concern: 'Customer reports grinding front brakes on every stop; pads and rotors recommended.',
            tech: $techAlex,
            laborCents: 42_500,
            promiseAt: self::shopNow()->addHours(3),
            parts: null,
        );

        $smsEmail = self::makeRow(
            marker: 'F',
            first: 'SYN',
            last: 'Comms Pair',
            vehicle: [2017, 'Nissan', 'Rogue'],
            status: RepairOrderStatus::WaitingApproval,
            concern: 'Customer asked for written estimate follow-up after phone diagnosis.',
            tech: $techAlex,
            laborCents: 22_000,
            promiseAt: null,
            parts: null,
        );
        self::event($smsEmail, OperationalCommunicationType::SmsDelivered, OperationalCommunicationChannel::Sms, OperationalCommunicationDirection::Outbound, now()->subHours(3));
        self::event($smsEmail, OperationalCommunicationType::InvoiceSent, OperationalCommunicationChannel::Email, OperationalCommunicationDirection::Outbound, now()->subHours(2));

        $viewedInspection = self::makeRow(
            marker: 'C',
            first: 'SYN',
            last: 'Wip Bay',
            vehicle: [2018, 'Ford', 'F-150'],
            status: RepairOrderStatus::InProgress,
            concern: 'Replace serpentine belt and tensioner; bay 2 in progress.',
            tech: $techAlex,
            laborCents: 31_200,
            promiseAt: self::shopNow()->addDay()->setTime(11, 30),
            parts: [
                ['description' => 'Serpentine belt', 'state' => PartProcurementState::Received, 'cents' => 6_400],
                ['description' => 'Tensioner assembly', 'state' => PartProcurementState::Ordered, 'cents' => 12_900],
            ],
        );
        self::event($viewedInspection, OperationalCommunicationType::EstimateViewed, OperationalCommunicationChannel::Website, OperationalCommunicationDirection::Inbound, now()->subHour());
        Inspection::query()->create([
            'repair_order_id' => $viewedInspection->id,
            'started_at' => now()->subHours(4),
            'completed_at' => now()->subHours(3),
        ]);

        $missedCall = self::makeRow(
            marker: 'B',
            first: 'SYN',
            last: 'Parts Hold',
            vehicle: [2021, 'Toyota', 'Tacoma'],
            status: RepairOrderStatus::WaitingParts,
            concern: 'Timing cover leak; OEM gasket kit on order from dealer.',
            tech: $techJordan,
            laborCents: 88_000,
            promiseAt: self::shopNow()->subDays(2)->setTime(16, 0),
            parts: [
                ['description' => 'Timing cover gasket kit', 'state' => PartProcurementState::Ordered, 'cents' => 18_600],
            ],
        );
        CallSession::query()->create([
            'provider' => 'twilio',
            'provider_call_sid' => 'CAsyn251missed',
            'direction' => CallSessionDirection::Inbound,
            'from_number' => '+17195552002',
            'to_number' => '+17195559999',
            'normalized_from' => '7195552002',
            'status' => CallSessionStatus::Missed,
            'repair_order_id' => $missedCall->id,
            'started_at' => now()->subHours(5),
        ]);

        $overflow = self::makeRow(
            marker: 'G',
            first: 'SYN',
            last: 'Full Strip',
            vehicle: [2015, 'Jeep', 'Cherokee'],
            status: RepairOrderStatus::InProgress,
            concern: 'Multi-channel activity sample for overflow presentation.',
            tech: $techJordan,
            laborCents: 55_000,
            promiseAt: null,
            parts: null,
        );
        self::event($overflow, OperationalCommunicationType::EstimateViewed, OperationalCommunicationChannel::Website, OperationalCommunicationDirection::Inbound, now()->subHours(6));
        self::event($overflow, OperationalCommunicationType::CustomerReply, OperationalCommunicationChannel::Sms, OperationalCommunicationDirection::Inbound, now()->subMinutes(40));
        self::event($overflow, OperationalCommunicationType::MessageRead, OperationalCommunicationChannel::Email, OperationalCommunicationDirection::Inbound, now()->subHours(2));
        CallSession::query()->create([
            'provider' => 'twilio',
            'provider_call_sid' => 'CAsyn251worked',
            'direction' => CallSessionDirection::Outbound,
            'from_number' => '+17195559999',
            'to_number' => '+17195552007',
            'normalized_from' => '7195559999',
            'status' => CallSessionStatus::Completed,
            'repair_order_id' => $overflow->id,
            'started_at' => now()->subHours(8),
            'worked_at' => now()->subHours(7),
        ]);
        Inspection::query()->create([
            'repair_order_id' => $overflow->id,
            'started_at' => now()->subDay(),
            'completed_at' => now()->subDay()->addHours(2),
        ]);
        Appointment::query()->create([
            'customer_id' => $overflow->customer_id,
            'vehicle_id' => $overflow->vehicle_id,
            'repair_order_id' => $overflow->id,
            'created_by_user_id' => $advisor->id,
            'advisor_user_id' => $advisor->id,
            'starts_at' => self::shopNow()->addDay()->setTime(10, 0)->utc(),
            'ends_at' => self::shopNow()->addDay()->setTime(11, 0)->utc(),
            'concern' => self::MARKER.' follow-up',
            'status' => AppointmentStatus::Scheduled,
        ]);

        $exceptionPlus = self::makeRow(
            marker: 'D',
            first: 'SYN',
            last: 'Ready Pickup',
            vehicle: [2020, 'Subaru', 'Outback'],
            status: RepairOrderStatus::ReadyPickup,
            concern: 'Four-wheel alignment complete after tire install; ready for customer pickup.',
            tech: $techJordan,
            laborCents: 19_500,
            promiseAt: self::shopNow()->setTime(17, 0),
            parts: [
                ['description' => 'Alignment kit hardware', 'state' => PartProcurementState::Installed, 'cents' => 2_200],
            ],
        );
        self::event($exceptionPlus, OperationalCommunicationType::SmsDeliveryFailed, OperationalCommunicationChannel::Sms, OperationalCommunicationDirection::Outbound, now()->subMinutes(25));
        self::event($exceptionPlus, OperationalCommunicationType::EstimateSent, OperationalCommunicationChannel::Sms, OperationalCommunicationDirection::Outbound, now()->subDays(1));

        return collect([
            $quiet,
            $estimateOnly,
            $smsEmail,
            $viewedInspection,
            $missedCall,
            $overflow,
            $exceptionPlus,
        ]);
    }

    public static function purge(): void
    {
        $customers = Customer::query()
            ->where('email', 'like', 'syn.%.251@example.test')
            ->get();

        $customerIds = $customers->pluck('id');

        if ($customerIds->isEmpty()) {
            return;
        }

        $ros = RepairOrder::query()->whereIn('customer_id', $customerIds)->get();
        $roIds = $ros->pluck('id');

        if ($roIds->isNotEmpty()) {
            DB::table('communication_events')->whereIn('repair_order_id', $roIds)->delete();
            CallSession::query()->whereIn('repair_order_id', $roIds)->delete();
            Inspection::query()->whereIn('repair_order_id', $roIds)->delete();
            Appointment::query()->whereIn('repair_order_id', $roIds)->delete();
            OperationalCommitment::query()->whereIn('repair_order_id', $roIds)->delete();
            RepairOrderLine::query()->whereIn('repair_order_id', $roIds)->delete();
            RepairOrderConcern::query()->whereIn('repair_order_id', $roIds)->delete();
            RepairOrder::query()->whereIn('id', $roIds)->delete();
        }

        Vehicle::query()->whereIn('customer_id', $customerIds)->delete();
        Customer::query()->whereIn('id', $customerIds)->delete();
    }

    private static function shopNow(): Carbon
    {
        return Carbon::now(ShopDisplayTimezone::resolve());
    }

    private static function technician(string $name, string $email): User
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $user = User::factory()->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make('SynTech251!'),
            ]);
        }

        $user->assignRole(ArkRole::Technician->value);

        return $user;
    }

    private static function event(
        RepairOrder $repairOrder,
        OperationalCommunicationType $type,
        OperationalCommunicationChannel $channel,
        OperationalCommunicationDirection $direction,
        Carbon $occurredAt,
    ): void {
        CommunicationEvent::query()->create([
            'repair_order_id' => $repairOrder->id,
            'event_type' => $type,
            'channel' => $channel,
            'direction' => $direction,
            'summary' => self::MARKER.' '.$type->value,
            'occurred_at' => $occurredAt,
        ]);
    }

    /**
     * @param  array{0: int, 1: string, 2: string}  $vehicle
     * @param  list<array{description: string, state: PartProcurementState, cents: int}>|null  $parts
     */
    private static function makeRow(
        string $marker,
        string $first,
        string $last,
        array $vehicle,
        RepairOrderStatus $status,
        string $concern,
        ?User $tech,
        int $laborCents,
        ?Carbon $promiseAt,
        ?array $parts,
    ): RepairOrder {
        $customer = Customer::query()->create([
            'first_name' => $first,
            'last_name' => $last,
            'phone' => '7195552'.str_pad((string) (ord($marker) - 64), 3, '0', STR_PAD_LEFT),
            'email' => 'syn.'.Str::lower(str_replace(' ', '.', $last)).'.251@example.test',
        ]);

        $vehicleModel = Vehicle::query()->create([
            'customer_id' => $customer->id,
            'year' => $vehicle[0],
            'make' => $vehicle[1],
            'model' => $vehicle[2],
            'plate' => 'SYN'.$marker.'251',
        ]);

        $repairOrder = RepairOrder::query()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicleModel->id,
            'status' => $status,
            'concern_summary' => $concern,
            'assigned_technician_id' => $tech?->id,
        ]);

        $disposition = in_array($status, [
            RepairOrderStatus::WaitingParts,
            RepairOrderStatus::InProgress,
            RepairOrderStatus::ReadyPickup,
        ], true)
            ? RepairOrderConcernDisposition::Approved
            : RepairOrderConcernDisposition::Recommended;

        $concernModel = RepairOrderConcern::query()->create([
            'repair_order_id' => $repairOrder->id,
            'summary' => $concern,
            'disposition' => $disposition,
            'position' => 1,
        ]);

        if ($laborCents > 0) {
            RepairOrderLine::query()->create([
                'repair_order_id' => $repairOrder->id,
                'repair_order_concern_id' => $concernModel->id,
                'type' => RepairOrderLineType::Labor,
                'description' => 'SYN labor '.$marker,
                'quantity' => '1.00',
                'unit_price_cents' => $laborCents,
                'subtotal_cents' => $laborCents,
                'total_cents' => $laborCents,
            ]);
        }

        foreach ($parts ?? [] as $part) {
            RepairOrderLine::query()->create([
                'repair_order_id' => $repairOrder->id,
                'repair_order_concern_id' => $concernModel->id,
                'type' => RepairOrderLineType::Part,
                'description' => $part['description'],
                'quantity' => '1.00',
                'unit_price_cents' => max(100, $part['cents']),
                'part_cost_cents' => (int) max(50, round($part['cents'] * 0.55)),
                'procurement_state' => $part['state'],
                'subtotal_cents' => max(100, $part['cents']),
                'total_cents' => max(100, $part['cents']),
            ]);
        }

        if ($promiseAt instanceof Carbon) {
            OperationalCommitment::query()->create([
                'repair_order_id' => $repairOrder->id,
                'owner_user_id' => $tech?->id,
                'created_by' => $tech?->id,
                'type' => CommitmentType::CustomerUpdate,
                'status' => CommitmentStatus::Open,
                'reason' => self::MARKER.' promise '.$marker,
                'due_at' => $promiseAt->copy()->utc(),
            ]);
        }

        return $repairOrder->fresh(['customer', 'vehicle', 'concerns.lines', 'lines', 'assignedTechnician']);
    }
}
