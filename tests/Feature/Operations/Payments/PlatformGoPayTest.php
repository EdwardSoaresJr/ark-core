<?php

use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\Payments\CreateCustomerPayTokenAction;
use App\Ark\Operations\Payments\PaymentCaptureSurface;
use App\Ark\Operations\Payments\PaymentGateway;
use App\Ark\Operations\Payments\PaymentGatewayAttempt;
use App\Ark\Operations\Payments\PaymentGatewayAttemptStatus;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Portal\CreatePortalShortLinkAction;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    enableHostedPlatformPayments();
});

function hostedGoPayToken($repairOrder)
{
    issueFinalInvoiceFor($repairOrder);

    return app(CreateCustomerPayTokenAction::class)
        ->execute($repairOrder->fresh(), $repairOrder->fresh()->estimateDocuments()->latest('id')->first());
}

function fakeHostedGoPayPlatform(?callable $onCapture = null): void
{
    Http::fake(function (Request $request) use ($onCapture) {
        expect($request->url())->toStartWith('https://cloud.test/')
            ->and($request->url())->not->toContain('squareup')
            ->and($request->url())->not->toContain('lugsnplugs.com')
            ->and($request->body())->not->toContain('sq0atp')
            ->and($request->body())->not->toContain('access_token');

        if (str_contains($request->url(), '/readiness')) {
            return Http::response(platformPaymentReadinessPayload(), 200);
        }

        if ($request->method() === 'POST' && str_contains($request->url(), '/captures')) {
            $json = $request->data();
            expect($json['capture_method'])->toBe('keyed')
                ->and($json)->not->toHaveKey('square_access_token')
                ->and($json)->not->toHaveKey('location_id')
                ->and($json['context']['kind'])->toBe('payment');

            if ($onCapture !== null) {
                return $onCapture($json);
            }

            return Http::response(platformCaptureKeyedSucceededPayload(
                $json['idempotency_key'],
                $json['capture_attempt_public_id'],
                $json['amount_cents'],
            ), 200);
        }

        return Http::response(['ok' => false], 500);
    });
}

test('hosted go pay page does not require Core Square credentials', function () {
    fakeHostedGoPayPlatform();

    $repairOrder = financialCloseoutRepairOrder();
    $token = hostedGoPayToken($repairOrder);

    $this->get(route('portal.invoice-pay.show', ['token' => $token->plainToken]))
        ->assertOk()
        ->assertSee('Pay your invoice')
        ->assertSee('Balance due')
        ->assertSee('$150.00')
        ->assertSee('sandbox-sq0idb-public')
        ->assertSee('LOC_PUBLIC')
        ->assertDontSee('sq0atp')
        ->assertDontSee('access_token');
});

test('hosted go short link stays on CoreApplicationOrigin and opens the pay page', function () {
    fakeHostedGoPayPlatform();

    $repairOrder = financialCloseoutRepairOrder();
    $token = hostedGoPayToken($repairOrder);
    $destination = route('portal.invoice-pay.show', ['token' => $token->plainToken]);
    $shortUrl = app(CreatePortalShortLinkAction::class)->execute($destination);
    $code = (string) str($shortUrl)->after('/go/');

    expect($shortUrl)->toContain('/go/')
        ->and($destination)->toContain('/portal/pay/')
        ->and($destination)->not->toContain('lugsnplugs.com')
        ->and(route('portal.short.redirect', ['code' => $code]))->toBe($shortUrl);

    $this->get(route('portal.short.redirect', ['code' => $code]))
        ->assertRedirect($destination);

    $this->get($destination)
        ->assertOk()
        ->assertSee('Pay your invoice');
});

test('hosted go pay is unavailable when Platform payments are not ready', function () {
    Http::fake([
        'https://cloud.test/api/v1/services/payments/readiness' => Http::response(platformPaymentReadinessPayload([
            'ok' => true,
            'status' => 'disconnected',
            'supports_terminal' => false,
            'supports_keyed' => false,
            'supports_portal' => false,
            'available_devices' => [],
            'public_config' => null,
            'message' => 'Square is not connected.',
        ]), 200),
    ]);

    $repairOrder = financialCloseoutRepairOrder();
    $token = hostedGoPayToken($repairOrder);

    $this->get(route('portal.invoice-pay.show', ['token' => $token->plainToken]))
        ->assertStatus(503);
});

test('hosted go pay rejects unknown tokens and foreign attempts', function () {
    fakeHostedGoPayPlatform();

    $this->get(route('portal.invoice-pay.show', ['token' => str_repeat('a', 64)]))
        ->assertNotFound();

    $first = financialCloseoutRepairOrder();
    $second = financialCloseoutRepairOrder();
    $firstToken = hostedGoPayToken($first);
    $secondToken = hostedGoPayToken($second);

    $attemptId = $this->postJson(route('portal.invoice-pay.attempts.store', ['token' => $firstToken->plainToken]))
        ->assertOk()
        ->json('attempt.id');

    $this->postJson(route('portal.invoice-pay.attempts.complete', [
        'token' => $secondToken->plainToken,
        'attempt' => $attemptId,
    ]), [
        'source_id' => 'cnon:go-token',
    ])->assertNotFound();
});

test('hosted go pay charges through Platform keyed capture and posts ledger once', function () {
    fakeHostedGoPayPlatform();

    $repairOrder = financialCloseoutRepairOrder();
    $token = hostedGoPayToken($repairOrder);

    $initiate = $this->postJson(route('portal.invoice-pay.attempts.store', ['token' => $token->plainToken]))
        ->assertOk()
        ->assertJsonPath('attempt.status', PaymentGatewayAttemptStatus::Pending->value)
        ->assertJsonPath('attempt.amount_cents', 15000);

    $attempt = PaymentGatewayAttempt::query()->find($initiate->json('attempt.id'));
    expect($attempt?->gateway)->toBe(PaymentGateway::Managed)
        ->and($attempt?->capture_surface)->toBe(PaymentCaptureSurface::Portal)
        ->and($attempt?->public_id)->not->toBeNull()
        ->and($attempt?->amount_cents)->toBe(15000);

    $this->postJson(route('portal.invoice-pay.attempts.complete', [
        'token' => $token->plainToken,
        'attempt' => $attempt->id,
    ]), [
        'source_id' => 'cnon:go-token',
    ])->assertOk()
        ->assertJsonPath('attempt.status', PaymentGatewayAttemptStatus::Completed->value)
        ->assertJsonPath('message', 'Thank you — we received your $150.00 payment.');

    $this->postJson(route('portal.invoice-pay.attempts.complete', [
        'token' => $token->plainToken,
        'attempt' => $attempt->id,
    ]), [
        'source_id' => 'cnon:go-token',
    ])->assertOk()
        ->assertJsonPath('attempt.status', PaymentGatewayAttemptStatus::Completed->value);

    expect(RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('entry_type', LedgerEntryType::Payment)
        ->count())->toBe(1)
        ->and($repairOrder->fresh()->isPaid())->toBeTrue()
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe(0);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/captures'));
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'terminals/checkouts'));
});

test('hosted go pay decline does not create a Core payment', function () {
    fakeHostedGoPayPlatform(function (array $json) {
        return Http::response([
            'ok' => true,
            'status' => 'failed',
            'capture_attempt_public_id' => $json['capture_attempt_public_id'],
            'idempotency_key' => $json['idempotency_key'],
            'amount_cents' => $json['amount_cents'],
            'reason_code' => 'declined',
            'message' => 'Card declined.',
        ], 200);
    });

    $repairOrder = financialCloseoutRepairOrder();
    $token = hostedGoPayToken($repairOrder);
    $attemptId = $this->postJson(route('portal.invoice-pay.attempts.store', ['token' => $token->plainToken]))
        ->assertOk()
        ->json('attempt.id');

    $this->postJson(route('portal.invoice-pay.attempts.complete', [
        'token' => $token->plainToken,
        'attempt' => $attemptId,
    ]), [
        'source_id' => 'cnon:declined',
    ])->assertStatus(422)
        ->assertJsonPath('attempt.status', PaymentGatewayAttemptStatus::Failed->value);

    expect(RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('entry_type', LedgerEntryType::Payment)
        ->count())->toBe(0)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe(15000);
});

test('hosted go pay cancellation does not create a Core payment', function () {
    fakeHostedGoPayPlatform(function (array $json) {
        return Http::response([
            'ok' => true,
            'status' => 'cancelled',
            'capture_attempt_public_id' => $json['capture_attempt_public_id'],
            'idempotency_key' => $json['idempotency_key'],
            'amount_cents' => $json['amount_cents'],
            'reason_code' => 'cancelled',
            'message' => 'Payment canceled.',
        ], 200);
    });

    $repairOrder = financialCloseoutRepairOrder();
    $token = hostedGoPayToken($repairOrder);
    $attemptId = $this->postJson(route('portal.invoice-pay.attempts.store', ['token' => $token->plainToken]))
        ->assertOk()
        ->json('attempt.id');

    $this->postJson(route('portal.invoice-pay.attempts.complete', [
        'token' => $token->plainToken,
        'attempt' => $attemptId,
    ]), [
        'source_id' => 'cnon:canceled',
    ])->assertOk()
        ->assertJsonPath('attempt.status', PaymentGatewayAttemptStatus::Canceled->value);

    expect(RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('entry_type', LedgerEntryType::Payment)
        ->count())->toBe(0);
});

test('hosted go pay fabric result is consumed exactly once and unsigned events are rejected', function () {
    fakeHostedGoPayPlatform();

    $repairOrder = financialCloseoutRepairOrder();
    $token = hostedGoPayToken($repairOrder);
    $attemptId = $this->postJson(route('portal.invoice-pay.attempts.store', ['token' => $token->plainToken]))
        ->assertOk()
        ->json('attempt.id');
    $attempt = PaymentGatewayAttempt::query()->findOrFail($attemptId);

    $this->postJson('/webhooks/cloud/fabric/events', [
        'operation' => 'payments.capture.updated',
        'installation_id' => (string) \App\Ark\Install\InstallationIdentity::uuid(),
        'payload' => [
            'capture_attempt_public_id' => $attempt->public_id,
            'idempotency_key' => $attempt->idempotency_key,
            'status' => 'succeeded',
            'amount_cents' => 15000,
            'provider_payment_id' => 'pay_unsigned',
        ],
    ])->assertUnauthorized();

    $payload = [
        'capture_attempt_public_id' => $attempt->public_id,
        'idempotency_key' => $attempt->idempotency_key,
        'status' => 'succeeded',
        'amount_cents' => 15000,
        'provider_payment_id' => 'pay_go_fabric_1',
    ];

    postSignedFabricEvent('payments.capture.updated', $payload)->assertOk()->assertJsonPath('applied', true);
    postSignedFabricEvent('payments.capture.updated', $payload)->assertOk()->assertJsonPath('applied', true);

    expect(RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('entry_type', LedgerEntryType::Payment)
        ->count())->toBe(1)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe(0);
});

test('hosted send pay link no longer requires Core Square and uses /go', function () {
    $smsBodies = [];

    Http::fake(function (Request $request) use (&$smsBodies) {
        expect($request->url())->toStartWith('https://cloud.test/')
            ->and($request->url())->not->toContain('squareup')
            ->and($request->url())->not->toContain('lugsnplugs.com');

        if (str_contains($request->url(), '/readiness')) {
            return Http::response(platformPaymentReadinessPayload(), 200);
        }

        if (str_contains($request->url(), '/sms/messages/conversation')) {
            $smsBodies[] = (string) ($request->data()['body'] ?? '');

            return Http::response([
                'ok' => true,
                'message_id' => 'plat-msg-go',
                'provider_message_id' => 'SMhostedgo01',
                'status' => 'queued',
            ], 200);
        }

        return Http::response(['ok' => true], 200);
    });

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['phone' => '7195558080'])->save();
    $normalized = PhoneNumber::normalize('7195558080') ?? '7195558080';
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
    issueFinalInvoiceFor($repairOrder);

    $response = $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-payment', $repairOrder));

    $response->assertOk()
        ->assertJsonPath('balance_due_display', '$150.00');

    expect($response->json('payment_url'))->toContain('/portal/pay/')
        ->and($smsBodies)->not->toBeEmpty()
        ->and($smsBodies[0])->toContain('/go/')
        ->and($smsBodies[0])->not->toContain('/portal/pay/');
});

test('hosted repair order still offers charge card on reader after go pay wiring', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['phone' => '7195558080'])->save();
    issueFinalInvoiceFor($repairOrder);

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/readiness') || str_contains($request->url(), '/payments/')) {
            return Http::response(platformPaymentReadinessPayload(), 200);
        }

        return Http::response(['ok' => true], 200);
    });

    $this->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('Charge card on reader');
});
