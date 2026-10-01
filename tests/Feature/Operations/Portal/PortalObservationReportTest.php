<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Portal\PortalAccessChallenge;
use App\Ark\Operations\Portal\PortalObservationReport;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Support\Carbon;

test('portal observation report summarizes funnel surface and historical split', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Molly',
        'last_name' => 'Customer',
        'phone' => '7195551212',
        'email' => 'molly.report@example.test',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2014,
        'make' => 'Jeep',
        'model' => 'Wrangler',
        'plate' => 'JEEP14',
    ]);

    $otherVehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2021,
        'make' => 'Subaru',
        'model' => 'Outback',
        'plate' => 'SUB21',
    ]);

    $since = Carbon::parse('2026-03-01 00:00:00');
    $sessionA = 'session-a';
    $sessionB = 'session-b';
    $sessionC = 'session-c';

    $challengeCreated = PortalAccessChallenge::query()->create([
        'customer_id' => $customer->id,
        'channel' => 'email',
        'destination' => $customer->email,
        'code_hash' => PortalAccessChallenge::hashCode('123456'),
        'attempts' => 0,
        'expires_at' => $since->copy()->addMinutes(15),
    ]);
    $challengeCreated->forceFill(['created_at' => $since->copy()->addDay()])->save();

    $challengeVerified = PortalAccessChallenge::query()->create([
        'customer_id' => $customer->id,
        'channel' => 'email',
        'destination' => $customer->email,
        'code_hash' => PortalAccessChallenge::hashCode('654321'),
        'attempts' => 0,
        'expires_at' => $since->copy()->addMinutes(15),
        'used_at' => $since->copy()->addDays(2),
    ]);
    $challengeVerified->forceFill([
        'created_at' => $since->copy()->addDays(2),
        'updated_at' => $since->copy()->addDays(2),
    ])->save();

    $events = [
        [OperationalEventName::PortalVehicleViewed, $sessionA, ['has_active_visit' => false, 'vehicle_id' => $vehicle->id, 'customer_id' => $customer->id]],
        [OperationalEventName::PortalVehicleViewed, $sessionB, ['has_active_visit' => true, 'vehicle_id' => $vehicle->id, 'customer_id' => $customer->id]],
        [OperationalEventName::PortalVehicleViewed, $sessionC, ['has_active_visit' => false, 'vehicle_id' => $vehicle->id, 'customer_id' => $customer->id]],
        [OperationalEventName::PortalVehicleViewed, $sessionA, ['has_active_visit' => false, 'vehicle_id' => $otherVehicle->id, 'customer_id' => $customer->id]],
        [OperationalEventName::PortalActiveVisitViewed, $sessionB, ['vehicle_id' => $vehicle->id, 'customer_id' => $customer->id]],
        [OperationalEventName::PortalDocumentViewed, $sessionA, ['vehicle_id' => $vehicle->id, 'customer_id' => $customer->id, 'document_type' => 'invoice']],
        [OperationalEventName::PortalDocumentDownloaded, $sessionA, ['vehicle_id' => $vehicle->id, 'customer_id' => $customer->id, 'document_type' => 'invoice']],
    ];

    foreach ($events as [$name, $sessionId, $payload]) {
        OperationalEvent::query()->create([
            'event_name' => $name->value,
            'aggregate_type' => Vehicle::class,
            'aggregate_id' => $vehicle->id,
            'occurred_at' => $since->copy()->addDays(3),
            'payload_json' => [
                ...$payload,
                'portal_session_id' => $sessionId,
            ],
        ]);
    }

    $report = app(PortalObservationReport::class)->forPeriod(
        $since,
        $since->copy()->addDays(30),
    );

    expect($report['funnel'][0]['count'])->toBe(2)
        ->and($report['funnel'][1]['count'])->toBe(1)
        ->and($report['funnel'][2]['count'])->toBe(4)
        ->and($report['funnel'][2]['unique_sessions'])->toBe(3)
        ->and($report['funnel'][3]['count'])->toBe(1)
        ->and($report['event_volume'][0]['event'])->toBe('portal_vehicle_viewed')
        ->and($report['vehicle_concentration'][0]['vehicle'])->toBe('2014 Jeep Wrangler')
        ->and($report['vehicle_concentration'][0]['unique_sessions'])->toBe(3)
        ->and($report['vehicle_concentration'][1]['vehicle'])->toBe('2021 Subaru Outback')
        ->and($report['vehicle_concentration'][1]['unique_sessions'])->toBe(1)
        ->and($report['historical_vs_active'][0]['vehicle_views'])->toBe(1)
        ->and($report['historical_vs_active'][1]['vehicle_views'])->toBe(3)
        ->and($report['document_types'][0]['document_type'])->toBe('invoice')
        ->and($report['document_types'][0]['viewed'])->toBe(1)
        ->and($report['document_types'][0]['downloaded'])->toBe(1);
});

test('portal observation report command runs', function () {
    $this->artisan('ark:portal-observation', ['--days' => 7])
        ->assertSuccessful()
        ->expectsOutputToContain('Portal observation report');
});
