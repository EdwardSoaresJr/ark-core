<?php

use App\Ark\Operations\Communications\CommunicationsWorkspaceProjection;
use App\Ark\Operations\Communications\CommunicationsWorkspaceSignalPresenter;
use App\Ark\Operations\Communications\CommunicationsWorkspaceSignals;
use App\Ark\Operations\Communications\CoreCommunicationsWorkspaceSignals;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationLinker;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipant;
use App\Ark\Operations\Conversations\ConversationParticipantResolver;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\ConversationResolver;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSmsConsentStatus;
use App\Ark\Operations\Customers\CustomerSmsSendEligibility;
use App\Ark\Operations\Messaging\OutboundSmsResult;
use App\Ark\Operations\Messaging\OutboundSmsTransport;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
    config()->set('broadcasting.default', 'null');
    $this->advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    ShopSettings::current()->update([
        'shop_name' => 'Signals Auto',
        'telephony_inbound_number' => '7195559999',
    ]);
    ShopSettings::forgetCurrent();
});

function signalsBindSmsTransport(): void
{
    config([
        'services.twilio.account_sid' => 'ACsyntheticsignals',
        'services.twilio.auth_token' => 'synthetic-signals-token',
    ]);
    bindFakeOutboundSms('SMsignalscompanion000000000000000001');
}

function signalsCapability(string $phone, bool $capable, string $lineType = 'mobile'): void
{
    PhoneSmsCapability::query()->create([
        'normalized_phone' => $phone,
        'valid' => true,
        'line_type' => $lineType,
        'carrier_name' => 'Test',
        'sms_capable' => $capable,
        'reason' => $capable ? null : 'Landline (Test) - cannot receive SMS.',
        'checked_at' => now(),
    ]);
}

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
        ->and($page)->not->toContain('data-comms-delivery-notice')
        ->and($page)->not->toContain('Last text not delivered')
        ->and(substr_count($page, 'data-comms-delivery='))->toBe(2);
});

test('a failed text shows one status beside the message with careful 30006 wording and the raw code under Details', function () {
    $conversation = signalsConversation($this->advisor, [
        ['delivery_status' => 'undelivered', 'delivery_error_code' => '30006'],
    ]);

    $page = signalsPage($this->advisor, $conversation);

    expect($page)->toContain('data-comms-delivery="undelivered"')
        ->and($page)->toContain('Not delivered</span> — this number may not support SMS. Check the number or call the customer.')
        ->and($page)->toContain('Delivery: Not delivered · Carrier error 30006')
        ->and($page)->not->toContain('data-comms-delivery-notice')
        ->and($page)->not->toContain('border-danger')
        ->and(substr_count($page, 'may not support SMS'))->toBe(1);
});

test('several failures keep their own status beside each text and add no thread-level repeat', function () {
    $conversation = signalsConversation($this->advisor, [
        ['delivery_status' => 'undelivered', 'delivery_error_code' => '30003'],
        ['delivery_status' => 'delivered'],
        ['delivery_status' => 'failed', 'delivery_error_code' => '30006'],
        ['delivery_status' => 'undelivered'],
    ]);

    $page = signalsPage($this->advisor, $conversation);

    expect(substr_count($page, 'data-comms-delivery="undelivered"'))->toBe(2)
        ->and(substr_count($page, 'data-comms-delivery="failed"'))->toBe(1)
        ->and($page)->toContain('the carrier could not deliver this text yet')
        ->and($page)->toContain('this number may not support SMS')
        ->and($page)->toContain('the customer did not get this message')
        ->and($page)->not->toContain('data-comms-delivery-notice');
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

test('presenter never invites retry for STOP or landline delivery failures', function () {
    $stop = CommunicationsWorkspaceSignalPresenter::delivery([
        'status' => 'undelivered',
        'error_code' => '21610',
    ]);
    $landline = CommunicationsWorkspaceSignalPresenter::delivery([
        'status' => 'failed',
        'error_code' => '30006',
    ]);
    $temporary = CommunicationsWorkspaceSignalPresenter::delivery([
        'status' => 'undelivered',
        'error_code' => '30003',
    ]);

    expect($stop['allows_retry'])->toBeFalse()
        ->and($stop['detail'])->not->toContain('send it again')
        ->and($stop['detail'])->toContain('STOP')
        ->and($landline['allows_retry'])->toBeFalse()
        ->and($landline['detail'])->not->toContain('send it again')
        ->and($landline['detail'])->toContain('may not support SMS')
        ->and($landline['detail'])->not->toContain('landline')
        ->and($landline['guidance'])->toBe('this number may not support SMS. Check the number or call the customer.')
        ->and($landline['error_code'])->toBe('30006')
        ->and($temporary['allows_retry'])->toBeTrue()
        ->and($temporary['detail'])->toContain('try again later');
});

test('presenter shows Texting unavailable for landline and service-disabled permissions', function () {
    $landline = CommunicationsWorkspaceSignalPresenter::permission([
        'texting' => 'unavailable',
        'unavailable_reason' => 'landline',
        'source' => 'This number cannot receive texts.',
    ]);
    $service = CommunicationsWorkspaceSignalPresenter::permission([
        'texting' => 'unavailable',
        'unavailable_reason' => 'service_disabled',
        'source' => 'Shop texting is not available',
    ]);
    $legacyUnknown = CommunicationsWorkspaceSignalPresenter::permission([
        'texting' => 'unknown',
        'source' => 'Texting unavailable for this number',
        'notes' => ['Landline (Test) - cannot receive texts.'],
    ]);

    expect($landline['texting'])->toBe('unavailable')
        ->and($landline['label'])->toContain('landline')
        ->and($landline['label'])->not->toBe('Texting allowed')
        ->and($service['label'])->toBe('Texting unavailable')
        ->and($legacyUnknown['texting'])->toBe('unavailable')
        ->and($legacyUnknown['label'])->toContain('Texting unavailable');
});

test('core signals mark destination landline unavailable without blaming the primary phone', function () {
    signalsBindSmsTransport();
    $customer = Customer::query()->create([
        'first_name' => 'Multi',
        'last_name' => 'Number',
        'phone' => '7195553001',
        'sms_consent_status' => CustomerSmsConsentStatus::Subscribed,
    ]);
    signalsCapability('7195553001', true);
    signalsCapability('7195553002', false, 'landline');

    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195553002',
        'status' => ConversationStatus::Open,
    ]);
    app(ConversationLinker::class)->link($conversation, $customer);

    $permission = app(CoreCommunicationsWorkspaceSignals::class)->contactPermission($conversation, $customer);

    expect($permission['texting'])->toBe('unavailable')
        ->and($permission['unavailable_reason'])->toBe('landline')
        ->and(CustomerSmsSendEligibility::for($customer, ShopIntegrationCredentials::forCurrentShop(), '7195553001')->canSend())->toBeTrue()
        ->and(CustomerSmsSendEligibility::for($customer, ShopIntegrationCredentials::forCurrentShop(), '7195553002')->canSend())->toBeFalse();
});

test('changed primary phone eligibility uses the new number while the old landline stays blocked', function () {
    signalsBindSmsTransport();
    $customer = Customer::query()->create([
        'first_name' => 'Changed',
        'last_name' => 'Primary',
        'phone' => '7195553011',
        'sms_consent_status' => CustomerSmsConsentStatus::Subscribed,
    ]);
    signalsCapability('7195553011', false, 'landline');
    $customer->update(['phone' => '7195553012']);
    signalsCapability('7195553012', true);

    expect(CustomerSmsSendEligibility::for($customer->fresh(), ShopIntegrationCredentials::forCurrentShop())->canSend())->toBeTrue()
        ->and(CustomerSmsSendEligibility::for($customer->fresh(), ShopIntegrationCredentials::forCurrentShop(), '7195553011')->canSend())->toBeFalse();
});

test('STOP consent still blocks every destination while temporary failure warnings may invite retry', function () {
    signalsBindSmsTransport();
    $optedOut = Customer::query()->create([
        'first_name' => 'Stop',
        'last_name' => 'Customer',
        'phone' => '7195553021',
        'sms_consent_status' => CustomerSmsConsentStatus::OptedOut,
    ]);
    signalsCapability('7195553022', true);

    $temporary = Customer::query()->create([
        'first_name' => 'Temp',
        'last_name' => 'Fail',
        'phone' => '7195553023',
        'sms_consent_status' => CustomerSmsConsentStatus::Subscribed,
        'last_sms_delivery_status' => 'undelivered',
        'last_sms_error_code' => '30003',
        'last_sms_failed_at' => now(),
    ]);
    $permanent = Customer::query()->create([
        'first_name' => 'Perm',
        'last_name' => 'Fail',
        'phone' => '7195553024',
        'sms_consent_status' => CustomerSmsConsentStatus::Subscribed,
        'last_sms_delivery_status' => 'undelivered',
        'last_sms_error_code' => '21610',
        'last_sms_failed_at' => now(),
    ]);

    expect(CustomerSmsSendEligibility::for($optedOut, ShopIntegrationCredentials::forCurrentShop(), '7195553022')->canSend())->toBeFalse()
        ->and(CustomerSmsSendEligibility::for($temporary, ShopIntegrationCredentials::forCurrentShop())->deliveryWarning())->toContain('before texting again')
        ->and(CustomerSmsSendEligibility::for($permanent, ShopIntegrationCredentials::forCurrentShop())->deliveryWarning())->toContain('STOP')
        ->and(CustomerSmsSendEligibility::for($permanent, ShopIntegrationCredentials::forCurrentShop())->deliveryWarning())->not->toContain('before texting again');
});

test('workspace shows Texting unavailable for a landline destination conversation', function () {
    signalsBindSmsTransport();
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Pat',
        'last_name' => 'Land',
        'phone' => '7195553031',
        'email' => 'pat.land@example.com',
        'sms_consent_status' => CustomerSmsConsentStatus::Subscribed,
    ]);
    signalsCapability('7195553031', false, 'landline');

    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195553031',
        'status' => ConversationStatus::Open,
    ]);
    app(ConversationLinker::class)->link($conversation, $customer);
    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'conversation_participant_id' => ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'participant_type' => ConversationParticipantType::Customer,
            'customer_id' => $customer->id,
        ])->id,
        'channel' => OperationalCommunicationChannel::Sms,
        'direction' => OperationalCommunicationDirection::Inbound,
        'body' => 'Hello',
        'occurred_at' => now(),
    ]);

    $page = (string) test()->actingAs($advisor)
        ->get(route('operations.communications.inbox', ['filter' => 'all', 'conversation' => $conversation->id]))
        ->assertOk()
        ->getContent();

    expect($page)->toContain('data-comms-texting="unavailable"')
        ->and($page)->toContain('Texting unavailable')
        ->and($page)->not->toContain('data-comms-texting="allowed"');
});

test('delivery failure classification keeps STOP and landline permanent while temporary may retry', function () {
    expect(CommunicationsWorkspaceSignalPresenter::deliveryFailureAllowsRetry('21610'))->toBeFalse()
        ->and(CommunicationsWorkspaceSignalPresenter::deliveryFailureAllowsRetry('30006'))->toBeFalse()
        ->and(CommunicationsWorkspaceSignalPresenter::deliveryFailureAllowsRetry('30003'))->toBeTrue()
        ->and(CommunicationsWorkspaceSignalPresenter::deliveryFailureDetail('21610'))->toContain('STOP');
});

test('host signals can emit unavailable and Core presenter keeps standalone Core behavior when unbound', function () {
    expect(app(CommunicationsWorkspaceSignals::class))->toBeInstanceOf(CoreCommunicationsWorkspaceSignals::class);

    $presented = CommunicationsWorkspaceSignalPresenter::permission([
        'texting' => 'unavailable',
        'unavailable_reason' => 'service_disabled',
        'source' => 'Shop texting is not available',
    ]);

    expect($presented['texting'])->toBe('unavailable')
        ->and($presented['tone'])->toBe('warning');
});
