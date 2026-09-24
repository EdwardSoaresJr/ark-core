<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\GenerateInvoiceSnapshotAction;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\ManualDepositSubmission;
use App\Ark\Operations\Financial\ManualPaymentSubmission;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderFinancialChanged;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Http\Middleware\CommitRepairOrderEstimateLock;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

$role = $argv[1] ?? 'parent';

$database = 'ark_financial_intent_p1';
$env = static function (string $key, string $default = ''): string {
    $contents = (string) file_get_contents(dirname(__DIR__, 2).'/.env');
    if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $matches)) {
        return $default;
    }

    return trim($matches[1], " \t\"'");
};

$host = $env('DB_HOST', '127.0.0.1');
$port = $env('DB_PORT', '3306');
$username = $env('DB_USERNAME', 'root');
$password = $env('DB_PASSWORD', '');

if ($role === 'parent') {
    $server = new PDO(
        "mysql:host={$host};port={$port}",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $server->exec(
        "CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
    );
}

foreach ([
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => $host,
    'DB_PORT' => $port,
    'DB_DATABASE' => $database,
    'DB_USERNAME' => $username,
    'DB_PASSWORD' => $password,
] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

putenv('DB_URL');
unset($_ENV['DB_URL'], $_SERVER['DB_URL']);

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

config([
    'database.default' => 'mysql',
    'database.connections.mysql.database' => $database,
    'broadcasting.default' => 'log',
]);
DB::purge('mysql');
DB::reconnect('mysql');

$submit = static function (string $operation, int $repairOrderId, string $intentKey, int $amountCents, int $openedVersion) use ($app): array {
    $repairOrder = RepairOrder::query()->findOrFail($repairOrderId);
    $request = Request::create('/'.$operation, 'PATCH', [
        RepairOrderConcurrency::FIELD => $openedVersion,
    ]);
    $events = 0;
    Event::listen(RepairOrderFinancialChanged::class, function () use (&$events): void {
        $events++;
    });

    $replayed = false;

    try {
        $response = $app->make(CommitRepairOrderEstimateLock::class)->handle($request, function () use ($app, $request, $repairOrder, $operation, $intentKey, $amountCents, &$replayed): Response {
            $app->make(RepairOrderConcurrency::class)->guard($request, $repairOrder);

            if ($operation === 'deposit') {
                $claim = $app->make(ManualDepositSubmission::class)->execute(
                    $repairOrder,
                    null,
                    $intentKey,
                    $amountCents,
                    PaymentMethod::Cash,
                    'overlap',
                    true,
                );
            } else {
                $claim = $app->make(ManualPaymentSubmission::class)->execute(
                    $repairOrder,
                    null,
                    $intentKey,
                    $amountCents,
                    PaymentMethod::Cash,
                    'overlap',
                    null,
                    null,
                );
            }

            $replayed = $claim->replayed;

            return response('ok');
        });
        $status = $response->getStatusCode();
        $error = null;
    } catch (ValidationException $exception) {
        $status = 422;
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        $status = 500;
        $error = $exception::class.': '.$exception->getMessage();
    }

    return [
        'status' => $status,
        'replayed' => $replayed,
        'events' => $events,
        'error' => $error,
    ];
};

if ($role === 'child') {
    $operation = (string) $argv[2];
    $repairOrderId = (int) $argv[3];
    $intentKey = (string) $argv[4];
    $amountCents = (int) $argv[5];
    $openedVersion = (int) $argv[6];
    $gateFile = (string) $argv[7];
    $deadline = microtime(true) + 20;

    while (! is_file($gateFile)) {
        if (microtime(true) > $deadline) {
            fwrite(STDERR, "timed out waiting for the overlap gate\n");
            exit(1);
        }
        usleep(20000);
    }

    echo json_encode($submit($operation, $repairOrderId, $intentKey, $amountCents, $openedVersion), JSON_THROW_ON_ERROR);

    exit(0);
}

$kernel->call('migrate', ['--force' => true, '--quiet' => true]);

ShopSettings::current()->update([
    'shop_fee_enabled' => false,
    'tax_enabled' => false,
    'default_deposit_enabled' => false,
]);

$customer = Customer::query()->create([
    'first_name' => 'Intent',
    'last_name' => 'Overlap',
    'phone' => '555019901',
]);
$vehicle = Vehicle::query()->create([
    'customer_id' => $customer->id,
    'year' => 2018,
    'make' => 'Test',
    'model' => 'Fixture',
]);
$repairOrder = RepairOrder::query()->create([
    'customer_id' => $customer->id,
    'vehicle_id' => $vehicle->id,
    'status' => RepairOrderStatus::Approved,
    'concern_summary' => 'Financial intent overlap',
]);
$concern = RepairOrderConcern::query()->create([
    'repair_order_id' => $repairOrder->id,
    'summary' => 'Approved labor',
    'disposition' => RepairOrderConcernDisposition::Approved,
    'recommendation_intent' => 'maintenance',
    'position' => 1,
]);
RepairOrderLine::query()->create([
    'repair_order_id' => $repairOrder->id,
    'repair_order_concern_id' => $concern->id,
    'type' => RepairOrderLineType::Labor,
    'description' => 'Overlap labor',
    'quantity' => '1.00',
    'unit_price_cents' => 15000,
]);
app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

$race = static function (string $operation, int $repairOrderId, string $intentKey, int $amountCents, int $openedVersion): array {
    $gateFile = sys_get_temp_dir().'/ark-fi-intent-'.bin2hex(random_bytes(6));
    @unlink($gateFile);

    $command = [
        PHP_BINARY,
        __FILE__,
        'child',
        $operation,
        (string) $repairOrderId,
        $intentKey,
        (string) $amountCents,
        (string) $openedVersion,
        $gateFile,
    ];
    $start = static function (array $command): array {
        $pipes = [];
        $process = proc_open($command, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (! is_resource($process)) {
            throw new RuntimeException('Could not start an overlapping financial submission.');
        }

        return [$process, $pipes];
    };

    [$first, $firstPipes] = $start($command);
    [$second, $secondPipes] = $start($command);
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

        throw new RuntimeException('Overlapping financial submission exited '.$firstExit.' and '.$secondExit);
    }

    $left = json_decode((string) $firstOut, true);
    $right = json_decode((string) $secondOut, true);

    if (! is_array($left) || ! is_array($right)) {
        fwrite(STDERR, $firstErr.$secondErr.$firstOut.$secondOut);

        throw new RuntimeException('Overlapping financial submission returned invalid output.');
    }

    if (($left['error'] ?? null) !== null || ($right['error'] ?? null) !== null) {
        fwrite(STDERR, json_encode(['left' => $left, 'right' => $right], JSON_THROW_ON_ERROR));
    }

    return [$left, $right];
};

$ledger = static function (int $repairOrderId, LedgerEntryType $type) {
    return RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrderId)
        ->where('entry_type', $type)
        ->get();
};

$openedVersion = (int) $repairOrder->fresh()->estimate_version;
$depositKey = (string) Str::uuid();
[$depositLeft, $depositRight] = $race('deposit', $repairOrder->id, $depositKey, 1000, $openedVersion);
$sameKeyDeposits = $ledger($repairOrder->id, LedgerEntryType::Deposit);

$submit('deposit', $repairOrder->id, (string) Str::uuid(), 1000, (int) $repairOrder->fresh()->estimate_version);
$allDeposits = $ledger($repairOrder->id, LedgerEntryType::Deposit);

$repairOrder->forceFill(['status' => RepairOrderStatus::ReadyPickup])->save();
app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder->fresh());

$paymentKey = (string) Str::uuid();
$paymentVersion = (int) $repairOrder->fresh()->estimate_version;
[$paymentLeft, $paymentRight] = $race('payment', $repairOrder->id, $paymentKey, 1000, $paymentVersion);
$sameKeyPayments = $ledger($repairOrder->id, LedgerEntryType::Payment);

$submit('payment', $repairOrder->id, (string) Str::uuid(), 1000, (int) $repairOrder->fresh()->estimate_version);
$allPayments = $ledger($repairOrder->id, LedgerEntryType::Payment);

echo json_encode([
    'deposit_same_key' => [
        'rows' => $sameKeyDeposits->count(),
        'cents' => (int) $sameKeyDeposits->sum('amount_cents'),
        'events' => (int) $depositLeft['events'] + (int) $depositRight['events'],
        'statuses' => [(int) $depositLeft['status'], (int) $depositRight['status']],
        'replays' => (int) $depositLeft['replayed'] + (int) $depositRight['replayed'],
    ],
    'deposit_different_key' => [
        'rows' => $allDeposits->count(),
        'cents' => (int) $allDeposits->sum('amount_cents'),
    ],
    'payment_same_key' => [
        'rows' => $sameKeyPayments->count(),
        'cents' => (int) $sameKeyPayments->sum('amount_cents'),
        'events' => (int) $paymentLeft['events'] + (int) $paymentRight['events'],
        'statuses' => [(int) $paymentLeft['status'], (int) $paymentRight['status']],
        'replays' => (int) $paymentLeft['replayed'] + (int) $paymentRight['replayed'],
    ],
    'payment_different_key' => [
        'rows' => $allPayments->count(),
        'cents' => (int) $allPayments->sum('amount_cents'),
    ],
], JSON_THROW_ON_ERROR);
