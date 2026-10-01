<?php

use App\Ark\Operations\Communications\CommunicationsInboxPresentation;
use App\Ark\Operations\Communications\CommunicationsWorkspaceProjection;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Conversations\ConversationWork;
use App\Ark\Operations\Timeline\OperationalEventEntry;
use App\Ark\Operations\Timeline\OperationalEventKind;
use App\Ark\Operations\Timeline\OperationalEventSource;
use App\Ark\Operations\Timeline\OperationalEventTone;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('decision banner names the evidence instead of calling every event a message', function (): void {
    $presentation = app(CommunicationsInboxPresentation::class);

    $portal = $presentation->decorateThread([
        'identity' => ['name' => 'Terry'],
        'events' => [evidence(OperationalEventKind::EstimateViewed, OperationalEventTone::Shop, 'Customer opened the estimate portal link.')],
    ], ['key' => 'none']);

    $missed = $presentation->decorateThread([
        'identity' => ['name' => 'Unknown'],
        'events' => [evidence(OperationalEventKind::MissedCall, OperationalEventTone::Customer, '(312) 940-4952 · Missed')],
    ], ['key' => 'none']);

    $status = $presentation->decorateThread([
        'identity' => ['name' => 'Pedro'],
        'events' => [evidence(OperationalEventKind::VehicleStatus, OperationalEventTone::Shop, 'Previously Ready for Pickup')],
    ], ['key' => 'none']);

    $text = $presentation->decorateThread([
        'identity' => ['name' => 'Terry'],
        'events' => [evidence(OperationalEventKind::Sms, OperationalEventTone::Customer, 'Ok, please call me.')],
    ], ['key' => 'none']);

    expect($portal['decision']['title'])->toBe('Portal activity')
        ->and($portal['decision']['excerpt'])->toBe('Customer opened the estimate portal link.')
        ->and($missed['decision']['title'])->toBe('Missed call')
        ->and($status['decision']['title'])->toBe('Repair order updated')
        ->and($text['decision']['title'])->toBe('Customer wrote last')
        ->and($text['decision']['prompt'])->toContain('reply if needed')
        ->and($text['decision']['prompt'])->toContain('mark waiting')
        ->and($portal['decision']['title'])->not->toBe('Shop wrote last')
        ->and($missed['decision']['title'])->not->toBe('Customer wrote last');
});

test('lane counts stay mutually exclusive on every filter', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195554101',
        'status' => ConversationStatus::Open,
        'waiting_on' => ConversationWaitingOn::Shop,
    ]);
    Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195554102',
        'status' => ConversationStatus::Open,
        'waiting_on' => ConversationWaitingOn::Customer,
        'follow_up_due_at' => now()->addDay(),
    ]);
    Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195554103',
        'status' => ConversationStatus::Open,
        'waiting_on' => ConversationWaitingOn::Customer,
        'follow_up_due_at' => now()->subHour(),
    ]);
    Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195554104',
        'status' => ConversationStatus::Resolved,
        'waiting_on' => ConversationWaitingOn::Customer,
        'resolved_at' => now(),
    ]);

    $counts = app(ConversationWork::class)->counts();
    $projection = app(CommunicationsWorkspaceProjection::class);

    expect($counts['needs'] + $counts['waiting'] + $counts['resolved'])->toBe(Conversation::query()->count())
        ->and($counts['needs'])->toBe(2)
        ->and($counts['waiting'])->toBe(1)
        ->and($counts['resolved'])->toBe(1);

    $needs = $projection->inbox($advisor, null, null, null, listFilter: 'needs');
    $waiting = $projection->inbox($advisor, null, null, null, listFilter: 'waiting');

    expect($needs['filter_counts'])->toBe($waiting['filter_counts'])
        ->and($needs['filter_counts'])->toBe($counts)
        ->and(collect($waiting['list_items'])->pluck('key'))->not->toContain(
            'conversation:'.Conversation::query()->where('contact_address', '7195554103')->value('id'),
        );
});

function evidence(OperationalEventKind $kind, OperationalEventTone $tone, string $body): OperationalEventEntry
{
    return new OperationalEventEntry(
        source: OperationalEventSource::OperationalEvent,
        kind: $kind,
        occurredAt: Carbon::parse('2026-09-16 10:00:00'),
        headline: $body,
        body: $body,
        actor: null,
        tone: $tone,
    );
}
