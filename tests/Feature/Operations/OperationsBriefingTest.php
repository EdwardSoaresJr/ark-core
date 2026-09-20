<?php

use App\Ark\Operations\Briefing\BriefingPriority;
use App\Ark\Operations\Briefing\OperationsBriefingProjection;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Operations\Workstations\WorkstationBrowserBinding;
use App\Ark\Operations\Workstations\WorkstationBrowserRoster;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;
use Tests\TestCase;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    Carbon::setTestNow(Carbon::parse('2026-06-27 08:00:00', config('app.timezone')));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function briefingAdvisor(): User
{
    $advisor = User::factory()->create(['name' => 'Alex Rivera'])->assignRole(ArkRole::Advisor->value);
    $advisor->setOperatorPin('1234');

    return $advisor;
}

/**
 * @param  TestCase  $test
 */
function briefingGet(TestCase $test, User $advisor): \Illuminate\Testing\TestResponse
{
    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Counter',
        'is_active' => true,
        'current_operator_user_id' => $advisor->id,
    ]);

    $binding = WorkstationBrowserBinding::issueForWorkstation($workstation);
    app(WorkstationBrowserRoster::class)->remember($binding, $advisor);

    return $test->actingAs($advisor)->call(
        'GET',
        route('operations.today'),
        [],
        [WorkstationPresence::BINDING_COOKIE => $binding->token],
    );
}

it('renders briefing empty state with yesterday summary', function (): void {
    $advisor = briefingAdvisor();

    $briefing = app(OperationsBriefingProjection::class)->forUser($advisor);

    expect($briefing->greeting)->toBe('Good morning, Alex.')
        ->and($briefing->hasAttentionItems)->toBeFalse()
        ->and(collect($briefing->yesterdaySummary)->pluck('label')->all())
        ->toContain('Revenue', 'Completed repair orders', 'Approval rate');

    // Advisor Today is Shop Dashboard (no briefing greeting cards).
    briefingGet($this, $advisor)
        ->assertOk()
        ->assertSee('Shop Dashboard')
        ->assertSee('Car Count')
        ->assertDontSee('ops-today-cockpit', false)
        ->assertDontSee('Attention items');
});

it('surfaces estimate follow-up when estimate viewed repeatedly without follow-up', function (): void {
    $advisor = briefingAdvisor();
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Follow Up Customer');

    foreach (range(1, 3) as $index) {
        CommunicationEvent::query()->create([
            'repair_order_id' => $repairOrder->id,
            'event_type' => OperationalCommunicationType::EstimateViewed,
            'channel' => OperationalCommunicationChannel::Website,
            'direction' => OperationalCommunicationDirection::Inbound,
            'summary' => 'Customer opened estimate portal',
            'occurred_at' => now()->subHours(4 - $index),
        ]);
    }

    $briefing = app(OperationsBriefingProjection::class)->forUser($advisor);

    expect($briefing->hasAttentionItems)->toBeTrue();

    $items = $briefing->sections[0]->items;
    $followUp = collect($items)->first(
        fn ($item) => str_contains($item->headline, 'viewed estimate'),
    );

    expect($followUp)->not->toBeNull()
        ->and($followUp->confidence->reason)->not->toBe('')
        ->and($followUp->evidenceItems)->not->toBeEmpty()
        ->and($followUp->actionUrl)->toContain((string) $repairOrder->repair_order_id);

    // Advisor Today is Shop Dashboard; briefing cards are projection-only.
    briefingGet($this, $advisor)
        ->assertOk()
        ->assertSee('Shop Dashboard');
});

it('surfaces missed calls from yesterday that were not handled', function (): void {
    $advisor = briefingAdvisor();
    $yesterday = briefingYesterdayInstant();

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAbriefingmissed001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => CallSessionStatus::Missed,
        'started_at' => $yesterday,
        'worked_at' => null,
    ]);

    $briefing = app(OperationsBriefingProjection::class)->forUser($advisor);
    $missed = collect($briefing->sections[0]->items ?? [])
        ->first(fn ($item) => str_contains($item->headline, 'Missed call'));

    expect($missed)->not->toBeNull()
        ->and($missed->priority)->toBe(BriefingPriority::High)
        ->and($missed->evidenceItems)->toHaveCount(1);

    briefingGet($this, $advisor)
        ->assertOk()
        ->assertSee('Shop Dashboard');
});

it('surfaces repairs waiting on parts beyond threshold', function (): void {
    $advisor = briefingAdvisor();
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingParts, 'Parts Wait Customer');
    $repairOrder->forceFill(['updated_at' => now()->subDays(3)])->save();

    $briefing = app(OperationsBriefingProjection::class)->forUser($advisor);
    $waiting = collect($briefing->sections[0]->items ?? [])
        ->first(fn ($item) => str_contains($item->headline, 'waiting on parts'));

    expect($waiting)->not->toBeNull()
        ->and($waiting->confidence->facts)->not->toBeEmpty();

    briefingGet($this, $advisor)
        ->assertOk()
        ->assertSee('Shop Dashboard');
});

it('includes revenue summary from operational report projections', function (): void {
    $advisor = briefingAdvisor();

    briefingPostedRepairOrder('Briefing Revenue', briefingYesterdayInstant());

    $briefing = app(OperationsBriefingProjection::class)->forUser($advisor);

    expect(collect($briefing->yesterdaySummary)->pluck('label')->all())
        ->toContain('Revenue', 'Completed repair orders', 'Approval rate')
        ->and(collect($briefing->yesterdaySummary)->firstWhere('label', 'Revenue')['value'])
        ->not->toBe('$0.00');
});

it('expands evidence references through the evidence resolver', function (): void {
    $advisor = briefingAdvisor();
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Evidence Customer');

    $event = CommunicationEvent::query()->create([
        'repair_order_id' => $repairOrder->id,
        'event_type' => OperationalCommunicationType::EstimateViewed,
        'channel' => OperationalCommunicationChannel::Website,
        'direction' => OperationalCommunicationDirection::Inbound,
        'summary' => 'Portal view for evidence expansion',
        'occurred_at' => now()->subHour(),
    ]);

    $projection = app(OperationsBriefingProjection::class);
    $expanded = $projection->expandEvidence([
        ['type' => 'communication_event', 'id' => $event->id],
        ['type' => 'repair_order', 'id' => $repairOrder->repair_order_id],
    ]);

    expect($expanded)->toHaveCount(2)
        ->and($expanded[0]->summary)->toBe('Portal view for evidence expansion')
        ->and($expanded[1]->sourceType)->toBe('repair_order');
});

it('rebuilds projection on demand when new attention appears', function (): void {
    $advisor = briefingAdvisor();
    $projection = app(OperationsBriefingProjection::class);

    expect($projection->forUser($advisor)->hasAttentionItems)->toBeFalse();

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAbriefingrebuild001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195559876',
        'to_number' => '+17195559999',
        'normalized_from' => '7195559876',
        'status' => CallSessionStatus::Missed,
        'started_at' => briefingYesterdayInstant(),
        'worked_at' => null,
    ]);

    $rebuilt = $projection->forUser($advisor);

    expect($rebuilt->hasAttentionItems)->toBeTrue()
        ->and(collect($rebuilt->sections[0]->items)->contains(
            fn ($item) => str_contains($item->headline, 'Missed call'),
        ))->toBeTrue();
});

function briefingYesterdayInstant(): Carbon
{
    return OperationalReportDateScope::shopNow()->copy()->subDay()->setTime(10, 30)->timezone(config('app.timezone'));
}

function briefingPostedRepairOrder(string $customerName, Carbon $postedAt): \App\Ark\Operations\RepairOrders\RepairOrder
{
    [$firstName, $lastName] = array_pad(explode(' ', $customerName, 2), 2, 'Customer');

    $customer = \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone' => '555-0100',
    ]);

    $vehicle = \App\Ark\Operations\Vehicles\Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $repairOrder = \App\Ark\Operations\RepairOrders\RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Closed,
        'concern_summary' => 'Briefing revenue fixture.',
        'opened_at' => $postedAt->copy()->subHour(),
        'closed_at' => $postedAt,
        'posted_at' => $postedAt,
        'paid_at' => $postedAt,
        'updated_at' => $postedAt,
        'payment_status' => \App\Ark\Operations\RepairOrders\RepairOrderPaymentStatus::Paid,
    ]);

    $concern = \App\Ark\Operations\RepairOrders\RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Approved service',
        'disposition' => \App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition::Approved,
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    \App\Ark\Operations\RepairOrders\RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => \App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor,
        'description' => 'Labor line',
        'quantity' => '2.00',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 30000,
        'total_cents' => 30000,
    ]);

    \App\Ark\Operations\RepairOrders\RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => \App\Ark\Operations\RepairOrders\RepairOrderLineType::Part,
        'description' => 'Part line',
        'quantity' => '1.00',
        'unit_price_cents' => 20000,
        'part_cost_cents' => 9000,
        'subtotal_cents' => 20000,
        'total_cents' => 20000,
    ]);

    return $repairOrder->fresh();
}
