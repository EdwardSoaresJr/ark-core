<?php

use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\Payments\PaymentCaptureSurface;
use App\Ark\Operations\Payments\PaymentGateway;
use App\Ark\Operations\Payments\PaymentGatewayAttempt;
use App\Ark\Operations\Payments\PaymentGatewayAttemptStatus;
use App\Ark\Operations\RepairOrders\RepairOrderPaymentStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    enableHostedPlatformPayments();
});

test('hosted terminal initiate is provider-neutral and does not use Core Square credentials', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    Http::fake(function (Request $request) {
        expect($request->url())->toStartWith('https://cloud.test/api/v1/services/payments/')
            ->and($request->url())->not->toContain('lugsnplugs')
            ->and($request->url())->not->toContain('squareup')
            ->and($request->body())->not->toContain('sq0atp')
            ->and($request->body())->not->toContain('access_token');

        if (str_contains($request->url(), '/readiness')) {
            return Http::response(platformPaymentReadinessPayload(), 200);
        }

        expect($request->method())->toBe('POST');
        $json = $request->data();
        expect($json['amount_cents'])->toBe(15000)
            ->and($json['currency'])->toBe('USD')
            ->and($json['capture_method'])->toBe('terminal')
            ->and($json['device_ref'])->toBe('front-counter')
            ->and($json['context']['kind'])->toBe('payment')
            ->and($json)->not->toHaveKey('square_access_token')
            ->and($json)->not->toHaveKey('location_id');

        return Http::response(platformCapturePendingPayload(
            $json['idempotency_key'],
            $json['capture_attempt_public_id'],
        ), 200);
    });

    $response = $this->postJson(route('operations.repair-orders.square-payments.store', $repairOrder), [
        'capture_surface' => PaymentCaptureSurface::Terminal->value,
        'amount' => 150.00,
    ]);

    $response->assertOk()
        ->assertJsonPath('attempt.status', PaymentGatewayAttemptStatus::Pending->value);

    $attempt = PaymentGatewayAttempt::query()->find($response->json('attempt.id'));
    expect($attempt?->gateway)->toBe(PaymentGateway::Managed)
        ->and($attempt?->amount_cents)->toBe(15000)
        ->and($attempt?->square_checkout_id)->toBe('chk_hosted_1')
        ->and($attempt?->public_id)->not->toBeNull();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/captures')
        && $request->method() === 'POST');
});

test('hosted terminal poll success posts ledger and balance due exactly once', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/readiness')) {
            return Http::response(platformPaymentReadinessPayload(), 200);
        }

        if ($request->method() === 'POST' && str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?: '', '/captures')) {
            $json = $request->data();

            return Http::response(platformCapturePendingPayload(
                $json['idempotency_key'],
                $json['capture_attempt_public_id'],
            ), 200);
        }

        if ($request->method() === 'GET' && str_contains($request->url(), '/captures/')) {
            $json = $request->data();

            return Http::response([
                'ok' => true,
                'status' => 'succeeded',
                'capture_attempt_public_id' => 'ignored',
                'idempotency_key' => basename(parse_url($request->url(), PHP_URL_PATH) ?: ''),
                'amount_cents' => 15000,
                'currency' => 'USD',
                'provider_payment_id' => 'pay_hosted_1',
                'provider_refs' => ['terminal_checkout_id' => 'chk_hosted_1'],
            ], 200);
        }

        return Http::response(['ok' => false], 500);
    });

    $response = $this->postJson(route('operations.repair-orders.square-payments.store', $repairOrder), [
        'capture_surface' => PaymentCaptureSurface::Terminal->value,
    ])->assertOk();

    $attemptId = $response->json('attempt.id');

    $this->getJson(route('operations.repair-orders.square-payments.show', [$repairOrder, $attemptId]))
        ->assertOk()
        ->assertJsonPath('attempt.status', PaymentGatewayAttemptStatus::Completed->value);

    $this->getJson(route('operations.repair-orders.square-payments.show', [$repairOrder, $attemptId]))
        ->assertOk()
        ->assertJsonPath('attempt.status', PaymentGatewayAttemptStatus::Completed->value);

    expect(RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('entry_type', LedgerEntryType::Payment)
        ->count())->toBe(1)
        ->and($repairOrder->fresh()->isPaid())->toBeTrue()
        ->and($repairOrder->fresh()->payment_status)->toBe(RepairOrderPaymentStatus::Paid)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe(0);
});

test('fabric capture result is consumed exactly once and failed captures do not post ledger', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/readiness')) {
            return Http::response(platformPaymentReadinessPayload(), 200);
        }

        $json = $request->data();

        return Http::response(platformCapturePendingPayload(
            $json['idempotency_key'] ?? 'x',
            $json['capture_attempt_public_id'] ?? 'y',
        ), 200);
    });

    $attemptId = $this->postJson(route('operations.repair-orders.square-payments.store', $repairOrder), [
        'capture_surface' => PaymentCaptureSurface::Terminal->value,
    ])->assertOk()->json('attempt.id');

    $attempt = PaymentGatewayAttempt::query()->findOrFail($attemptId);

    $failed = postSignedFabricEvent('payments.capture.updated', [
        'capture_attempt_public_id' => $attempt->public_id,
        'idempotency_key' => $attempt->idempotency_key,
        'status' => 'failed',
        'amount_cents' => 15000,
        'reason_code' => 'declined',
        'message' => 'Card declined.',
    ]);
    $failed->assertOk()->assertJsonPath('applied', true);

    expect($attempt->fresh()->status)->toBe(PaymentGatewayAttemptStatus::Failed)
        ->and(RepairOrderLedgerEntry::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('entry_type', LedgerEntryType::Payment)
            ->count())->toBe(0);

    $secondRo = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($secondRo);
    $secondId = $this->postJson(route('operations.repair-orders.square-payments.store', $secondRo), [
        'capture_surface' => PaymentCaptureSurface::Terminal->value,
    ])->assertOk()->json('attempt.id');
    $second = PaymentGatewayAttempt::query()->findOrFail($secondId);

    $payload = [
        'capture_attempt_public_id' => $second->public_id,
        'idempotency_key' => $second->idempotency_key,
        'status' => 'succeeded',
        'amount_cents' => 15000,
        'provider_payment_id' => 'pay_fabric_1',
    ];

    postSignedFabricEvent('payments.capture.updated', $payload)->assertOk()->assertJsonPath('applied', true);
    postSignedFabricEvent('payments.capture.updated', $payload)->assertOk()->assertJsonPath('applied', true);

    expect(RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $secondRo->id)
        ->where('entry_type', LedgerEntryType::Payment)
        ->count())->toBe(1)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($secondRo->fresh())->balanceDueCents)->toBe(0);
});

test('cancelled terminal capture does not create a Core payment', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/readiness')) {
            return Http::response(platformPaymentReadinessPayload(), 200);
        }

        if (str_contains($request->url(), '/cancel')) {
            $attempt = PaymentGatewayAttempt::query()->latest('id')->first();

            return Http::response([
                'ok' => true,
                'status' => 'cancelled',
                'capture_attempt_public_id' => $attempt?->public_id,
                'idempotency_key' => $attempt?->idempotency_key,
                'amount_cents' => 15000,
                'reason_code' => 'cancelled',
            ], 200);
        }

        $json = $request->data();

        return Http::response(platformCapturePendingPayload(
            $json['idempotency_key'],
            $json['capture_attempt_public_id'],
        ), 200);
    });

    $attemptId = $this->postJson(route('operations.repair-orders.square-payments.store', $repairOrder), [
        'capture_surface' => PaymentCaptureSurface::Terminal->value,
    ])->assertOk()->json('attempt.id');

    $this->deleteJson(route('operations.repair-orders.square-payments.destroy', [$repairOrder, $attemptId]))
        ->assertOk()
        ->assertJsonPath('attempt.status', PaymentGatewayAttemptStatus::Canceled->value);

    expect(RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('entry_type', LedgerEntryType::Payment)
        ->count())->toBe(0);
});

test('provider outage does not record a successful payment', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/readiness')) {
            return Http::response(platformPaymentReadinessPayload(), 200);
        }

        return Http::response([
            'ok' => false,
            'reason_code' => 'provider_unavailable',
            'message' => 'Card capture is unavailable.',
        ], 502);
    });

    $this->postJson(route('operations.repair-orders.square-payments.store', $repairOrder), [
        'capture_surface' => PaymentCaptureSurface::Terminal->value,
    ])->assertStatus(422);

    expect(PaymentGatewayAttempt::query()->where('status', PaymentGatewayAttemptStatus::Completed)->count())->toBe(0)
        ->and(RepairOrderLedgerEntry::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('entry_type', LedgerEntryType::Payment)
            ->count())->toBe(0);
});

test('succeeded capture without provider payment id does not post ledger', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/readiness')) {
            return Http::response(platformPaymentReadinessPayload(), 200);
        }

        $json = $request->data();

        return Http::response(platformCapturePendingPayload(
            $json['idempotency_key'],
            $json['capture_attempt_public_id'],
        ), 200);
    });

    $attemptId = $this->postJson(route('operations.repair-orders.square-payments.store', $repairOrder), [
        'capture_surface' => PaymentCaptureSurface::Terminal->value,
    ])->assertOk()->json('attempt.id');
    $attempt = PaymentGatewayAttempt::query()->findOrFail($attemptId);

    postSignedFabricEvent('payments.capture.updated', [
        'capture_attempt_public_id' => $attempt->public_id,
        'idempotency_key' => $attempt->idempotency_key,
        'status' => 'succeeded',
        'amount_cents' => 15000,
        'provider_payment_id' => '',
    ])->assertOk();

    expect($attempt->fresh()->status)->toBe(PaymentGatewayAttemptStatus::Pending)
        ->and(RepairOrderLedgerEntry::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('entry_type', LedgerEntryType::Payment)
            ->count())->toBe(0);
});

test('hosted financial rail still offers charge card on reader without Core Square secrets', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    Http::fake([
        'https://cloud.test/api/v1/services/payments/readiness' => Http::response(platformPaymentReadinessPayload(), 200),
    ]);

    $this->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('Charge card on reader');
});
