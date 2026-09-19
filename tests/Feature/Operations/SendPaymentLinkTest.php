<?php

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\Payments\CustomerDocumentAccessToken;
use App\Ark\Operations\Portal\PortalShortLink;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Mail\InvoicePaymentCustomerMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('services.twilio.auth_token', 'test-token');
    config()->set('services.twilio.account_sid', 'ACtestaccount');
    config()->set('services.postmark.token', 'pm-token');
    config()->set('services.square.application_id', 'sq0idp-test-app');
    config()->set('services.square.access_token', 'test-token');
    config()->set('services.square.location_id', 'LOC123');

    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
        'square_enabled' => true,
        'square_portal_pay_enabled' => true,
    ]);
});

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

test('send payment link creates access token and sends sms conversation message', function () {
    Http::fake([
        'https://api.twilio.com/*' => Http::response([
            'sid' => 'SMpayment01',
            'status' => 'queued',
        ], 201),
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['phone' => '7195558080'])->save();
    seedPaymentSmsCapablePhone('7195558080');
    issueFinalInvoiceFor($repairOrder);

    $response = $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-payment', $repairOrder));

    $response->assertOk()
        ->assertJsonStructure(['payment_url', 'balance_due_display', 'html', 'message_id']);

    expect($response->json('payment_url'))->toContain('/portal/pay/');

    $token = CustomerDocumentAccessToken::query()->sole();
    $message = ConversationMessage::query()->sole();

    expect($token->repair_order_id)->toBe($repairOrder->id)
        ->and($token->scope)->toBe('pay_invoice')
        ->and($message->channel)->toBe(OperationalCommunicationChannel::Sms)
        ->and($message->direction)->toBe(OperationalCommunicationDirection::Outbound)
        ->and($message->body)->toContain('/go/')
        ->and($message->body)->not->toContain('/portal/pay/')
        ->and($message->body)->toContain('Balance due')
        ->and(strlen($message->body))->toBeLessThan(120)
        ->and($message->participant->participant_type)->toBe(ConversationParticipantType::Advisor)
        ->and($message->metadata['repair_order_id'])->toBe($repairOrder->id)
        ->and(CommunicationEvent::query()->where('event_type', OperationalCommunicationType::InvoiceSent)->exists())->toBeTrue();
});

test('resending payment link reuses the same short url', function () {
    Http::fake([
        'https://api.twilio.com/*' => Http::response([
            'sid' => 'SMpayment-resend',
            'status' => 'queued',
        ], 201),
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['phone' => '7195558080'])->save();
    seedPaymentSmsCapablePhone('7195558080');
    issueFinalInvoiceFor($repairOrder);

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-payment', $repairOrder))
        ->assertOk();

    $firstGo = (string) str(ConversationMessage::query()->orderBy('id')->value('body'))->after('/go/');

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-payment', $repairOrder))
        ->assertOk();

    $secondGo = (string) str(ConversationMessage::query()->orderByDesc('id')->value('body'))->after('/go/');

    expect($secondGo)->toBe($firstGo)
        ->and($firstGo)->not->toBe('')
        ->and(PortalShortLink::query()->count())->toBe(1)
        ->and(CustomerDocumentAccessToken::query()->count())->toBe(2)
        ->and(ConversationMessage::query()->count())->toBe(2);
});

test('send payment link requires issued invoice and balance due', function () {
    Http::fake();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['phone' => '7195558080'])->save();

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
    Http::fake();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['phone' => '7195558080'])->save();
    issueFinalInvoiceFor($repairOrder);

    ShopSettings::current()->update(['square_portal_pay_enabled' => false]);

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-payment', $repairOrder))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Customer portal payments are not enabled.');
});

test('ro review shows send payment link action when portal pay enabled', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['phone' => '7195558080'])->save();
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
    Http::fake([
        'https://api.twilio.com/*' => Http::response([
            'sid' => 'SMpayment02',
            'status' => 'queued',
        ], 201),
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
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
        ->assertJsonStructure(['payment_url', 'balance_due_display', 'html', 'message_id']);
});

test('send payment link via email creates conversation message', function () {
    Mail::fake();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
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

    Mail::assertSent(InvoicePaymentCustomerMail::class, function (InvoicePaymentCustomerMail $mail) use ($repairOrder): bool {
        return $mail->hasTo('customer@example.test')
            && $mail->repairOrder->is($repairOrder)
            && str_contains($mail->portalUrl, '/portal/pay/');
    });

    $message = ConversationMessage::query()->sole();

    expect($message->channel)->toBe(OperationalCommunicationChannel::Email)
        ->and($message->body)->toContain('Payment link emailed');
});

test('hosted payment link email uses platform mail', function () {
    enableHostedPlatformMail();
    config()->set('services.ark_platform.payments_capture', false);
    fakeHostedPlatformMail();
    Mail::fake();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
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
    Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
        $body = $request->data();

        return $request->url() === 'https://cloud.test/api/v1/services/mail/messages/transactional'
            && ($body['operation'] ?? null) === 'payment_link.send'
            && ($body['to'] ?? null) === 'customer@example.test'
            && filled($body['html_body'] ?? null)
            && ! array_key_exists('postmark_server_token', $body);
    });

    expect(ConversationMessage::query()->sole()->channel)->toBe(OperationalCommunicationChannel::Email);
});

test('staff payment portal preview shows customer page without enabling card entry', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
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
    Http::fake([
        'https://api.twilio.com/*' => Http::response([
            'sid' => 'SMpayment03',
            'status' => 'queued',
        ], 201),
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
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
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $response = $this->actingAs($advisor)
        ->getJson(route('operations.repair-orders.payment-portal-link', $repairOrder));

    $response->assertOk()
        ->assertJsonStructure(['url', 'balance_due_display']);

    expect($response->json('url'))->toContain('/portal/pay/');
});

test('ro review hides send payment link when portal pay disabled', function () {
    ShopSettings::current()->update(['square_portal_pay_enabled' => false]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['phone' => '7195558080'])->save();
    issueFinalInvoiceFor($repairOrder);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.workspace-tabs.show', [
            'repairOrder' => $repairOrder,
            'tab' => 'comms',
        ]))
        ->assertOk()
        ->assertDontSee('Send Pay Link');
});

test('hosted platform payment send records invoice_sent without a core conversation message', function () {
    enableHostedPlatformSendWithoutCoreMirror();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['phone' => '7195558080'])->save();
    seedPaymentSmsCapablePhone('7195558080');
    issueFinalInvoiceFor($repairOrder);

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-payment', $repairOrder))
        ->assertOk()
        ->assertJsonPath('message_id', null);

    expect(ConversationMessage::query()->count())->toBe(0)
        ->and(CommunicationEvent::query()->where('event_type', OperationalCommunicationType::InvoiceSent)->exists())->toBeTrue();
});

