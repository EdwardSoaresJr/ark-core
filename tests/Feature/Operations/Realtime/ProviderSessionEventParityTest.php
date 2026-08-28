<?php

use App\Ark\Operations\Realtime\Providers\FakeSessionProvider;
use App\Ark\Operations\Realtime\ReplayCanonicalSessionStreamAction;
use App\Ark\Operations\Realtime\Scenarios\StandardSessionLifecycleScenario;
use App\Ark\Operations\Realtime\SessionEvent;
use App\Ark\Operations\Realtime\SessionEventIngress;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Ark\Operations\Timeline\UnifiedOperationalTimeline;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('fake and twilio streams produce identical call session projection', function (): void {
    $edward = User::factory()->create(['name' => StandardSessionLifecycleScenario::FROM_USER_NAME])->assignRole(ArkRole::Advisor->value);
    $molly = User::factory()->create(['name' => StandardSessionLifecycleScenario::TO_USER_NAME])->assignRole(ArkRole::Advisor->value);

    $ingress = app(SessionEventIngress::class);
    $startedAt = Carbon::parse('2026-06-29 10:00:00');

    $fake = app(FakeSessionProvider::class)->runStandardLifecycle(
        identity: array_merge(StandardSessionLifecycleScenario::sessionIdentity(), [
            'normalized_from' => StandardSessionLifecycleScenario::FROM_NUMBER,
            'direction' => CallSessionDirection::Inbound,
        ]),
        fromUser: $edward,
        toUser: $molly,
        startedAt: $startedAt,
    )['session'];

    $twilio = $ingress->ingestRawStream(
        TelephonyProviderType::Twilio,
        StandardSessionLifecycleScenario::twilioRawEvents($edward->id, $molly->id),
    );

    foreach ([$fake, $twilio] as $session) {
        expect($session->status)->toBe(CallSessionStatus::Completed)
            ->and($session->answered_at)->not->toBeNull()
            ->and($session->ended_at)->not->toBeNull()
            ->and($session->owned_by_user_id)->toBe($molly->id)
            ->and(SessionEvent::query()->where('call_session_id', $session->id)->count())->toBe(5);
    }
});

test('canonical golden stream replay matches provider ingested timeline headlines', function (): void {
    $edward = User::factory()->create(['name' => StandardSessionLifecycleScenario::FROM_USER_NAME])->assignRole(ArkRole::Advisor->value);
    $molly = User::factory()->create(['name' => StandardSessionLifecycleScenario::TO_USER_NAME])->assignRole(ArkRole::Advisor->value);

    $golden = StandardSessionLifecycleScenario::goldenStream();

    $replay = app(ReplayCanonicalSessionStreamAction::class)->replay(
        $golden,
        identityOverrides: array_merge(StandardSessionLifecycleScenario::sessionIdentity(), [
            'normalized_from' => StandardSessionLifecycleScenario::FROM_NUMBER,
        ]),
        actor: $edward,
    );

    $twilio = app(SessionEventIngress::class)->ingestRawStream(
        TelephonyProviderType::Twilio,
        StandardSessionLifecycleScenario::twilioRawEvents($edward->id, $molly->id),
    );

    $replayHeadlines = app(UnifiedOperationalTimeline::class)
        ->forCallSession($replay)
        ->pluck('headline')
        ->all();

    $twilioHeadlines = app(UnifiedOperationalTimeline::class)
        ->forCallSession($twilio)
        ->pluck('headline')
        ->all();

    expect($replayHeadlines)->toBe($twilioHeadlines)
        ->and($replayHeadlines)->toContain('Session transferred');
});

test('twilio ingress creates session events not direct call session mutation', function (): void {
    $ingress = app(SessionEventIngress::class);

    $session = $ingress->ingestRawStream(
        TelephonyProviderType::Twilio,
        array_slice(StandardSessionLifecycleScenario::twilioRawEvents(1, 2), 0, 1),
    );

    expect($session->provider)->toBe(TelephonyProviderType::Twilio)
        ->and(SessionEvent::query()->where('call_session_id', $session->id)->count())->toBe(1);
});
