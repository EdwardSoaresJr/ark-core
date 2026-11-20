<?php

/**
 * Local/Herd owner-acceptance fixtures for Core #224 / PR #226.
 *
 * Usage (from the AR worktree, against the local shop DB only):
 *   php scripts/seed-ar-224-acceptance.php
 *
 * Refuses non-local databases. Never run against production.
 */

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\GenerateInvoiceSnapshotAction;
use App\Ark\Operations\Financial\OverpaymentDisposition;
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

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$connection = config('database.default');
$host = (string) config("database.connections.{$connection}.host");
$database = (string) config("database.connections.{$connection}.database");
$env = (string) app()->environment();

$localHosts = ['127.0.0.1', '::1', 'localhost'];
if (! in_array($host, $localHosts, true) || ! in_array($env, ['local', 'testing'], true)) {
    fwrite(STDERR, "Refusing to seed AR acceptance data outside a local/testing Herd DB (host={$host}, env={$env}, db={$database}).\n");
    exit(1);
}

if (preg_match('/prod|live|lugs/i', $database) === 1) {
    fwrite(STDERR, "Refusing to seed a database name that looks production-like: {$database}\n");
    exit(1);
}

$app->make(ArkAuthorizationSeeder::class)->run();
RepairOrderStatusCatalogDefaults::sync(app(RepairOrderStatusCatalog::class));
ShopDisplayTimezone::apply();

ShopSettings::current()->update([
    'shop_fee_enabled' => false,
    'tax_enabled' => false,
    'default_deposit_enabled' => false,
]);

$advisor = User::query()->role(ArkRole::Advisor->value)->orderBy('id')->first()
    ?? User::factory()->create(['email' => 'ar-accept-advisor@example.test', 'name' => 'AR Accept Advisor'])->assignRole(ArkRole::Advisor->value);

auth()->login($advisor);

$marker = 'AR224-ACCEPT';
$baseUrl = rtrim((string) config('app.url'), '/');

$money = static fn (int $cents): string => '$'.number_format($cents / 100, 2);

$resetCustomerAccount = static function (Customer $customer) use ($marker): void {
    // Fresh acceptance session: drop prior synthetic ROs/invoices/receipts for this customer only.
    $roIds = RepairOrder::query()
        ->where('customer_id', $customer->id)
        ->where('concern_summary', 'like', $marker.'%')
        ->pluck('id');

    if ($roIds->isNotEmpty()) {
        \App\Ark\Operations\Financial\RepairOrderLedgerEntry::query()
            ->whereIn('repair_order_id', $roIds)
            ->delete();
        \App\Ark\Operations\Documents\EstimateDocument::query()
            ->whereIn('repair_order_id', $roIds)
            ->delete();
        \App\Ark\Operations\Payments\Capture\PaymentCaptureAttempt::query()
            ->whereIn('repair_order_id', $roIds)
            ->delete();
        RepairOrderLine::query()->whereIn('repair_order_id', $roIds)->delete();
        RepairOrderConcern::query()->whereIn('repair_order_id', $roIds)->delete();
        RepairOrder::query()->whereIn('id', $roIds)->delete();
    }

    \App\Ark\Operations\Financial\CustomerPaymentReceipt::query()
        ->where('customer_id', $customer->id)
        ->delete();

    $customer->forceFill(['store_credit_balance_cents' => 0])->save();
};

$makeCustomer = static function (string $first, string $last, string $email, string $phone, OverpaymentDisposition $default) use ($resetCustomerAccount): Customer {
    $existing = Customer::query()->where('email', $email)->first();
    if ($existing !== null) {
        $existing->forceFill([
            'first_name' => $first,
            'last_name' => $last,
            'phone' => $phone,
            'overpayment_disposition_default' => $default,
            'store_credit_balance_cents' => 0,
        ])->save();
        $resetCustomerAccount($existing->fresh());

        return $existing->fresh();
    }

    return Customer::query()->create([
        'first_name' => $first,
        'last_name' => $last,
        'email' => $email,
        'phone' => $phone,
        'address_line_1' => '100 Acceptance Ave',
        'city' => 'Denver',
        'state' => 'CO',
        'postal_code' => '80202',
        'overpayment_disposition_default' => $default,
        'store_credit_balance_cents' => 0,
    ]);
};

$onAccountInvoice = static function (Customer $customer, string $summary, int $laborCents) use ($advisor, $marker): array {
    $vehicle = $customer->vehicles()->first() ?? Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Civic',
        'license_plate' => 'AR224',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::ReadyPickup,
        'concern_summary' => $marker.' · '.$summary,
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => $summary,
        'disposition' => RepairOrderConcernDisposition::Approved,
        'production_status' => ScopeProductionStatus::Completed,
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => $summary,
        'quantity' => '1.00',
        'unit_price_cents' => $laborCents,
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
    $balance = app(BalanceDueCalculator::class)->forRepairOrder($closed->fresh())->balanceDueCents;

    return [$closed->fresh(), $invoice, $balance];
};

$customers = [];

$multi = $makeCustomer(
    'AR Multi',
    'Invoice',
    'ar224.multi@example.test',
    '5550102241',
    OverpaymentDisposition::Ask,
);
[$roA, $invA, $balA] = $onAccountInvoice($multi, 'Front brakes', 15000);
[$roB, $invB, $balB] = $onAccountInvoice($multi, 'Alignment', 12000);
[$roC, $invC, $balC] = $onAccountInvoice($multi, 'Oil service', 8000);
$customers['multi'] = [
    'customer' => $multi,
    'purpose' => 'Multi-invoice receive payment (partial + full)',
    'invoices' => [
        ['ro' => $roA, 'invoice' => $invA, 'balance_cents' => $balA],
        ['ro' => $roB, 'invoice' => $invB, 'balance_cents' => $balB],
        ['ro' => $roC, 'invoice' => $invC, 'balance_cents' => $balC],
    ],
    'total_outstanding_cents' => $balA + $balB + $balC,
];

$credit = $makeCustomer(
    'AR Credit',
    'Retain',
    'ar224.credit@example.test',
    '5550102242',
    OverpaymentDisposition::KeepCredit,
);
$credit->forceFill(['store_credit_balance_cents' => 5000])->save();
[$roCredit, $invCredit, $balCredit] = $onAccountInvoice($credit, 'Battery replacement', 18000);
$customers['credit'] = [
    'customer' => $credit->fresh(),
    'purpose' => 'Apply retained account credit, then keep-credit overpayment',
    'store_credit_cents' => 5000,
    'invoices' => [
        ['ro' => $roCredit, 'invoice' => $invCredit, 'balance_cents' => $balCredit],
    ],
    'total_outstanding_cents' => $balCredit,
];

$refund = $makeCustomer(
    'AR Refund',
    'Pending',
    'ar224.refund@example.test',
    '5550102243',
    OverpaymentDisposition::Ask,
);
[$roRefund, $invRefund, $balRefund] = $onAccountInvoice($refund, 'Diagnostic fee', 10000);
$customers['refund'] = [
    'customer' => $refund,
    'purpose' => 'Check overpayment → pending refund → confirm manual return',
    'invoices' => [
        ['ro' => $roRefund, 'invoice' => $invRefund, 'balance_cents' => $balRefund],
    ],
    'total_outstanding_cents' => $balRefund,
];

$statement = $makeCustomer(
    'AR Statement',
    'Fleet',
    'ar224.statement@example.test',
    '5550102244',
    OverpaymentDisposition::Ask,
);
[$roStmt1, $invStmt1, $balStmt1] = $onAccountInvoice($statement, 'Fleet inspection A', 22000);
[$roStmt2, $invStmt2, $balStmt2] = $onAccountInvoice($statement, 'Fleet inspection B', 17500);
$customers['statement'] = [
    'customer' => $statement,
    'purpose' => 'Statement preview / PDF / email (email needs Shop #29 + Platform mail)',
    'invoices' => [
        ['ro' => $roStmt1, 'invoice' => $invStmt1, 'balance_cents' => $balStmt1],
        ['ro' => $roStmt2, 'invoice' => $invStmt2, 'balance_cents' => $balStmt2],
    ],
    'total_outstanding_cents' => $balStmt1 + $balStmt2,
];

$card = $makeCustomer(
    'AR Card',
    'Keyed',
    'ar224.card@example.test',
    '5550102245',
    OverpaymentDisposition::Ask,
);
[$roCard1, $invCard1, $balCard1] = $onAccountInvoice($card, 'Keyed capture A', 15000);
[$roCard2, $invCard2, $balCard2] = $onAccountInvoice($card, 'Keyed capture B', 15000);
$customers['card'] = [
    'customer' => $card,
    'purpose' => 'Keyed / portal card capture when Platform readiness is connected',
    'invoices' => [
        ['ro' => $roCard1, 'invoice' => $invCard1, 'balance_cents' => $balCard1],
        ['ro' => $roCard2, 'invoice' => $invCard2, 'balance_cents' => $balCard2],
    ],
    'total_outstanding_cents' => $balCard1 + $balCard2,
];

echo "ARK #224 owner-acceptance fixtures seeded\n";
echo "SHA tip: run `git rev-parse HEAD` in the AR worktree\n";
echo "App: {$baseUrl}\n";
echo "DB: {$database}@{$host} (env={$env})\n";
echo "Staff login: use an existing advisor/admin (seed actor {$advisor->email})\n";
echo "Marker on RO concern summaries: {$marker}\n\n";

foreach ($customers as $key => $row) {
    /** @var Customer $customer */
    $customer = $row['customer'];
    echo strtoupper($key)." — {$row['purpose']}\n";
    echo "  Customer #{$customer->id} {$customer->name} <{$customer->email}>\n";
    echo "  Account: {$baseUrl}/app/financials/accounts-receivable/customers/{$customer->id}\n";
    echo "  Receive: {$baseUrl}/app/financials/accounts-receivable/customers/{$customer->id}/receive-payment\n";
    echo "  Statement: {$baseUrl}/app/financials/accounts-receivable/customers/{$customer->id}/statement\n";
    if (isset($row['store_credit_cents'])) {
        echo '  Store credit on file: '.$money((int) $row['store_credit_cents'])."\n";
    }
    echo '  Outstanding total: '.$money((int) $row['total_outstanding_cents'])."\n";
    foreach ($row['invoices'] as $invoiceRow) {
        $invoice = $invoiceRow['invoice'];
        $ro = $invoiceRow['ro'];
        echo '    Invoice id '.$invoice->id
            .' (doc #'.($invoice->document_number ?? 'n/a').')'
            .' · RO id '.$ro->id
            .' · balance '.$money((int) $invoiceRow['balance_cents'])."\n";
    }
    echo "\n";
}

echo "AR index: {$baseUrl}/app/financials/accounts-receivable\n";
echo "See docs/review-artifacts/accounts-receivable-224/OWNER_ACCEPTANCE.md for the test script.\n";
