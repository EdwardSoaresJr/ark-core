<?php

use App\Ark\Operations\Communications\CommunicationDevice;
use App\Ark\Operations\Communications\CommunicationDeviceProvider;
use App\Ark\Operations\Communications\CommunicationDeviceStatus;
use App\Ark\Operations\Communications\CommunicationsNeedsYou;
use App\Ark\Operations\Communications\InternalChannel;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipant;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\InternalChannelSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(InternalChannelSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
});

test('communications index redirects to attention workspace', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.communications.index'))
        ->assertRedirect(CommunicationsNeedsYou::url());
});

test('advisor can open attention and internal workspace shells', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $html = $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url())
        ->assertOk()
        ->assertSee('data-comms-workspace', false)
        ->assertSee('Needs attention', false)
        ->assertSee('Waiting', false)
        ->assertSee('Resolved', false)
        ->assertDontSee('Mark handled', false)
        ->getContent();

    $filters = [];
    if (preg_match('/aria-label="Communications sections"(.*?)<\/nav>/s', $html, $nav) === 1) {
        preg_match_all('/<span>(Needs attention|Waiting|Resolved)<\/span>/', $nav[1], $labels);
        $filters = $labels[1];
    }
    expect($filters)->toBe(['Needs attention', 'Waiting', 'Resolved']);

    $this->actingAs($advisor)
        ->get(route('operations.communications.internal'))
        ->assertOk()
        ->assertSee('General', false)
        ->assertSee('Management', false);
});

test('attention workspace thread renders inbound mms image attachments', function (): void {
    \Illuminate\Support\Facades\Http::fake([
        'https://api.twilio.com/*' => \Illuminate\Support\Facades\Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg']),
    ]);
    config()->set('services.twilio.auth_token', null);
    config()->set('broadcasting.default', 'null');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => 'Photo',
        'last_name' => 'Customer',
        'phone' => '7196416535',
        'customer_type' => 'Retail',
    ]);

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMworkspacemms001',
        'From' => '+17196416535',
        'To' => '+17195559999',
        'Body' => '',
        'NumMedia' => '1',
        'MediaUrl0' => 'https://api.twilio.com/2010-04-01/Accounts/ACtest/Messages/MM123/Media/ME456',
        'MediaContentType0' => 'image/jpeg',
    ])->assertOk();

    $conversation = \App\Ark\Operations\Conversations\Conversation::query()->sole();
    $message = \App\Ark\Operations\Conversations\ConversationMessage::query()->sole();
    $attachment = \App\Ark\Operations\Conversations\ConversationMessageAttachment::query()->sole();

    $attachmentUrl = route('operations.conversation-attachments.show', [
        'conversation' => $conversation,
        'message' => $message,
        'attachment' => $attachment,
    ]);

    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url(['conversation' => $conversation->id]))
        ->assertOk()
        ->assertSee('ops-attachment-thumb', false)
        ->assertSee($attachmentUrl, false)
        ->assertSee('ops-attachment-thumb__image', false);
});

test('internal channel workspace shows read only thread shell', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $channel = InternalChannel::query()->where('slug', 'general')->firstOrFail();

    $this->actingAs($advisor)
        ->get(route('operations.communications.internal.channel', $channel))
        ->assertOk()
        ->assertSee('General', false)
        ->assertSee('Internal only', false);
});

test('technician can open internal workspace but not attention inbox', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);

    $this->actingAs($technician)
        ->get(route('operations.communications.inbox'))
        ->assertForbidden();

    $this->actingAs($technician)
        ->get(CommunicationsNeedsYou::url())
        ->assertForbidden();

    $this->actingAs($technician)
        ->get(route('operations.communications.internal'))
        ->assertOk()
        ->assertSee('Internal channels', false);

    expect($technician->can(ArkCapability::CommunicationsInternalView->value))->toBeTrue()
        ->and($technician->can(ArkCapability::OperationsAccess->value))->toBeFalse();
});

test('legacy workboard redirects to attention workspace', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.communications.workboard'))
        ->assertRedirect(CommunicationsNeedsYou::url());
});

function commsTablerFakeHttp(): void
{
    Http::fake();
}

function commsTablerAdvisor(): User
{
    return User::factory()->create()->assignRole(ArkRole::Advisor->value);
}

function commsTablerAdmin(): User
{
    return User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);
}

function commsTablerConversation(): Conversation
{
    $customer = Customer::query()->create([
        'first_name' => 'Tabler',
        'last_name' => 'Inbox',
        'phone' => '7195553401',
        'email' => 'tabler.inbox@example.test',
    ]);

    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195553401',
        'status' => ConversationStatus::Open,
        'waiting_on' => ConversationWaitingOn::Customer,
        'posture_changed_at' => now()->subHour(),
    ]);

    $participant = ConversationParticipant::query()->create([
        'conversation_id' => $conversation->id,
        'participant_type' => ConversationParticipantType::Customer,
        'customer_id' => $customer->id,
    ]);

    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'conversation_participant_id' => $participant->id,
        'channel' => OperationalCommunicationChannel::Sms,
        'direction' => OperationalCommunicationDirection::Outbound,
        'body' => 'Your estimate is ready.',
        'occurred_at' => now()->subHour(),
    ]);

    return $conversation->fresh();
}

/** The page's own markup: the shell's header islands sit outside <main>. */
function commsTablerMain(string $html): string
{
    expect(preg_match('/<main>(.*)<\/main>/s', $html, $match))->toBe(1);

    return $match[1];
}

/** Class attributes left once every .ark-legacy island is removed. */
function commsTablerClassesOutsideIslands(string $markup): string
{
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8"?><div>'.$markup.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $xpath = new DOMXPath($document);
    foreach (iterator_to_array($xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' ark-legacy ')]")) as $island) {
        $island->parentNode?->removeChild($island);
    }

    $classes = [];
    foreach ($xpath->query('//*[@class]') as $node) {
        $classes[] = $node->getAttribute('class');
    }

    return implode("\n", $classes);
}

function commsTablerSection(string $html, string $from, string $to): string
{
    $start = strpos($html, $from);
    expect($start)->toBeInt();
    $end = strpos($html, $to, $start);
    expect($end)->toBeInt();

    return substr($html, $start, $end - $start);
}

test('the inbox renders in the Tabler shell with the split workspace and its hooks', function (): void {
    commsTablerFakeHttp();
    $advisor = commsTablerAdvisor();
    commsTablerConversation();

    $html = $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', ['filter' => 'waiting']))
        ->assertOk()
        ->assertSee('navbar navbar-vertical', false)
        ->assertSee('class="d-flex flex-column flex-fill ark-split"', false)
        ->assertSee('data-comms-workspace', false)
        ->assertSee('threadOpen: false', false)
        ->assertSee('id="ops-comms-workspace-live"', false)
        ->assertSee('data-fragment-url="', false)
        ->assertSee('data-poll-signature="', false)
        ->assertSee('id="ops-comms-workspace-list"', false)
        ->assertSee('id="ops-comms-workspace-list-body"', false)
        ->assertSee('id="ops-comms-workspace-thread"', false)
        ->assertSee('id="ops-comms-workspace-context"', false)
        ->assertSee('id="comms-workspace-thread-messages"', false)
        ->assertSee('data-comms-list-items', false)
        ->assertSee('data-comms-list-row', false)
        ->assertSee('data-comms-switch', false)
        ->assertSee('aria-label="Communications sections"', false)
        ->assertSee('aria-label="Advisor filter"', false)
        ->assertSee('data-ops-global-search-open', false)
        ->assertSee(route('operations.communications.inbox', ['filter' => 'needs']), false)
        ->assertSee(route('operations.communications.calls'), false)
        ->assertSee(route('operations.communications.history'), false)
        ->getContent();

    $main = commsTablerMain($html);

    expect($main)->not->toContain('ark-legacy')
        ->and(commsTablerClassesOutsideIslands($main))->not->toMatch('/(^|\s)ops-/m');
});

test('an open inbox thread keeps the composer, forms, and event bubble islands', function (): void {
    commsTablerFakeHttp();
    $advisor = commsTablerAdvisor();
    $conversation = commsTablerConversation();

    $html = $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', [
            'filter' => 'waiting',
            'conversation' => $conversation->id,
        ]))
        ->assertOk()
        ->assertSee('threadOpen: true', false)
        ->assertSee('data-comms-list-row', false)
        ->assertSee('aria-current="true"', false)
        ->assertSee('id="comms-thread-composer"', false)
        ->assertSee('data-ark-conversation-composer', false)
        ->assertSee('x-data="arkConversationQuickReply(', false)
        ->assertSee('x-ref="actionsMenu"', false)
        ->assertSee('data-comms-actions-panel', false)
        ->assertSee('x-ref="replyBody"', false)
        ->assertSee('role="tab"', false)
        ->assertSee('aria-controls="comms-composer-panel-internal"', false)
        ->assertSee('action="'.route('operations.communications.conversations.internal-note', $conversation).'"', false)
        ->assertSee('data-comms-context-toggle', false)
        ->assertSee('@click="contextOpen = ! contextOpen"', false)
        ->assertSee('Tabler Inbox', false)
        ->getContent();

    $main = commsTablerMain($html);
    $islands = substr_count($main, 'ark-legacy');

    expect(substr_count($main, '<div class="ark-legacy bg-white text-dark rounded">'))->toBe($islands)
        ->and(commsTablerClassesOutsideIslands($main))->not->toMatch('/(^|\s)ops-/m');

    expect($islands)->toBeGreaterThan(0)
        ->and($main)->toContain('ops-comms-workspace__bubble');
});

test('the workspace fragment returns Tabler list, thread, and context panels', function (): void {
    commsTablerFakeHttp();
    $advisor = commsTablerAdvisor();
    $conversation = commsTablerConversation();

    $payload = $this->actingAs($advisor)
        ->getJson(route('operations.communications.workspace.fragment', [
            'section' => 'inbox',
            'filter' => 'waiting',
            'conversation' => $conversation->id,
        ]))
        ->assertOk()
        ->json();

    expect($payload['list'] ?? '')->toContain('data-comms-list-items')
        ->and($payload['list'] ?? '')->toContain('data-comms-list-row')
        ->and($payload['thread'] ?? '')->toContain('data-comms-thread-card')
        ->and($payload['thread'] ?? '')->toContain('data-comms-thread-body')
        ->and($payload['thread'] ?? '')->toContain('data-comms-switch')
        ->and($payload['context'] ?? '')->toContain('data-comms-context-card')
        ->and(commsTablerClassesOutsideIslands($payload['list'] ?? ''))->not->toMatch('/(^|\s)ops-/m')
        ->and(commsTablerClassesOutsideIslands($payload['thread'] ?? ''))->not->toMatch('/(^|\s)ops-/m')
        ->and(commsTablerClassesOutsideIslands($payload['context'] ?? ''))->not->toMatch('/(^|\s)ops-/m');
});

test('history and internal render in the Tabler shell and keep the history filter fields', function (): void {
    commsTablerFakeHttp();
    $advisor = commsTablerAdvisor();

    $html = $this->actingAs($advisor)
        ->get(route('operations.communications.history'))
        ->assertOk()
        ->assertSee('navbar navbar-vertical', false)
        ->assertSee('ark-split', false)
        ->assertSee('id="ops-comms-workspace"', false)
        ->assertSee('data-comms-history-filters', false)
        ->assertSee('action="'.route('operations.communications.history').'"', false)
        ->getContent();

    $form = commsTablerSection($html, 'data-comms-history-filters', '</form>');
    preg_match_all('/name="([a-z_]+)"/', $form, $names);

    expect($names[1])->toBe(['q', 'from', 'to', 'media'])
        ->and(commsTablerMain($html))->not->toContain('ark-legacy');

    $internal = $this->actingAs($advisor)
        ->get(route('operations.communications.internal'))
        ->assertOk()
        ->assertSee('navbar navbar-vertical', false)
        ->assertSee('ark-split', false)
        ->getContent();

    expect(commsTablerMain($internal))->not->toContain('ark-legacy');
});

test('calls and voicemail renders in the Tabler shell with the same filters and player hooks', function (): void {
    commsTablerFakeHttp();
    $advisor = commsTablerAdvisor();

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAtablercalls01',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195553402',
        'to_number' => '+17195559999',
        'normalized_from' => '7195553402',
        'status' => CallSessionStatus::Completed,
        'started_at' => now()->subMinutes(30),
    ]);

    $html = $this->actingAs($advisor)
        ->get(route('operations.communications.calls'))
        ->assertOk()
        ->assertSee('navbar navbar-vertical', false)
        ->assertSee('id="ops-call-library"', false)
        ->assertSee('data-call-library', false)
        ->assertSee("document.querySelector('[data-call-library]')", false)
        ->assertSee('aria-label="Communications sections"', false)
        ->assertSee('aria-current="page"', false)
        ->assertSee('Calls & VM', false)
        ->assertSee('data-call-row', false)
        ->getContent();

    $form = commsTablerSection($html, 'aria-label="Filter calls"', '</form>');
    preg_match_all('/name="([a-z_]+)"/', $form, $names);

    expect($names[1])->toBe(['filter', 'handled', 'from', 'to', 'q'])
        ->and($html)->toContain('<form method="get" action="'.route('operations.communications.calls').'" role="search" aria-label="Filter calls">')
        ->and(commsTablerMain($html))->not->toContain('ark-legacy')
        ->and(commsTablerClassesOutsideIslands(commsTablerMain($html)))->not->toMatch('/(^|\s)ops-/m');
});

test('stations and phones pages render in the Tabler shell with their forms', function (): void {
    commsTablerFakeHttp();
    $admin = commsTablerAdmin();
    ShopSettings::forgetCurrent();

    $setup = $this->actingAs($admin)
        ->get(route('operations.shop.communications'))
        ->assertOk()
        ->assertSee('navbar navbar-vertical', false)
        ->assertSee('Stations &amp; Phones', false)
        ->assertSee('data-shop-comms-setup-steps', false)
        ->assertSee('action="'.route('operations.shop.workstations.store').'"', false)
        ->assertSee('name="location_label"', false)
        ->getContent();

    expect(commsTablerMain($setup))->not->toContain('ark-legacy')
        ->and(commsTablerClassesOutsideIslands(commsTablerMain($setup)))->not->toMatch('/(^|\s)ops-/m');

    $workstation = Workstation::query()->create([
        'name' => 'Front Counter',
        'location_label' => 'Front desk',
    ]);
    $device = CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'workstation_id' => $workstation->id,
        'name' => 'Front Counter phone',
        'assigned_user_id' => $admin->id,
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::Connected,
        'capabilities' => ['voice'],
    ]);

    $deviceHtml = $this->actingAs($admin)
        ->get(route('operations.shop.devices.show', $device))
        ->assertOk()
        ->assertSee('navbar navbar-vertical', false)
        ->assertSee('id="first-contact"', false)
        ->assertSee('action="'.route('operations.shop.devices.destroy', $device).'"', false)
        ->assertSee('name="_method" value="DELETE"', false)
        ->getContent();

    expect(commsTablerMain($deviceHtml))->not->toContain('ark-legacy')
        ->and(commsTablerClassesOutsideIslands(commsTablerMain($deviceHtml)))->not->toMatch('/(^|\s)ops-/m');

    $personHtml = $this->actingAs($admin)
        ->get(route('operations.shop.people.show', $admin))
        ->assertOk()
        ->assertSee('navbar navbar-vertical', false)
        ->assertSee('action="'.route('operations.shop.devices.store').'"', false)
        ->assertSee('name="assigned_user_id" value="'.$admin->id.'"', false)
        ->assertSee('name="provider"', false)
        ->getContent();

    expect(commsTablerMain($personHtml))->not->toContain('ark-legacy');

    $this->actingAs($admin);
    $required = view('operations.shop.communications.setup-required', ['missing' => ['communication_device_models table']])->render();

    expect($required)->toContain('navbar navbar-vertical')
        ->and($required)->toContain('communication_device_models table')
        ->and(commsTablerMain($required))->not->toContain('ark-legacy');
});

test('the customer hub work and comms tabs render the shared communication components in Tabler', function (): void {
    commsTablerFakeHttp();
    $advisor = commsTablerAdvisor();
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Hub Comms Tabler');
    $customer = $repairOrder->customer;

    $message = app(ConversationRecorder::class)->recordAdvisorLog(
        $repairOrder,
        $advisor,
        OperationalCommunicationChannel::Sms,
        OperationalCommunicationDirection::Inbound,
        'Is my truck ready?',
    );

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAtablerhub01',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551000',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551000',
        'status' => CallSessionStatus::Completed,
        'customer_id' => $customer->id,
        'started_at' => now()->subMinutes(10),
    ]);

    $html = $this->actingAs($advisor)
        ->get(route('operations.customers.show', $customer))
        ->assertOk()
        ->getContent();

    $work = commsTablerSection($html, 'id="customer-work"', "x-show=\"tab === 'vehicles'\"");
    $comms = commsTablerSection($html, "x-show=\"tab === 'comms'\"", "x-show=\"tab === 'visits'\"");

    expect($work)->toContain('data-conversation-context')
        ->and($work)->toContain('id="open-repair-orders"')
        ->and($work)->toContain('Active Work')
        ->and(commsTablerClassesOutsideIslands($work))->not->toMatch('/(^|\s)ops-/m')
        ->and(substr_count($work, 'class="ark-legacy'))->toBe(substr_count($work, 'class="ark-legacy flex-shrink-0"') + substr_count($work, 'class="ark-legacy bg-white text-dark rounded mt-2"'));

    expect($comms)->not->toContain('ark-legacy')
        ->and($comms)->toContain('x-data="arkCustomerHubComms(')
        ->and($comms)->toContain('data-ark-conversation-composer')
        ->and($comms)->toContain('x-data="arkConversationQuickReply(')
        ->and($comms)->toContain('id="conversation-messages-relationship"')
        ->and($comms)->toContain('data-conversation-row')
        ->and($comms)->toContain('data-conversation-message-id="'.$message->id.'"')
        ->and($comms)->toContain('Is my truck ready?')
        ->and($comms)->toContain('badge bg-azure-lt">Call</span>')
        ->and(commsTablerClassesOutsideIslands($comms))->not->toMatch('/(^|\s)ops-/m');
});

test('legacy consumers of the shared components keep their legacy markup', function (): void {
    commsTablerFakeHttp();
    $this->actingAs(actingAsLearnCurrentAdvisor());

    app(ConversationRecorder::class)->recordInboundSms(
        normalizedPhone: '7195553403',
        body: 'Can you look at my brakes?',
        providerMessageSid: 'SM_tabler_legacy_consumer',
    );

    $this->get(route('operations.caller-lookup', ['phone' => '719-555-3403']))
        ->assertOk()
        ->assertSee('ops-index-results-head', false)
        ->assertSee('ops-state-pill', false)
        ->assertSee('Can you look at my brakes?')
        ->assertDontSee('data-conversation-context', false);

    $repairOrder = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Legacy Rail Customer');

    $this->get(route('operations.repair-orders.workspace-tabs.show', ['repairOrder' => $repairOrder, 'tab' => 'comms']))
        ->assertOk()
        ->assertSee('id="communication-rail"', false)
        ->assertDontSee('data-comms-actions-panel', false)
        ->assertDontSee('dropdown-menu show position-fixed', false);
});
