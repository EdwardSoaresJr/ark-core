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

function staffPaymentCaptureAdvisor(): User
{
    return User::factory()->create()->assignRole(ArkRole::Advisor->value);
}

test('staff terminal capture records one ledger payment through payment-capture', function () {
    $repairOrder = financialCloseoutRepairOrder();
    app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder);
    $advisor = staffPaymentCaptureAdvisor();
    $amountCents = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents;
    expect($amountCents)->toBeGreaterThan(0);

    Http::fake(function (\Illuminate\Http\Client\Request $request) use ($amountCents) {
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

        if ($request->method() === 'POST' && str_contains($request->url(), '/payments/captures')) {
            expect($body['capture_method'] ?? null)->toBe('terminal')
                ->and($body['device_ref'] ?? null)->toBe('stub-front-counter')
                ->and($body['amount_cents'] ?? null)->toBe($amountCents)
                ->and($body)->not->toHaveKey('square_access_token')
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

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Take payment', false)
        ->assertSee('Terminal', false)
        ->assertSee('>Manual</button>', false)
        ->assertDontSee('>Card entry</button>', false);

    $initiate = $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.payment-capture.store', $repairOrder), [
            'amount' => number_format($amountCents / 100, 2, '.', ''),
            'context_kind' => 'payment',
            'capture_method' => 'terminal',
            'device_ref' => 'stub-front-counter',
        ]);

    $initiate->assertOk()
        ->assertJsonPath('attempt.status', PaymentCaptureAttemptStatus::Pending->value)
        ->assertJsonPath('attempt.capture_method', 'terminal');

    $attemptId = $initiate->json('attempt.id');
    expect($attemptId)->not->toBeNull();

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.payment-capture.refresh', [$repairOrder, $attemptId]))
        ->assertOk()
        ->assertJsonPath('attempt.status', PaymentCaptureAttemptStatus::Succeeded->value);

    $attempt = PaymentCaptureAttempt::query()->find($attemptId);
    expect($attempt?->status)->toBe(PaymentCaptureAttemptStatus::Succeeded)
        ->and($attempt?->ledger_entry_id)->not->toBeNull()
        ->and($repairOrder->fresh()->ledgerEntries()->where('entry_type', 'payment')->count())->toBe(1)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe(0);

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.payment-capture.refresh', [$repairOrder, $attemptId]))
        ->assertOk();

    expect($repairOrder->fresh()->ledgerEntries()->where('entry_type', 'payment')->count())->toBe(1)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe(0);
});

test('staff can cancel a waiting terminal capture without recording a payment', function () {
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->forceFill(['repair_order_id' => (int) $repairOrder->id + 8000])->save();
    $repairOrder = $repairOrder->fresh();
    expect($repairOrder->repair_order_id)->not->toBe((int) $repairOrder->id);

    app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder);
    $advisor = staffPaymentCaptureAdvisor();
    $amountCents = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents;

    Http::fake(function (\Illuminate\Http\Client\Request $request) use ($amountCents) {
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

        if (str_contains($request->url(), '/cancel')) {
            $attempt = PaymentCaptureAttempt::query()->latest('id')->first();

            return Http::response([
                'ok' => true,
                'status' => 'cancelled',
                'capture_id' => (string) Str::uuid(),
                'capture_attempt_public_id' => $attempt?->public_id,
                'idempotency_key' => $attempt?->idempotency_key,
                'amount_cents' => $amountCents,
                'reason_code' => 'cancelled',
            ], 200);
        }

        if ($request->method() === 'POST' && str_contains($request->url(), '/payments/captures')) {
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

        return Http::response(['ok' => false], 404);
    });

    $attemptId = $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.payment-capture.store', $repairOrder), [
            'amount' => number_format($amountCents / 100, 2, '.', ''),
            'context_kind' => 'payment',
            'capture_method' => 'terminal',
            'device_ref' => 'stub-front-counter',
        ])
        ->assertOk()
        ->json('attempt.id');

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Cancel request', false)
        ->assertSee('Waiting on terminal', false)
        ->assertSee(
            'repair-orders\\/'.$repairOrder->repair_order_id.'\\/payment-capture\\/__ID__\\/cancel',
            false,
        )
        ->assertDontSee(
            'repair-orders\\/'.$repairOrder->id.'\\/payment-capture',
            false,
        );

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.payment-capture.cancel', [$repairOrder, $attemptId]))
        ->assertOk()
        ->assertJsonPath('attempt.status', PaymentCaptureAttemptStatus::Cancelled->value);

    $attempt = PaymentCaptureAttempt::query()->find($attemptId);
    expect($attempt?->status)->toBe(PaymentCaptureAttemptStatus::Cancelled)
        ->and($attempt?->ledger_entry_id)->toBeNull()
        ->and($repairOrder->fresh()->ledgerEntries()->where('entry_type', 'payment')->count())->toBe(0);
});
