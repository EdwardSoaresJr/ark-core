<?php

namespace Tests\Support;

use App\Ark\Operations\Commitments\CommitmentStatus;
use App\Ark\Operations\Commitments\CommitmentType;
use App\Ark\Operations\Commitments\OperationalCommitment;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Deterministic synthetic Job Board rows for layout acceptance.
 * Customers and emails are clearly fake (SYN- prefix). Never mutate real shop data.
 *
 * Activity/communication fixtures live in WorkboardCardActivityTest (baseline-allowed),
 * not here — Core communications boundary rejects new Support files that reference those namespaces.
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

        // Keep advisor referenced for local login; unused beyond ownership.
        unset($advisor);

        return collect([
            self::makeRow(
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
            ),
            self::makeRow(
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
            ),
            self::makeRow(
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
            ),
            self::makeRow(
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
            ),
            self::makeRow(
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
            ),
            self::makeRow(
                marker: 'G',
                first: 'SYN',
                last: 'Full Strip',
                vehicle: [2015, 'Jeep', 'Cherokee'],
                status: RepairOrderStatus::InProgress,
                concern: 'Multi-channel activity sample for six-category presentation.',
                tech: $techJordan,
                laborCents: 55_000,
                promiseAt: null,
                parts: null,
            ),
            self::makeRow(
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
            ),
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
