<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\Messaging\RepairOrderConversationSendProjection;
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

function connectDepositPortal(): void
{
    config()->set('services.ark_platform.payments_capture', true);
    config()->set('services.ark_platform.communications_authority', true);
    config()->set('services.ark_platform.communications_send', true);
    config()->set('services.ark_platform.communications_core_mirror', false);
    config()->set('services.ark_platform.mail_send', true);

    InstallationIdentity::write((string) Str::uuid());
    ShopSettings::current()->persistTrusted([
        'shop_name' => 'Deposit Request Shop',
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
function fakeDepositPortalHttp(bool $portalReady = true, ?array &$smsPosts = null): void
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
                'message_id' => 'plat-msg-dep',
                'provider_message_id' => 'SMplatformdep',
                'status' => 'queued',
            ], 200);
        }

        if (str_contains($request->url(), '/mail/messages/transactional')) {
            return Http::response([
                'ok' => true,
                'status' => 'provider_sent',
                'message_id' => 'mail-dep-1',
            ], 200);
        }

        return Http::response(['ok' => true], 200);
    });
}

function seedDepositSmsCapablePhone(string $phone = '7195558080'): void
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

test('send deposit request creates a deposit token and sends through platform', function () {
    connectDepositPortal();
    $smsPosts = [];
    fakeDepositPortalHttp(smsPosts: $smsPosts);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['phone' => '7195558080'])->save();
    seedDepositSmsCapablePhone('7195558080');

    $response = $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-deposit', $repairOrder), [
            'amount' => 50,
            'delivery' => 'sms',
        ]);

    $response->assertOk()
        ->assertJsonPath('message_id', null);

    expect($response->json('deposit_url'))->toContain('/portal/pay/')
        ->and($response->json('amount_display'))->toContain('50');

    $token = CustomerDocumentAccessToken::query()->sole();
    $depositReference = collect($smsPosts[0]['context'] ?? [])->firstWhere('type', 'deposit_request');

    expect($smsPosts)->toHaveCount(1)
        ->and($smsPosts[0]['body'] ?? '')->toContain('/go/')
        ->and($smsPosts[0]['body'] ?? '')->toContain('Deposit requested')
        ->and($smsPosts[0]['body'] ?? '')->not->toContain('/portal/pay/')
        ->and($depositReference['id'] ?? null)->toBe((string) $token->id)
        ->and($token->repair_order_id)->toBe($repairOrder->id)
        ->and($token->scope)->toBe(CustomerDocumentAccessToken::SCOPE_PAY_DEPOSIT)
        ->and($token->amount_cents)->toBe(5000)
        ->and($token->financial_document_id)->toBeNull()
        ->and(ConversationMessage::query()->count())->toBe(0)
        ->and(CommunicationEvent::query()->where('event_type', OperationalCommunicationType::InvoiceSent)->exists())->toBeTrue();
});

test('resending deposit request reuses the same short url', function () {
    connectDepositPortal();
    $smsPosts = [];
    fakeDepositPortalHttp(smsPosts: $smsPosts);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['phone' => '7195558080'])->save();
    seedDepositSmsCapablePhone('7195558080');

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-deposit', $repairOrder), [
            'amount' => 50,
            'delivery' => 'sms',
        ])
        ->assertOk();

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-deposit', $repairOrder), [
            'amount' => 75,
            'delivery' => 'sms',
        ])
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

test('send deposit request via email creates conversation message', function () {
    connectDepositPortal();
    fakeDepositPortalHttp();
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

    assertPlatformTransactionalMail('deposit_request.send', 'customer@example.test', '/portal/pay/');
    Mail::assertNothingSent();

    $smsPosts = collect(Http::recorded())
        ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/sms/messages/conversation'));
    $message = ConversationMessage::query()->sole();

    expect($message->channel)->toBe(OperationalCommunicationChannel::Email)
        ->and($message->body)->toContain('Deposit request emailed')
        ->and($smsPosts)->toHaveCount(0)
        ->and(CustomerDocumentAccessToken::query()->sole()->amount_cents)->toBe(10050);
});

test('send deposit request rejects zero amount issued invoice and missing portal pay', function () {
    connectDepositPortal();
    fakeDepositPortalHttp();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['phone' => '7195558080'])->save();
    seedDepositSmsCapablePhone('7195558080');

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-deposit', $repairOrder), [
            'amount' => 0,
            'delivery' => 'sms',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('amount');

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-deposit', $repairOrder), [
            'amount' => 500,
            'delivery' => 'sms',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Deposit amount cannot exceed $150.00.');

    issueFinalInvoiceFor($repairOrder);

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-deposit', $repairOrder->fresh()), [
            'amount' => 50,
            'delivery' => 'sms',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Use Send Pay Link after the final invoice is issued.');

    config()->set('services.ark_platform.payments_capture', false);

    $openRo = financialCloseoutRepairOrder();
    $openRo->customer->forceFill(['phone' => '7195558081'])->save();
    seedDepositSmsCapablePhone('7195558081');

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-deposit', $openRo), [
            'amount' => 50,
            'delivery' => 'sms',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Customer portal payments are not enabled.');
});

test('deposit send projection allows an open repair order and blocks after invoice', function () {
    connectDepositPortal();
    fakeDepositPortalHttp();

    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['phone' => '7195558080'])->save();
    seedDepositSmsCapablePhone('7195558080');

    $projection = app(RepairOrderConversationSendProjection::class)
        ->forRepairOrder($repairOrder, $advisor);

    expect($projection['deposit']['send_block_reason'])->toBeNull()
        ->and($projection['deposit']['can_sms'])->toBeTrue()
        ->and($projection['payment']['send_block_reason'])->toBe('Generate the final invoice before sending a payment link.');

    $invoiced = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($invoiced);
    $invoiced->customer->forceFill(['phone' => '7195559090'])->save();
    seedDepositSmsCapablePhone('7195559090');

    $afterInvoice = app(RepairOrderConversationSendProjection::class)
        ->forRepairOrder($invoiced->fresh(), $advisor)['deposit'];

    expect($afterInvoice['send_block_reason'])->toBe('Use Send Pay Link after the final invoice is issued.');
});

test('ro review shows send deposit when portal pay is ready and no invoice exists', function () {
    connectDepositPortal();
    fakeDepositPortalHttp();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['phone' => '7195558080'])->save();
    seedDepositSmsCapablePhone('7195558080');

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.workspace-tabs.show', [
            'repairOrder' => $repairOrder,
            'tab' => 'comms',
        ]))
        ->assertOk()
        ->assertSee('Send Deposit', false)
        ->assertSee('Send Pay Link', false);
});
