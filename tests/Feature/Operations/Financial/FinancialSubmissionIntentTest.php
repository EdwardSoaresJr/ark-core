<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Financial\FinancialSubmissionIntent;
use App\Ark\Operations\Financial\FinancialSubmissionIntentGate;
use App\Ark\Operations\Financial\FinancialSubmissionOperation;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderFinancialChanged;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    config(['broadcasting.default' => 'log']);
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));
});

test('deposit replay of the same submission records one ledger row and one financial change', function () {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Approved);
    $key = financialSubmissionKey();
    $financialBroadcasts = 0;

    Event::listen(RepairOrderFinancialChanged::class, function (RepairOrderFinancialChanged $event) use (&$financialBroadcasts): void {
        $financialBroadcasts++;

        expect($event->broadcastWith()['reason'])->toBe('deposit_recorded');
    });

    $payload = [
        'amount' => '10.00',
        'payment_method' => PaymentMethod::Cash->value,
        'reference' => 'Counter',
        'deposit_confirmed' => '1',
        RepairOrderConcurrency::FIELD => $repairOrder->estimate_version,
        FinancialSubmissionIntentGate::FIELD => $key,
    ];

    $this->patch(route('operations.repair-orders.deposit.update', $repairOrder), $payload)->assertRedirect();
    $this->patch(route('operations.repair-orders.deposit.update', $repairOrder->fresh()), $payload)->assertRedirect();

    expect(depositCents($repairOrder->id))->toBe(1000)
        ->and(depositCount($repairOrder->id))->toBe(1)
        ->and(FinancialSubmissionIntent::query()->where('intent_key', $key)->count())->toBe(1)
        ->and($financialBroadcasts)->toBe(1)
        ->and((int) $repairOrder->fresh()->estimate_version)->toBe((int) $repairOrder->estimate_version);
});

test('two deposit submissions with different keys both record when the cap allows', function () {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Approved);

    foreach (['k1', 'k2'] as $ignored) {
        $this->patch(route('operations.repair-orders.deposit.update', $repairOrder->fresh()), [
            'amount' => '10.00',
            'payment_method' => PaymentMethod::Cash->value,
            'deposit_confirmed' => '1',
            RepairOrderConcurrency::FIELD => $repairOrder->fresh()->estimate_version,
            FinancialSubmissionIntentGate::FIELD => financialSubmissionKey(),
        ])->assertRedirect();
    }

    expect(depositCents($repairOrder->id))->toBe(2000)
        ->and(depositCount($repairOrder->id))->toBe(2)
        ->and(FinancialSubmissionIntent::query()->count())->toBe(2);
});

test('deposit replay with a different amount is rejected and leaves the original row', function () {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Approved);
    $key = financialSubmissionKey();
    $show = route('operations.repair-orders.show', $repairOrder);

    $this->from($show)->patch(route('operations.repair-orders.deposit.update', $repairOrder), [
        'amount' => '10.00',
        'payment_method' => PaymentMethod::Cash->value,
        'reference' => 'Counter',
        'deposit_confirmed' => '1',
        RepairOrderConcurrency::FIELD => $repairOrder->estimate_version,
        FinancialSubmissionIntentGate::FIELD => $key,
    ])->assertRedirect();

    $this->from($show)->patch(route('operations.repair-orders.deposit.update', $repairOrder->fresh()), [
        'amount' => '20.00',
        'payment_method' => PaymentMethod::Check->value,
        'reference' => 'Other',
        'deposit_confirmed' => '1',
        RepairOrderConcurrency::FIELD => $repairOrder->fresh()->estimate_version,
        FinancialSubmissionIntentGate::FIELD => $key,
    ])->assertRedirect()->assertSessionHasErrors(FinancialSubmissionIntentGate::FIELD);

    expect(depositCents($repairOrder->id))->toBe(1000)
        ->and(depositCount($repairOrder->id))->toBe(1);
});

test('a deposit submission key cannot be replayed on another repair order', function () {
    $first = financialCloseoutRepairOrder(RepairOrderStatus::Approved);
    $second = financialCloseoutRepairOrder(RepairOrderStatus::Approved);
    $key = financialSubmissionKey();

    $this->patch(route('operations.repair-orders.deposit.update', $first), [
        'amount' => '10.00',
        'payment_method' => PaymentMethod::Cash->value,
        'deposit_confirmed' => '1',
        RepairOrderConcurrency::FIELD => $first->estimate_version,
        FinancialSubmissionIntentGate::FIELD => $key,
    ])->assertRedirect();

    $this->from(route('operations.repair-orders.show', $second))
        ->patch(route('operations.repair-orders.deposit.update', $second), [
            'amount' => '10.00',
            'payment_method' => PaymentMethod::Cash->value,
            'deposit_confirmed' => '1',
            RepairOrderConcurrency::FIELD => $second->estimate_version,
            FinancialSubmissionIntentGate::FIELD => $key,
        ])->assertRedirect()->assertSessionHasErrors(FinancialSubmissionIntentGate::FIELD);

    expect(depositCount($first->id))->toBe(1)
        ->and(depositCount($second->id))->toBe(0);
});

test('a deposit that fails before the ledger insert can be retried with the same key', function () {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Approved);
    $key = financialSubmissionKey();
    $show = route('operations.repair-orders.show', $repairOrder);

    $this->from($show)->patch(route('operations.repair-orders.deposit.update', $repairOrder), [
        'amount' => '500.00',
        'payment_method' => PaymentMethod::Cash->value,
        'deposit_confirmed' => '1',
        RepairOrderConcurrency::FIELD => $repairOrder->estimate_version,
        FinancialSubmissionIntentGate::FIELD => $key,
    ])->assertRedirect()->assertSessionHasErrors('amount');

    expect(FinancialSubmissionIntent::query()->count())->toBe(0)
        ->and(depositCount($repairOrder->id))->toBe(0);

    $this->patch(route('operations.repair-orders.deposit.update', $repairOrder->fresh()), [
        'amount' => '10.00',
        'payment_method' => PaymentMethod::Cash->value,
        'deposit_confirmed' => '1',
        RepairOrderConcurrency::FIELD => $repairOrder->fresh()->estimate_version,
        FinancialSubmissionIntentGate::FIELD => $key,
    ])->assertRedirect();

    expect(depositCents($repairOrder->id))->toBe(1000)
        ->and(FinancialSubmissionIntent::query()->where('intent_key', $key)->count())->toBe(1);
});

test('a stale estimate token still rejects a deposit before any money is recorded', function () {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Approved);
    $key = financialSubmissionKey();

    $this->patch(route('operations.repair-orders.deposit.update', $repairOrder), [
        'amount' => '10.00',
        'payment_method' => PaymentMethod::Cash->value,
        'deposit_confirmed' => '1',
        RepairOrderConcurrency::FIELD => 0,
        FinancialSubmissionIntentGate::FIELD => $key,
    ])->assertStatus(409);

    expect(depositCount($repairOrder->id))->toBe(0)
        ->and(FinancialSubmissionIntent::query()->count())->toBe(0);

    $this->patch(route('operations.repair-orders.deposit.update', $repairOrder->fresh()), [
        'amount' => '10.00',
        'payment_method' => PaymentMethod::Cash->value,
        'deposit_confirmed' => '1',
        RepairOrderConcurrency::FIELD => $repairOrder->fresh()->estimate_version,
        FinancialSubmissionIntentGate::FIELD => $key,
    ])->assertRedirect();

    expect(depositCount($repairOrder->id))->toBe(1);
});

test('desktop deposit without a submission key does not record money', function () {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Approved);

    $this->from(route('operations.repair-orders.show', $repairOrder))
        ->patch(route('operations.repair-orders.deposit.update', $repairOrder), [
            'amount' => '10.00',
            'payment_method' => PaymentMethod::Cash->value,
            'deposit_confirmed' => '1',
            RepairOrderConcurrency::FIELD => $repairOrder->estimate_version,
        ])->assertRedirect()->assertSessionHasErrors(FinancialSubmissionIntentGate::FIELD);

    expect(depositCount($repairOrder->id))->toBe(0);
});

test('payment replay does not create a second payment, store credit, or financial change', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    $repairOrder = $repairOrder->fresh();
    $key = financialSubmissionKey();
    $financialBroadcasts = 0;

    Event::listen(RepairOrderFinancialChanged::class, function () use (&$financialBroadcasts): void {
        $financialBroadcasts++;
    });

    $payload = [
        'amount' => '200.00',
        'payment_method' => PaymentMethod::Card->value,
        'reference' => 'Card',
        RepairOrderConcurrency::FIELD => $repairOrder->estimate_version,
        FinancialSubmissionIntentGate::FIELD => $key,
    ];

    $this->patch(route('operations.repair-orders.payment.update', $repairOrder), $payload)->assertRedirect();
    $this->patch(route('operations.repair-orders.payment.update', $repairOrder->fresh()), $payload)->assertRedirect();

    $customer = Customer::query()->findOrFail($repairOrder->customer_id);

    expect(paymentCents($repairOrder->id))->toBe(15000)
        ->and(paymentCount($repairOrder->id))->toBe(1)
        ->and(storeCreditCents($repairOrder->id))->toBe(5000)
        ->and((int) $customer->store_credit_balance_cents)->toBe(5000)
        ->and(FinancialSubmissionIntent::query()->where('intent_key', $key)->count())->toBe(1)
        ->and($financialBroadcasts)->toBe(1)
        ->and(OperationalEvent::query()
            ->where('event_name', OperationalEventName::RepairOrderPaymentReceived->value)
            ->where('aggregate_id', $repairOrder->id)
            ->count())->toBe(1);
});

test('two payment submissions with different keys both record when the balance allows', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    foreach ([10, 10] as $ignored) {
        $this->patch(route('operations.repair-orders.payment.update', $repairOrder->fresh()), [
            'amount' => '10.00',
            'payment_method' => PaymentMethod::Cash->value,
            RepairOrderConcurrency::FIELD => $repairOrder->fresh()->estimate_version,
            FinancialSubmissionIntentGate::FIELD => financialSubmissionKey(),
        ])->assertRedirect();
    }

    expect(paymentCents($repairOrder->id))->toBe(2000)
        ->and(paymentCount($repairOrder->id))->toBe(2)
        ->and(storeCreditCents($repairOrder->id))->toBe(0);
});

test('payment replay with a different amount is rejected', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    $repairOrder = $repairOrder->fresh();
    $key = financialSubmissionKey();
    $show = route('operations.repair-orders.show', $repairOrder);

    $this->from($show)->patch(route('operations.repair-orders.payment.update', $repairOrder), [
        'amount' => '10.00',
        'payment_method' => PaymentMethod::Cash->value,
        RepairOrderConcurrency::FIELD => $repairOrder->estimate_version,
        FinancialSubmissionIntentGate::FIELD => $key,
    ])->assertRedirect();

    $this->from($show)->patch(route('operations.repair-orders.payment.update', $repairOrder->fresh()), [
        'amount' => '20.00',
        'payment_method' => PaymentMethod::Cash->value,
        RepairOrderConcurrency::FIELD => $repairOrder->fresh()->estimate_version,
        FinancialSubmissionIntentGate::FIELD => $key,
    ])->assertRedirect()->assertSessionHasErrors(FinancialSubmissionIntentGate::FIELD);

    expect(paymentCents($repairOrder->id))->toBe(1000)
        ->and(paymentCount($repairOrder->id))->toBe(1);
});

test('a stale estimate token still rejects a payment', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $this->patch(route('operations.repair-orders.payment.update', $repairOrder->fresh()), [
        'amount' => '10.00',
        'payment_method' => PaymentMethod::Cash->value,
        RepairOrderConcurrency::FIELD => 0,
        FinancialSubmissionIntentGate::FIELD => financialSubmissionKey(),
    ])->assertStatus(409);

    expect(paymentCount($repairOrder->id))->toBe(0);
});

test('mobile deposits without a submission key stay repeatable', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Approved);

    foreach ([1, 2] as $ignored) {
        $this->withToken($token)
            ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/deposit', [
                'amount' => '10.00',
                'payment_method' => 'cash',
            ])->assertOk();
    }

    expect(depositCount($repairOrder->id))->toBe(2)
        ->and(FinancialSubmissionIntent::query()->count())->toBe(0);
});

test('mobile deposit replay uses the same submission key when the phone sends one', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Approved);
    $key = financialSubmissionKey();

    $payload = [
        'amount' => '10.00',
        'payment_method' => 'cash',
        FinancialSubmissionIntentGate::FIELD => $key,
    ];

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/deposit', $payload)
        ->assertOk()
        ->assertJsonPath('unapplied_deposits_cents', 1000);

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/deposit', $payload)
        ->assertOk()
        ->assertJsonPath('unapplied_deposits_cents', 1000);

    expect(depositCount($repairOrder->id))->toBe(1);
});

test('submission keys are unique in the database', function () {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Approved);
    $key = financialSubmissionKey();

    FinancialSubmissionIntent::query()->create([
        'intent_key' => $key,
        'repair_order_id' => $repairOrder->id,
        'operation' => FinancialSubmissionOperation::Deposit,
        'payload_fingerprint' => str_repeat('a', 64),
    ]);

    expect(fn () => FinancialSubmissionIntent::query()->create([
        'intent_key' => $key,
        'repair_order_id' => $repairOrder->id,
        'operation' => FinancialSubmissionOperation::Payment,
        'payload_fingerprint' => str_repeat('b', 64),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('overlapping same-key deposits and payments record one effect and different keys stay distinct', function () {
    $script = base_path('tests/Support/financial_submission_intent_overlap.php');
    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' parent';
    $descriptor = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $pipes = [];
    $process = proc_open($command, $descriptor, $pipes, base_path());

    expect(is_resource($process))->toBeTrue();

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    $exit = proc_close($process);

    expect($exit)->toBe(0, $stderr."\n".$stdout);

    $payload = json_decode((string) $stdout, true);

    expect($payload['deposit_same_key']['rows'])->toBe(1)
        ->and($payload['deposit_same_key']['cents'])->toBe(1000)
        ->and($payload['deposit_same_key']['events'])->toBe(1)
        ->and($payload['deposit_same_key']['statuses'])->toEqual([200, 200])
        ->and($payload['deposit_same_key']['replays'])->toBe(1)
        ->and($payload['deposit_different_key']['rows'])->toBe(2)
        ->and($payload['deposit_different_key']['cents'])->toBe(2000)
        ->and($payload['payment_same_key']['rows'])->toBe(1)
        ->and($payload['payment_same_key']['cents'])->toBe(1000)
        ->and($payload['payment_same_key']['events'])->toBe(1)
        ->and($payload['payment_same_key']['statuses'])->toEqual([200, 200])
        ->and($payload['payment_same_key']['replays'])->toBe(1)
        ->and($payload['payment_different_key']['rows'])->toBe(2)
        ->and($payload['payment_different_key']['cents'])->toBe(2000);
});

function depositCount(int $repairOrderId): int
{
    return RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrderId)
        ->where('entry_type', LedgerEntryType::Deposit)
        ->count();
}

function depositCents(int $repairOrderId): int
{
    return (int) RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrderId)
        ->where('entry_type', LedgerEntryType::Deposit)
        ->sum('amount_cents');
}

function paymentCount(int $repairOrderId): int
{
    return RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrderId)
        ->where('entry_type', LedgerEntryType::Payment)
        ->count();
}

function paymentCents(int $repairOrderId): int
{
    return (int) RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrderId)
        ->where('entry_type', LedgerEntryType::Payment)
        ->sum('amount_cents');
}

function storeCreditCents(int $repairOrderId): int
{
    return (int) RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrderId)
        ->where('entry_type', LedgerEntryType::StoreCreditIssuance)
        ->sum('amount_cents');
}
