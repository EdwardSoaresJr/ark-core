<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\Payments\CustomerDocumentAccessToken;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Portal\PortalShortLink;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

function connectPaymentPortal(): void
{
    config()->set('services.ark_platform.payments_capture', true);
    config()->set('services.ark_platform.communications_authority', true);
    config()->set('services.ark_platform.communications_send', true);
    config()->set('services.ark_platform.communications_core_mirror', false);
    config()->set('services.ark_platform.mail_send', true);

    InstallationIdentity::write((string) Str::uuid());
    ShopSettings::current()->persistTrusted([
        'shop_name' => 'Payment Link Shop',
        'platform_status' => 'connected',
        'platform_credential' => 'test-platform-payments-credential',
        'platform_base_url' => 'https://cloud.test',
        'platform_shop_public_id' => (string) Str::uuid(),
        'telephony_inbound_number' => '7195559999',
        'ark_mail_from_email' => 'shop@example.test',
        'ark_mail_status' => 'connected',
    ]);
}

/**
 * @param  list<array<string, mixed>>|null  $smsPosts
 */
function fakePaymentPortalHttp(bool $portalReady = true, ?array &$smsPosts = null): void
{
    Http::fake(function (Request $request) use ($portalReady, &$smsPosts) {
        if (str_contains($request->url(), '/api/v1/services/payments/')) {
            return Http::response(platformPaymentReadinessPayload([
                'supports_portal' => $portalReady,
            ]), 200);
        }

        if (str_contains($request->url(), '/sms/messages/conversation')) {
            if ($smsPosts !== null) {
                $smsPosts[] = $request->data();
            }

            return Http::response([
                'ok' => true,
                'message_id' => 'plat-msg-pay',
                'provider_message_id' => 'SMplatform01',
                'status' => 'queued',
            ], 200);
        }

        if (str_contains($request->url(), '/mail/messages/transactional')) {
            return Http::response([
                'ok' => true,
                'status' => 'provider_sent',
                'message_id' => 'mail-test-1',
            ], 200);
        }

        return Http::response(['ok' => true], 200);
    });
}

function seedPaymentSmsCapablePhone(string $phone = '7195558080'): void
{
    $normalized = PhoneNumber::normalize($phone) ?? $phone;

    PhoneSmsCapability::query()->updateOrCreate(
        ['normalized_phone' => $normalized],
        [
            'valid' => true,
            'line_type' => 'mobile',
            'carrier_name' => 'Test Carrier',
            'sms_capable' => true,
            'reason' => null,
            'checked_at' => now(),
            'raw_payload' => ['source' => 'test'],
        ],
    );
}

function paymentLinkAdvisor(): User
{
    return User::factory()->create()->assignRole(ArkRole::Advisor->value);
}

test('send payment link creates a pay token and sends through platform', function () {
    connectPaymentPortal();
    $smsPosts = [];
    fakePaymentPortalHttp(smsPosts: $smsPosts);

    $advisor = paymentLinkAdvisor();
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['phone' => '7195558080'])->save();
    seedPaymentSmsCapablePhone('7195558080');
    issueFinalInvoiceFor($repairOrder);

    $response = $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-payment', $repairOrder));

    $response->assertOk()
        ->assertJsonPath('message_id', null);

    expect($response->json('payment_url'))->toContain('/portal/pay/');

    $token = CustomerDocumentAccessToken::query()->sole();
    $paymentReference = collect($smsPosts[0]['context'] ?? [])->firstWhere('type', 'payment_request');

    expect($smsPosts)->toHaveCount(1)
        ->and($smsPosts[0]['body'] ?? '')->toContain('/go/')
        ->and($smsPosts[0]['body'] ?? '')->not->toContain('/portal/pay/')
        ->and($smsPosts[0]['body'] ?? '')->toContain('Balance due')
        ->and($paymentReference['id'] ?? null)->toBe((string) $token->id)
        ->and($token->repair_order_id)->toBe($repairOrder->id)
        ->and($token->scope)->toBe(CustomerDocumentAccessToken::SCOPE_PAY_INVOICE)
        ->and(ConversationMessage::query()->count())->toBe(0)
        ->and(CommunicationEvent::query()->where('event_type', OperationalCommunicationType::InvoiceSent)->exists())->toBeTrue();
});

test('resending payment link reuses the same short url', function () {
    connectPaymentPortal();
    $smsPosts = [];
    fakePaymentPortalHttp(smsPosts: $smsPosts);

    $advisor = paymentLinkAdvisor();
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['phone' => '7195558080'])->save();
    seedPaymentSmsCapablePhone('7195558080');
    issueFinalInvoiceFor($repairOrder);

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-payment', $repairOrder))
        ->assertOk();

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-payment', $repairOrder))
        ->assertOk();

    $firstGo = (string) str($smsPosts[0]['body'] ?? '')->after('/go/');
    $secondGo = (string) str($smsPosts[1]['body'] ?? '')->after('/go/');

    expect($smsPosts)->toHaveCount(2)
        ->and($secondGo)->toBe($firstGo)
        ->and($firstGo)->not->toBe('')
        ->and(PortalShortLink::query()->count())->toBe(1)
        ->and(CustomerDocumentAccessToken::query()->count())->toBe(2)
        ->and(ConversationMessage::query()->count())->toBe(0);
});

test('send payment link requires issued invoice and balance due', function () {
    connectPaymentPortal();
    fakePaymentPortalHttp();

    $advisor = paymentLinkAdvisor();
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['phone' => '7195558080'])->save();
    seedPaymentSmsCapablePhone('7195558080');

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-payment', $repairOrder))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Generate the final invoice before sending a payment link.');

    issueFinalInvoiceFor($repairOrder);
    payRepairOrderInFull($repairOrder);

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-payment', $repairOrder->fresh()))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This repair order has no balance due.');
});

test('send payment link requires portal pay enabled', function () {
    connectPaymentPortal();
    fakePaymentPortalHttp(portalReady: false);

    $advisor = paymentLinkAdvisor();
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['phone' => '7195558080'])->save();
    seedPaymentSmsCapablePhone('7195558080');
    issueFinalInvoiceFor($repairOrder);

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-payment', $repairOrder))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Customer portal payments are not enabled.');

    expect(CustomerDocumentAccessToken::query()->count())->toBe(0);
});

test('ro review shows send payment link action when portal pay enabled', function () {
    connectPaymentPortal();
    fakePaymentPortalHttp();

    $advisor = paymentLinkAdvisor();
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['phone' => '7195558080'])->save();
    seedPaymentSmsCapablePhone('7195558080');
    issueFinalInvoiceFor($repairOrder);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.workspace-tabs.show', [
            'repairOrder' => $repairOrder,
            'tab' => 'comms',
        ]))
        ->assertOk()
        ->assertSee('Send Pay Link')
        ->assertSee('Send Estimate');
});

test('send payment link via sms ignores invalid customer email on file', function () {
    connectPaymentPortal();
    $smsPosts = [];
    fakePaymentPortalHttp(smsPosts: $smsPosts);

    $advisor = paymentLinkAdvisor();
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill([
        'phone' => '7195558080',
        'email' => 'not-an-email',
    ])->save();
    seedPaymentSmsCapablePhone('7195558080');
    issueFinalInvoiceFor($repairOrder);

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-payment', $repairOrder), [
            'delivery' => 'sms',
            'email' => 'not-an-email',
        ])
        ->assertOk()
        ->assertJsonPath('message_id', null);

    $mailPosts = collect(Http::recorded())
        ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/mail/messages/transactional'));

    expect($smsPosts)->toHaveCount(1)
        ->and($mailPosts)->toHaveCount(0)
        ->and(ConversationMessage::query()->count())->toBe(0);
});

test('send payment link via email creates conversation message', function () {
    connectPaymentPortal();
    fakePaymentPortalHttp();

    $advisor = paymentLinkAdvisor();
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill([
        'phone' => '7195558080',
        'email' => 'customer@example.test',
    ])->save();
    issueFinalInvoiceFor($repairOrder);

    $response = $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-payment', $repairOrder), [
            'delivery' => 'email',
        ]);

    $response->assertOk()
        ->assertJsonStructure(['deliveries', 'html', 'message_id']);

    assertPlatformTransactionalMail('payment_link.send', 'customer@example.test', '/portal/pay/');

    $message = ConversationMessage::query()->sole();

    $smsPosts = collect(Http::recorded())
        ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/sms/messages/conversation'));

    expect($message->channel)->toBe(OperationalCommunicationChannel::Email)
        ->and($message->body)->toContain('Payment link emailed')
        ->and($smsPosts)->toHaveCount(0);
});

test('hosted payment link email uses platform mail', function () {
    connectPaymentPortal();
    fakePaymentPortalHttp();
    Mail::fake();

    $advisor = paymentLinkAdvisor();
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill([
        'phone' => '7195558080',
        'email' => 'customer@example.test',
    ])->save();
    issueFinalInvoiceFor($repairOrder);

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-payment', $repairOrder), [
            'delivery' => 'email',
        ])
        ->assertOk();

    Mail::assertNothingSent();
    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->url() === 'https://cloud.test/api/v1/services/mail/messages/transactional'
            && ($body['operation'] ?? null) === 'payment_link.send'
            && ($body['to'] ?? null) === 'customer@example.test'
            && filled($body['variables']['pay_url'] ?? null)
            && ! array_key_exists('postmark_server_token', $body);
    });

    expect(ConversationMessage::query()->sole()->channel)->toBe(OperationalCommunicationChannel::Email);
});

test('staff payment portal preview shows customer page without enabling card entry', function () {
    connectPaymentPortal();
    fakePaymentPortalHttp();

    $advisor = paymentLinkAdvisor();
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.payment-portal-preview', $repairOrder))
        ->assertOk()
        ->assertSee('Staff preview')
        ->assertSee('Pay your invoice')
        ->assertSee('Balance due')
        ->assertDontSee('x-ref="cardMount"', false);
});

test('staff payment preview token does not invalidate existing customer pay link', function () {
    connectPaymentPortal();
    fakePaymentPortalHttp();

    $advisor = paymentLinkAdvisor();
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['phone' => '7195558080'])->save();
    seedPaymentSmsCapablePhone('7195558080');
    issueFinalInvoiceFor($repairOrder);

    $sendResponse = $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-payment', $repairOrder))
        ->assertOk();

    $customerPayUrl = (string) $sendResponse->json('payment_url');
    $customerPlainToken = str($customerPayUrl)->afterLast('/')->toString();

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.payment-portal-preview', $repairOrder))
        ->assertOk()
        ->assertSee('Staff preview');

    expect(CustomerDocumentAccessToken::query()->count())->toBe(2);

    $this->get(route('portal.invoice-pay.show', ['token' => $customerPlainToken]))
        ->assertOk()
        ->assertSee('Pay your invoice');
});

test('payment portal link json returns customer url', function () {
    connectPaymentPortal();
    fakePaymentPortalHttp();

    $advisor = paymentLinkAdvisor();
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $response = $this->actingAs($advisor)
        ->getJson(route('operations.repair-orders.payment-portal-link', $repairOrder));

    $response->assertOk()
        ->assertJsonStructure(['url', 'balance_due_display']);

    expect($response->json('url'))->toContain('/portal/pay/');
});

test('ro review hides send payment link when portal pay is not ready', function () {
    connectPaymentPortal();
    fakePaymentPortalHttp(portalReady: false);

    $advisor = paymentLinkAdvisor();
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['phone' => '7195558080'])->save();
    seedPaymentSmsCapablePhone('7195558080');
    issueFinalInvoiceFor($repairOrder);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.workspace-tabs.show', [
            'repairOrder' => $repairOrder,
            'tab' => 'comms',
        ]))
        ->assertOk()
        ->assertDontSee('Send Pay Link');
});

test('opening a repair order does not create or send a payment link', function () {
    connectPaymentPortal();
    $smsPosts = [];
    fakePaymentPortalHttp(smsPosts: $smsPosts);

    $advisor = paymentLinkAdvisor();
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    expect(CustomerDocumentAccessToken::query()->count())->toBe(0);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk();

    expect(CustomerDocumentAccessToken::query()->count())->toBe(0)
        ->and($smsPosts)->toHaveCount(0)
        ->and(ConversationMessage::query()->count())->toBe(0);
});
