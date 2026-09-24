<?php

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipant;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

test('conversation switching shows a short header cue and ignores stale completions', function () {
    $js = (string) file_get_contents(resource_path('js/ark-comms-workspace.js'));
    $css = (string) file_get_contents(resource_path('css/app.css'));
    $open = substr($js, (int) strpos($js, 'const openSelection'));

    expect($js)->toContain('const SELECTION_FEEDBACK_MS = 180;')
        ->and($js)->toContain('if (opened === false)');

    $mark = strpos($open, 'markSelectedRow(selectedHref)');
    $fetch = strpos($open, 'await fetchWorkspace');
    $wait = strpos($open, 'await wait(selectionFeedbackRemaining(startedAt))');
    $afterWait = substr($open, (int) $wait);

    expect($mark)->toBeInt()->toBeLessThan($fetch)
        ->and($fetch)->toBeLessThan($wait)
        ->and(strpos($afterWait, "return 'stale'"))->toBeLessThan(strpos($afterWait, 'setSelectionSwitching(false)'))
        ->and(strpos($afterWait, 'setSelectionSwitching(false)'))->toBeLessThan(strpos($afterWait, 'applyPayload(payload'));

    expect($css)
        ->toContain('.ops-comms-workspace__switch { display: none;')
        ->toContain('.ops-comms-workspace__thread.is-switching .ops-comms-workspace__switch { display: inline-flex; }')
        ->not->toContain('.ops-comms-workspace.is-switching');
});

test('detail fragments keep the switch cue beside the conversation name', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
    Http::fake();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Dana',
        'last_name' => 'Wait',
        'phone' => '7195553201',
        'email' => 'dana.wait@example.test',
    ]);
    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195553201',
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
        'body' => 'We sent the estimate.',
        'occurred_at' => now()->subHour(),
    ]);

    $empty = $this->actingAs($advisor)
        ->getJson(route('operations.communications.workspace.fragment', [
            'section' => 'inbox',
            'filter' => 'waiting',
        ]))
        ->assertOk()
        ->json('thread');

    $open = $this->actingAs($advisor)
        ->getJson(route('operations.communications.workspace.fragment', [
            'section' => 'inbox',
            'filter' => 'waiting',
            'conversation' => $conversation->id,
        ]))
        ->assertOk()
        ->json('thread');

    expect($empty)->toContain('ops-comms-workspace__switch')
        ->and($open)->toContain('Dana Wait')
        ->and($open)->toContain('ops-comms-workspace__switch')
        ->and(strpos($open, 'Dana Wait'))->toBeLessThan(strpos($open, 'ops-comms-workspace__switch'));
});
