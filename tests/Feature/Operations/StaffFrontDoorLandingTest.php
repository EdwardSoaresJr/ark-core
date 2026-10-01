<?php

use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Staff\StaffFrontDoorLandingSummary;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('first attention visit in a session records a front door landing event', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk();

    $event = OperationalEvent::query()->sole();

    expect($event->event_name)->toBe(OperationalEventName::StaffFrontDoorLanded->value)
        ->and($event->aggregate_id)->toBe($advisor->id)
        ->and($event->payload_json)->toMatchArray(['surface' => 'attention']);

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk();

    expect(OperationalEvent::query()->count())->toBe(1);
});

test('advisor workboard redirects home without recording a workboard landing', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->get(route('operations.workboard'))
        ->assertRedirect(route('operations.index'));

    expect(OperationalEvent::query()->count())->toBe(0);

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk();

    expect(OperationalEvent::query()->sole()->payload_json)->toMatchArray(['surface' => 'attention']);
});

test('front door summary aggregates attention and workboard landings', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    OperationalEvent::query()->create([
        'event_name' => OperationalEventName::StaffFrontDoorLanded->value,
        'aggregate_type' => get_class($advisor),
        'aggregate_id' => $advisor->id,
        'actor_user_id' => $advisor->id,
        'occurred_at' => now()->subDay(),
        'payload_json' => ['surface' => 'attention'],
    ]);

    OperationalEvent::query()->create([
        'event_name' => OperationalEventName::StaffFrontDoorLanded->value,
        'aggregate_type' => get_class($advisor),
        'aggregate_id' => $advisor->id,
        'actor_user_id' => $advisor->id,
        'occurred_at' => now()->subDay(),
        'payload_json' => ['surface' => 'workboard'],
    ]);

    $summary = app(StaffFrontDoorLandingSummary::class)->lastDays(7);

    expect($summary['attention'])->toBe(1)
        ->and($summary['workboard'])->toBe(1)
        ->and($summary['total'])->toBe(2)
        ->and($summary['attention_share'])->toBe(50);
});

test('operational report operations tab shows shop behavior pulse', function () {
    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.reports.operational', ['tab' => 'operations']))
        ->assertOk()
        ->assertSee('Shop Behavior Pulse')
        ->assertSee('Waiting approval')
        ->assertSee('Stalled 4h+')
        ->assertSee('Diagnostic → Repair Follow-Through');
});
