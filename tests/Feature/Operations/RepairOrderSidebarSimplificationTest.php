<?php

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\RepairOrderFinancialPresenter;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttempt;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttemptStatus;
use App\Ark\Operations\Payments\Capture\PaymentCaptureContextKind;
use App\Ark\Operations\Payments\Capture\PaymentCaptureMethod;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('repair order sidebar rest state is financial summary next actions order status and more', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::WaitingApproval);
    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $repairOrder->concerns()->first()->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Mopar Engine Oil Filter',
        'quantity' => '1.00',
        'unit_price_cents' => 2616,
        'procurement_state' => PartProcurementState::None,
    ]);
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    CommunicationEvent::query()->create([
        'repair_order_id' => $repairOrder->id,
        'event_type' => OperationalCommunicationType::EstimateViewed,
        'channel' => OperationalCommunicationChannel::Website,
        'direction' => OperationalCommunicationDirection::Inbound,
        'summary' => 'Estimate viewed.',
        'occurred_at' => now(),
    ]);

    $this->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('Financial summary')
        ->assertSee('Balance due')
        ->assertSee('Take payment')
        ->assertSee('Record external')
        ->assertSee('Next actions')
        ->assertSee('Mopar Engine Oil Filter')
        ->assertSee('Follow up viewed estimate')
        ->assertSee('Order status')
        ->assertSee('Waiting Approval')
        ->assertSee('Not issued')
        ->assertSee('More financial actions')
        ->assertDontSee('>Closeout<', false)
        ->assertDontSee('Pre-invoice')
        ->assertDontSee('Settlement balance')
        ->assertDontSee('Recent captures');
});

test('issued invoice while waiting approval shows workflow and invoice independently', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    $repairOrder->fresh()->forceFill(['status' => RepairOrderStatus::WaitingApproval])->save();
    $repairOrder = $repairOrder->fresh();

    $presenter = app(RepairOrderFinancialPresenter::class)->for(
        $repairOrder,
        app(EstimateTotalsCalculator::class)->totalsFor($repairOrder),
    );

    expect($presenter['hasIssuedInvoice'])->toBeTrue()
        ->and($presenter['invoiceStatusLabel'])->toBe('Issued')
        ->and($presenter['invoiceIssuedOutsideCloseout'])->toBeTrue()
        ->and($presenter['workflowPosture'])->toBe('invoice_issued')
        ->and($presenter['workflowLabel'])->toBe('Invoice issued');

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Waiting Approval')
        ->assertSee('Issued')
        ->assertSee('Invoice is issued while this order is still Waiting Approval.')
        ->assertDontSee('Pre-invoice')
        ->assertSee('Take payment')
        ->assertSee('Record external');
});

test('cancelled terminal captures appear in payment history without reducing balance', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    $repairOrder = $repairOrder->fresh();

    PaymentCaptureAttempt::query()->create([
        'public_id' => (string) Str::uuid(),
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $repairOrder->customer_id,
        'amount_cents' => 2923,
        'currency' => 'USD',
        'context_kind' => PaymentCaptureContextKind::Payment,
        'capture_method' => PaymentCaptureMethod::Terminal,
        'status' => PaymentCaptureAttemptStatus::Cancelled,
        'idempotency_key' => 'core-'.Str::uuid(),
        'initiated_at' => now(),
        'completed_at' => now(),
    ]);

    $presenter = app(RepairOrderFinancialPresenter::class)->for(
        $repairOrder,
        app(EstimateTotalsCalculator::class)->totalsFor($repairOrder),
    );

    expect($presenter['settlementBalanceDueCents'])->toBe(15000)
        ->and($presenter['isPaid'])->toBeFalse()
        ->and(collect($presenter['paymentHistoryItems'])->contains(
            fn (array $item): bool => ($item['kind'] ?? '') === 'capture'
                && ($item['attempt']['statusLabel'] ?? '') === 'Cancelled',
        ))->toBeTrue();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Card capture')
        ->assertSee('Cancelled')
        ->assertSee('Not a payment — does not change balance due.')
        ->assertSee('$150.00')
        ->assertDontSee('Recent captures');
});
