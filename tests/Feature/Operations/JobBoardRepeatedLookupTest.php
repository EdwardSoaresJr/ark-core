<?php

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
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Today\AdvisorHomeCardSurfaceProjection;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Workboard\JobBoardLaneCatalog;
use App\Ark\Operations\Workboard\WorkboardTriageProjection;
use App\Ark\Operations\Workboard\WorkboardTriageRepairOrderQuery;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('job board reuses timezone, communication graph, and lane catalog across cards', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $eventCount = 0;

    foreach ([
        RepairOrderStatus::Estimate,
        RepairOrderStatus::WaitingApproval,
        RepairOrderStatus::InProgress,
        RepairOrderStatus::ReadyPickup,
    ] as $index => $status) {
        $repairOrder = jobBoardLookupRepairOrder($index, $status);

        foreach (range(1, 5) as $eventIndex) {
            CommunicationEvent::query()->create([
                'repair_order_id' => $repairOrder->id,
                'event_type' => OperationalCommunicationType::EstimateViewed,
                'channel' => OperationalCommunicationChannel::Website,
                'direction' => OperationalCommunicationDirection::Inbound,
                'summary' => 'Portal view '.$eventIndex,
                'occurred_at' => now()->subHours($eventIndex),
            ]);
            $eventCount++;
        }

        Appointment::query()->create([
            'customer_id' => $repairOrder->customer_id,
            'vehicle_id' => $repairOrder->vehicle_id,
            'repair_order_id' => $repairOrder->id,
            'created_by_user_id' => $advisor->id,
            'advisor_user_id' => $advisor->id,
            'starts_at' => now()->addHours($index + 1),
            'ends_at' => now()->addHours($index + 2),
            'concern' => 'Lookup appointment '.$index,
            'status' => AppointmentStatus::Scheduled,
        ]);

        OperationalCommitment::query()->create([
            'repair_order_id' => $repairOrder->id,
            'owner_user_id' => $advisor->id,
            'created_by' => $advisor->id,
            'type' => CommitmentType::CustomerUpdate,
            'status' => CommitmentStatus::Open,
            'reason' => 'Call with an update',
            'due_at' => now()->addHours(3),
        ]);
    }

    $repairOrders = app(WorkboardTriageRepairOrderQuery::class)->forAdvisor();

    expect($repairOrders)->toHaveCount(4)
        ->and($repairOrders->sum(fn (RepairOrder $repairOrder): int => $repairOrder->communicationEvents->count()))
        ->toBe($eventCount);

    app(JobBoardLaneCatalog::class)->forgetCache();
    ShopDisplayTimezone::apply();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $columns = app(WorkboardTriageProjection::class)->forAdvisorHomeBoard($repairOrders);
    $visible = new \Illuminate\Database\Eloquent\Collection(
        collect($columns)
            ->flatMap(fn ($column) => $column->visibleCards)
            ->map(fn ($card) => $card->repairOrder)
            ->unique(fn (RepairOrder $repairOrder): int => $repairOrder->id)
            ->values()
            ->all(),
    );
    $totals = $visible->mapWithKeys(fn (RepairOrder $repairOrder): array => [
        $repairOrder->id => app(EstimateTotalsCalculator::class)->totalsFor($repairOrder),
    ]);
    $surfaces = app(AdvisorHomeCardSurfaceProjection::class)->mapForHomeBoard($visible, $columns, $totals);

    $queries = DB::getQueryLog();

    expect($visible)->toHaveCount(4)
        ->and(collect($surfaces)->contains(fn ($surface): bool => filled($surface->promiseLabel)))->toBeTrue()
        ->and(collect($surfaces)->contains(fn ($surface): bool => filled($surface->scheduleLabel)))->toBeTrue()
        ->and(singleIdReloads($queries, 'repair_orders'))->toBeEmpty()
        ->and(singleIdReloads($queries, 'vehicles'))->toBeEmpty()
        ->and(singleIdReloads($queries, 'customers'))->toBeEmpty()
        ->and(schemaQueriesForTable($queries, 'shop_settings'))->toBeEmpty()
        ->and(schemaQueriesForTable($queries, 'job_board_lanes'))->toHaveCount(1);
});

function jobBoardLookupRepairOrder(int $index, RepairOrderStatus $status): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Lookup',
        'last_name' => 'Card '.$index,
        'phone' => '71955519'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'LK'.$index,
        'year' => 2018 + $index,
        'make' => 'Honda',
        'model' => 'Civic',
        'vin' => '1HGCM82633A00'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
        'normalized_vin' => '1HGCM82633A00'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => 'Lookup board '.$status->value,
    ]);
}
