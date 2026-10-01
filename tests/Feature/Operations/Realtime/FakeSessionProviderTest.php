<?php

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Realtime\Providers\FakeSessionProvider;
use App\Ark\Operations\Realtime\SessionEvent;
use App\Ark\Operations\Realtime\SessionEventType;
use App\Ark\Operations\Realtime\SessionProviderManager;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Ark\Operations\Timeline\OperationalEventKind;
use App\Ark\Operations\Timeline\OperationalEventSource;
use App\Ark\Operations\Timeline\UnifiedOperationalTimeline;
use App\Models\User;
use App\Ark\Runtime\Authorization\ArkRole;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('fake session provider runs standard lifecycle without transport', function (): void {
    $edward = User::factory()->create(['name' => 'Alex Rivera'])->assignRole(ArkRole::Advisor->value);
    $molly = User::factory()->create(['name' => 'Molly Advisor'])->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Demo',
        'last_name' => 'Customer',
        'phone' => '7195550100',
    ]);

    $startedAt = Carbon::parse('2026-06-29 10:00:00');

    $result = app(FakeSessionProvider::class)->runStandardLifecycle(
        identity: [
            'normalized_from' => '7195550100',
            'customer_id' => $customer->id,
            'direction' => CallSessionDirection::Inbound,
        ],
        fromUser: $edward,
        toUser: $molly,
        startedAt: $startedAt,
    );

    $session = $result['session'];

    expect($session->provider)->toBe(TelephonyProviderType::Fake)
        ->and($session->status)->toBe(CallSessionStatus::Completed)
        ->and($session->answered_at)->not->toBeNull()
        ->and($session->ended_at)->not->toBeNull()
        ->and($session->owned_by_user_id)->toBe($molly->id)
        ->and($result['events'])->toHaveCount(5)
        ->and(SessionEvent::query()->where('call_session_id', $session->id)->count())->toBe(5);
});

test('session events are append-only', function (): void {
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    ['session' => $session] = app(FakeSessionProvider::class)->runStandardLifecycle(
        identity: ['normalized_from' => '7195550200'],
        fromUser: $user,
        toUser: $user,
    );

    $event = SessionEvent::query()->where('call_session_id', $session->id)->firstOrFail();

    expect(fn () => $event->update(['payload' => ['tampered' => true]]))
        ->toThrow(LogicException::class);

    expect(fn () => $event->delete())
        ->toThrow(LogicException::class);
});

test('fake session lifecycle projects into conversation relationship timeline', function (): void {
    $edward = User::factory()->create(['name' => 'Alex Rivera'])->assignRole(ArkRole::Advisor->value);
    $molly = User::factory()->create(['name' => 'Molly Advisor'])->assignRole(ArkRole::Advisor->value);

    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195550300',
        'status' => ConversationStatus::Open,
    ]);

    ['session' => $session] = app(FakeSessionProvider::class)->runStandardLifecycle(
        identity: [
            'normalized_from' => '7195550300',
            'direction' => CallSessionDirection::Inbound,
        ],
        fromUser: $edward,
        toUser: $molly,
        startedAt: Carbon::parse('2026-06-29 11:00:00'),
    );

    $entries = app(UnifiedOperationalTimeline::class)
        ->forCallSession($session)
        ->values();

    $headlines = $entries->pluck('headline')->all();

    expect($headlines)->toContain('Incoming call · Completed', 'Session transferred', 'Session held')
        ->and($headlines)->not->toContain('Session started', 'Session ended')
        ->and($entries->first(fn ($entry) => $entry->headline === 'Session transferred')?->body)
        ->toBe('From Alex Rivera to Molly Advisor')
        ->and($entries->contains(fn ($entry) => $entry->source === OperationalEventSource::CallSession))
        ->toBeTrue();
});

test('session provider manager defaults to fake provider', function (): void {
    config(['communications.session_provider' => 'fake']);

    $provider = app(SessionProviderManager::class)->current();

    expect($provider->key())->toBe('fake')
        ->and($provider->providerType())->toBe(TelephonyProviderType::Fake);
});

test('session event types use telephony-agnostic vocabulary', function (): void {
    expect(SessionEventType::SessionAnswered->value)->toBe('session_answered')
        ->and(SessionEventType::SessionTransferred->label())->toBe('Session transferred');
});
