<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\CustomerPaymentReceipt;
use App\Ark\Operations\Financial\InitiateAccountsReceivableCaptureAction;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\ReconcileAccountsReceivableCaptureAction;
use App\Ark\Operations\Payments\Capture\ApplyPaymentCaptureResultAction;
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

test('processor success survives allocation failure and blocks a duplicate charge', function () {
    [$roA, $invoiceA] = arCardOnAccountInvoice();
    $customer = $roA->customer;
    [$roB, $invoiceB] = arCardOnAccountInvoice($customer);

    $capturePosts = 0;
    Http::fake(function (Request $request) use (&$capturePosts) {
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
            $capturePosts++;

            return Http::response(['ok' => false, 'reason' => 'should_not_charge_again'], 500);
        }

        return Http::response(['ok' => false], 404);
    });

    $intent = (string) Str::uuid();
    $publicId = (string) Str::uuid();
    $attempt = PaymentCaptureAttempt::query()->create([
        'public_id' => $publicId,
        'repair_order_id' => $roA->id,
        'customer_id' => $customer->id,
        'amount_cents' => 30000,
        'currency' => 'USD',
        'context_kind' => PaymentCaptureContextKind::AccountsReceivable,
        'capture_method' => PaymentCaptureMethod::Keyed,
        'status' => PaymentCaptureAttemptStatus::Pending,
        'idempotency_key' => 'core-'.$publicId,
        'submission_intent_key' => $intent,
        'initiated_by' => $this->advisor->id,
        'initiated_at' => now(),
        // Empty allocations force a post-charge ledger failure while retaining the processor ids.
        'allocation_plan' => [
            'intent_key' => $intent,
            'reference' => 'AR-RECON',
            'disposition' => 'keep_credit',
            'allocations' => [],
        ],
    ]);

    $failed = app(ApplyPaymentCaptureResultAction::class)->apply($attempt, [
        'status' => 'succeeded',
        'capture_id' => (string) Str::uuid(),
        'provider' => 'vizypay',
        'provider_payment_id' => 'vizy_pay_recon_1',
        'amount_cents' => 30000,
    ]);

    expect($failed->status)->toBe(PaymentCaptureAttemptStatus::ReconciliationRequired)
        ->and($failed->provider_payment_id)->toBe('vizy_pay_recon_1')
        ->and($failed->provider)->toBe('vizypay')
        ->and($failed->customer_payment_receipt_id)->toBeNull()
        ->and(CustomerPaymentReceipt::query()->count())->toBe(0)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($roA->fresh())->balanceDueCents)->toBe(15000);

    expect(fn () => app(InitiateAccountsReceivableCaptureAction::class)->execute(
        $customer->fresh(),
        [
            ['invoice_id' => $invoiceA->id, 'amount_cents' => 15000],
            ['invoice_id' => $invoiceB->id, 'amount_cents' => 15000],
        ],
        $this->advisor,
        [
            'capture_method' => PaymentCaptureMethod::Keyed,
            'source_token' => 'tok_recon_retry',
            'intent_key' => $intent,
            'stub_scenario' => 'immediate_success',
        ],
    ))->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(fn () => app(InitiateAccountsReceivableCaptureAction::class)->execute(
        $customer->fresh(),
        [
            ['invoice_id' => $invoiceA->id, 'amount_cents' => 15000],
            ['invoice_id' => $invoiceB->id, 'amount_cents' => 15000],
        ],
        $this->advisor,
        [
            'capture_method' => PaymentCaptureMethod::Keyed,
            'source_token' => 'tok_recon_new',
            'intent_key' => (string) Str::uuid(),
            'stub_scenario' => 'immediate_success',
        ],
    ))->toThrow(\Illuminate\Validation\ValidationException::class);

    expect($capturePosts)->toBe(0);

    $failed->forceFill([
        'allocation_plan' => [
            'intent_key' => $intent,
            'reference' => 'AR-RECON',
            'disposition' => 'keep_credit',
            'allocations' => [
                ['invoice_id' => $invoiceA->id, 'amount_cents' => 15000],
                ['invoice_id' => $invoiceB->id, 'amount_cents' => 15000],
            ],
        ],
    ])->save();

    $reconciled = app(ReconcileAccountsReceivableCaptureAction::class)->execute($failed->fresh(), $this->advisor);

    expect($reconciled->status)->toBe(PaymentCaptureAttemptStatus::Succeeded)
        ->and($reconciled->provider_payment_id)->toBe('vizy_pay_recon_1')
        ->and($reconciled->customer_payment_receipt_id)->not->toBeNull()
        ->and(CustomerPaymentReceipt::query()->count())->toBe(1)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($roA->fresh())->balanceDueCents)->toBe(0)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($roB->fresh())->balanceDueCents)->toBe(0)
        ->and($capturePosts)->toBe(0);

    app(ApplyPaymentCaptureResultAction::class)->apply($reconciled->fresh(), [
        'status' => 'succeeded',
        'capture_id' => $reconciled->cloud_capture_id,
        'provider_payment_id' => 'vizy_pay_recon_1',
        'provider' => 'vizypay',
    ]);

    expect(CustomerPaymentReceipt::query()->count())->toBe(1)
        ->and(PaymentCaptureAttempt::query()->where('customer_id', $customer->id)->count())->toBe(1);
});

test('failed allocation reconciliation persists reconciliation_required after the rolled-back transaction', function () {
    [$roA, $invoiceA] = arCardOnAccountInvoice();
    $customer = $roA->customer;

    $publicId = (string) Str::uuid();
    $intent = (string) Str::uuid();
    $attempt = PaymentCaptureAttempt::query()->create([
        'public_id' => $publicId,
        'repair_order_id' => $roA->id,
        'customer_id' => $customer->id,
        'amount_cents' => 15000,
        'currency' => 'USD',
        'context_kind' => PaymentCaptureContextKind::AccountsReceivable,
        'capture_method' => PaymentCaptureMethod::Keyed,
        'status' => PaymentCaptureAttemptStatus::ReconciliationRequired,
        'idempotency_key' => 'core-'.$publicId,
        'submission_intent_key' => $intent,
        'provider' => 'vizypay',
        'provider_payment_id' => 'vizy_pay_recon_fail',
        'initiated_by' => $this->advisor->id,
        'initiated_at' => now(),
        'failure_reason' => 'allocation_pending',
        'allocation_plan' => [
            'intent_key' => $intent,
            'disposition' => 'keep_credit',
            'allocations' => [],
        ],
    ]);

    expect(fn () => app(ReconcileAccountsReceivableCaptureAction::class)->execute($attempt->fresh(), $this->advisor))
        ->toThrow(RuntimeException::class);

    $after = $attempt->fresh();

    expect($after->status)->toBe(PaymentCaptureAttemptStatus::ReconciliationRequired)
        ->and($after->failure_reason)->toBe('allocation_failed')
        ->and($after->provider_payment_id)->toBe('vizy_pay_recon_fail')
        ->and($after->customer_payment_receipt_id)->toBeNull()
        ->and(CustomerPaymentReceipt::query()->count())->toBe(0);
});

test('succeeded capture intent key rejects a different payload instead of replaying', function () {
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
                'amount_cents' => $body['amount_cents'] ?? 15000,
                'currency' => 'USD',
                'context_kind' => PaymentCaptureContextKind::AccountsReceivable->value,
                'provider' => 'stub',
                'provider_payment_id' => 'stub_pay_intent_fp',
            ], 200);
        }

        return Http::response(['ok' => false], 404);
    });

    $intent = (string) Str::uuid();
    $first = app(InitiateAccountsReceivableCaptureAction::class)->execute(
        $customer->fresh(),
        [['invoice_id' => $invoiceA->id, 'amount_cents' => 15000]],
        $this->advisor,
        [
            'capture_method' => PaymentCaptureMethod::Keyed,
            'source_token' => 'tok_fp',
            'intent_key' => $intent,
            'stub_scenario' => 'immediate_success',
        ],
    );

    expect($first['attempt']->status)->toBe(PaymentCaptureAttemptStatus::Succeeded);

    expect(fn () => app(InitiateAccountsReceivableCaptureAction::class)->execute(
        $customer->fresh(),
        [['invoice_id' => $invoiceB->id, 'amount_cents' => 15000]],
        $this->advisor,
        [
            'capture_method' => PaymentCaptureMethod::Keyed,
            'source_token' => 'tok_fp_other',
            'intent_key' => $intent,
            'stub_scenario' => 'immediate_success',
        ],
    ))->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(PaymentCaptureAttempt::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and(CustomerPaymentReceipt::query()->where('customer_id', $customer->id)->count())->toBe(1);
});

test('receive payment mounts Web Payments public config when Platform advertises keyed capture', function () {
    Http::fake(function (Request $request) {
        if ($request->method() === 'GET' && str_contains($request->url(), '/payments/readiness')) {
            return Http::response(platformPaymentReadinessPayload([
                'supports_saved_cards' => false,
            ]), 200);
        }

        return Http::response(['ok' => false], 404);
    });

    [$roA] = arCardOnAccountInvoice();
    $customer = $roA->customer;

    $this->get(route('operations.financials.accounts-receivable.receive-payment.create', $customer))
        ->assertOk()
        ->assertSee('arReceivePayment(')
        ->assertSee('sandbox-sq0idb-public')
        ->assertSee('LOC_PUBLIC')
        ->assertSee('Platform reports saved cards are not available for this shop.');
});

test('verified processor refund settlement clears pending card refund only with provider refund id', function () {
    [$repairOrder, $invoice] = arCardOnAccountInvoice();
    $customer = $repairOrder->customer;

    $receipt = app(\App\Ark\Operations\Financial\AllocateCustomerPaymentAction::class)->execute(
        $customer,
        20000,
        \App\Ark\Operations\Financial\PaymentMethod::Card,
        [['invoice_id' => $invoice->id, 'amount_cents' => 15000]],
        (string) Str::uuid(),
        $this->advisor,
        'CARD-PROC-REFUND',
        null,
        \App\Ark\Operations\Financial\OverpaymentDisposition::Refund,
    );

    expect($receipt->refund_pending_cents)->toBe(5000);

    $publicId = (string) Str::uuid();
    PaymentCaptureAttempt::query()->create([
        'public_id' => $publicId,
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $customer->id,
        'amount_cents' => 20000,
        'currency' => 'USD',
        'context_kind' => PaymentCaptureContextKind::AccountsReceivable,
        'capture_method' => PaymentCaptureMethod::Keyed,
        'status' => PaymentCaptureAttemptStatus::Succeeded,
        'idempotency_key' => 'core-'.$publicId,
        'submission_intent_key' => (string) Str::uuid(),
        'provider' => 'stub',
        'provider_payment_id' => 'stub_pay_refund_source',
        'cloud_capture_id' => (string) Str::uuid(),
        'customer_payment_receipt_id' => $receipt->id,
        'initiated_by' => $this->advisor->id,
        'initiated_at' => now(),
        'completed_at' => now(),
    ]);

    $refundPosts = [];
    Http::fake(function (Request $request) use (&$refundPosts) {
        if ($request->method() === 'GET' && str_contains($request->url(), '/payments/refunds/')) {
            return Http::response(['ok' => false], 404);
        }
        if ($request->method() === 'POST' && str_contains($request->url(), '/payments/refunds')) {
            $body = json_decode($request->body(), true) ?: [];
            $refundPosts[] = $body;

            return Http::response([
                'ok' => true,
                'status' => 'succeeded',
                'provider_refund_id' => 'ref_verified_991',
                'idempotency_key' => $body['idempotency_key'] ?? null,
            ], 200);
        }

        return Http::response(['ok' => false], 404);
    });

    $settled = app(\App\Ark\Operations\Financial\SettleCustomerPaymentProcessorRefundAction::class)
        ->execute($receipt->fresh(), $this->advisor);

    expect($refundPosts)->toHaveCount(1)
        ->and($refundPosts[0]['amount_cents'] ?? null)->toBe(5000)
        ->and($refundPosts[0]['provider_payment_id'] ?? null)->toBe('stub_pay_refund_source')
        ->and($refundPosts[0]['idempotency_key'] ?? null)->toBe('ar-refund-'.$receipt->id)
        ->and($settled['provider_refund_id'])->toBe('ref_verified_991')
        ->and($settled['replayed'])->toBeFalse()
        ->and($settled['receipt']->refund_pending_cents)->toBe(0)
        ->and($settled['receipt']->change_cents)->toBe(5000)
        ->and($settled['receipt']->refund_evidence_kind)->toBe('processor_settlement')
        ->and($settled['receipt']->refund_evidence_reference)->toBe('ref_verified_991')
        ->and($settled['receipt']->refund_confirmed_by)->toBe($this->advisor->id);
});

test('processor refund remains pending when Platform has not shipped verified refunds', function () {
    [$repairOrder, $invoice] = arCardOnAccountInvoice();
    $customer = $repairOrder->customer;

    $receipt = app(\App\Ark\Operations\Financial\AllocateCustomerPaymentAction::class)->execute(
        $customer,
        20000,
        \App\Ark\Operations\Financial\PaymentMethod::Card,
        [['invoice_id' => $invoice->id, 'amount_cents' => 15000]],
        (string) Str::uuid(),
        $this->advisor,
        'CARD-PROC-UNSUPPORTED',
        null,
        \App\Ark\Operations\Financial\OverpaymentDisposition::Refund,
    );

    $publicId = (string) Str::uuid();
    PaymentCaptureAttempt::query()->create([
        'public_id' => $publicId,
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $customer->id,
        'amount_cents' => 20000,
        'currency' => 'USD',
        'context_kind' => PaymentCaptureContextKind::AccountsReceivable,
        'capture_method' => PaymentCaptureMethod::Keyed,
        'status' => PaymentCaptureAttemptStatus::Succeeded,
        'idempotency_key' => 'core-'.$publicId,
        'submission_intent_key' => (string) Str::uuid(),
        'provider' => 'stub',
        'provider_payment_id' => 'stub_pay_unsupported',
        'customer_payment_receipt_id' => $receipt->id,
        'initiated_by' => $this->advisor->id,
        'initiated_at' => now(),
        'completed_at' => now(),
    ]);

    Http::fake(fn () => Http::response([
        'ok' => false,
        'reason_code' => 'not_supported',
        'message' => 'Refunds are not enabled.',
    ], 404));

    expect(fn () => app(\App\Ark\Operations\Financial\SettleCustomerPaymentProcessorRefundAction::class)
        ->execute($receipt->fresh(), $this->advisor))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    expect($receipt->fresh()->refund_pending_cents)->toBe(5000)
        ->and($receipt->fresh()->refund_confirmed_at)->toBeNull();
});

test('accepted or pending processor refund responses leave the liability outstanding', function () {
    [$repairOrder, $invoice] = arCardOnAccountInvoice();
    $customer = $repairOrder->customer;

    $receipt = app(\App\Ark\Operations\Financial\AllocateCustomerPaymentAction::class)->execute(
        $customer,
        20000,
        \App\Ark\Operations\Financial\PaymentMethod::Card,
        [['invoice_id' => $invoice->id, 'amount_cents' => 15000]],
        (string) Str::uuid(),
        $this->advisor,
        'CARD-PROC-PENDING',
        null,
        \App\Ark\Operations\Financial\OverpaymentDisposition::Refund,
    );

    $publicId = (string) Str::uuid();
    PaymentCaptureAttempt::query()->create([
        'public_id' => $publicId,
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $customer->id,
        'amount_cents' => 20000,
        'currency' => 'USD',
        'context_kind' => PaymentCaptureContextKind::AccountsReceivable,
        'capture_method' => PaymentCaptureMethod::Keyed,
        'status' => PaymentCaptureAttemptStatus::Succeeded,
        'idempotency_key' => 'core-'.$publicId,
        'submission_intent_key' => (string) Str::uuid(),
        'provider' => 'stub',
        'provider_payment_id' => 'stub_pay_pending_refund',
        'customer_payment_receipt_id' => $receipt->id,
        'initiated_by' => $this->advisor->id,
        'initiated_at' => now(),
        'completed_at' => now(),
    ]);

    foreach (['accepted', 'pending', 'initiated'] as $status) {
        Http::fake(function (Request $request) use ($status) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/payments/refunds/')) {
                return Http::response(['ok' => false], 404);
            }
            if ($request->method() === 'POST' && str_contains($request->url(), '/payments/refunds')) {
                return Http::response([
                    'ok' => true,
                    'status' => $status,
                    'provider_refund_id' => null,
                ], 200);
            }

            return Http::response(['ok' => false], 404);
        });

        expect(fn () => app(\App\Ark\Operations\Financial\SettleCustomerPaymentProcessorRefundAction::class)
            ->execute($receipt->fresh(), $this->advisor))
            ->toThrow(\Illuminate\Validation\ValidationException::class);

        expect($receipt->fresh()->refund_pending_cents)->toBe(5000)
            ->and($receipt->fresh()->refund_confirmed_at)->toBeNull();
    }
});

test('successful processor refund settlement is idempotent and retries do not create a second refund', function () {
    [$repairOrder, $invoice] = arCardOnAccountInvoice();
    $customer = $repairOrder->customer;

    $receipt = app(\App\Ark\Operations\Financial\AllocateCustomerPaymentAction::class)->execute(
        $customer,
        20000,
        \App\Ark\Operations\Financial\PaymentMethod::Card,
        [['invoice_id' => $invoice->id, 'amount_cents' => 15000]],
        (string) Str::uuid(),
        $this->advisor,
        'CARD-PROC-IDEMP',
        null,
        \App\Ark\Operations\Financial\OverpaymentDisposition::Refund,
    );

    $publicId = (string) Str::uuid();
    PaymentCaptureAttempt::query()->create([
        'public_id' => $publicId,
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $customer->id,
        'amount_cents' => 20000,
        'currency' => 'USD',
        'context_kind' => PaymentCaptureContextKind::AccountsReceivable,
        'capture_method' => PaymentCaptureMethod::Keyed,
        'status' => PaymentCaptureAttemptStatus::Succeeded,
        'idempotency_key' => 'core-'.$publicId,
        'submission_intent_key' => (string) Str::uuid(),
        'provider' => 'stub',
        'provider_payment_id' => 'stub_pay_refund_idem',
        'cloud_capture_id' => (string) Str::uuid(),
        'customer_payment_receipt_id' => $receipt->id,
        'initiated_by' => $this->advisor->id,
        'initiated_at' => now(),
        'completed_at' => now(),
    ]);

    $createPosts = [];
    Http::fake(function (Request $request) use (&$createPosts) {
        if ($request->method() === 'GET' && str_contains($request->url(), '/payments/refunds/')) {
            return Http::response(['ok' => false], 404);
        }
        if ($request->method() === 'POST' && str_contains($request->url(), '/payments/refunds')) {
            $body = json_decode($request->body(), true) ?: [];
            $createPosts[] = $body;

            return Http::response([
                'ok' => true,
                'status' => 'succeeded',
                'provider_refund_id' => 'ref_idem_1',
                'idempotency_key' => $body['idempotency_key'] ?? null,
            ], 200);
        }

        return Http::response(['ok' => false], 404);
    });

    $first = app(\App\Ark\Operations\Financial\SettleCustomerPaymentProcessorRefundAction::class)
        ->execute($receipt->fresh(), $this->advisor);
    $second = app(\App\Ark\Operations\Financial\SettleCustomerPaymentProcessorRefundAction::class)
        ->execute($receipt->fresh(), $this->advisor);

    expect($createPosts)->toHaveCount(1)
        ->and($createPosts[0]['idempotency_key'] ?? null)->toBe('ar-refund-'.$receipt->id)
        ->and($first['replayed'])->toBeFalse()
        ->and($second['replayed'])->toBeTrue()
        ->and($second['provider_refund_id'])->toBe('ref_idem_1')
        ->and($receipt->fresh()->refund_pending_cents)->toBe(0)
        ->and($receipt->fresh()->change_cents)->toBe(5000);
});

test('live AR keyed capture rejects stub source tokens outside local testing', function () {
    $previous = app()->environment();
    app()['env'] = 'production';

    try {
        [$roA, $invoiceA] = arCardOnAccountInvoice();

        expect(fn () => app(InitiateAccountsReceivableCaptureAction::class)->execute(
            $roA->customer->fresh(),
            [['invoice_id' => $invoiceA->id, 'amount_cents' => 15000]],
            $this->advisor,
            [
                'capture_method' => PaymentCaptureMethod::Keyed,
                'source_token' => 'tok_stub_ar',
                'intent_key' => (string) Str::uuid(),
                'stub_scenario' => 'immediate_success',
            ],
        ))->toThrow(\Illuminate\Validation\ValidationException::class);

        expect(PaymentCaptureAttempt::query()->where('customer_id', $roA->customer_id)->count())->toBe(0);
    } finally {
        app()['env'] = $previous;
    }
});
