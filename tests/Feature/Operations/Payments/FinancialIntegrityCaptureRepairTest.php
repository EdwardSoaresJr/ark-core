<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Financial\GenerateInvoiceSnapshotAction;
use App\Ark\Operations\Financial\RepairOrderDepositRecordingGuard;
use App\Ark\Operations\Payments\Capture\ApplyPaymentCaptureResultAction;
use App\Ark\Operations\Payments\Capture\InitiatePaymentCaptureAction;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttempt;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttemptStatus;
use App\Ark\Operations\Payments\Capture\PaymentCaptureContextKind;
use App\Ark\Operations\Payments\Capture\PaymentCaptureMethod;
use App\Ark\Operations\Payments\PaymentCaptureSurface;
use App\Ark\Operations\Payments\PaymentGateway;
use App\Ark\Operations\Payments\PaymentGatewayAttempt;
use App\Ark\Operations\Payments\PaymentGatewayAttemptStatus;
use App\Ark\Operations\Payments\PollPlatformPaymentCaptureAction;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
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

function integrityCaptureAdvisor(): User
{
    return User::factory()->create()->assignRole(ArkRole::Advisor->value);
}

test('deposit at the guard limit still initiates a capture', function () {
    $repairOrder = repairOrderWithSuggestedPartDeposit(RepairOrderStatus::InProgress);
    $advisor = integrityCaptureAdvisor();
    $allowed = app(RepairOrderDepositRecordingGuard::class)->remainingAllowedDepositCents($repairOrder);
    expect($allowed)->toBeGreaterThan(0);

    $captured = false;
    Http::fake(function (Request $request) use (&$captured, $allowed) {
        $body = json_decode($request->body(), true) ?: [];
        if ($request->method() === 'POST' && str_contains($request->url(), '/payments/captures')) {
            $captured = true;

            return Http::response([
                'ok' => true,
                'status' => 'succeeded',
                'capture_id' => (string) Str::uuid(),
                'capture_attempt_public_id' => $body['capture_attempt_public_id'] ?? '',
                'idempotency_key' => $body['idempotency_key'] ?? '',
                'amount_cents' => $body['amount_cents'] ?? $allowed,
                'currency' => 'USD',
                'context_kind' => 'deposit',
                'provider' => 'stub',
                'provider_payment_id' => 'stub_dep_ok',
            ], 200);
        }

        return Http::response(['ok' => false], 404);
    });

    $result = app(InitiatePaymentCaptureAction::class)->execute($repairOrder, $advisor, [
        'amount_cents' => $allowed,
        'context_kind' => PaymentCaptureContextKind::Deposit,
        'capture_method' => PaymentCaptureMethod::Keyed,
        'source_token' => 'tok_test',
    ]);

    expect($captured)->toBeTrue()
        ->and($result['attempt']->status)->toBe(PaymentCaptureAttemptStatus::Succeeded)
        ->and($result['attempt']->ledger_entry_id)->not->toBeNull()
        ->and($repairOrder->fresh()->ledgerEntries()->where('entry_type', 'deposit')->count())->toBe(1);
});

test('deposit above the guard limit is rejected before provider capture', function () {
    $repairOrder = repairOrderWithSuggestedPartDeposit(RepairOrderStatus::InProgress);
    $advisor = integrityCaptureAdvisor();
    $allowed = app(RepairOrderDepositRecordingGuard::class)->remainingAllowedDepositCents($repairOrder);
    expect($allowed)->toBeGreaterThan(0);

    $captured = false;
    Http::fake(function () use (&$captured) {
        $captured = true;

        return Http::response(['ok' => true, 'status' => 'succeeded'], 200);
    });

    expect(fn () => app(InitiatePaymentCaptureAction::class)->execute($repairOrder, $advisor, [
        'amount_cents' => $allowed + 1,
        'context_kind' => PaymentCaptureContextKind::Deposit,
        'capture_method' => PaymentCaptureMethod::Keyed,
        'source_token' => 'tok_test',
    ]))->toThrow(ValidationException::class);

    expect($captured)->toBeFalse()
        ->and(PaymentCaptureAttempt::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(0)
        ->and($repairOrder->fresh()->ledgerEntries()->count())->toBe(0);
});

test('deposit after an invoice is rejected before provider capture', function () {
    $repairOrder = financialCloseoutRepairOrder();
    app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder);
    $advisor = integrityCaptureAdvisor();

    $captured = false;
    Http::fake(function () use (&$captured) {
        $captured = true;

        return Http::response(['ok' => true, 'status' => 'succeeded'], 200);
    });

    expect(fn () => app(InitiatePaymentCaptureAction::class)->execute($repairOrder->fresh(), $advisor, [
        'amount_cents' => 2500,
        'context_kind' => PaymentCaptureContextKind::Deposit,
        'capture_method' => PaymentCaptureMethod::Keyed,
        'source_token' => 'tok_test',
    ]))->toThrow(ValidationException::class);

    expect($captured)->toBeFalse()
        ->and(PaymentCaptureAttempt::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(0)
        ->and($repairOrder->fresh()->ledgerEntries()->count())->toBe(0);
});

test('mismatched capture amount enters reconciliation and blocks another charge of that amount', function () {
    $repairOrder = financialCloseoutRepairOrder();
    app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder);
    $advisor = integrityCaptureAdvisor();
    $amount = $repairOrder->fresh()->balanceDue()->balanceDueCents;

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
        'initiated_by' => $advisor->id,
        'initiated_at' => now(),
    ]);

    $held = app(ApplyPaymentCaptureResultAction::class)->apply($attempt, [
        'status' => 'succeeded',
        'capture_id' => (string) Str::uuid(),
        'provider_payment_id' => 'stub_mismatch',
        'amount_cents' => $amount + 1,
    ]);

    expect($held->status)->toBe(PaymentCaptureAttemptStatus::ReconciliationRequired)
        ->and($held->ledger_entry_id)->toBeNull()
        ->and($held->provider_payment_id)->toBe('stub_mismatch')
        ->and($repairOrder->fresh()->ledgerEntries()->count())->toBe(0);

    $captured = false;
    Http::fake(function () use (&$captured) {
        $captured = true;

        return Http::response(['ok' => true, 'status' => 'succeeded'], 200);
    });

    expect(fn () => app(InitiatePaymentCaptureAction::class)->execute($repairOrder->fresh(), $advisor, [
        'amount_cents' => $amount,
        'context_kind' => PaymentCaptureContextKind::Payment,
        'capture_method' => PaymentCaptureMethod::Keyed,
        'source_token' => 'tok_test',
    ]))->toThrow(ValidationException::class);

    expect($captured)->toBeFalse()
        ->and(PaymentCaptureAttempt::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(1);

    $posted = app(ApplyPaymentCaptureResultAction::class)->apply($held->fresh(), [
        'status' => 'succeeded',
        'capture_id' => $held->cloud_capture_id,
        'provider_payment_id' => 'stub_mismatch',
        'amount_cents' => $amount,
    ]);

    expect($posted->status)->toBe(PaymentCaptureAttemptStatus::Succeeded)
        ->and($posted->provider_payment_id)->toBe('stub_mismatch')
        ->and($repairOrder->fresh()->ledgerEntries()->where('entry_type', 'payment')->count())->toBe(1);

    app(ApplyPaymentCaptureResultAction::class)->apply($posted->fresh(), [
        'status' => 'succeeded',
        'capture_id' => $posted->cloud_capture_id,
        'provider_payment_id' => 'stub_mismatch',
        'amount_cents' => $amount,
    ]);

    expect($repairOrder->fresh()->ledgerEntries()->where('entry_type', 'payment')->count())->toBe(1);
});

test('legacy poll success posts through the gateway result action', function () {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::InProgress);
    $attempt = PaymentGatewayAttempt::query()->create([
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $repairOrder->customer_id,
        'financial_document_id' => null,
        'gateway' => PaymentGateway::Managed,
        'capture_surface' => PaymentCaptureSurface::Terminal,
        'amount_cents' => 2500,
        'currency' => 'USD',
        'public_id' => (string) Str::uuid(),
        'idempotency_key' => (string) Str::uuid(),
        'status' => PaymentGatewayAttemptStatus::Pending,
        'initiated_at' => now(),
    ]);

    Http::fake([
        'cloud.example.test/api/v1/services/payments/captures/*' => Http::response([
            'ok' => true,
            'status' => 'succeeded',
            'amount_cents' => 2500,
            'provider_payment_id' => 'stub_poll_1',
            'currency' => 'USD',
        ], 200),
    ]);

    $polled = app(PollPlatformPaymentCaptureAction::class)->execute($attempt);

    expect($polled->status)->toBe(PaymentGatewayAttemptStatus::Completed)
        ->and($polled->ledger_entry_id)->not->toBeNull()
        ->and($repairOrder->fresh()->ledgerEntries()->where('entry_type', 'deposit')->count())->toBe(1);

    app(PollPlatformPaymentCaptureAction::class)->execute($polled->fresh());

    expect($repairOrder->fresh()->ledgerEntries()->where('entry_type', 'deposit')->count())->toBe(1);
});

test('legacy poll success without a provider payment id does not mark the attempt completed', function () {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::InProgress);
    $attempt = PaymentGatewayAttempt::query()->create([
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $repairOrder->customer_id,
        'financial_document_id' => null,
        'gateway' => PaymentGateway::Managed,
        'capture_surface' => PaymentCaptureSurface::Terminal,
        'amount_cents' => 2500,
        'currency' => 'USD',
        'public_id' => (string) Str::uuid(),
        'idempotency_key' => (string) Str::uuid(),
        'status' => PaymentGatewayAttemptStatus::Pending,
        'initiated_at' => now(),
    ]);

    Http::fake([
        'cloud.example.test/api/v1/services/payments/captures/*' => Http::response([
            'ok' => true,
            'status' => 'succeeded',
            'amount_cents' => 2500,
            'currency' => 'USD',
        ], 200),
    ]);

    $polled = app(PollPlatformPaymentCaptureAction::class)->execute($attempt);

    expect($polled->status)->toBe(PaymentGatewayAttemptStatus::Pending)
        ->and($polled->ledger_entry_id)->toBeNull()
        ->and($polled->completed_at)->toBeNull()
        ->and($repairOrder->fresh()->ledgerEntries()->count())->toBe(0);
});
