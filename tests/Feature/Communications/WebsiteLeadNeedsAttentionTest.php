<?php

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\ConversationWork;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Leads\WebsiteLeadInterruptBroadcaster;
use App\Ark\Operations\Leads\WebsiteLeadInterruptDismissal;
use App\Ark\Operations\Leads\WebsiteLeadInterruptPresenter;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Platform\Communications\PlatformCommunicationsInboxProjection;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);

    config()->set('services.ark_platform.communications_authority', true);
    config()->set('services.ark_platform.communications_inbox', true);
    config()->set('services.ark_platform.communications_core_mirror', false);

    ShopSettings::current()->persistTrusted([
        'platform_status' => 'connected',
        'platform_credential' => 'test-platform-credential',
        'platform_base_url' => 'https://cloud.test',
    ]);

    Http::fake([
        'https://cloud.test/api/v1/services/communications/conversations*' => Http::response([
            'ok' => true,
            'conversations' => [],
        ], 200),
    ]);
});

test('an open website quote appears in default needs attention', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$lead, $conversation] = openWebsiteQuote();

    $workspace = app(PlatformCommunicationsInboxProjection::class)->inbox($advisor, null, 'needs');
    expect(collect($workspace['list_items'])->pluck('lead_id')->all())->toContain($lead->id);

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', ['filter' => 'needs']))
        ->assertOk()
        ->assertSee('data-comms-row-key="conversation:'.$conversation->id.'"', false)
        ->assertSee('Markilla S Smith')
        ->assertSee('Website Lead')
        ->assertSee('I would like a quote')
        ->assertSee('(719) 555-6289')
        ->assertSee('2012 Dodge Durango')
        ->assertSee('Unassigned')
        ->assertSee('Needs attention')
        ->assertSee('Check In')
        ->assertSee(route('operations.leads.intake', $lead), false);
});

test('dismissing the website lead popup leaves the quote in needs attention', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $other = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$lead] = openWebsiteQuote();

    Cache::put(
        WebsiteLeadInterruptBroadcaster::cacheKey(),
        app(WebsiteLeadInterruptPresenter::class)->forLead($lead),
        now()->addHour(),
    );

    app(WebsiteLeadInterruptDismissal::class)->dismiss($advisor->id, $lead->id);

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', ['filter' => 'needs']))
        ->assertOk()
        ->assertSee('Markilla S Smith')
        ->assertSee('I would like a quote');

    $this->actingAs($advisor)
        ->getJson(route('operations.comms.interrupts'))
        ->assertOk()
        ->assertJsonMissing(['lead_id' => $lead->id]);

    $this->actingAs($other)
        ->getJson(route('operations.comms.interrupts'))
        ->assertOk()
        ->assertJsonPath('messages.0.lead_id', $lead->id);

    $otherInbox = app(PlatformCommunicationsInboxProjection::class)->inbox($other, null, 'needs');
    expect(collect($otherInbox['list_items'])->pluck('lead_id')->all())->toContain($lead->id);
});

test('website lead popup dismissal expires after eight hours and does not change that duration', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$lead] = openWebsiteQuote();

    $dismissedAt = now();
    app(WebsiteLeadInterruptDismissal::class)->dismiss($advisor->id, $lead->id);

    $this->travelTo($dismissedAt->copy()->addHours(7)->addMinutes(59));
    expect(app(WebsiteLeadInterruptDismissal::class)->isDismissed($advisor->id, $lead->id))->toBeTrue();

    $this->travelTo($dismissedAt->copy()->addHours(8)->addMinute());
    expect(app(WebsiteLeadInterruptDismissal::class)->isDismissed($advisor->id, $lead->id))->toBeFalse();
});

test('resolving the conversation removes the website quote from needs attention', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$lead, $conversation] = openWebsiteQuote();

    app(ConversationWork::class)->resolve($conversation, $advisor);

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', ['filter' => 'needs']))
        ->assertOk()
        ->assertDontSee('Markilla S Smith');

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', ['filter' => 'resolved']))
        ->assertOk()
        ->assertSee('Markilla S Smith');

    expect($lead->fresh()->state)->toBe(LeadState::Received)
        ->and($lead->fresh()->first_contacted_at)->toBeNull();
});

test('contacted spam and lost website leads stay out of needs attention', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    contactedWebsiteQuote('Casey Contacted', '7195557001');
    closedWebsiteQuote(LeadState::Spam, 'Pat Spam', '7195557002');
    closedWebsiteQuote(LeadState::Lost, 'Lee Lost', '7195557003');

    $headlines = collect(app(PlatformCommunicationsInboxProjection::class)->inbox($advisor, null, 'needs')['list_items'])
        ->pluck('headline');

    expect($headlines->all())
        ->not->toContain('Casey Contacted')
        ->not->toContain('Pat Spam')
        ->not->toContain('Lee Lost');
});

test('a website quote with the same phone as an sms thread is one row', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$lead] = openWebsiteQuote();

    Http::fake([
        'https://cloud.test/api/v1/services/communications/conversations*' => Http::response([
            'ok' => true,
            'conversations' => [[
                'public_id' => 'pc_quote',
                'contact_address' => '+17195556289',
                'core_customer_id' => null,
                'preview' => 'Hi Markilla, we received your request.',
                'last_message_at' => now()->toIso8601String(),
                'unread' => true,
            ]],
        ], 200),
    ]);

    $workspace = app(PlatformCommunicationsInboxProjection::class)->inbox($advisor, null, 'needs');
    $matches = array_values(array_filter(
        $workspace['list_items'],
        fn (array $item): bool => ($item['lead_id'] ?? null) === $lead->id
            || str_contains((string) ($item['phone'] ?? ''), '6289'),
    ));

    expect($matches)->toHaveCount(1)
        ->and($matches[0]['source_label'] ?? null)->toBe('Website Lead')
        ->and($matches[0]['headline'])->toBe('Markilla S Smith')
        ->and(collect($workspace['list_items'])->where('source_label', 'Website Lead'))->toHaveCount(1);
});

test('opening the website quote reaches check in without a memorized lead url', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$lead, $conversation] = openWebsiteQuote();

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', [
            'filter' => 'needs',
            'conversation' => $conversation->id,
        ]))
        ->assertOk()
        ->assertSee('Check In')
        ->assertSee(route('operations.leads.intake', $lead), false)
        ->assertSee('I would like a quote')
        ->assertSee('2012 Dodge Durango');
});

/**
 * @return array{0: Lead, 1: Conversation}
 */
function openWebsiteQuote(): array
{
    $concern = 'Subject: I would like a quote if possible. 2012 Dodge Durango front brakes.';
    $message = app(ConversationRecorder::class)->recordWebsiteLead(
        null,
        $concern,
        '7195556289',
        'Markilla S Smith',
    );

    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => $concern,
        'contact_name' => 'Markilla S Smith',
        'contact_phone' => '7195556289',
        'contact_email' => 'markilla@example.com',
        'vehicle_year' => 2012,
        'vehicle_make' => 'Dodge',
        'vehicle_model' => 'Durango',
        'conversation_id' => $message->conversation_id,
    ]);

    return [$lead, $message->conversation];
}

function contactedWebsiteQuote(string $name, string $phone): void
{
    $message = app(ConversationRecorder::class)->recordWebsiteLead(null, 'Already called.', $phone, $name);

    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Already called.',
        'contact_name' => $name,
        'contact_phone' => $phone,
        'conversation_id' => $message->conversation_id,
        'first_contacted_at' => now(),
    ]);
}

function closedWebsiteQuote(LeadState $state, string $name, string $phone): void
{
    $message = app(ConversationRecorder::class)->recordWebsiteLead(null, 'Closed request.', $phone, $name);

    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => $state,
        'concern' => 'Closed request.',
        'contact_name' => $name,
        'contact_phone' => $phone,
        'conversation_id' => $message->conversation_id,
    ]);
}
