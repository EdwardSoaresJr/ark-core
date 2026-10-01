<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\GenerateInvoiceSnapshotAction;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttempt;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttemptStatus;
use App\Ark\Operations\Payments\Capture\PaymentCaptureReadinessProjection;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    PaymentCaptureReadinessProjection::resetMemo();
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

test('mobile terminal payment-capture records one ledger payment through the public contract', function () {
    $repairOrder = financialCloseoutRepairOrder();
    app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;
    $amountCents = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents;
    expect($amountCents)->toBeGreaterThan(0);

    Http::fake(function (Request $request) use ($amountCents) {
        $body = json_decode($request->body(), true) ?: [];

        if (str_contains($request->url(), '/payments/readiness')) {
            return Http::response([
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
            ], 200);
        }

        if ($request->method() === 'POST' && str_contains($request->url(), '/payments/captures') && ! str_contains($request->url(), '/cancel')) {
            expect($body['capture_method'] ?? null)->toBe('terminal')
                ->and($body['device_ref'] ?? null)->toBe('stub-front-counter')
                ->and($body['amount_cents'] ?? null)->toBe($amountCents)
                ->and($request->url())->not->toContain('squareup');

            return Http::response([
                'ok' => true,
                'status' => 'pending',
                'capture_id' => (string) Str::uuid(),
                'capture_attempt_public_id' => $body['capture_attempt_public_id'] ?? '',
                'idempotency_key' => $body['idempotency_key'] ?? '',
                'amount_cents' => $amountCents,
                'currency' => 'USD',
                'context_kind' => 'payment',
                'capture_method' => 'terminal',
                'provider' => 'stub',
                'provider_payment_id' => null,
            ], 200);
        }

        if ($request->method() === 'GET' && str_contains($request->url(), '/payments/captures/')) {
            return Http::response([
                'ok' => true,
                'status' => 'succeeded',
                'capture_id' => (string) Str::uuid(),
                'amount_cents' => $amountCents,
                'currency' => 'USD',
                'context_kind' => 'payment',
                'provider' => 'stub',
                'provider_payment_id' => 'stub_term_1',
            ], 200);
        }

        return Http::response(['ok' => false], 404);
    });

    $this->withToken($token)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->id)
        ->assertOk()
        ->assertJsonPath('workspace.payment_capture.can_charge_terminal', true)
        ->assertJsonPath('workspace.payment_capture.default_device_ref', 'stub-front-counter')
        ->assertJsonMissingPath('workspace.payment_capture.square_access_token');

    $initiate = $this->withToken($token)
        ->postJson('/api/mobile/repair-orders/'.$repairOrder->id.'/payment-capture', [
            'amount' => number_format($amountCents / 100, 2, '.', ''),
            'context_kind' => 'payment',
            'capture_method' => 'terminal',
            'device_ref' => 'stub-front-counter',
        ]);

    $initiate->assertOk()
        ->assertJsonPath('attempt.status', PaymentCaptureAttemptStatus::Pending->value)
        ->assertJsonPath('attempt.capture_method', 'terminal')
        ->assertJsonPath('attempt.device_ref', 'stub-front-counter');

    $attemptId = $initiate->json('attempt.id');
    expect($attemptId)->not->toBeNull();

    $this->withToken($token)
        ->postJson('/api/mobile/repair-orders/'.$repairOrder->id.'/payment-capture/'.$attemptId.'/refresh')
        ->assertOk()
        ->assertJsonPath('attempt.status', PaymentCaptureAttemptStatus::Succeeded->value);

    $attempt = PaymentCaptureAttempt::query()->find($attemptId);
    expect($attempt?->status)->toBe(PaymentCaptureAttemptStatus::Succeeded)
        ->and($attempt?->ledger_entry_id)->not->toBeNull()
        ->and($repairOrder->fresh()->ledgerEntries()->where('entry_type', 'payment')->count())->toBe(1)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe(0);
});

test('mobile payment-capture rejects a terminal charge without a device', function () {
    $repairOrder = financialCloseoutRepairOrder();
    app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->withToken($advisor->createToken('test')->plainTextToken)
        ->postJson('/api/mobile/repair-orders/'.$repairOrder->id.'/payment-capture', [
            'amount' => '10.00',
            'context_kind' => 'payment',
            'capture_method' => 'terminal',
        ])
        ->assertUnprocessable();
});
