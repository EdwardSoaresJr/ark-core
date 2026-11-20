<?php

use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\CustomerPaymentReceipt;
use App\Ark\Operations\Financial\InitiateAccountsReceivableCaptureAction;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttemptStatus;
use App\Ark\Operations\Payments\Capture\PaymentCaptureMethod;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalogDefaults;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    RepairOrderStatusCatalogDefaults::sync(app(RepairOrderStatusCatalog::class));
    $this->advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($this->advisor);
});

test('one keyed ARK Pay capture allocates across multiple AR invoices', function () {
    [$roA, $invoiceA] = arOnAccountInvoiceForCustomer();
    $customer = $roA->customer;
    [$roB, $invoiceB] = arOnAccountInvoiceForCustomer($customer);

    enableHostedPlatformPayments();

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/readiness')) {
            return Http::response(platformPaymentReadinessPayload(), 200);
        }

        if ($request->method() === 'POST' && str_contains($request->url(), '/captures')) {
            $body = $request->data();

            return Http::response(platformCaptureKeyedSucceededPayload(
                (string) $body['idempotency_key'],
                (string) $body['capture_attempt_public_id'],
                (int) $body['amount_cents'],
                [
                    'context_kind' => 'accounts_receivable',
                    'provider' => 'square',
                    'provider_payment_id' => 'pay_ar_multi_1',
                ],
            ), 200);
        }

        if (str_contains($request->url(), '/api/v1/status')) {
            return Http::response(['ok' => true, 'services' => []], 200);
        }

        return Http::response(['ok' => false], 404);
    });

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
            'intent_key' => (string) Str::uuid(),
            'reference' => 'AR-CARD',
        ],
    );

    $attempt = $result['attempt']->fresh();

    expect($attempt->status)->toBe(PaymentCaptureAttemptStatus::Succeeded)
        ->and($attempt->customer_payment_receipt_id)->not->toBeNull()
        ->and(CustomerPaymentReceipt::query()->count())->toBe(1)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($roA->fresh())->balanceDueCents)->toBe(0)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($roB->fresh())->balanceDueCents)->toBe(0);

    $receipt = CustomerPaymentReceipt::query()->findOrFail($attempt->customer_payment_receipt_id);
    expect($receipt->amount_cents)->toBe(30000)
        ->and($receipt->allocated_cents)->toBe(30000)
        ->and($receipt->ledgerEntries()->where('entry_type', 'payment')->count())->toBe(2);
});
