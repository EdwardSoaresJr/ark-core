<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderEstimateChanged;
use App\Ark\Operations\RepairOrders\RepairOrderEstimateVersion;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Http\Middleware\CommitRepairOrderEstimateLock;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpFoundation\Response;

$role = $argv[1] ?? 'parent';

$database = 'ark_concurrency_phase2';
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

$mutate = static function (int $repairOrderId, int $lineId, ?int $openedVersion, string $description) use ($app): array {
    $repairOrder = RepairOrder::query()->findOrFail($repairOrderId);
    $line = RepairOrderLine::query()->findOrFail($lineId);
    $request = Request::create('/overlap', 'POST', array_filter([
        RepairOrderConcurrency::FIELD => $openedVersion,
    ], static fn (mixed $value): bool => $value !== null));
    $concurrency = $app->make(RepairOrderConcurrency::class);
    $middleware = $app->make(CommitRepairOrderEstimateLock::class);
    $enteredAt = microtime(true);

    try {
        $response = $middleware->handle($request, function () use ($concurrency, $request, $repairOrder, $line, $description): Response {
            $concurrency->guard($request, $repairOrder);
            RepairOrderLine::query()->whereKey($line->id)->update([
                'description' => $description,
            ]);
            app(RepairOrderEstimateVersion::class)->bump($repairOrder->fresh());

            return response('ok');
        });
        $status = $response->getStatusCode();
    } catch (HttpResponseException $exception) {
        $status = $exception->getResponse()->getStatusCode();
    }

    $freshLine = RepairOrderLine::query()->findOrFail($lineId);
    $freshOrder = RepairOrder::query()->findOrFail($repairOrderId);

    return [
        'status' => $status,
        'entered_at' => $enteredAt,
        'finished_at' => microtime(true),
        'description' => $freshLine->description,
        'estimate_version' => (int) $freshOrder->estimate_version,
    ];
};

if ($role === 'child') {
    $repairOrderId = (int) $argv[2];
    $lineId = (int) $argv[3];
    $openedVersion = (int) $argv[4];
    $gateFile = $argv[5];
    $deadline = microtime(true) + 20;

    while (! is_file($gateFile)) {
        if (microtime(true) > $deadline) {
            fwrite(STDERR, "timed out waiting for the lock gate\n");
            exit(1);
        }
        usleep(20000);
    }

    $result = $mutate($repairOrderId, $lineId, $openedVersion, 'Loser');
    echo json_encode($result, JSON_THROW_ON_ERROR);

    exit(0);
}

$kernel->call('migrate', ['--force' => true, '--quiet' => true]);

$customer = Customer::query()->create([
    'first_name' => 'Overlap',
    'last_name' => 'Writer',
    'phone' => '5550199',
]);
$vehicle = Vehicle::query()->create([
    'customer_id' => $customer->id,
    'year' => 2019,
    'make' => 'Honda',
    'model' => 'Civic',
]);
$repairOrder = RepairOrder::query()->create([
    'customer_id' => $customer->id,
    'vehicle_id' => $vehicle->id,
    'status' => RepairOrderStatus::Estimate,
    'concern_summary' => 'Overlap fixture',
    'estimate_version' => 4,
]);
$concern = RepairOrderConcern::query()->create([
    'repair_order_id' => $repairOrder->id,
    'summary' => 'Overlap concern',
    'disposition' => RepairOrderConcernDisposition::Recommended,
    'recommendation_intent' => 'maintenance',
    'position' => 1,
]);
$line = RepairOrderLine::query()->create([
    'repair_order_id' => $repairOrder->id,
    'repair_order_concern_id' => $concern->id,
    'type' => RepairOrderLineType::Labor,
    'description' => 'Original labor',
    'quantity' => '1.00',
    'unit_price_cents' => 10000,
    'subtotal_cents' => 10000,
    'tax_cents' => 0,
    'shop_fee_cents' => 0,
    'total_cents' => 10000,
]);

$openedVersion = (int) $repairOrder->fresh()->estimate_version;
$gateFile = sys_get_temp_dir().'/ark-ro-overlap-'.bin2hex(random_bytes(6));
@unlink($gateFile);

$childCommand = [
    PHP_BINARY,
    __FILE__,
    'child',
    (string) $repairOrder->id,
    (string) $line->id,
    (string) $openedVersion,
    $gateFile,
];
$pipes = [];
$child = proc_open($childCommand, [
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
], $pipes);

if (! is_resource($child)) {
    fwrite(STDERR, "could not start the overlapping writer\n");
    exit(1);
}

Event::fake([RepairOrderEstimateChanged::class]);
$lockTiming = ['held_at' => null, 'released_at' => null];
app(RepairOrderConcurrency::class)->setAfterLockHeld(function () use ($gateFile, &$lockTiming): void {
    $lockTiming['held_at'] = microtime(true);
    file_put_contents($gateFile, 'held');
    usleep(1500000);
    $lockTiming['released_at'] = microtime(true);
});

$winner = $mutate($repairOrder->id, $line->id, $openedVersion, 'Winner');
$winner['committed_at'] = microtime(true);
$winner['lock'] = $lockTiming;
$winner['broadcasts'] = count(Event::dispatched(RepairOrderEstimateChanged::class));

$childStdout = stream_get_contents($pipes[1]);
$childStderr = stream_get_contents($pipes[2]);
$childExit = proc_close($child);
@unlink($gateFile);

$loser = json_decode($childStdout ?: 'null', true);

$rollbackOrder = RepairOrder::query()->create([
    'customer_id' => $customer->id,
    'vehicle_id' => $vehicle->id,
    'status' => RepairOrderStatus::Estimate,
    'concern_summary' => 'Rollback fixture',
    'estimate_version' => 7,
]);
$rollbackConcern = RepairOrderConcern::query()->create([
    'repair_order_id' => $rollbackOrder->id,
    'summary' => 'Rollback concern',
    'disposition' => RepairOrderConcernDisposition::Recommended,
    'recommendation_intent' => 'maintenance',
    'position' => 1,
]);
$rollbackLine = RepairOrderLine::query()->create([
    'repair_order_id' => $rollbackOrder->id,
    'repair_order_concern_id' => $rollbackConcern->id,
    'type' => RepairOrderLineType::Labor,
    'description' => 'Keep this labor',
    'quantity' => '1.00',
    'unit_price_cents' => 10000,
    'subtotal_cents' => 10000,
    'tax_cents' => 0,
    'shop_fee_cents' => 0,
    'total_cents' => 10000,
]);

Event::fake([RepairOrderEstimateChanged::class]);
$rollbackRequest = Request::create('/overlap-rollback', 'POST', [
    RepairOrderConcurrency::FIELD => (int) $rollbackOrder->estimate_version,
]);
$rollbackConcurrency = app(RepairOrderConcurrency::class);
$rollbackConcurrency->setAfterLockHeld(null);

try {
    app(CommitRepairOrderEstimateLock::class)->handle($rollbackRequest, function () use ($rollbackConcurrency, $rollbackRequest, $rollbackOrder, $rollbackLine): Response {
        $rollbackConcurrency->guard($rollbackRequest, $rollbackOrder);
        RepairOrderLine::query()->whereKey($rollbackLine->id)->update([
            'description' => 'Should roll back',
        ]);
        app(RepairOrderEstimateVersion::class)->bump($rollbackOrder->fresh());

        throw new RuntimeException('domain mutation failed');
    });
    $rollbackThrew = false;
} catch (RuntimeException $exception) {
    $rollbackThrew = $exception->getMessage() === 'domain mutation failed';
}

$probe = DB::connection('mysql');
$rolledLine = $probe->table('repair_order_lines')->where('id', $rollbackLine->id)->first();
$rolledOrder = $probe->table('repair_orders')->where('id', $rollbackOrder->id)->first();
$broadcasts = Event::dispatched(RepairOrderEstimateChanged::class);

echo json_encode([
    'winner' => $winner,
    'loser' => $loser,
    'child_exit' => $childExit,
    'child_stderr' => $childStderr,
    'rollback' => [
        'threw' => $rollbackThrew,
        'description' => $rolledLine->description ?? null,
        'estimate_version' => isset($rolledOrder->estimate_version) ? (int) $rolledOrder->estimate_version : null,
        'broadcasts' => count($broadcasts),
    ],
], JSON_THROW_ON_ERROR);
