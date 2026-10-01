<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\Inspections\InspectionObservedState;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttempt;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttemptStatus;
use App\Ark\Operations\Payments\Capture\PaymentCaptureReadinessProjection;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Portal\InspectionAccessToken;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
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
        'shop_name' => 'Acceptance Shop',
        'cloud_status' => 'connected',
        'cloud_base_url' => 'https://cloud.example.test',
        'cloud_shop_public_id' => (string) Str::uuid(),
        'cloud_credential' => 'test-credential-32-characters-min!!',
        'platform_status' => 'connected',
        'platform_base_url' => 'https://cloud.example.test',
        'platform_credential' => 'test-credential-32-characters-min!!',
    ]);
});

function acceptanceAdvisor(): User
{
    return User::factory()->create()->assignRole(ArkRole::Advisor->value);
}

function acceptanceAdmin(): User
{
    return User::factory()->create()->assignRole(ArkRole::Admin->value);
}

function fakePlatformSandbox(): void
{
    Http::fake(function (Request $request) {
        $url = $request->url();
        $body = json_decode($request->body(), true) ?: [];

        expect($url)->not->toContain('twilio.com')
            ->and($url)->not->toContain('squareup');

        if (str_contains($url, '/payments/readiness')) {
            return Http::response([
                'ok' => true,
                'provider' => 'stub',
                'status' => 'connected',
                'supports_terminal' => true,
                'supports_keyed' => true,
                'available_devices' => [
                    ['device_ref' => 'stub-front-counter', 'label' => 'Front Counter', 'ready' => true],
                ],
            ], 200);
        }

        if ($request->method() === 'POST' && str_contains($url, '/payments/captures')) {
            return Http::response([
                'ok' => true,
                'status' => 'pending',
                'capture_id' => (string) Str::uuid(),
                'capture_attempt_public_id' => $body['capture_attempt_public_id'] ?? '',
                'idempotency_key' => $body['idempotency_key'] ?? '',
                'amount_cents' => $body['amount_cents'] ?? 0,
                'currency' => 'USD',
                'capture_method' => 'terminal',
                'provider' => 'stub',
            ], 200);
        }

        if ($request->method() === 'GET' && str_contains($url, '/payments/captures/')) {
            return Http::response([
                'ok' => true,
                'status' => 'succeeded',
                'capture_id' => (string) Str::uuid(),
                'amount_cents' => 25000,
                'currency' => 'USD',
                'provider' => 'stub',
                'provider_payment_id' => 'stub_term_accept',
            ], 200);
        }

        if (str_contains($url, '/services/sms/messages/conversation')) {
            return Http::response([
                'ok' => true,
                'status' => 'queued',
                'message_id' => 'plat-msg-accept',
                'provider_message_id' => 'SM-platform-stub',
            ], 200);
        }

        return Http::response(['ok' => true], 200);
    });
}

test('workflow repair order creates labor parts public id and balance', function () {
    $repairOrder = repairOrderWithSuggestedPartDeposit(RepairOrderStatus::Estimate);

    expect($repairOrder->ensurePublicId())->not->toBe('')
        ->and(Str::isUuid($repairOrder->ensurePublicId()))->toBeTrue()
        ->and($repairOrder->lines()->where('type', RepairOrderLineType::Labor->value)->count())->toBe(1)
        ->and($repairOrder->lines()->where('type', RepairOrderLineType::Part->value)->count())->toBe(1);

    $balance = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh());

    expect($balance->balanceDueCents)->toBe(0)
        ->and($repairOrder->fresh()->lines()->sum('total_cents'))->toBeGreaterThan(0);
});

test('workflow terminal payment writes one ledger entry and ignores replay', function () {
    fakePlatformSandbox();

    $repairOrder = repairOrderWithSuggestedPartDeposit(RepairOrderStatus::ReadyPickup);
    issueFinalInvoiceFor($repairOrder);
    $amountCents = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents;
    expect($amountCents)->toBeGreaterThan(0);

    $advisor = acceptanceAdvisor();

    $initiate = $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.payment-capture.store', $repairOrder), [
            'amount' => number_format($amountCents / 100, 2, '.', ''),
            'context_kind' => 'payment',
            'capture_method' => 'terminal',
            'device_ref' => 'stub-front-counter',
        ]);

    $initiate->assertOk()->assertJsonPath('attempt.status', PaymentCaptureAttemptStatus::Pending->value);
    $attemptId = $initiate->json('attempt.id');

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.payment-capture.refresh', [$repairOrder, $attemptId]))
        ->assertOk()
        ->assertJsonPath('attempt.status', PaymentCaptureAttemptStatus::Succeeded->value);

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.payment-capture.refresh', [$repairOrder, $attemptId]))
        ->assertOk();

    expect(PaymentCaptureAttempt::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(1)
        ->and($repairOrder->fresh()->ledgerEntries()->where('entry_type', 'payment')->count())->toBe(1)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe(0);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/payments/captures')
        && ! str_contains($request->url(), 'squareup'));
});

test('workflow communications saves settings and sends inspection through platform', function () {
    fakePlatformSandbox();

    $this->actingAs(acceptanceAdmin())
        ->patch(route('operations.settings.shop.customer-messaging.update'), [
            'postmark_reply_to' => 'shop@example.test',
            'telephony_call_flow' => [
                'missed_call_rescue_enabled' => '1',
                'missed_call_rescue_delay_seconds' => 90,
            ],
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'customer-messaging']));

    ShopSettings::forgetCurrent();
    expect(ShopSettings::current()->postmark_reply_to)->toBe('shop@example.test');

    $phone = '7195551212';
    PhoneSmsCapability::query()->updateOrCreate(
        ['normalized_phone' => PhoneNumber::normalize($phone) ?? $phone],
        [
            'valid' => true,
            'line_type' => 'mobile',
            'sms_capable' => true,
            'checked_at' => now(),
        ],
    );

    $repairOrder = repairOrderWithSuggestedPartDeposit(RepairOrderStatus::InProgress);
    $repairOrder->customer->update(['phone' => $phone]);
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, null);
    InspectionItem::query()->create([
        'inspection_id' => $inspection->id,
        'category' => 'brakes',
        'label' => 'Front pads',
        'observed_state' => InspectionObservedState::Fail->value,
        'position' => 0,
    ]);

    $response = $this->actingAs(acceptanceAdvisor())
        ->postJson(route('operations.repair-orders.conversation-actions.send-inspection', $repairOrder->fresh()));

    $response->assertOk();
    expect($response->json('inspection_url'))->toContain('/portal/inspections/');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/api/v1/services/sms/messages/conversation')
        && ($request->data()['operation'] ?? null) === 'conversation.send'
        && ! str_contains($request->url(), 'twilio.com'));
});

test('workflow inspections generate a token portal that customers can open', function () {
    $repairOrder = repairOrderWithSuggestedPartDeposit(RepairOrderStatus::InProgress);
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, null);
    InspectionItem::query()->create([
        'inspection_id' => $inspection->id,
        'category' => 'brakes',
        'checklist_category_name' => 'Brakes',
        'label' => 'Front brake pads',
        'observed_state' => InspectionObservedState::Fail->value,
        'notes' => 'Pads worn.',
        'position' => 0,
    ]);

    $response = $this->actingAs(acceptanceAdvisor())
        ->getJson(route('operations.repair-orders.inspection-portal-link', $repairOrder));

    $response->assertOk();
    $url = (string) $response->json('url');
    expect($url)->toContain('/portal/inspections/');

    $token = str($url)->afterLast('/')->toString();
    expect(InspectionAccessToken::query()->count())->toBe(1);

    $this->get(route('portal.inspections.show', ['token' => $token]))
        ->assertOk()
        ->assertSee('Vehicle Inspection')
        ->assertSee('Front brake pads');
});
