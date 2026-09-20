<?php

use App\Ark\Operations\Communications\CommunicationsNeedsYou;
use App\Ark\Operations\Communications\InternalChannel;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\InternalChannelSeeder;

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
        ->assertSee('ops-comms-workspace', false)
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
