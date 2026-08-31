<?php

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\Messaging\RepairOrderConversationSendProjection;
use App\Ark\Operations\Payments\Contracts\SquarePaymentsClient;
use App\Ark\Operations\Payments\CreateCustomerDepositPayTokenAction;
use App\Ark\Operations\Payments\CreateCustomerPayTokenAction;
use App\Ark\Operations\Payments\CustomerDocumentAccessToken;
use App\Ark\Operations\Payments\FakeSquarePaymentsClient;
use App\Ark\Operations\Payments\PaymentCaptureSurface;
use App\Ark\Operations\Payments\PaymentGatewayAttemptStatus;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Mail\DepositRequestCustomerMail;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
            config()->set('mail.default', 'array');
    config()->set('services.square.application_id', 'sq0idp-test-app');
    config()->set('services.square.access_token', 'test-token');
    config()->set('services.square.location_id', 'LOC123');

    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
        'square_enabled' => true,
        'square_portal_pay_enabled' => true,
        'square_keyed_enabled' => true,
    ]);

    $this->fakeSquare = new FakeSquarePaymentsClient;
    $this->app->instance(FakeSquarePaymentsClient::class, $this->fakeSquare);
    $this->app->bind(SquarePaymentsClient::class, fn () => $this->fakeSquare);
});

function seedSmsCapablePhone(string $phone = '7195558080'): void
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

function fakeDepositOutboundHttp(string $messageSid = 'SMdeposit01'): void
{
    Http::fake([
        'lookups.twilio.com/*' => Http::response([
            'calling_country_code' => '1',
            'country_code' => 'US',
            'phone_number' => '+17195558080',
            'national_format' => '(719) 555-8080',
            'valid' => true,
            'validation_errors' => null,
            'line_type_intelligence' => [
                'error_code' => null,
                'mobile_country_code' => '310',
                'mobile_network_code' => '260',
                'carrier_name' => 'T-Mobile USA',
                'type' => 'mobile',
            ],
        ], 200),
        'https://api.twilio.com/*' => Http::response([
            'sid' => $messageSid,
            'status' => 'queued',
        ], 201),
    ]);
    bindFakeOutboundSms();
}

test('send deposit request creates pay_deposit token with amount and sends sms', function () {
    fakeDepositOutboundHttp();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['phone' => '7195558080'])->save();
    seedSmsCapablePhone('7195558080');

    $response = $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-deposit', $repairOrder), [
            'amount' => 50,
            'delivery' => 'sms',
        ]);

    $response->assertOk()
        ->assertJsonStructure(['deposit_url', 'amount_display', 'html', 'message_id']);

    expect($response->json('deposit_url'))->toContain('/portal/pay/')
        ->and($response->json('amount_display'))->toContain('50');

    $token = CustomerDocumentAccessToken::query()->sole();
    $message = ConversationMessage::query()->sole();

    expect($token->repair_order_id)->toBe($repairOrder->id)
        ->and($token->scope)->toBe(CustomerDocumentAccessToken::SCOPE_PAY_DEPOSIT)
        ->and($token->amount_cents)->toBe(5000)
        ->and($token->financial_document_id)->toBeNull()
        ->and($message->channel)->toBe(OperationalCommunicationChannel::Sms)
        ->and($message->direction)->toBe(OperationalCommunicationDirection::Outbound)
        ->and($message->body)->toContain('/go/')
        ->and($message->body)->toContain('Deposit requested')
        ->and($message->body)->not->toContain('/portal/pay/')
        ->and($message->participant->participant_type)->toBe(ConversationParticipantType::Advisor)
        ->and($message->metadata['repair_order_id'])->toBe($repairOrder->id);
});

test('send deposit request via email creates conversation message', function () {
    Mail::fake();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill([
        'phone' => '7195558080',
        'email' => 'customer@example.test',
    ])->save();

    $response = $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-deposit', $repairOrder), [
            'amount' => 100.50,
            'delivery' => 'email',
        ]);

    $response->assertOk()
        ->assertJsonStructure(['deliveries', 'html', 'message_id']);

    Mail::assertSent(DepositRequestCustomerMail::class, function (DepositRequestCustomerMail $mail) use ($repairOrder): bool {
        return $mail->hasTo('customer@example.test')
            && $mail->repairOrder->is($repairOrder)
            && str_contains($mail->portalUrl, '/portal/pay/')
            && str_contains($mail->amountDisplay, '100.50');
    });

    $message = ConversationMessage::query()->sole();

    expect($message->channel)->toBe(OperationalCommunicationChannel::Email)
        ->and($message->body)->toContain('Deposit request emailed');
});

test('send deposit request rejects zero amount issued invoice and missing portal pay', function () {
    Http::fake();
    seedSmsCapablePhone('7195558080');
    seedSmsCapablePhone('7195558081');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['phone' => '7195558080'])->save();

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-deposit', $repairOrder), [
            'amount' => 0,
            'delivery' => 'sms',
        ])
        ->assertUnprocessable();

    issueFinalInvoiceFor($repairOrder);

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-deposit', $repairOrder->fresh()), [
            'amount' => 50,
            'delivery' => 'sms',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Use Send Pay Link after the final invoice is issued.');

    $openRo = financialCloseoutRepairOrder();
    $openRo->customer->forceFill(['phone' => '7195558081'])->save();
    ShopSettings::current()->update(['square_portal_pay_enabled' => false]);

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-deposit', $openRo), [
            'amount' => 50,
            'delivery' => 'sms',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Customer portal payments are not enabled.');
});

test('portal deposit pay link completes as ledger deposit for token amount', function () {
    $repairOrder = financialCloseoutRepairOrder();
    $token = app(CreateCustomerDepositPayTokenAction::class)
        ->execute($repairOrder, 25000);

    $this->get(route('portal.invoice-pay.show', ['token' => $token->plainToken]))
        ->assertOk()
        ->assertSee('Pay your deposit')
        ->assertSee('Deposit requested');

    $initiate = $this->postJson(route('portal.invoice-pay.attempts.store', ['token' => $token->plainToken]))
        ->assertOk()
        ->assertJsonPath('attempt.capture_surface', PaymentCaptureSurface::PortalDepositRequest->value)
        ->assertJsonPath('attempt.amount_cents', 25000);

    $attemptId = $initiate->json('attempt.id');

    $this->postJson(route('portal.invoice-pay.attempts.complete', [
        'token' => $token->plainToken,
        'attempt' => $attemptId,
    ]), [
        'source_id' => 'cnon:deposit-token',
    ])->assertOk()
        ->assertJsonPath('attempt.status', PaymentGatewayAttemptStatus::Completed->value);

    $ledger = RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('entry_type', LedgerEntryType::Deposit)
        ->sole();

    expect($ledger->amount_cents)->toBe(25000);
});

test('invoice pay link still charges balance due after deposit feature', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $token = app(CreateCustomerPayTokenAction::class)
        ->execute($repairOrder->fresh(), $repairOrder->fresh()->estimateDocuments()->latest('id')->first());

    $initiate = $this->postJson(route('portal.invoice-pay.attempts.store', ['token' => $token->plainToken]))
        ->assertOk()
        ->assertJsonPath('attempt.capture_surface', PaymentCaptureSurface::Portal->value);

    $attemptId = $initiate->json('attempt.id');

    $this->postJson(route('portal.invoice-pay.attempts.complete', [
        'token' => $token->plainToken,
        'attempt' => $attemptId,
    ]), [
        'source_id' => 'cnon:invoice-token',
    ])->assertOk()
        ->assertJsonPath('attempt.status', PaymentGatewayAttemptStatus::Completed->value);

    expect($repairOrder->fresh()->isPaid())->toBeTrue();
});

test('deposit send projection allows open ro without invoice and blocks after invoice', function () {
    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::Estimate);
    seedSmsCapablePhone((string) $repairOrder->customer->phone);
    $projection = app(RepairOrderConversationSendProjection::class)
        ->forRepairOrder($repairOrder, actingAsLearnCurrentAdvisor());

    expect($projection['deposit']['send_block_reason'])->toBeNull()
        ->and($projection['deposit']['can_sms'])->toBeTrue()
        ->and($projection['payment']['send_block_reason'])->toBe('Generate the final invoice before sending a payment link.');

    $invoiced = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($invoiced);
    $invoiced->customer->forceFill(['phone' => '7195559090'])->save();
    seedSmsCapablePhone('7195559090');

    $afterInvoice = app(RepairOrderConversationSendProjection::class)
        ->forRepairOrder($invoiced->fresh(), actingAsLearnCurrentAdvisor())['deposit'];

    expect($afterInvoice['send_block_reason'])->toBe('Use Send Pay Link after the final invoice is issued.');
});

test('ro review shows send deposit when portal pay enabled and no invoice', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['phone' => '7195558080'])->save();
    seedSmsCapablePhone('7195558080');

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.workspace-tabs.show', [
            'repairOrder' => $repairOrder,
            'tab' => 'comms',
        ]))
        ->assertOk()
        ->assertSee('Send Deposit', false)
        ->assertSee('Send Pay Link', false);
});
