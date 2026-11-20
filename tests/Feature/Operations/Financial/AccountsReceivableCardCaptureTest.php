<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\CustomerPaymentReceipt;
use App\Ark\Operations\Financial\InitiateAccountsReceivableCaptureAction;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttempt;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttemptStatus;
use App\Ark\Operations\Payments\Capture\PaymentCaptureContextKind;
use App\Ark\Operations\Payments\Capture\PaymentCaptureMethod;
use App\Ark\Operations\Payments\Capture\PaymentCaptureReadinessProjection;
use App\Ark\Operations\RepairOrders\RepairOrderLifecycleTransition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalogDefaults;
use App\Ark\Operations\RepairOrders\WorksheetMutationIdempotency;
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
    RepairOrderStatusCatalogDefaults::sync(app(RepairOrderStatusCatalog::class));
    InstallationIdentity::write((string) Str::uuid());
    ShopSettings::current()->persistTrusted([
        'shop_name' => 'AR Capture Shop',
        'cloud_status' => 'connected',
        'cloud_base_url' => 'https://cloud.example.test',
        'cloud_shop_public_id' => (string) Str::uuid(),
        'cloud_credential' => 'test-credential-32-characters-min!!',
    ]);
    $this->advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($this->advisor);
});

function arCardOnAccountInvoice(?\App\Ark\Operations\Customers\Customer $customer = null): array
{
    $repairOrder = financialCloseoutRepairOrder();

    if ($customer !== null) {
        $repairOrder->forceFill([
            'customer_id' => $customer->id,
            'vehicle_id' => $customer->vehicles()->first()?->id ?? $repairOrder->vehicle_id,
        ])->save();
    }

    issueFinalInvoiceFor($repairOrder->fresh());

    $closed = app(RepairOrderLifecycleTransition::class)->move(
        $repairOrder->fresh(),
        RepairOrderStatus::Closed,
        auth()->user(),
        'on_account',
    );

    $invoice = app(BalanceDueCalculator::class)->issuedInvoice($closed);

    return [$closed->fresh(), $invoice];
}

test('staff VizyPay keyed capture allocates one charge across multiple AR invoices once', function () {
    [$roA, $invoiceA] = arCardOnAccountInvoice();
    $customer = $roA->customer;
    [$roB, $invoiceB] = arCardOnAccountInvoice($customer);

    $seenRequest = null;
    Http::fake(function (Request $request) use (&$seenRequest) {
        if ($request->method() === 'GET' && str_contains($request->url(), '/payments/readiness')) {
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
            $body = json_decode($request->body(), true) ?: [];
            $seenRequest = $body;

            return Http::response([
                'ok' => true,
                'status' => 'succeeded',
                'capture_id' => (string) Str::uuid(),
                'capture_attempt_public_id' => $body['capture_attempt_public_id'] ?? '',
                'idempotency_key' => $body['idempotency_key'] ?? '',
                'amount_cents' => $body['amount_cents'] ?? 30000,
                'currency' => 'USD',
                'context_kind' => PaymentCaptureContextKind::AccountsReceivable->value,
                'provider' => 'stub',
                'provider_payment_id' => 'stub_pay_ar_multi',
            ], 200);
        }

        return Http::response(['ok' => false], 404);
    });

    $intent = (string) Str::uuid();
    $result = app(InitiateAccountsReceivableCaptureAction::class)->execute(
        $customer->fresh(),
        [
            ['invoice_id' => $invoiceA->id, 'amount_cents' => 15000],
            ['invoice_id' => $invoiceB->id, 'amount_cents' => 15000],
        ],
        $this->advisor,
        [
            'capture_method' => PaymentCaptureMethod::Keyed,
            'source_token' => 'tok_test_ar',
            'intent_key' => $intent,
            'reference' => 'AR-CARD-1',
            'stub_scenario' => 'immediate_success',
        ],
    );

    $attempt = $result['attempt']->fresh();
    $receipt = CustomerPaymentReceipt::query()->find($attempt->customer_payment_receipt_id);

    expect($seenRequest)->not->toBeNull()
        ->and($seenRequest['amount_cents'] ?? null)->toBe(30000)
        ->and($seenRequest['context']['kind'] ?? null)->toBe(PaymentCaptureContextKind::AccountsReceivable->value)
        ->and($seenRequest['context']['invoice_ids'] ?? null)->toBe([$invoiceA->id, $invoiceB->id])
        ->and($attempt->status)->toBe(PaymentCaptureAttemptStatus::Succeeded)
        ->and($attempt->context_kind)->toBe(PaymentCaptureContextKind::AccountsReceivable)
        ->and($receipt)->not->toBeNull()
        ->and($receipt->amount_cents)->toBe(30000)
        ->and($receipt->allocated_cents)->toBe(30000)
        ->and($receipt->unapplied_cents)->toBe(0)
        ->and($receipt->change_cents)->toBe(0)
        ->and($receipt->ledgerEntries()->where('entry_type', LedgerEntryType::Payment)->count())->toBe(2)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($roA->fresh())->balanceDueCents)->toBe(0)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($roB->fresh())->balanceDueCents)->toBe(0);

    // Replay of the same capture success must not double-allocate.
    app(\App\Ark\Operations\Payments\Capture\ApplyPaymentCaptureResultAction::class)->apply($attempt, [
        'status' => 'succeeded',
        'capture_id' => $attempt->cloud_capture_id,
        'provider_payment_id' => 'stub_pay_ar_multi',
        'provider' => 'stub',
    ]);

    expect(CustomerPaymentReceipt::query()->count())->toBe(1)
        ->and(PaymentCaptureAttempt::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and($roA->fresh()->ledgerEntries()->where('entry_type', LedgerEntryType::Payment)->count())->toBe(1);
});

test('receive payment card route posts a multi-invoice AR capture through the staff UI', function () {
    [$roA, $invoiceA] = arCardOnAccountInvoice();
    $customer = $roA->customer;
    [$roB, $invoiceB] = arCardOnAccountInvoice($customer);

    Http::fake(function (Request $request) {
        if ($request->method() === 'GET' && str_contains($request->url(), '/payments/readiness')) {
            return Http::response([
                'ok' => true,
                'provider' => 'stub',
                'status' => 'connected',
                'supports_terminal' => false,
                'supports_keyed' => true,
                'supports_portal' => false,
                'available_devices' => [],
                'public_config' => null,
            ], 200);
        }

        if ($request->method() === 'POST' && str_contains($request->url(), '/payments/captures')) {
            $body = json_decode($request->body(), true) ?: [];

            return Http::response([
                'ok' => true,
                'status' => 'succeeded',
                'capture_id' => (string) Str::uuid(),
                'capture_attempt_public_id' => $body['capture_attempt_public_id'] ?? '',
                'idempotency_key' => $body['idempotency_key'] ?? '',
                'amount_cents' => $body['amount_cents'] ?? 30000,
                'currency' => 'USD',
                'context_kind' => PaymentCaptureContextKind::AccountsReceivable->value,
                'provider' => 'stub',
                'provider_payment_id' => 'stub_pay_ar_ui',
            ], 200);
        }

        return Http::response(['ok' => false], 404);
    });

    $this->get(route('operations.financials.accounts-receivable.receive-payment.create', $customer))
        ->assertOk()
        ->assertSee('Receive Payment');

    $this->post(route('operations.financials.accounts-receivable.receive-payment.card', $customer), [
        'capture_method' => PaymentCaptureMethod::Keyed->value,
        'source_token' => 'tok_ui_ar',
        'stub_scenario' => 'immediate_success',
        WorksheetMutationIdempotency::FIELD => (string) Str::uuid(),
        'allocations' => [
            ['invoice_id' => $invoiceA->id, 'selected' => '1', 'amount' => '150.00'],
            ['invoice_id' => $invoiceB->id, 'selected' => '1', 'amount' => '150.00'],
        ],
    ])->assertRedirect(route('operations.financials.accounts-receivable.customer', $customer));

    expect(app(BalanceDueCalculator::class)->forRepairOrder($roA->fresh())->balanceDueCents)->toBe(0)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($roB->fresh())->balanceDueCents)->toBe(0)
        ->and(CustomerPaymentReceipt::query()->where('customer_id', $customer->id)->count())->toBe(1);
});
