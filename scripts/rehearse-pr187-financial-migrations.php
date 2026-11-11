<?php

/**
 * Disposable migration rehearsal for PR #187's six financial migrations.
 *
 * php scripts/rehearse-pr187-financial-migrations.php
 *
 * Creates/drops MySQL database ark_pr187_mig_rehearsal. Never touches production.
 */

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';

$database = 'ark_pr187_mig_rehearsal';
$pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec("DROP DATABASE IF EXISTS `{$database}`");
$pdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$app = require $root.'/bootstrap/app.php';
/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

config([
    'database.default' => 'mysql',
    'database.connections.mysql.database' => $database,
    'database.connections.mysql.host' => '127.0.0.1',
    'database.connections.mysql.port' => '3306',
    'database.connections.mysql.username' => 'root',
    'database.connections.mysql.password' => '',
]);
DB::purge('mysql');
DB::reconnect('mysql');

$migrations = [
    '2026_11_10_010000_mark_standard_labor_policies.php',
    '2026_11_10_120000_add_labor_pricing_profile_and_billing_minimums.php',
    '2026_11_10_180000_labor_matrix_profile_v2_and_programming_split.php',
    '2026_11_10_210000_shop_fee_flat_mode_and_profile.php',
    '2026_11_10_220000_commercial_pricing_profile_on_repair_orders.php',
    '2026_11_10_230000_tax_profile_on_repair_orders.php',
];

$dir = $root.'/database/migrations';
$parked = sys_get_temp_dir().'/ark-pr187-migrations-parked';
@mkdir($parked, 0777, true);
foreach (glob("{$parked}/*.php") ?: [] as $stale) {
    @unlink($stale);
}

foreach ($migrations as $file) {
    if (! is_file("{$dir}/{$file}")) {
        fwrite(STDERR, "Missing migration {$file}\n");
        exit(1);
    }
    if (! rename("{$dir}/{$file}", "{$parked}/{$file}")) {
        fwrite(STDERR, "Failed to park {$file}\n");
        exit(1);
    }
}

$restore = static function () use ($migrations, $dir, $parked): void {
    foreach ($migrations as $file) {
        $from = "{$parked}/{$file}";
        $to = "{$dir}/{$file}";
        if (is_file($from) && ! is_file($to)) {
            rename($from, $to);
        }
    }
};
register_shutdown_function($restore);

$fail = static function (string $message) use ($pdo, $database): never {
    fwrite(STDERR, $message."\n");
    try {
        $pdo->exec("DROP DATABASE IF EXISTS `{$database}`");
    } catch (Throwable) {
    }
    exit(1);
};

echo "==> Migrating existing-schema baseline (PR #187 six parked)\n";
if ($kernel->call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
    $fail('Baseline migrate failed');
}

echo "==> Seeding ShopSettings + authorization (existing-schema shop data)\n";
if ($kernel->call('db:seed', [
    '--class' => 'Database\\Seeders\\ArkAuthorizationSeeder',
    '--force' => true,
    '--no-interaction' => true,
]) !== 0) {
    $fail('ArkAuthorizationSeeder failed');
}
if ($kernel->call('db:seed', [
    '--class' => 'Database\\Seeders\\ShopSettingsSeeder',
    '--force' => true,
    '--no-interaction' => true,
]) !== 0) {
    $fail('ShopSettingsSeeder failed');
}

$now = now();
$defaultCents = (int) (DB::table('shop_settings')->value('default_labor_rate_cents') ?: 16500);
DB::table('shop_settings')->limit(1)->update([
    'default_labor_rate_cents' => $defaultCents,
    'updated_at' => $now,
]);

if (! Schema::hasTable('labor_policies') || DB::table('labor_policies')->count() === 0) {
    $fail('Expected labor_policies seeded by baseline migrations');
}

// Force legacy shape: open Customer Pay rows matching shop default are hourly (pre-mark_standard).
DB::table('labor_policies')
    ->where('billing_posture', 'customer_pay')
    ->where('hourly_rate_cents', $defaultCents)
    ->whereNull('effective_until')
    ->update([
        'rate_type' => 'hourly',
        'updated_at' => $now,
    ]);

$mechId = DB::table('operation_classes')->where('key', 'general_repair')->value('id')
    ?? DB::table('operation_classes')->orderBy('id')->value('id');
if ($mechId === null) {
    $fail('No operation_classes');
}

// Open specialty custom rate — must remain hourly after mark_standard.
DB::table('labor_policies')->insert([
    'billing_posture' => 'customer_pay',
    'operation_class_id' => $mechId,
    'rate_type' => 'hourly',
    'hourly_rate_cents' => 19000,
    'effective_from' => '2026-06-01',
    'effective_until' => null,
    'priority' => 50,
    'version' => 99,
    'change_reason' => 'pr187-rehearsal open specialty',
    'created_at' => $now,
    'updated_at' => $now,
]);

// Closed historical matching-default hourly — must NOT become standard (effective_until set).
DB::table('labor_policies')->insert([
    'billing_posture' => 'customer_pay',
    'operation_class_id' => $mechId,
    'rate_type' => 'hourly',
    'hourly_rate_cents' => $defaultCents,
    'effective_from' => '2025-01-01',
    'effective_until' => '2025-12-31',
    'priority' => 1,
    'version' => 1,
    'change_reason' => 'pr187-rehearsal closed matching default',
    'created_at' => $now,
    'updated_at' => $now,
]);

$closedRoId = null;
$closedSnapshot = null;
if (Schema::hasTable('repair_orders') && Schema::hasTable('customers') && Schema::hasTable('vehicles')) {
    $customerCols = array_flip(Schema::getColumnListing('customers'));
    $customer = ['created_at' => $now, 'updated_at' => $now];
    if (isset($customerCols['first_name'])) {
        $customer['first_name'] = 'PR187';
    }
    if (isset($customerCols['last_name'])) {
        $customer['last_name'] = 'Rehearsal';
    }
    if (isset($customerCols['display_name'])) {
        $customer['display_name'] = 'PR187 Rehearsal';
    }
    $customerId = DB::table('customers')->insertGetId($customer);

    $vehicleCols = array_flip(Schema::getColumnListing('vehicles'));
    $vehicle = [
        'customer_id' => $customerId,
        'created_at' => $now,
        'updated_at' => $now,
    ];
    if (isset($vehicleCols['year'])) {
        $vehicle['year'] = 2018;
    }
    if (isset($vehicleCols['make'])) {
        $vehicle['make'] = 'Honda';
    }
    if (isset($vehicleCols['model'])) {
        $vehicle['model'] = 'Accord';
    }
    $vehicleId = DB::table('vehicles')->insertGetId($vehicle);

    $cols = array_flip(Schema::getColumnListing('repair_orders'));
    $ro = [
        'customer_id' => $customerId,
        'vehicle_id' => $vehicleId,
        'created_at' => $now,
        'updated_at' => $now,
    ];
    if (isset($cols['status'])) {
        $ro['status'] = 'closed';
    }
    if (isset($cols['concern_summary'])) {
        $ro['concern_summary'] = 'PR187 migration rehearsal closed RO';
    }
    if (isset($cols['public_id'])) {
        $ro['public_id'] = 'pr187-closed-ro';
    }
    $closedRoId = DB::table('repair_orders')->insertGetId($ro);
    $closedSnapshot = (array) DB::table('repair_orders')->where('id', $closedRoId)->first();
}

$beforeOpenMatching = DB::table('labor_policies')
    ->where('billing_posture', 'customer_pay')
    ->where('rate_type', 'hourly')
    ->where('hourly_rate_cents', $defaultCents)
    ->whereNull('effective_until')
    ->count();
$beforeStandard = DB::table('labor_policies')->where('rate_type', 'standard')->count();
$beforePolicyCount = DB::table('labor_policies')->count();
$beforeClosedMatchingHourly = DB::table('labor_policies')
    ->where('change_reason', 'pr187-rehearsal closed matching default')
    ->where('rate_type', 'hourly')
    ->count();

echo "Baseline policies={$beforePolicyCount} standard={$beforeStandard} open_matching_hourly={$beforeOpenMatching} default_cents={$defaultCents}\n";

echo "==> Restoring six migrations and applying\n";
$restore();
if ($kernel->call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
    $fail('PR #187 migrations failed');
}

$checks = [];

$afterStandard = DB::table('labor_policies')->where('rate_type', 'standard')->count();
$afterOpenMatchingHourly = DB::table('labor_policies')
    ->where('billing_posture', 'customer_pay')
    ->where('rate_type', 'hourly')
    ->where('hourly_rate_cents', $defaultCents)
    ->whereNull('effective_until')
    ->count();

$checks['open_matching_became_standard'] = $beforeOpenMatching > 0
    && $afterStandard >= $beforeOpenMatching
    && $afterOpenMatchingHourly === 0;
$checks['specialty_19000_untouched'] = DB::table('labor_policies')
    ->where('hourly_rate_cents', 19000)
    ->where('rate_type', 'hourly')
    ->whereNull('effective_until')
    ->exists();
$checks['closed_matching_default_stays_hourly'] = DB::table('labor_policies')
    ->where('change_reason', 'pr187-rehearsal closed matching default')
    ->where('rate_type', 'hourly')
    ->count() === $beforeClosedMatchingHourly;

$checks['col_labor_pricing_profile'] = Schema::hasColumn('repair_orders', 'labor_pricing_profile');
$checks['col_commercial_pricing_profile'] = Schema::hasColumn('repair_orders', 'commercial_pricing_profile');
$checks['col_tax_profile'] = Schema::hasColumn('repair_orders', 'tax_profile');
$checks['col_shop_fee_profile'] = Schema::hasColumn('repair_orders', 'shop_fee_profile');
$checks['col_shop_fee_mode'] = Schema::hasColumn('shop_settings', 'shop_fee_mode');
$checks['col_labor_billing_minimums'] = Schema::hasColumn('shop_settings', 'labor_billing_minimums');
$checks['col_system_line_kind'] = Schema::hasColumn('repair_order_lines', 'system_line_kind');
$checks['programming_operation_class'] = DB::table('operation_classes')->where('key', 'programming')->exists();

if ($closedRoId !== null && $closedSnapshot !== null) {
    $afterRo = (array) DB::table('repair_orders')->where('id', $closedRoId)->first();
    $checks['closed_ro_profiles_null'] = ($afterRo['labor_pricing_profile'] ?? null) === null
        && ($afterRo['commercial_pricing_profile'] ?? null) === null
        && ($afterRo['tax_profile'] ?? null) === null
        && ($afterRo['shop_fee_profile'] ?? null) === null;
    foreach (['status', 'public_id'] as $col) {
        if (array_key_exists($col, $closedSnapshot)) {
            $checks["closed_ro_{$col}_preserved"] = ($closedSnapshot[$col] ?? null) == ($afterRo[$col] ?? null);
        }
    }
}

echo "==> Second artisan migrate (repeatability; no pending work)\n";
$checks['second_migrate_exit_0'] = $kernel->call('migrate', ['--force' => true, '--no-interaction' => true]) === 0;

echo "==> Re-invoke mark_standard up() (already-standard rows stay stable)\n";
$standardBeforeReplay = DB::table('labor_policies')->where('rate_type', 'standard')->count();
(require $dir.'/2026_11_10_010000_mark_standard_labor_policies.php')->up();
$checks['mark_standard_replay_stable'] = DB::table('labor_policies')->where('rate_type', 'standard')->count() === $standardBeforeReplay;
$checks['specialty_still_hourly_after_replay'] = DB::table('labor_policies')
    ->where('hourly_rate_cents', 19000)
    ->where('rate_type', 'hourly')
    ->whereNull('effective_until')
    ->exists();

echo "==> Re-invoke guarded additive ups\n";
foreach ([
    '2026_11_10_180000_labor_matrix_profile_v2_and_programming_split.php',
    '2026_11_10_210000_shop_fee_flat_mode_and_profile.php',
    '2026_11_10_220000_commercial_pricing_profile_on_repair_orders.php',
    '2026_11_10_230000_tax_profile_on_repair_orders.php',
] as $file) {
    try {
        (require "{$dir}/{$file}")->up();
        $checks["guarded_replay_ok_{$file}"] = true;
    } catch (Throwable $e) {
        $checks["guarded_replay_ok_{$file}"] = false;
        echo "Replay failed {$file}: {$e->getMessage()}\n";
    }
}

// 120000 lacks hasColumn guards — second artisan migrate covers it; direct replay is expected to fail.
try {
    (require $dir.'/2026_11_10_120000_add_labor_pricing_profile_and_billing_minimums.php')->up();
    $checks['unguarded_120000_replay_raises'] = false;
} catch (Throwable) {
    $checks['unguarded_120000_replay_raises'] = true;
}

$failed = array_filter($checks, fn ($ok) => $ok !== true);
echo "\n=== RESULTS ===\n";
foreach ($checks as $name => $ok) {
    echo ($ok ? 'PASS' : 'FAIL')."  {$name}\n";
}
echo "\nstandard_count={$afterStandard} open_matching_hourly_after={$afterOpenMatchingHourly}\n";

$pdo->exec("DROP DATABASE IF EXISTS `{$database}`");
echo "Dropped disposable database {$database}\n";

exit($failed === [] ? 0 : 1);
