<?php

use App\Ark\Operations\Events\EventContract;
use App\Ark\Operations\Events\EventContractScopeMembership;
use App\Ark\Operations\Events\EventStreamScope;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderPaymentStatus;
use App\Ark\Operations\Timeline\OperationalEventKind;
use App\Ark\Operations\Timeline\OperationalTimeline;
use App\Ark\Operations\Timeline\UnifiedOperationalTimeline;
use App\Models\User;
use App\Ark\Runtime\Authorization\ArkRole;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('payment received contract has exactly one financial emitter', function (): void {
    $emitterPath = app_path('Ark/Operations/Financial/EmitPaymentReceivedEvent.php');
    $recordingPaths = collect(File::allFiles(app_path()))
        ->map(fn ($file) => $file->getPathname())
        ->filter(fn (string $path): bool => ! str_contains($path, 'EmitPaymentReceivedEvent.php'))
        ->filter(function (string $path): bool {
            $contents = File::get($path);

            return str_contains($contents, 'OperationalEventName::RepairOrderPaymentReceived')
                && preg_match('/->record\s*\(/', $contents) === 1;
        })
        ->values();

    expect($recordingPaths)->toBeEmpty()
        ->and(File::exists($emitterPath))->toBeTrue();
});

test('payment received vertical slice — financial authority through projections', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $balanceBefore = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh());

    expect($balanceBefore->balanceDueCents)->toBe(15000)
        ->and($repairOrder->fresh()->paymentStatus())->toBe(RepairOrderPaymentStatus::Unpaid);

    app(RecordLedgerEntryAction::class)->recordPayment(
        $repairOrder->fresh(),
        15000,
        PaymentMethod::Cash,
        $advisor,
    );

    $event = OperationalEvent::query()
        ->where('event_name', OperationalEventName::RepairOrderPaymentReceived->value)
        ->sole();

    expect($event->payload_json)->toMatchArray([
        'event_contract' => EventContract::PaymentReceived->value,
        'amount_cents' => 15000,
        'balance_due_cents' => 0,
        'payment_status' => RepairOrderPaymentStatus::Paid->value,
    ])->and($repairOrder->fresh()->isPaid())->toBeTrue();

    $membership = app(EventContractScopeMembership::class);

    expect($membership->includes(EventContract::PaymentReceived, EventStreamScope::Customer))->toBeTrue()
        ->and($membership->includes(EventContract::PaymentReceived, EventStreamScope::RepairOrder))->toBeTrue()
        ->and($membership->includes(EventContract::PaymentReceived, EventStreamScope::ShopFeed))->toBeTrue()
        ->and($membership->includes(EventContract::PaymentReceived, EventStreamScope::Vehicle))->toBeFalse();

    $customer = $repairOrder->customer()->firstOrFail();

    $customerTimelineEntry = app(UnifiedOperationalTimeline::class)
        ->forCustomerRelationship($customer, null)
        ->first(fn ($entry) => $entry->kind === OperationalEventKind::Payment);

    expect($customerTimelineEntry)->not->toBeNull()
        ->and($customerTimelineEntry->headline)->toBe('Payment Received')
        ->and($customerTimelineEntry->subject)->toBeInstanceOf(OperationalEvent::class)
        ->and($customerTimelineEntry->subject->id)->toBe($event->id)
        ->and($customerTimelineEntry->metadata['event_contract'])->toBe(EventContract::PaymentReceived->value);

    $roTimelineEntry = app(OperationalTimeline::class)
        ->forRepairOrder($repairOrder->fresh())
        ->first(fn (array $entry): bool => $entry['title'] === 'Payment Received');

    expect($roTimelineEntry)->not->toBeNull()
        ->and($roTimelineEntry['tone'])->toBe('financial');
});

test('deposits do not emit payment received — different business fact', function (): void {
    $repairOrder = financialCloseoutRepairOrder();

    app(RecordLedgerEntryAction::class)->recordDeposit(
        $repairOrder->fresh(),
        5000,
        PaymentMethod::Cash,
    );

    expect(OperationalEvent::query()
        ->where('event_name', OperationalEventName::RepairOrderPaymentReceived->value)
        ->count())->toBe(0);
});

test('payment received clears waiting-on-payment authority posture', function (): void {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    expect($repairOrder->fresh()->paymentStatus())->toBe(RepairOrderPaymentStatus::Unpaid)
        ->and($repairOrder->fresh()->balanceDue()->balanceDueCents)->toBe(15000);

    payRepairOrderInFull($repairOrder->fresh());

    expect($repairOrder->fresh()->paymentStatus())->toBe(RepairOrderPaymentStatus::Paid)
        ->and($repairOrder->fresh()->balanceDue()->isPaid())->toBeTrue()
        ->and(OperationalEvent::query()
            ->where('event_name', OperationalEventName::RepairOrderPaymentReceived->value)
            ->count())->toBe(1);
});

test('timeline payment entries trace to authority — projections do not invent events', function (): void {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    payRepairOrderInFull($repairOrder->fresh());

    $entries = app(UnifiedOperationalTimeline::class)
        ->forCustomerRelationship($repairOrder->customer()->firstOrFail(), null)
        ->filter(fn ($entry) => $entry->headline === 'Payment Received');

    expect($entries)->toHaveCount(1);

    $subject = $entries->first()->subject;

    expect($subject)->toBeInstanceOf(OperationalEvent::class)
        ->and($subject->aggregate_type)->toBe(RepairOrder::class)
        ->and($subject->aggregate_id)->toBe($repairOrder->id)
        ->and($subject->payload_json['event_contract'] ?? null)->toBe(EventContract::PaymentReceived->value);
});
