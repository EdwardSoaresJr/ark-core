<?php

use App\Ark\Operations\Staff\StaffCoachingLog;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionAnalysisStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('owner can log coaching debrief on a call and view it on staff profile', function () {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $advisor = User::factory()->create(['name' => 'Sam Advisor'])->assignRole(ArkRole::Advisor->value);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAdebrief001',
        'direction' => 'inbound',
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => 'completed',
        'owned_by_user_id' => $advisor->id,
        'started_at' => now()->subDay(),
        'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/AC/Recordings/REdebrief001',
        'analysis_status' => CallSessionAnalysisStatus::Ready,
        'analysis_json' => [
            'summary' => 'Customer asked about brake inspection.',
            'coaching_priority' => 'medium',
        ],
        'coaching_follow_up_at' => now(),
        'analyzed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('operations.owner.call-intelligence.coaching-log.store', $session), [
            'staff_user_id' => $advisor->id,
            'notes' => 'Reviewed empathy and next-step clarity. Sam will shadow two calls tomorrow.',
            'complete_follow_up' => '1',
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(StaffCoachingLog::query()->count())->toBe(1)
        ->and($session->fresh()->coaching_follow_up_at)->toBeNull();

    $this->actingAs($admin)
        ->get(route('operations.owner.call-intelligence.show', $session))
        ->assertOk()
        ->assertSee('Reviewed empathy and next-step clarity')
        ->assertSee('Sam Advisor');

    $this->actingAs($admin)
        ->get(route('operations.owner.staff.coaching', $advisor))
        ->assertOk()
        ->assertSee('Sam Advisor')
        ->assertSee('Reviewed empathy and next-step clarity')
        ->assertSee('Customer asked about brake inspection')
        ->assertSee('Open call');
});

test('advisor cannot log coaching debrief', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAdebrief002',
        'direction' => 'inbound',
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => 'completed',
        'started_at' => now(),
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.owner.call-intelligence.coaching-log.store', $session), [
            'staff_user_id' => $advisor->id,
            'notes' => 'Should not save.',
        ])
        ->assertForbidden();
});

test('coaching debrief requires notes and valid staff member', function () {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAdebrief003',
        'direction' => 'inbound',
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => 'completed',
        'started_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('operations.owner.call-intelligence.coaching-log.store', $session), [
            'staff_user_id' => $admin->id,
            'notes' => '',
        ])
        ->assertSessionHasErrors('notes');
});
