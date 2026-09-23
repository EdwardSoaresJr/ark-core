<?php

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Today\AdvisorHomeActionableAttention;
use App\Ark\Operations\Today\AdvisorHomeAttentionReason;
use App\Ark\Operations\Today\AdvisorHomeAttentionBoardProjection;
use App\Ark\Operations\Today\AdvisorHomeAttentionZoneKey;
use App\Ark\Operations\Today\AdvisorHomeCardChip;
use App\Ark\Operations\Today\AdvisorHomeCardSurface;
use App\Ark\Operations\Today\AdvisorHomeCardSurfaceProjection;
use App\Ark\Operations\Workboard\WorkboardTriageCard;
use App\Ark\Operations\Workboard\WorkboardTriageProjection;
use App\Ark\Operations\Workboard\WorkboardTriageRepairOrderQuery;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('attention board places estimate viewed waiting approval in needs action zone', function () {
    Carbon::setTestNow('2026-06-15 12:00:00');

    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Lee',
        lastName: 'Wright',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 902_764,
    );

    CommunicationEvent::query()->create([
        'repair_order_id' => $repairOrder->id,
        'event_type' => OperationalCommunicationType::EstimateViewed,
        'channel' => OperationalCommunicationChannel::Website,
        'direction' => OperationalCommunicationDirection::Inbound,
        'summary' => 'Customer opened estimate portal',
        'occurred_at' => now()->subDays(4),
    ]);

    $repairOrders = app(WorkboardTriageRepairOrderQuery::class)->forAdvisor();
    $homeBoardColumns = app(WorkboardTriageProjection::class)->forAdvisorHomeBoard($repairOrders);
    $totals = $repairOrders->mapWithKeys(fn ($ro): array => [
        $ro->id => app(EstimateTotalsCalculator::class)->totalsFor($ro),
    ]);
    $surfaces = app(AdvisorHomeCardSurfaceProjection::class)->mapForHomeBoard($repairOrders, $homeBoardColumns);

    $zones = app(AdvisorHomeAttentionBoardProjection::class)->zones(
        $repairOrders,
        $surfaces,
        $totals,
    );

    $needsAction = collect($zones)->first(fn ($zone) => $zone->key === AdvisorHomeAttentionZoneKey::NeedsAction);

    expect($needsAction?->count)->toBe(1)
        ->and($needsAction?->rows[0]->customerName)->toBe('Lee Wright')
        ->and($needsAction?->rows[0]->statusChipTone)->toBe('waiting-approval')
        ->and($needsAction?->rows[0]->decorations)->toBe([]);

    Carbon::setTestNow();
});

test('attention board places ready pickup in ready pickup zone', function () {
    decisionPressureRepairOrder(
        firstName: 'Ready',
        lastName: 'Pickup',
        status: RepairOrderStatus::ReadyPickup,
        lineCents: 120_000,
        disposition: RepairOrderConcernDisposition::Approved,
    );

    $repairOrders = app(WorkboardTriageRepairOrderQuery::class)->forAdvisor();
    $homeBoardColumns = app(WorkboardTriageProjection::class)->forAdvisorHomeBoard($repairOrders);
    $totals = $repairOrders->mapWithKeys(fn ($ro): array => [
        $ro->id => app(EstimateTotalsCalculator::class)->totalsFor($ro),
    ]);
    $surfaces = app(AdvisorHomeCardSurfaceProjection::class)->mapForHomeBoard($repairOrders, $homeBoardColumns);

    $zones = app(AdvisorHomeAttentionBoardProjection::class)->zones(
        $repairOrders,
        $surfaces,
        $totals,
    );

    $allRows = app(AdvisorHomeAttentionBoardProjection::class)->allRows($zones);

    expect(collect($allRows)->pluck('customerName'))->toContain('Ready Pickup');

    $readyRow = collect($allRows)->first(fn ($row) => $row->customerName === 'Ready Pickup');

    expect($readyRow?->statusChipTone)->toBe('ready-pickup');
});

test('attention row status chip tone maps waiting approval to red scan color', function () {
    decisionPressureRepairOrder(
        firstName: 'Waiting',
        lastName: 'Approval',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 50_000,
    );

    $repairOrders = app(WorkboardTriageRepairOrderQuery::class)->forAdvisor();
    $homeBoardColumns = app(WorkboardTriageProjection::class)->forAdvisorHomeBoard($repairOrders);
    $totals = $repairOrders->mapWithKeys(fn ($ro): array => [
        $ro->id => app(EstimateTotalsCalculator::class)->totalsFor($ro),
    ]);
    $surfaces = app(AdvisorHomeCardSurfaceProjection::class)->mapForHomeBoard($repairOrders, $homeBoardColumns);

    $zones = app(AdvisorHomeAttentionBoardProjection::class)->zones(
        $repairOrders,
        $surfaces,
        $totals,
    );

    $row = collect(app(AdvisorHomeAttentionBoardProjection::class)->allRows($zones))
        ->first(fn ($row) => $row->customerName === 'Waiting Approval');

    expect($row?->statusChipTone)->toBe('waiting-approval');
});

test('attention row marks stale repair orders at fourteen and thirty days', function () {
    Carbon::setTestNow('2026-06-15 12:00:00');

    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Brianna',
        lastName: 'Watson',
        status: RepairOrderStatus::WaitingParts,
        lineCents: 80_000,
    );

    $repairOrder->vehicle?->update(['vin' => '4S3BP616X76430000']);
    $repairOrder->forceFill(['created_at' => now()->subDays(74)])->save();

    $repairOrders = app(WorkboardTriageRepairOrderQuery::class)->forAdvisor();
    $homeBoardColumns = app(WorkboardTriageProjection::class)->forAdvisorHomeBoard($repairOrders);
    $totals = $repairOrders->mapWithKeys(fn ($ro): array => [
        $ro->id => app(EstimateTotalsCalculator::class)->totalsFor($ro),
    ]);
    $surfaces = app(AdvisorHomeCardSurfaceProjection::class)->mapForHomeBoard($repairOrders, $homeBoardColumns);

    $zones = app(AdvisorHomeAttentionBoardProjection::class)->zones(
        $repairOrders,
        $surfaces,
        $totals,
    );

    $row = collect(app(AdvisorHomeAttentionBoardProjection::class)->allRows($zones))
        ->first(fn ($row) => $row->customerName === 'Brianna Watson');

    $activeWork = collect($zones)->first(fn ($zone) => $zone->key === AdvisorHomeAttentionZoneKey::ActiveWork);

    expect($row?->staleLevel)->toBe('critical')
        ->and($row?->statusChipTone)->toBe('waiting-parts')
        ->and($row?->ageDays)->toBe(74)
        ->and(collect($activeWork?->rows)->pluck('customerName'))->toContain('Brianna Watson')
        ->and($row?->attentionReason)->toBeNull();

    Carbon::setTestNow();
});

test('stale waiting parts stays in active work without actionable attention reason', function () {
    Carbon::setTestNow('2026-06-15 12:00:00');

    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Michael',
        lastName: 'Higashi',
        status: RepairOrderStatus::WaitingParts,
        lineCents: 427_564,
    );

    $repairOrder->vehicle?->update(['vin' => '1C4RJFBGXFC625789']);
    $repairOrder->forceFill(['created_at' => now()->subDays(26)])->save();

    $repairOrders = app(WorkboardTriageRepairOrderQuery::class)->forAdvisor();
    $homeBoardColumns = app(WorkboardTriageProjection::class)->forAdvisorHomeBoard($repairOrders);
    $totals = $repairOrders->mapWithKeys(fn ($ro): array => [
        $ro->id => app(EstimateTotalsCalculator::class)->totalsFor($ro),
    ]);
    $surfaces = app(AdvisorHomeCardSurfaceProjection::class)->mapForHomeBoard($repairOrders, $homeBoardColumns);

    $zones = app(AdvisorHomeAttentionBoardProjection::class)->zones(
        $repairOrders,
        $surfaces,
        $totals,
    );

    $needsAction = collect($zones)->first(fn ($zone) => $zone->key === AdvisorHomeAttentionZoneKey::NeedsAction);
    $activeWork = collect($zones)->first(fn ($zone) => $zone->key === AdvisorHomeAttentionZoneKey::ActiveWork);

    expect(collect($needsAction?->rows)->pluck('customerName'))->not->toContain('Michael Higashi')
        ->and(collect($activeWork?->rows)->pluck('customerName'))->toContain('Michael Higashi');

    Carbon::setTestNow();
});

test('building estimate stays in active work', function () {
    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Edward',
        lastName: 'Soares',
        status: RepairOrderStatus::Estimate,
        lineCents: 25_616,
    );

    $repairOrder->vehicle?->update(['vin' => '1D7HU18N45S512345']);

    $repairOrders = app(WorkboardTriageRepairOrderQuery::class)->forAdvisor();
    $homeBoardColumns = app(WorkboardTriageProjection::class)->forAdvisorHomeBoard($repairOrders);
    $totals = $repairOrders->mapWithKeys(fn ($ro): array => [
        $ro->id => app(EstimateTotalsCalculator::class)->totalsFor($ro),
    ]);
    $surfaces = app(AdvisorHomeCardSurfaceProjection::class)->mapForHomeBoard($repairOrders, $homeBoardColumns);

    $zones = app(AdvisorHomeAttentionBoardProjection::class)->zones(
        $repairOrders,
        $surfaces,
        $totals,
    );

    $needsAction = collect($zones)->first(fn ($zone) => $zone->key === AdvisorHomeAttentionZoneKey::NeedsAction);
    $activeWork = collect($zones)->first(fn ($zone) => $zone->key === AdvisorHomeAttentionZoneKey::ActiveWork);

    expect(collect($needsAction?->rows)->pluck('customerName'))->not->toContain('Edward Soares')
        ->and(collect($activeWork?->rows)->pluck('customerName'))->toContain('Edward Soares');
});

test('attention reason explains why approved repair order is in needs action', function () {
    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Mike',
        lastName: 'Kindig',
        status: RepairOrderStatus::Approved,
        lineCents: 43_656,
    );

    $card = new WorkboardTriageCard(
        repairOrder: $repairOrder,
        vehicleLabel: '2017 GMC Sierra 2500 HD',
        concernSummary: 'Test concern',
        concernHeadline: 'Test concern',
        signalLabel: 'Vehicle ID Needed',
        signalTone: 'warn',
        ageLabel: '5d',
        ageMinutes: 7200,
        pressureScore: 24,
        countsAsNeedsAttention: true,
        countsAsCustomerWaiting: false,
        countsAsUnassigned: false,
        countsAsOverduePickup: false,
        href: route('operations.repair-orders.show', $repairOrder),
    );

    $surface = new AdvisorHomeCardSurface(
        chip: new AdvisorHomeCardChip('Vehicle ID Needed', 'warn'),
        customerPhone: '7195550100',
        techInitials: null,
        promiseLabel: null,
        promiseTone: 'neutral',
        vehicleOnSite: false,
        laborProgress: null,
        customerHubUrl: route('operations.customers.show', $repairOrder->customer_id),
        textCustomerUrl: null,
        recordFindingUrl: null,
    );

    expect(AdvisorHomeAttentionReason::for(
        AdvisorHomeAttentionZoneKey::NeedsAction,
        $card,
        $surface,
    ))->toBe('Vehicle ID Needed')
        ->and(AdvisorHomeActionableAttention::belongsInNeedsAction($card, $surface))->toBeTrue();
});

test('advisor home renders customer first attention cockpit', function () {
    $customer = \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => 'John',
        'last_name' => 'Smith',
        'phone' => '7195550100',
    ]);

    $vehicle = \App\Ark\Operations\Vehicles\Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Ram',
        'model' => '2500',
    ]);

    $repairOrder = \App\Ark\Operations\RepairOrders\RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Brake noise when stopping.',
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.index'))
        ->assertOk()
        ->assertDontSee('Active Cars', false)
        ->assertDontSee('Biggest Pending', false)
        ->assertDontSee('ops-advisor-home-cockpit', false)
        ->assertSee('Estimates', false)
        ->assertSee('Waiting Approval', false)
        ->assertSee('Waiting Parts', false)
        ->assertSee('Work in Progress', false)
        ->assertSee('Completed', false)
        ->assertSee('John Smith', false)
        ->assertSee('2018 Ram 2500', false)
        ->assertSee('$0', false)
        ->assertSee('Estimate - None', false)
        ->assertSee('Scheduled - None', false)
        ->assertDontSee('ops-job-card__exception-mark', false)
        ->assertSee('Search job board', false)
        ->assertSee('ops-advisor-home__sticky-head', false);
});
