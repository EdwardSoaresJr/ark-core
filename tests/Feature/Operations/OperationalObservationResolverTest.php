<?php

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Observations\OperationalObservationResolver;
use App\Ark\Operations\Observations\OperationalObservationSeverity;
use App\Ark\Operations\Observations\OperationalObservationType;
use App\Ark\Operations\Timeline\Mappers\CommunicationEventMapper;
use App\Ark\Operations\Timeline\Mappers\ConversationMessageEventMapper;
use App\Ark\Operations\Timeline\OperationalEventEntry;
use App\Ark\Operations\Timeline\OperationalEventKind;
use App\Ark\Operations\Timeline\OperationalEventSource;
use App\Ark\Operations\Timeline\OperationalEventTone;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('observation resolver detects customer waiting after shop outbound message', function (): void {
    $resolver = app(OperationalObservationResolver::class);
    $messageMapper = app(ConversationMessageEventMapper::class);

    $inbound = new OperationalEventEntry(
        source: OperationalEventSource::ConversationMessage,
        kind: OperationalEventKind::Sms,
        occurredAt: Carbon::parse('2026-06-10 08:00:00'),
        headline: 'Incoming',
        body: 'Any update?',
        actor: null,
        tone: OperationalEventTone::Customer,
        metadata: ['conversation_id' => 12, 'direction' => 'inbound'],
    );

    $outbound = new OperationalEventEntry(
        source: OperationalEventSource::ConversationMessage,
        kind: OperationalEventKind::Sms,
        occurredAt: Carbon::parse('2026-06-10 08:05:00'),
        headline: 'Outgoing',
        body: 'Still working on it.',
        actor: 'Advisor',
        tone: OperationalEventTone::Shop,
        metadata: ['conversation_id' => 12, 'direction' => 'outbound'],
    );

    Carbon::setTestNow(Carbon::parse('2026-06-10 14:00:00'));

    $observations = $resolver->resolve([$inbound, $outbound], ['conversation_id' => 12]);

    Carbon::setTestNow();

    $waiting = collect($observations)->first(
        fn ($observation): bool => $observation->type === OperationalObservationType::CustomerWaitingResponse,
    );

    expect($waiting)->not->toBeNull()
        ->and($waiting->metadata['hours_waiting'])->toBe(5)
        ->and($waiting->description)->toBe('Shop replied 5 hours ago — no customer response since.');
});

test('customer waiting response copy uses whole hours when Carbon returns a float diff', function (): void {
    $resolver = app(OperationalObservationResolver::class);

    $outbound = new OperationalEventEntry(
        source: OperationalEventSource::ConversationMessage,
        kind: OperationalEventKind::Sms,
        occurredAt: Carbon::parse('2026-06-10 08:05:00'),
        headline: 'Outgoing',
        body: 'Still working on it.',
        actor: 'Advisor',
        tone: OperationalEventTone::Shop,
        metadata: ['conversation_id' => 12, 'direction' => 'outbound'],
    );

    // 2.15 hours later — Carbon 3 would stringify as 2.15 without an int cast.
    Carbon::setTestNow(Carbon::parse('2026-06-10 10:14:00'));

    $observations = $resolver->resolve([$outbound], ['conversation_id' => 12]);

    Carbon::setTestNow();

    $waiting = collect($observations)->first(
        fn ($observation): bool => $observation->type === OperationalObservationType::CustomerWaitingResponse,
    );

    expect($waiting)->not->toBeNull()
        ->and($waiting->metadata['hours_waiting'])->toBe(2)
        ->and($waiting->description)->toBe('Shop replied 2 hours ago — no customer response since.');
});

test('observation resolver detects multiple customer messages without shop reply', function (): void {
    $resolver = app(OperationalObservationResolver::class);

    $events = [
        shopSms('First reply', '2026-06-10 08:00:00'),
        customerSms('Hello?', '2026-06-10 09:00:00'),
        customerSms('Still there?', '2026-06-10 09:05:00'),
    ];

    $observations = $resolver->resolve($events, ['conversation_id' => 15]);

    expect(collect($observations)->contains(
        fn ($observation): bool => $observation->type === OperationalObservationType::CustomerSentMultipleMessages,
    ))->toBeTrue();
});

test('observation resolver maps estimate viewed events to estimate observations', function (): void {
    $repairOrder = repairOrderForCommunication(\App\Ark\Operations\RepairOrders\RepairOrderStatus::InProgress, 'Obs Customer');
    $mapper = app(CommunicationEventMapper::class);
    $resolver = app(OperationalObservationResolver::class);

    $events = collect(range(1, 3))->map(function (int $index) use ($repairOrder, $mapper): OperationalEventEntry {
        $event = CommunicationEvent::query()->create([
            'repair_order_id' => $repairOrder->id,
            'event_type' => OperationalCommunicationType::EstimateViewed,
            'channel' => OperationalCommunicationChannel::Website,
            'direction' => OperationalCommunicationDirection::Inbound,
            'summary' => 'Portal estimate view '.$index,
            'occurred_at' => Carbon::parse('2026-06-10 10:0'.$index.':00'),
        ]);

        return $mapper->map($event);
    })->all();

    $observations = $resolver->resolve($events, [
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $repairOrder->customer_id,
    ]);

    $types = collect($observations)->map(fn ($observation) => $observation->type)->all();

    expect($types)->toContain(OperationalObservationType::EstimateViewed)
        ->and($types)->toContain(OperationalObservationType::EstimateViewedMultipleTimes)
        ->and(collect($observations)->firstWhere(
            fn ($observation) => $observation->type === OperationalObservationType::EstimateViewedMultipleTimes,
        )?->severity)->toBe(OperationalObservationSeverity::Medium);
});

test('admin can open operational observations debug screen', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->get(route('operations.observations.index'))
        ->assertOk()
        ->assertSee('Operational observations', false)
        ->assertSee('Observation layer', false);
});

test('advisor cannot open operational observations debug screen', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    expect($advisor->can(ArkCapability::SettingsManage->value))->toBeFalse();

    $this->actingAs($advisor)
        ->get(route('operations.observations.index'))
        ->assertForbidden();
});

function customerSms(string $body, string $at): OperationalEventEntry
{
    return new OperationalEventEntry(
        source: OperationalEventSource::ConversationMessage,
        kind: OperationalEventKind::Sms,
        occurredAt: Carbon::parse($at),
        headline: 'Incoming',
        body: $body,
        actor: null,
        tone: OperationalEventTone::Customer,
        metadata: ['conversation_id' => 15, 'direction' => 'inbound'],
    );
}

function shopSms(string $body, string $at): OperationalEventEntry
{
    return new OperationalEventEntry(
        source: OperationalEventSource::ConversationMessage,
        kind: OperationalEventKind::Sms,
        occurredAt: Carbon::parse($at),
        headline: 'Outgoing',
        body: $body,
        actor: 'Advisor',
        tone: OperationalEventTone::Shop,
        metadata: ['conversation_id' => 15, 'direction' => 'outbound'],
    );
}
