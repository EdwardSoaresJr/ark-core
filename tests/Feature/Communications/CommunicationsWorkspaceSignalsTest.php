<?php

use App\Ark\Operations\Communications\CommunicationsWorkspaceProjection;
use App\Ark\Operations\Communications\CommunicationsWorkspaceSignals;
use App\Ark\Operations\Communications\CoreCommunicationsWorkspaceSignals;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationParticipantResolver;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\ConversationResolver;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSmsConsentStatus;
use App\Ark\Operations\Messaging\OutboundSmsResult;
use App\Ark\Operations\Messaging\OutboundSmsTransport;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
    config()->set('broadcasting.default', 'null');
    $this->advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
});

/**
 * A customer conversation with one inbound text and the given outbound texts' metadata.
 *
 * @param  list<array<string, mixed>>  $outbound
 */
function signalsConversation(User $advisor, array $outbound, array $customer = []): Conversation
{
    $customer = Customer::query()->create($customer + ['first_name' => 'Pat', 'last_name' => 'Signals', 'phone' => '7195550641', 'email' => 'pat.signals@example.com']);
    $conversation = app(ConversationResolver::class)->forCustomer($customer);
    $participants = app(ConversationParticipantResolver::class);
    $recorder = app(ConversationRecorder::class);
    $recorder->record($conversation, $participants->customer($conversation, $customer), OperationalCommunicationChannel::Sms,
        OperationalCommunicationDirection::Inbound, 'Is my car ready?', now()->subMinutes(30));
    foreach ($outbound as $i => $metadata) {
        $recorder->record($conversation, $participants->advisor($conversation, $advisor), OperationalCommunicationChannel::Sms,
            OperationalCommunicationDirection::Outbound, 'Update '.($i + 1), now()->subMinutes(20 - $i), $metadata);
    }

    return $conversation;
}

function signalsPage(User $advisor, Conversation $conversation): string
{
    return (string) test()->actingAs($advisor)
        ->get(route('operations.communications.inbox', ['filter' => 'all', 'conversation' => $conversation->id]))
        ->assertOk()
        ->getContent();
}

test('the thread shows where each text got to and why one was not delivered, from Core\'s own records', function () {
    $conversation = signalsConversation($this->advisor, [
        ['delivery_status' => 'delivered'],
        ['delivery_status' => 'undelivered', 'delivery_error_code' => '30007'],
    ]);

    $page = signalsPage($this->advisor, $conversation);

    expect($page)->toContain('data-comms-delivery="delivered"')
        ->and($page)->toContain('data-comms-delivery="undelivered"')
        ->and($page)->toContain('Carrier error 30007')
        ->and($page)->toContain('data-comms-delivery-notice')
        ->and(substr_count($page, 'data-comms-delivery='))->toBe(2);
});

test('a customer who replied STOP shows texting off in the header, the context lane, and above the composer', function () {
    $conversation = signalsConversation($this->advisor, [], ['sms_consent_status' => CustomerSmsConsentStatus::OptedOut]);

    $page = signalsPage($this->advisor, $conversation);

    expect($page)->toContain('data-comms-texting="opted_out"')
        ->and($page)->toContain('data-comms-context-texting="opted_out"')
        ->and($page)->toContain('Texting off');
});

test('with Core alone and no texting provider the composer keeps internal notes and says why texting and email are not available', function () {
    $page = signalsPage($this->advisor, signalsConversation($this->advisor, []));

    expect($page)->toContain('id="comms-composer-tab-reply"')
        ->and($page)->toContain('data-comms-composer-unavailable="Texting is not set up for this shop."')
        ->and($page)->toContain('data-comms-composer-unavailable="This conversation is by text. Email the customer from the Customer panel."')
        ->and($page)->toContain('id="comms-composer-tab-internal"')
        ->and($page)->toContain('x-data="{ tab: \'internal\' }"')
        ->and($page)->not->toContain('data-comms-email-composer')
        ->and($page)->toContain('data-live-events="[]"');
});

test('with a texting provider the composer offers SMS', function () {
    app()->instance(OutboundSmsTransport::class, new class implements OutboundSmsTransport
    {
        public function isConfigured(): bool
        {
            return true;
        }

        public function send(string $toPhone, string $body, array $mediaUrls = [], array $context = []): OutboundSmsResult
        {
            return new OutboundSmsResult('SMsignals0000000000000000000000001', 'queued');
        }
    });

    $page = signalsPage($this->advisor, signalsConversation($this->advisor, []));

    expect($page)->not->toContain('data-comms-composer-unavailable="Texting is not set up for this shop."')
        ->and($page)->toContain('x-data="{ tab: \'reply\' }"');
});

test('a host application\'s signals replace Core\'s: delivery, texting, message notes, notices, email replies, live events, and refresh', function () {
    $conversation = signalsConversation($this->advisor, [['delivery_status' => 'sent']]);
    app()->instance(CommunicationsWorkspaceSignals::class, new class implements CommunicationsWorkspaceSignals
    {
        public function deliveries(iterable $messages): array
        {
            $out = [];
            foreach ($messages as $message) {
                if ($message->direction === OperationalCommunicationDirection::Outbound) {
                    $out[(int) $message->id] = ['status' => 'read', 'at' => now()];
                }
            }

            return $out;
        }

        public function contactPermission(Conversation $conversation, ?Customer $customer): ?array
        {
            return ['texting' => 'allowed', 'quiet_hours_until' => now()->addHours(3), 'notes' => ['Agreed to marketing texts.']];
        }

        public function messageNotes(iterable $messages): array
        {
            $out = [];
            foreach ($messages as $message) {
                if ($message->direction === OperationalCommunicationDirection::Inbound) {
                    $out[(int) $message->id] = [['label' => 'Sender verified', 'tone' => 'success', 'title' => 'SPF pass']];
                }
            }

            return $out;
        }

        public function notices(): array
        {
            return ['Texts are not sent from this environment.'];
        }

        public function emailReplyUrl(Conversation $conversation): ?string
        {
            return 'https://host.test/email-replies/'.$conversation->id;
        }

        public function signature(?int $conversationId): string
        {
            return 'host-signature-'.$conversationId;
        }

        public function liveEvents(): array
        {
            return ['host.delivery.updated'];
        }
    });

    $page = signalsPage($this->advisor, $conversation);

    expect($page)->toContain('data-comms-delivery="read"')
        ->and($page)->not->toContain('data-comms-delivery="sent"')
        ->and($page)->toContain('Quiet hours: automated texts wait until')
        ->and($page)->toContain('data-comms-email-composer')
        ->and($page)->toContain('action="https://host.test/email-replies/'.$conversation->id.'"')
        ->and($page)->toContain('data-live-events="[&quot;host.delivery.updated&quot;]"')
        ->and($page)->toContain('data-comms-context-consent-note>Agreed to marketing texts.')
        ->and($page)->toContain('title="SPF pass"')
        ->and($page)->toContain('data-comms-message-note>Sender verified')
        ->and($page)->toContain('data-comms-workspace-notice>Texts are not sent from this environment.');

    $projection = app(CommunicationsWorkspaceProjection::class);
    expect($projection->pollSignature('all', $conversation->id))->not->toBe(
        (function () use ($conversation) {
            app()->instance(CommunicationsWorkspaceSignals::class, new CoreCommunicationsWorkspaceSignals);

            return app(CommunicationsWorkspaceProjection::class)->pollSignature('all', $conversation->id);
        })(),
    );
});
