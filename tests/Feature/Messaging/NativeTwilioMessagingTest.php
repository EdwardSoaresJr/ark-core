<?php

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Customers\CustomerSmsConsentStatus;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Messaging\MessagingCredentialStore;
use App\Ark\Operations\Messaging\MessagingWebhookReceipt;
use App\Ark\Operations\Messaging\OutboundSmsTransport;
use App\Ark\Operations\Messaging\TwilioRequestSignature;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function nativeMessagingAccount(): void
{
    app(MessagingCredentialStore::class)->save('ACshop', 'token-shop', '+17195550100');
}

test('native transport sends through twilio and retries the same key once', function () {
    nativeMessagingAccount();
    Http::fake([
        'https://api.twilio.com/*' => Http::response(['sid' => 'SMnative1', 'status' => 'queued'], 201),
    ]);

    $context = [['type' => 'idempotency_key', 'id' => 'send-1']];
    $first = app(OutboundSmsTransport::class)->send('+17195550199', 'Hello', ['https://files.example.test/a.jpg'], $context);
    $second = app(OutboundSmsTransport::class)->send('+17195550199', 'Hello', ['https://files.example.test/a.jpg'], $context);

    expect($first->messageId)->toBe('SMnative1')
        ->and($second->messageId)->toBe('SMnative1');

    Http::assertSentCount(1);
    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'https://api.twilio.com/2010-04-01/Accounts/ACshop/Messages.json')
            && ! str_contains($request->url(), 'cloud.arksms.com')
            && str_contains($request->body(), 'MediaUrl=');
    });

    $stored = DB::table('messaging_provider_credentials')->value('auth_token');
    expect($stored)->not->toBe('token-shop');
});

test('missing credentials and a legacy platform host fail before any request', function () {
    Http::fake();

    expect(fn () => app(OutboundSmsTransport::class)->send('+17195550199', 'Hello'))
        ->toThrow(RuntimeException::class, 'Outbound SMS is not configured.');

    expect(fn () => app(MessagingCredentialStore::class)->save(
        'AClegacy',
        'token-legacy',
        '+17195550100',
        'https://cloud.arksms.com',
    ))->toThrow(RuntimeException::class, 'Outbound SMS does not use the legacy platform.');

    Http::assertNothingSent();
});

test('inbound sms and mms land on the customer conversation and a replay does not duplicate', function () {
    nativeMessagingAccount();
    Customer::query()->create([
        'first_name' => 'Molly',
        'last_name' => 'Customer',
        'phone' => '7195550199',
        'customer_type' => 'Retail',
    ]);

    $url = route('webhooks.twilio.messaging.inbound');
    $params = [
        'MessageSid' => 'SMinbound1',
        'From' => '+17195550199',
        'To' => '+17195550100',
        'Body' => 'The photo is attached',
        'NumMedia' => '1',
        'MediaUrl0' => 'https://api.twilio.com/2010-04-01/Accounts/ACshop/Messages/SMinbound1/Media/ME1',
        'MediaContentType0' => 'image/jpeg',
    ];

    $headers = ['X-Twilio-Signature' => TwilioRequestSignature::sign($url, $params, 'token-shop')];
    $this->post($url, $params, $headers)->assertNoContent();
    $this->post($url, $params, $headers)->assertNoContent();

    $message = ConversationMessage::query()->where('metadata->twilio_message_sid', 'SMinbound1')->first();
    expect($message)->not->toBeNull()
        ->and(ConversationMessage::query()->count())->toBe(1)
        ->and($message->body)->toBe('The photo is attached')
        ->and($message->attachments()->count())->toBe(1)
        ->and($message->attachments()->first()->provider_url)->toContain('api.twilio.com')
        ->and(MessagingWebhookReceipt::query()->count())->toBe(1);
});

test('inbound messaging rejects a bad signature, another shops number, and foreign media', function () {
    nativeMessagingAccount();
    $url = route('webhooks.twilio.messaging.inbound');
    $params = [
        'MessageSid' => 'SMinbound2',
        'From' => '+17195550199',
        'To' => '+17195550100',
        'Body' => 'Hello',
        'NumMedia' => '0',
    ];

    $this->post($url, $params, ['X-Twilio-Signature' => 'not-a-signature'])->assertUnauthorized();

    $otherShop = $params;
    $otherShop['To'] = '+17195550999';
    $this->post($url, $otherShop, [
        'X-Twilio-Signature' => TwilioRequestSignature::sign($url, $otherShop, 'token-shop'),
    ])->assertForbidden();

    $foreign = $params;
    $foreign['MessageSid'] = 'SMinbound3';
    $foreign['NumMedia'] = '1';
    $foreign['MediaUrl0'] = 'https://files.example.test/secret.jpg';
    $this->post($url, $foreign, [
        'X-Twilio-Signature' => TwilioRequestSignature::sign($url, $foreign, 'token-shop'),
    ])->assertStatus(400);

    expect(ConversationMessage::query()->count())->toBe(0);
});

test('an inbound stop opts the customer out and confirms in twiml', function () {
    nativeMessagingAccount();
    $customer = Customer::query()->create([
        'first_name' => 'Molly',
        'last_name' => 'Customer',
        'phone' => '7195550199',
        'customer_type' => 'Retail',
    ]);
    $url = route('webhooks.twilio.messaging.inbound');
    $params = [
        'MessageSid' => 'SMstop1',
        'From' => '+17195550199',
        'To' => '+17195550100',
        'Body' => 'STOP',
        'NumMedia' => '0',
    ];

    $this->post($url, $params, [
        'X-Twilio-Signature' => TwilioRequestSignature::sign($url, $params, 'token-shop'),
    ])->assertOk()->assertSee('<Message>', false);

    expect($customer->fresh()->sms_consent_status)->toBe(CustomerSmsConsentStatus::OptedOut);
});

test('delivery callbacks update the customer once and ignore a replay', function () {
    nativeMessagingAccount();
    $customer = Customer::query()->create([
        'first_name' => 'Molly',
        'last_name' => 'Customer',
        'phone' => '7195550199',
        'customer_type' => 'Retail',
    ]);
    app(ConversationRecorder::class)->recordOutboundSms(
        $customer,
        User::factory()->create(),
        'Hello',
        'SMstatus1',
    );

    $url = route('webhooks.twilio.messaging.status');
    $params = [
        'MessageSid' => 'SMstatus1',
        'MessageStatus' => 'delivered',
        'From' => '+17195550100',
        'To' => '+17195550199',
    ];

    $headers = ['X-Twilio-Signature' => TwilioRequestSignature::sign($url, $params, 'token-shop')];
    $this->post($url, $params, $headers)->assertNoContent();
    expect($customer->fresh()->last_sms_delivery_status)->toBe('delivered');

    $customer->forceFill(['last_sms_delivery_status' => 'queued'])->save();
    $this->post($url, $params, $headers)->assertNoContent();
    expect($customer->fresh()->last_sms_delivery_status)->toBe('queued');
});
