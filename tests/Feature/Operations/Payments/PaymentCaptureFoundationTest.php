<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\GenerateInvoiceSnapshotAction;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttempt;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttemptStatus;
use App\Ark\Operations\Payments\Capture\PaymentCaptureContextKind;
use App\Ark\Operations\Payments\Capture\PaymentCaptureMethod;
use App\Ark\Operations\Payments\Capture\ApplyPaymentCaptureResultAction;
use App\Ark\Operations\Payments\Capture\InitiatePaymentCaptureAction;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Platform\Http\VerifyPlatformFabricSignature;
use App\Ark\Platform\PlatformConnection;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    \App\Ark\Operations\Payments\Capture\PaymentCaptureReadinessProjection::resetMemo();
    $this->seed(ArkAuthorizationSeeder::class);
    InstallationIdentity::write((string) Str::uuid());
    ShopSettings::current()->persistTrusted([
        'shop_name' => 'Capture Shop',
        'cloud_status' => 'connected',
        'cloud_base_url' => 'https://cloud.example.test',
        'cloud_shop_public_id' => (string) Str::uuid(),
        'cloud_credential' => 'test-credential-32-characters-min!!',
    ]);
});

function paymentCaptureAdvisor(): User
{
    return User::factory()->create()->assignRole(ArkRole::Advisor->value);
}

function fakeCloudCaptureSuccess(string $coreAttemptPublicId, string $idempotencyKey, int $amountCents = 15000): void
{
    Http::fake([
        'cloud.example.test/api/v1/services/payments/readiness' => Http::response([
            'ok' => true,
            'provider' => 'stub',
            'status' => 'connected',
            'supports_terminal' => true,
            'supports_keyed' => true,
            'supports_portal' => false,
            'available_devices' => [
                ['device_ref' => 'stub-front-counter', 'label' => 'Front Counter', 'ready' => true],
            ],
            'public_config' => null,
        ], 200),
        'cloud.example.test/api/v1/services/payments/devices' => Http::response([
            'ok' => true,
            'devices' => [
                ['device_ref' => 'stub-front-counter', 'label' => 'Front Counter', 'ready' => true],
            ],
        ], 200),
        'cloud.example.test/api/v1/services/payments/captures' => Http::response([
            'ok' => true,
            'status' => 'succeeded',
            'capture_id' => (string) Str::uuid(),
            'capture_attempt_public_id' => $coreAttemptPublicId,
            'idempotency_key' => $idempotencyKey,
            'amount_cents' => $amountCents,
            'currency' => 'USD',
            'context_kind' => 'payment',
            'provider' => 'stub',
            'provider_payment_id' => 'stub_pay_1',
        ], 200),
        'cloud.example.test/api/v1/services/payments/captures/*' => Http::response([
            'ok' => true,
            'status' => 'succeeded',
            'capture_id' => (string) Str::uuid(),
            'capture_attempt_public_id' => $coreAttemptPublicId,
            'idempotency_key' => $idempotencyKey,
            'amount_cents' => $amountCents,
            'currency' => 'USD',
            'context_kind' => 'payment',
            'provider' => 'stub',
            'provider_payment_id' => 'stub_pay_1',
        ], 200),
    ]);
}

test('successful capture records exactly one ledger payment and clears balance', function () {
    $repairOrder = financialCloseoutRepairOrder();
    app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder);
    $advisor = paymentCaptureAdvisor();
    $before = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents;
    expect($before)->toBeGreaterThan(0);

    $seenRequest = null;
    Http::fake(function (\Illuminate\Http\Client\Request $request) use ($before, &$seenRequest) {
        $body = json_decode($request->body(), true) ?: [];
        if ($request->method() === 'POST' && str_contains($request->url(), '/payments/captures')) {
            $seenRequest = $body;

            return Http::response([
                'ok' => true,
                'status' => 'succeeded',
                'capture_id' => (string) Str::uuid(),
                'capture_attempt_public_id' => $body['capture_attempt_public_id'] ?? '',
                'idempotency_key' => $body['idempotency_key'] ?? '',
                'amount_cents' => $body['amount_cents'] ?? $before,
                'currency' => 'USD',
                'context_kind' => 'payment',
                'provider' => 'stub',
                'provider_payment_id' => 'stub_pay_ok',
            ], 200);
        }

        return Http::response(['ok' => false], 404);
    });

    $result = app(InitiatePaymentCaptureAction::class)->execute($repairOrder, $advisor, [
        'amount_cents' => $before,
        'context_kind' => PaymentCaptureContextKind::Payment,
        'capture_method' => PaymentCaptureMethod::Keyed,
        'source_token' => 'tok_test',
        'stub_scenario' => 'immediate_success',
    ]);

    $attempt = $result['attempt']->fresh();
    expect($seenRequest)->not->toBeNull()
        ->and($seenRequest['amount_cents'] ?? null)->toBe($before)
        ->and($seenRequest['reference'] ?? null)->toBe($attempt->reference())
        ->and($seenRequest['capture_attempt_public_id'] ?? null)->toBe($attempt->public_id)
        ->and($attempt->status)->toBe(PaymentCaptureAttemptStatus::Succeeded)
        ->and($attempt->ledger_entry_id)->not->toBeNull();

    $after = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents;
    expect($after)->toBe(0);

    // Replay success must not double-write ledger.
    app(ApplyPaymentCaptureResultAction::class)->apply($attempt, [
        'status' => 'succeeded',
        'capture_id' => $attempt->cloud_capture_id,
        'provider_payment_id' => 'stub_pay_ok',
    ]);

    expect(PaymentCaptureAttempt::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(1)
        ->and($repairOrder->fresh()->ledgerEntries()->where('entry_type', 'payment')->count())->toBe(1)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe(0);
});

test('failed capture does not write ledger', function () {
    $repairOrder = financialCloseoutRepairOrder();
    app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder);
    $advisor = paymentCaptureAdvisor();
    $amount = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents;

    Http::fake([
        'cloud.example.test/api/v1/services/payments/captures' => Http::response([
            'ok' => true,
            'status' => 'failed',
            'reason_code' => 'declined',
            'capture_id' => (string) Str::uuid(),
            'amount_cents' => $amount,
            'currency' => 'USD',
            'context_kind' => 'payment',
            'provider' => 'stub',
        ], 200),
    ]);

    $result = app(InitiatePaymentCaptureAction::class)->execute($repairOrder, $advisor, [
        'amount_cents' => $amount,
        'context_kind' => PaymentCaptureContextKind::Payment,
        'capture_method' => PaymentCaptureMethod::Keyed,
        'source_token' => 'tok_test',
    ]);

    expect($result['attempt']->status)->toBe(PaymentCaptureAttemptStatus::Failed)
        ->and($result['attempt']->ledger_entry_id)->toBeNull()
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe($amount);
});

test('reconciliation_required blocks same amount recapture then status success records once', function () {
    $repairOrder = financialCloseoutRepairOrder();
    app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder);
    $advisor = paymentCaptureAdvisor();
    $amount = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents;

    Http::fake([
        'cloud.example.test/api/v1/services/payments/captures' => Http::response([
            'ok' => true,
            'status' => 'reconciliation_required',
            'reason_code' => 'ambiguous_outcome',
            'capture_id' => (string) Str::uuid(),
            'amount_cents' => $amount,
            'currency' => 'USD',
            'context_kind' => 'payment',
            'provider' => 'stub',
            'provider_payment_id' => 'stub_ambiguous',
        ], 200),
    ]);

    $first = app(InitiatePaymentCaptureAction::class)->execute($repairOrder, $advisor, [
        'amount_cents' => $amount,
        'context_kind' => PaymentCaptureContextKind::Payment,
        'capture_method' => PaymentCaptureMethod::Keyed,
        'source_token' => 'tok_test',
    ]);

    expect($first['attempt']->status)->toBe(PaymentCaptureAttemptStatus::ReconciliationRequired)
        ->and($first['attempt']->ledger_entry_id)->toBeNull();

    expect(fn () => app(InitiatePaymentCaptureAction::class)->execute($repairOrder, $advisor, [
        'amount_cents' => $amount,
        'context_kind' => PaymentCaptureContextKind::Payment,
        'capture_method' => PaymentCaptureMethod::Keyed,
        'source_token' => 'tok_test',
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);

    Http::fake([
        'cloud.example.test/api/v1/services/payments/captures/*' => Http::response([
            'ok' => true,
            'status' => 'succeeded',
            'capture_id' => $first['attempt']->cloud_capture_id,
            'capture_attempt_public_id' => $first['attempt']->public_id,
            'idempotency_key' => $first['attempt']->idempotency_key,
            'amount_cents' => $amount,
            'currency' => 'USD',
            'context_kind' => 'payment',
            'provider' => 'stub',
            'provider_payment_id' => 'stub_ambiguous',
        ], 200),
    ]);

    $resolved = app(InitiatePaymentCaptureAction::class)->refreshFromCloud($first['attempt']->fresh());

    expect($resolved->status)->toBe(PaymentCaptureAttemptStatus::Succeeded)
        ->and($resolved->ledger_entry_id)->not->toBeNull()
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe(0);
});

test('fabric duplicate completion is idempotent for ledger', function () {
    $repairOrder = financialCloseoutRepairOrder();
    app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder);
    $amount = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents;

    $attempt = PaymentCaptureAttempt::query()->create([
        'public_id' => (string) Str::uuid(),
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $repairOrder->customer_id,
        'amount_cents' => $amount,
        'currency' => 'USD',
        'context_kind' => PaymentCaptureContextKind::Payment,
        'capture_method' => PaymentCaptureMethod::Keyed,
        'status' => PaymentCaptureAttemptStatus::Pending,
        'idempotency_key' => 'core-'.Str::uuid(),
        'initiated_at' => now(),
    ]);

    $payload = [
        'status' => 'succeeded',
        'capture_id' => (string) Str::uuid(),
        'capture_attempt_public_id' => $attempt->public_id,
        'idempotency_key' => $attempt->idempotency_key,
        'provider_payment_id' => 'stub_fab',
        'amount_cents' => $amount,
    ];

    $body = [
        'operation' => 'payments.capture.updated',
        'installation_id' => InstallationIdentity::uuid(),
        'payload' => $payload,
    ];
    $raw = json_encode($body, JSON_THROW_ON_ERROR);
    $timestamp = (string) time();
    $nonce = Str::random(24);
    $credential = (string) PlatformConnection::current()->credential();
    $signature = hash_hmac('sha256', implode("\n", [
        $timestamp,
        $nonce,
        'POST',
        VerifyPlatformFabricSignature::PATH,
        hash('sha256', $raw),
    ]), $credential);

    $headers = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_ARK_INSTALLATION_ID' => InstallationIdentity::uuid(),
        'HTTP_X_ARK_TIMESTAMP' => $timestamp,
        'HTTP_X_ARK_NONCE' => $nonce,
        'HTTP_X_ARK_SIGNATURE' => $signature,
    ];

    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $headers, $raw)
        ->assertOk();

    $nonce2 = Str::random(24);
    $signature2 = hash_hmac('sha256', implode("\n", [
        $timestamp,
        $nonce2,
        'POST',
        VerifyPlatformFabricSignature::PATH,
        hash('sha256', $raw),
    ]), $credential);
    $headers['HTTP_X_ARK_NONCE'] = $nonce2;
    $headers['HTTP_X_ARK_SIGNATURE'] = $signature2;

    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $headers, $raw)
        ->assertOk();

    expect($repairOrder->fresh()->ledgerEntries()->where('entry_type', 'payment')->count())->toBe(1)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe(0);
});

test('cloud unavailable leaves record payment path intact and writes no ledger from capture', function () {
    $repairOrder = financialCloseoutRepairOrder();
    app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder);
    $advisor = paymentCaptureAdvisor();
    $amount = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents;

    Http::fake(fn () => throw new \RuntimeException('connection refused'));

    expect(fn () => app(InitiatePaymentCaptureAction::class)->execute($repairOrder, $advisor, [
        'amount_cents' => $amount,
        'context_kind' => PaymentCaptureContextKind::Payment,
        'capture_method' => PaymentCaptureMethod::Keyed,
        'source_token' => 'tok_test',
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe($amount)
        ->and($repairOrder->fresh()->ledgerEntries()->count())->toBe(0);
});

test('deposit context records deposit ledger entry once', function () {
    $repairOrder = financialCloseoutRepairOrder(status: \App\Ark\Operations\RepairOrders\RepairOrderStatus::InProgress);
    // No invoice — deposit path
    $advisor = paymentCaptureAdvisor();

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $body = json_decode($request->body(), true) ?: [];

        return Http::response([
            'ok' => true,
            'status' => 'succeeded',
            'capture_id' => (string) Str::uuid(),
            'capture_attempt_public_id' => $body['capture_attempt_public_id'] ?? '',
            'idempotency_key' => $body['idempotency_key'] ?? '',
            'amount_cents' => $body['amount_cents'] ?? 2500,
            'currency' => 'USD',
            'context_kind' => 'deposit',
            'provider' => 'stub',
            'provider_payment_id' => 'stub_dep',
        ], 200);
    });

    $result = app(InitiatePaymentCaptureAction::class)->execute($repairOrder, $advisor, [
        'amount_cents' => 2500,
        'context_kind' => PaymentCaptureContextKind::Deposit,
        'capture_method' => PaymentCaptureMethod::Keyed,
        'source_token' => 'tok_test',
    ]);

    expect($result['attempt']->status)->toBe(PaymentCaptureAttemptStatus::Succeeded)
        ->and($result['attempt']->ledger_entry_id)->not->toBeNull()
        ->and($repairOrder->fresh()->ledgerEntries()->where('entry_type', 'deposit')->count())->toBe(1);
});

test('a later pending status poll does not reopen a cancelled capture', function () {
    $repairOrder = financialCloseoutRepairOrder();
    app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder);
    $amount = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents;

    $attempt = PaymentCaptureAttempt::query()->create([
        'public_id' => (string) Str::uuid(),
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $repairOrder->customer_id,
        'amount_cents' => $amount,
        'currency' => 'USD',
        'context_kind' => PaymentCaptureContextKind::Payment,
        'capture_method' => PaymentCaptureMethod::Terminal,
        'status' => PaymentCaptureAttemptStatus::Cancelled,
        'idempotency_key' => 'core-'.Str::uuid(),
        'initiated_at' => now(),
        'completed_at' => now(),
    ]);

    $reopened = app(ApplyPaymentCaptureResultAction::class)->apply($attempt, [
        'status' => 'pending',
        'capture_id' => (string) Str::uuid(),
    ]);

    expect($reopened->status)->toBe(PaymentCaptureAttemptStatus::Cancelled)
        ->and($reopened->ledger_entry_id)->toBeNull()
        ->and($repairOrder->fresh()->ledgerEntries()->where('entry_type', 'payment')->count())->toBe(0);
});

test('cancel retries while platform still reports pending', function () {
    $repairOrder = financialCloseoutRepairOrder();
    app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder);
    $advisor = paymentCaptureAdvisor();
    $amount = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents;

    $cancelHits = 0;
    Http::fake(function (\Illuminate\Http\Client\Request $request) use ($amount, &$cancelHits) {
        $body = json_decode($request->body(), true) ?: [];

        if (str_contains($request->url(), '/cancel')) {
            $cancelHits++;
            $attempt = PaymentCaptureAttempt::query()->latest('id')->first();

            return Http::response([
                'ok' => true,
                'status' => $cancelHits === 1 ? 'pending' : 'cancelled',
                'capture_id' => (string) Str::uuid(),
                'capture_attempt_public_id' => $attempt?->public_id,
                'idempotency_key' => $attempt?->idempotency_key,
                'amount_cents' => $amount,
                'reason_code' => $cancelHits === 1 ? null : 'cancelled',
            ], 200);
        }

        if ($request->method() === 'POST' && str_contains($request->url(), '/payments/captures')) {
            return Http::response([
                'ok' => true,
                'status' => 'pending',
                'capture_id' => (string) Str::uuid(),
                'capture_attempt_public_id' => $body['capture_attempt_public_id'] ?? '',
                'idempotency_key' => $body['idempotency_key'] ?? '',
                'amount_cents' => $amount,
                'currency' => 'USD',
                'context_kind' => 'payment',
                'capture_method' => 'terminal',
                'provider' => 'stub',
            ], 200);
        }

        return Http::response(['ok' => false], 404);
    });

    $attemptId = app(InitiatePaymentCaptureAction::class)->execute($repairOrder, $advisor, [
        'amount_cents' => $amount,
        'context_kind' => PaymentCaptureContextKind::Payment,
        'capture_method' => PaymentCaptureMethod::Terminal,
        'device_ref' => 'stub-front-counter',
    ])['attempt']->id;

    $updated = app(InitiatePaymentCaptureAction::class)
        ->cancelFromCloud(PaymentCaptureAttempt::query()->findOrFail($attemptId));

    expect($updated->status)->toBe(PaymentCaptureAttemptStatus::Cancelled)
        ->and($cancelHits)->toBe(2)
        ->and($updated->ledger_entry_id)->toBeNull();
});
