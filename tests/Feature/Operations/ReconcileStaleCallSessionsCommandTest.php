<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\Jobs\SendMissedCallRescueSmsJob;
use Illuminate\Support\Facades\Queue;

test('reconcile stale call sessions command closes stale ringing sessions', function () {
    Queue::fake();

    $flow = ShopSettings::defaultTelephonyCallFlow();
    $flow['missed_call_rescue_enabled'] = true;
    $flow['missed_call_rescue_delay_seconds'] = 45;
    $flow['missed_call_rescue_cooldown_minutes'] = 45;
    ShopSettings::current()->update(['telephony_call_flow' => $flow]);
    ShopSettings::forgetCurrent();

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

    Queue::assertNotPushed(SendMissedCallRescueSmsJob::class);
});
