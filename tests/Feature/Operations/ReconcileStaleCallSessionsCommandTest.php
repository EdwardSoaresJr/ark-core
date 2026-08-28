<?php

use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;

test('reconcile stale call sessions command closes stale ringing sessions', function () {
    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAcmdstale001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '+17195551234',
        'status' => CallSessionStatus::Ringing,
        'started_at' => now()->subMinutes(30),
    ]);

    $this->artisan('comms:reconcile-stale-call-sessions')
        ->assertSuccessful();

    expect(CallSession::query()->where('provider_call_sid', 'CAcmdstale001')->value('status'))
        ->toBe(CallSessionStatus::Missed);
});
