<?php

use App\Ark\Operations\Communications\CommunicationsNeedsYou;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
    config()->set('broadcasting.default', 'null');
    config()->set('services.twilio.auth_token', null);
    Http::fake();
});

test('sarah text lands in needs attention filter — not a separate destination', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMsarahneedsyou001',
        'From' => '+17195550113',
        'To' => '+17195559999',
        'Body' => 'Can you look at my brakes today?',
        'NumMedia' => '0',
    ])->assertOk();

    $conversation = Conversation::query()->sole();

    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url())
        ->assertOk()
        ->assertSee('Needs attention', false)
        ->assertSee('Inbox', false)
        ->assertSee('(719) 555-0113', false)
        ->assertDontSee('Needs You', false);

    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url(['conversation' => $conversation->id]))
        ->assertOk()
        ->assertSee('Can you look at my brakes today?', false)
        ->assertSee('(719) 555-0113', false)
        ->assertSee('Mark handled', false)
        ->assertSee('Unknown contact', false)
        ->assertSee('Next Actions', false)
        ->assertSee('Conversation', false);

    $this->actingAs($advisor)
        ->get(route('operations.communications.attention', ['conversation' => $conversation->id]))
        ->assertRedirect(CommunicationsNeedsYou::url(['conversation' => $conversation->id]));

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', ['turn' => 'shop', 'conversation' => $conversation->id]))
        ->assertRedirect(route('operations.communications.inbox', [
            'filter' => 'needs',
            'conversation' => $conversation->id,
        ]));
});
