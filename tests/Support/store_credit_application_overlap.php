<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\ApplyCustomerStoreCreditAction;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\CustomerPaymentReceipt;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\GenerateInvoiceSnapshotAction;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLifecycleTransition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\ScopeProductionStatus;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalogDefaults;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

$role = $argv[1] ?? 'parent';

require dirname(__DIR__, 2).'/vendor/autoload.php';
$mysql = \Tests\Support\DisposableMySqlIntegration::helperConfiguration();
\Tests\Support\TestEnvironment::initialize($mysql);
$mysqlEnvironment = \Tests\Support\DisposableMySqlIntegration::subprocessEnvironment(getenv('ARK_TEST_MYSQL_CONTEXT'));
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
\Tests\Support\TestEnvironment::configureApplication($app, $mysql);
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$apply = static function (int $customerId, int $invoiceId, int $amountCents, string $intentKey): array {
    try {
        $customer = Customer::query()->findOrFail($customerId);
        $receipt = app(ApplyCustomerStoreCreditAction::class)->execute(
            $customer,
            [['invoice_id' => $invoiceId, 'amount_cents' => $amountCents]],
            $intentKey,
            User::query()->orderBy('id')->first(),
            'credit-overlap',
        );

        return [
            'ok' => true,
            'receipt_id' => $receipt->id,
            'allocated_cents' => $receipt->allocated_cents,
            'error' => null,
        ];
    } catch (ValidationException $exception) {
        return [
            'ok' => false,
            'receipt_id' => null,
            'allocated_cents' => 0,
            'error' => $exception->getMessage(),
        ];
    } catch (Throwable $exception) {
        return [
            'ok' => false,
            'receipt_id' => null,
            'allocated_cents' => 0,
            'error' => $exception::class.': '.$exception->getMessage(),
        ];
    }
};

if ($role === 'child') {
    $customerId = (int) $argv[2];
    $invoiceId = (int) $argv[3];
    $amountCents = (int) $argv[4];
    $intentKey = (string) $argv[5];
    $gateFile = (string) $argv[6];
    $deadline = microtime(true) + 20;

    while (! is_file($gateFile)) {
        if (microtime(true) > $deadline) {
            fwrite(STDERR, "timed out waiting for the store-credit overlap gate\n");
            exit(1);
        }
        usleep(20000);
    }

    echo json_encode($apply($customerId, $invoiceId, $amountCents, $intentKey), JSON_THROW_ON_ERROR);
    exit(0);
}

if ($kernel->call('migrate', ['--force' => true, '--quiet' => true]) !== 0) {
    throw new RuntimeException('Isolated MySQL migrations failed.');
}

$app->make(ArkAuthorizationSeeder::class)->run();
RepairOrderStatusCatalogDefaults::sync(app(RepairOrderStatusCatalog::class));
ShopDisplayTimezone::apply();

ShopSettings::current()->update([
    'shop_fee_enabled' => false,
    'tax_enabled' => false,
    'default_deposit_enabled' => false,
]);

$advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
auth()->login($advisor);

$onAccount = static function (?Customer $customer = null) use ($advisor): array {
    ShopSettings::current()->update(['shop_fee_enabled' => false, 'tax_enabled' => false]);

    $customer ??= Customer::query()->create([
        'first_name' => 'Credit',
        'last_name' => 'Overlap',
        'phone' => '555019902',
        'store_credit_balance_cents' => 0,
    ]);

    $vehicle = $customer->vehicles()->first() ?? Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::ReadyPickup,
        'concern_summary' => 'Store credit overlap',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Approved brakes',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'production_status' => ScopeProductionStatus::Completed,
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Brake service',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());
    app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder->fresh());

    $closed = app(RepairOrderLifecycleTransition::class)->move(
        $repairOrder->fresh(),
        RepairOrderStatus::Closed,
        $advisor,
        'on_account',
    );

    $invoice = app(BalanceDueCalculator::class)->issuedInvoice($closed);

    return [$closed->fresh(), $invoice, $customer->fresh()];
};

[$roA, $invoiceA, $customer] = $onAccount();
[$roB, $invoiceB] = $onAccount($customer);

$race = static function (
    int $customerId,
    int $invoiceIdLeft,
    int $invoiceIdRight,
    int $amountCents,
) use ($mysqlEnvironment): array {
    $gateFile = \Tests\Support\TestEnvironment::root().'/ark-store-credit-'.bin2hex(random_bytes(6));
    @unlink($gateFile);

    $start = static function (int $invoiceId, string $intentKey) use (
        $mysqlEnvironment,
        $customerId,
        $amountCents,
        $gateFile,
    ): array {
        $command = [
            PHP_BINARY,
            __FILE__,
            'child',
            (string) $customerId,
            (string) $invoiceId,
            (string) $amountCents,
            $intentKey,
            $gateFile,
        ];
        $pipes = [];
        $process = proc_open($command, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, dirname(__DIR__, 2), $mysqlEnvironment);

        if (! is_resource($process)) {
            throw new RuntimeException('Could not start overlapping store-credit application.');
        }

        return [$process, $pipes];
    };

    [$first, $firstPipes] = $start($invoiceIdLeft, (string) Str::uuid());
    [$second, $secondPipes] = $start($invoiceIdRight, (string) Str::uuid());
    file_put_contents($gateFile, 'go');

    $firstOut = stream_get_contents($firstPipes[1]);
    $firstErr = stream_get_contents($firstPipes[2]);
    $secondOut = stream_get_contents($secondPipes[1]);
    $secondErr = stream_get_contents($secondPipes[2]);
    $firstExit = proc_close($first);
    $secondExit = proc_close($second);
    @unlink($gateFile);

    if ($firstExit !== 0 || $secondExit !== 0) {
        fwrite(STDERR, $firstErr.$secondErr);

        throw new RuntimeException('Overlapping store-credit application exited '.$firstExit.' and '.$secondExit);
    }

    $left = json_decode((string) $firstOut, true);
    $right = json_decode((string) $secondOut, true);

    if (! is_array($left) || ! is_array($right)) {
        fwrite(STDERR, $firstErr.$secondErr.$firstOut.$secondOut);

        throw new RuntimeException('Overlapping store-credit application returned invalid output.');
    }

    return [$left, $right];
};

$activeApplications = static function (int $customerId): \Illuminate\Support\Collection {
    return RepairOrderLedgerEntry::query()
        ->where('customer_id', $customerId)
        ->where('entry_type', LedgerEntryType::StoreCreditApplication->value)
        ->whereNull('voided_at')
        ->get();
};

// Race 1: $100 credit, two concurrent $100 applications against different invoices.
$customer->forceFill(['store_credit_balance_cents' => 10000])->save();
[$creditLeft, $creditRight] = $race(
    (int) $customer->id,
    (int) $invoiceA->id,
    (int) $invoiceB->id,
    10000,
);
$creditApps = $activeApplications((int) $customer->id);
$creditRace = [
    'left' => $creditLeft,
    'right' => $creditRight,
    'winners' => collect([$creditLeft, $creditRight])->where('ok', true)->count(),
    'balance_cents' => (int) $customer->fresh()->store_credit_balance_cents,
    'active_application_rows' => $creditApps->count(),
    'active_application_cents' => (int) $creditApps->sum('amount_cents'),
];

// Clear race-1 effects before the invoice overpay race.
RepairOrderLedgerEntry::query()
    ->where('customer_id', $customer->id)
    ->where('entry_type', LedgerEntryType::StoreCreditApplication->value)
    ->whereNull('voided_at')
    ->update(['voided_at' => now()]);
CustomerPaymentReceipt::query()
    ->where('customer_id', $customer->id)
    ->whereNull('voided_at')
    ->update(['voided_at' => now()]);
$customer->forceFill(['store_credit_balance_cents' => 20000])->save();

// Race 2: $200 credit, $150 invoice, two concurrent $150 applications on the same invoice.
[$invoiceLeft, $invoiceRight] = $race(
    (int) $customer->id,
    (int) $invoiceA->id,
    (int) $invoiceA->id,
    15000,
);
$invoiceApps = $activeApplications((int) $customer->id);
$invoiceRace = [
    'left' => $invoiceLeft,
    'right' => $invoiceRight,
    'winners' => collect([$invoiceLeft, $invoiceRight])->where('ok', true)->count(),
    'balance_due_cents' => app(BalanceDueCalculator::class)->forRepairOrder($roA->fresh())->balanceDueCents,
    'active_application_rows' => $invoiceApps->count(),
    'active_application_cents' => (int) $invoiceApps->sum('amount_cents'),
    'remaining_credit_cents' => (int) $customer->fresh()->store_credit_balance_cents,
];

echo json_encode([
    'credit_race' => $creditRace,
    'invoice_race' => $invoiceRace,
], JSON_THROW_ON_ERROR)."\n";
