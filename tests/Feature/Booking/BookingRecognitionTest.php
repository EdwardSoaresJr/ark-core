<?php

use App\Ark\Operations\Booking\BookingRecognition;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Recommendations\Recommendation;
use App\Ark\Operations\Vehicles\Vehicle;

function recognitionCustomer(string $first, string $phone, ?string $email = null): Customer
{
    return Customer::query()->create(['first_name' => $first, 'last_name' => 'Example', 'phone' => $phone, 'email' => $email]);
}

function recognitionVehicle(Customer $customer, array $attributes = []): Vehicle
{
    return Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Toyota',
        'model' => 'Camry',
        'trim' => 'SE',
        'plate' => 'abc123',
        'vin' => '4T1B11HK5KU000001',
        'private_notes' => 'Customer is difficult',
        ...$attributes,
    ]);
}

function recognitionRecommendation(Customer $customer, Vehicle $vehicle, array $attributes = []): Recommendation
{
    return Recommendation::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'title' => 'Replace front brake pads',
        'customer_description' => 'Pads are at 3 mm.',
        'advisor_note' => 'Quoted $289, customer balked',
        'lifecycle' => 'open',
        'urgency' => 'soon',
        'discovered_at' => now(),
        ...$attributes,
    ]);
}

test('a verified phone held by exactly one customer recognizes them, with their vehicles and open recommendations only', function (): void {
    $pat = recognitionCustomer('Pat', '7195550142');
    $camry = recognitionVehicle($pat);
    $brakes = recognitionRecommendation($pat, $camry, ['safety_related' => true, 'urgency' => 'now']);
    recognitionRecommendation($pat, $camry, ['title' => 'Old work', 'lifecycle' => 'resolved']);
    recognitionRecommendation($pat, $camry, ['title' => 'Declined work', 'lifecycle' => 'dismissed']);

    $sam = recognitionCustomer('Sam', '7195550199');
    $samVehicle = recognitionVehicle($sam, ['make' => 'Ford', 'model' => 'F-150']);
    $samWork = recognitionRecommendation($sam, $samVehicle, ['title' => 'Sam only']);

    $seen = BookingRecognition::forVerifiedContact('(719) 555-0142', null);

    expect($seen->status)->toBe(BookingRecognition::RECOGNIZED)
        ->and($seen->customerId())->toBe($pat->id)
        ->and($seen->vehicles())->toBe([['id' => $camry->id, 'label' => '2019 Toyota Camry SE', 'plate' => 'ABC123']])
        ->and($seen->vehicle($samVehicle->id))->toBeNull()
        ->and($seen->recommendations($samVehicle->id))->toBe([])
        ->and($seen->recommendation($camry->id, $samWork->id))->toBeNull();

    $recommendations = $seen->recommendations($camry->id);
    expect($recommendations)->toHaveCount(1)
        ->and($recommendations[0])->toMatchArray(['id' => $brakes->id, 'title' => 'Replace front brake pads', 'description' => 'Pads are at 3 mm.', 'urgency' => 'Due now', 'safety' => true])
        ->and(json_encode($recommendations))->not->toContain('289')->not->toContain('balked');
    expect(json_encode($seen->vehicles()))->not->toContain('4T1B11HK5KU000001')->not->toContain('difficult');
});

test('no match or more than one match discloses nothing', function (): void {
    recognitionCustomer('Pat', '7195550142', 'shared@example.test');
    recognitionCustomer('Pat Jr', '7195550142');
    recognitionCustomer('Lee', '7195550177', 'shared@example.test');

    foreach ([
        BookingRecognition::forVerifiedContact('7195550142', null),
        BookingRecognition::forVerifiedContact(null, 'SHARED@example.test'),
    ] as $seen) {
        expect($seen->status)->toBe(BookingRecognition::AMBIGUOUS)
            ->and($seen->recognized())->toBeFalse()
            ->and($seen->vehicles())->toBe([]);
    }

    expect(BookingRecognition::forVerifiedContact('7195550100', 'nobody@example.test')->status)->toBe(BookingRecognition::UNKNOWN)
        ->and(BookingRecognition::forVerifiedContact(null, null)->recognized())->toBeFalse();
});

test('a verified email recognizes the single customer who has it', function (): void {
    $lee = recognitionCustomer('Lee', '7195550177', 'Lee@Example.test');

    expect(BookingRecognition::forVerifiedContact(null, 'lee@example.test')->customerId())->toBe($lee->id)
        ->and(BookingRecognition::forVerifiedContact(null, 'lee@example.test')->firstName())->toBe('Lee');
});
