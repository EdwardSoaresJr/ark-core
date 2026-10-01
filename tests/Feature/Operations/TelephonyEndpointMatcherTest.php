<?php

use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyEndpointMatcher;
use App\Ark\Operations\Telephony\TelephonyEndpointType;
use App\Models\User;

test('desktop callback uses an unassigned shop sip phone before the advisor cell', function () {
    $advisor = User::factory()->create(['phone' => '7195551001']);

    TelephonyEndpoint::query()->create([
        'name' => 'Advisor Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '7195551001',
        'user_id' => $advisor->id,
        'enabled' => true,
        'position' => 1,
    ]);

    TelephonyEndpoint::query()->create([
        'name' => 'Shop Phone',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:wp820@example.sip.twilio.com',
        'user_id' => null,
        'enabled' => true,
        'position' => 0,
    ]);

    $matcher = app(TelephonyEndpointMatcher::class);

    expect($matcher->callbackDestinationFor($advisor))->toBe('sip:wp820@example.sip.twilio.com')
        ->and($matcher->mobileCallbackDestinationFor($advisor))->toBe('+17195551001');
});
