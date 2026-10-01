<?php

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\FinancialSubmissionIntentGate;
use App\Ark\Operations\Financial\ManualPaymentMethods;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RepairOrderFinancialPresenter;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttempt;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttemptStatus;
use App\Ark\Operations\Payments\Capture\PaymentCaptureReadinessProjection;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    PaymentCaptureReadinessProjection::resetMemo();
    $this->seed(ArkAuthorizationSeeder::class);
});

test('take payment is one workspace and the amount comes from the server presentation', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));
    $repairOrder = financialCloseoutRepairOrder();
    $presenter = app(RepairOrderFinancialPresenter::class)->for(
        $repairOrder->fresh(),
        app(EstimateTotalsCalculator::class)->totalsFor($repairOrder->fresh()),
    );
    $suggested = (string) ($presenter['remainingSuggestedDepositDecimal'] ?? $presenter['remainingCollectableDepositDecimal'] ?? '');

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('data-payment-workspace-open', false)
        ->assertSee('data-payment-workspace-keypad', false)
        ->assertSee('data-payment-workspace-suggested="'.$suggested.'"', false)
        ->assertSee('Record deposit in ledger', false)
        ->assertSee(route('operations.repair-orders.deposit.update', $repairOrder), false)
        ->assertDontSee(route('operations.repair-orders.payment.update', $repairOrder), false);
});

test('an issued invoice keeps cash on the ledger payment path', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    $repairOrder = $repairOrder->fresh();
    $presenter = app(RepairOrderFinancialPresenter::class)->for(
        $repairOrder,
        app(EstimateTotalsCalculator::class)->totalsFor($repairOrder),
    );

    $this->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('data-payment-workspace-suggested="'.$presenter['settlementBalanceDueDecimal'].'"', false)
        ->assertSee('Record Payment', false)
        ->assertSee('name="paid_at"', false)
        ->assertSee('ops-payment-workspace__date', false)
        ->assertSee('data-payment-paid-date', false)
        ->assertSee(now()->timezone(config('app.display_timezone'))->format('M j, Y'), false)
        ->assertSee(route('operations.repair-orders.payment.update', $repairOrder), false)
        ->assertDontSee(route('operations.repair-orders.deposit.update', $repairOrder), false);
});

test('a live reader wait stays in the workspace and cannot be dismissed casually', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $repairOrder->forceFill([
        'repair_order_id' => $repairOrder->id + 900000,
    ])->save();

    $attempt = PaymentCaptureAttempt::query()->create([
        'public_id' => (string) Str::uuid(),
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $repairOrder->customer_id,
        'amount_cents' => 43538,
        'currency' => 'USD',
        'context_kind' => 'payment',
        'capture_method' => 'terminal',
        'status' => PaymentCaptureAttemptStatus::Pending->value,
        'idempotency_key' => 'core-'.Str::uuid(),
        'device_ref' => 'stub-front-counter',
        'initiated_at' => now(),
    ]);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('data-payment-workspace-dismiss="locked"', false)
        ->assertSee('Waiting for customer', false)
        ->assertSee('Cancel terminal', false)
        ->assertSee(route('operations.repair-orders.payment-capture.cancel', [$repairOrder, $attempt->id]), false)
        ->assertDontSee('data-payment-workspace-keypad', false)
        ->assertDontSee('action="'.route('operations.repair-orders.payment-capture.store', $repairOrder).'"', false);
});

test('take payment seeds check, external card, and eft under cash', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));
    $repairOrder = financialCloseoutRepairOrder();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('>Check</button>', false)
        ->assertSee('>External Card</button>', false)
        ->assertSee('>EFT</button>', false)
        ->assertDontSee('>Other</button>', false);
});

test('a shop can rename, add, and remove the methods under cash', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $this->get(route('operations.settings.shop.edit', ['section' => 'payments']))
        ->assertOk()
        ->assertSee('Other ways to pay')
        ->assertSee('Check', false);

    $this->patch(route('operations.settings.shop.manual-payment-methods.update'), [
        'methods' => [
            ['key' => 'check', 'label' => 'Cheque'],
            ['key' => '', 'label' => 'Fleet'],
        ],
    ])->assertRedirect(route('operations.settings.shop.edit', ['section' => 'payments']));

    expect(ShopSettings::current()->manual_payment_methods)->toBe([
        ['key' => 'check', 'label' => 'Cheque'],
        ['key' => 'fleet', 'label' => 'Fleet'],
    ]);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));
    $repairOrder = financialCloseoutRepairOrder();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('>Cheque</button>', false)
        ->assertSee('>Fleet</button>', false)
        ->assertDontSee('>External Card</button>', false)
        ->assertDontSee('>EFT</button>', false);
});

test('seeded and shop methods record on the existing ledger path', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));
    $repairOrder = financialCloseoutRepairOrder();

    $this->patch(route('operations.repair-orders.deposit.update', $repairOrder), [
        'amount' => '10.00',
        'payment_method' => PaymentMethod::Eft->value,
        'deposit_confirmed' => '1',
        FinancialSubmissionIntentGate::FIELD => financialSubmissionKey(),
    ])->assertRedirect();

    $eft = RepairOrderLedgerEntry::query()->where('repair_order_id', $repairOrder->id)->first();
    expect($eft->payment_method)->toBe(PaymentMethod::Eft)
        ->and(ManualPaymentMethods::label($eft->payment_method))->toBe('EFT');

    ShopSettings::current()->update([
        'manual_payment_methods' => ManualPaymentMethods::normalize([
            ['key' => '', 'label' => 'Fleet'],
        ]),
    ]);

    $this->patch(route('operations.repair-orders.deposit.update', $repairOrder->fresh()), [
        'amount' => '5.00',
        'payment_method' => 'fleet',
        'deposit_confirmed' => '1',
        FinancialSubmissionIntentGate::FIELD => financialSubmissionKey(),
    ])->assertRedirect();

    $fleet = RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('amount_cents', 500)
        ->first();
    expect($fleet->payment_method)->toBe('fleet')
        ->and(ManualPaymentMethods::label($fleet->payment_method))->toBe('Fleet');

    $this->patch(route('operations.repair-orders.deposit.update', $repairOrder->fresh()), [
        'amount' => '1.00',
        'payment_method' => 'financing',
        'deposit_confirmed' => '1',
        FinancialSubmissionIntentGate::FIELD => financialSubmissionKey(),
    ])->assertSessionHasErrors('payment_method');

    $this->patch(route('operations.repair-orders.deposit.update', $repairOrder->fresh()), [
        'amount' => '1.00',
        'payment_method' => PaymentMethod::Cash->value,
        'deposit_confirmed' => '1',
        FinancialSubmissionIntentGate::FIELD => financialSubmissionKey(),
    ])->assertRedirect();

    $cash = RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('amount_cents', 100)
        ->first();
    expect($cash->payment_method)->toBe(PaymentMethod::Cash);
});
