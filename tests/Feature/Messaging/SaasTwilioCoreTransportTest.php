<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSmsConsentStatus;
use App\Ark\Operations\Messaging\NotConfiguredOutboundSmsTransport;
use App\Ark\Operations\Messaging\OutboundSmsTransport;
use App\Models\User;
use Ark\Saas\Capability;
use Ark\Saas\Entitlement\Entitlement;
use Ark\Saas\Hosted\LocalShop;
use Ark\Saas\Provider\ProviderMode;
use Ark\Saas\SaasServiceProvider;
use Ark\Saas\ShopRef;
use Ark\Saas\Texting\HostedShopSmsTransport;
use Ark\Saas\Texting\ProviderCallbackSignature;
use Ark\Saas\Texting\TextAccounts;
use Ark\Saas\Texting\TextCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

beforeEach(function () {
    $saas = dirname(base_path()).'/ark-saas/saas';
    if (! is_file($saas.'/src/SaasServiceProvider.php')) {
        $this->markTestSkipped('ARK SaaS is not beside this checkout.');
    }

    spl_autoload_register(static function (string $class) use ($saas): void {
        $prefix = 'Ark\\Saas\\';
        if (! str_starts_with($class, $prefix)) {
            return;
        }
        $path = $saas.'/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        if (is_file($path)) {
            require $path;
        }
    });

    config(['saas.texting.delivery' => 'provider']);
    $this->app->register(SaasServiceProvider::class);
    if (! Route::has('webhooks.twilio.messaging.inbound')) {
        require $saas.'/routes/web.php';
        app('router')->getRoutes()->refreshNameLookups();
        app('router')->getRoutes()->refreshActionLookups();
    }
    $this->artisan('migrate', [
        '--path' => $saas.'/database/migrations',
        '--realpath' => true,
        '--force' => true,
    ]);
    InstallationIdentity::write((string) Str::uuid());
    app(Entitlement::class)->grant(LocalShop::ref(), Capability::Texting);
});

function saasShopTwilio(string $account = 'ACthis', string $token = 'token-this', string $from = '+17195550111'): void
{
    app(TextAccounts::class)->saveByo(LocalShop::ref(), new TextCredential(
        'https://api.twilio.com',
        $account,
        $token,
        $from,
    ));
}

test('saas byo credentials are what core sends, and another shop is not used', function () {
    app(TextAccounts::class)->saveByo(new ShopRef('other-shop'), new TextCredential(
        'https://api.twilio.com',
        'ACother',
        'token-other',
        '+17195550999',
    ));
    saasShopTwilio();

    Http::fake([
        'https://api.twilio.com/*' => Http::response(['sid' => 'SMsaas1', 'status' => 'queued'], 201),
    ]);

    $transport = app(OutboundSmsTransport::class);
    expect($transport)->toBeInstanceOf(HostedShopSmsTransport::class)
        ->and($transport)->not->toBeInstanceOf(NotConfiguredOutboundSmsTransport::class);

    $result = $transport->send('+17195550199', 'From this shop', ['https://files.example.test/a.jpg'], [
        ['type' => 'idempotency_key', 'id' => 'saas-send-1'],
    ]);
    $again = $transport->send('+17195550199', 'From this shop', ['https://files.example.test/a.jpg'], [
        ['type' => 'idempotency_key', 'id' => 'saas-send-1'],
    ]);

    expect($result->messageId)->toBe('SMsaas1')
        ->and($again->messageId)->toBe('SMsaas1');
    Http::assertSentCount(1);
    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), '/Accounts/ACthis/Messages.json')
            && str_contains($request->body(), 'MediaUrl=')
            && ! str_contains($request->url(), 'ACother')
            && ! str_contains($request->url(), 'cloud.arksms.com');
    });
});

test('disabled mode and a missing entitlement block core before any request', function () {
    saasShopTwilio();
    app(TextAccounts::class)->selectMode(LocalShop::ref(), ProviderMode::Disabled);
    Http::fake();

    expect(fn () => app(OutboundSmsTransport::class)->send('+17195550199', 'Nope'))
        ->toThrow(RuntimeException::class, 'Outbound SMS is not configured.');

    app(TextAccounts::class)->saveByo(LocalShop::ref(), new TextCredential(
        'https://api.twilio.com',
        'ACthis',
        'token-this',
        '+17195550111',
    ));
    app(Entitlement::class)->revoke(LocalShop::ref(), Capability::Texting);

    expect(fn () => app(OutboundSmsTransport::class)->send('+17195550199', 'Nope'))
        ->toThrow(RuntimeException::class, 'Outbound SMS is not configured.');

    Http::assertNothingSent();
});

test('inbound sms and mms land on the core conversation and a replay does not duplicate', function () {
    saasShopTwilio('ACshop', 'token-shop', '+17195550100');
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
    $headers = ['X-Twilio-Signature' => signature($url, $params)];

    expect(Route::has('webhooks.twilio.messaging.inbound'))->toBeTrue();
    $this->post($url, $params, $headers)->assertNoContent();
    $this->post($url, $params, $headers)->assertNoContent();

    $message = ConversationMessage::query()->where('metadata->twilio_message_sid', 'SMinbound1')->first();
    expect($message)->not->toBeNull()
        ->and(ConversationMessage::query()->count())->toBe(1)
        ->and($message->attachments()->count())->toBe(1)
        ->and($message->attachments()->first()->provider_url)->toContain('api.twilio.com');
});

test('inbound messaging rejects a bad signature, another shops number, and foreign media', function () {
    saasShopTwilio('ACshop', 'token-shop', '+17195550100');
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
        'X-Twilio-Signature' => signature($url, $otherShop),
    ])->assertForbidden();

    $foreign = $params;
    $foreign['MessageSid'] = 'SMinbound3';
    $foreign['NumMedia'] = '1';
    $foreign['MediaUrl0'] = 'https://files.example.test/secret.jpg';
    $this->post($url, $foreign, [
        'X-Twilio-Signature' => signature($url, $foreign),
    ])->assertStatus(400);

    expect(ConversationMessage::query()->count())->toBe(0);
});

test('an inbound stop opts the customer out through core consent', function () {
    saasShopTwilio('ACshop', 'token-shop', '+17195550100');
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
        'X-Twilio-Signature' => signature($url, $params),
    ])->assertOk()->assertSee('<Message>', false);

    expect($customer->fresh()->sms_consent_status)->toBe(CustomerSmsConsentStatus::OptedOut);
});

test('delivery callbacks update the core customer once and ignore a replay', function () {
    saasShopTwilio('ACshop', 'token-shop', '+17195550100');
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
    $headers = ['X-Twilio-Signature' => signature($url, $params)];

    $this->post($url, $params, $headers)->assertNoContent();
    expect($customer->fresh()->last_sms_delivery_status)->toBe('delivered');

    $customer->forceFill(['last_sms_delivery_status' => 'queued'])->save();
    $this->post($url, $params, $headers)->assertNoContent();
    expect($customer->fresh()->last_sms_delivery_status)->toBe('queued');
});

function signature(string $url, array $params): string
{
    $flat = [];
    foreach ($params as $key => $value) {
        $flat[$key] = (string) $value;
    }
    ksort($flat);
    $data = $url;
    foreach ($flat as $key => $value) {
        $data .= $key.$value;
    }

    $signed = base64_encode(hash_hmac('sha1', $data, 'token-shop', true));
    expect(ProviderCallbackSignature::verifies($url, $params, 'token-shop', $signed))->toBeTrue();

    return $signed;
}
