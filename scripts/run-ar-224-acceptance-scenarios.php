<?php

/**
 * Executes owner-acceptance scenarios 1–4 against the local Herd DB.
 * Prints evidence. Does not touch production.
 */

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\AccountsReceivableOutstandingQuery;
use App\Ark\Operations\Financial\AllocateCustomerPaymentAction;
use App\Ark\Operations\Financial\ApplyCustomerStoreCreditAction;
use App\Ark\Operations\Financial\ConfirmCustomerPaymentRefundAction;
use App\Ark\Operations\Financial\CustomerAccountStatementBuilder;
use App\Ark\Operations\Financial\CustomerPaymentReceipt;
use App\Ark\Operations\Financial\OverpaymentDisposition;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$host = (string) config('database.connections.'.config('database.default').'.host');
$env = (string) app()->environment();
if (! in_array($host, ['127.0.0.1', '::1', 'localhost'], true) || ! in_array($env, ['local', 'testing'], true)) {
    fwrite(STDERR, "Refusing acceptance runner outside local/testing.\n");
    exit(1);
}

$advisor = User::query()->role(ArkRole::Advisor->value)->orderBy('id')->first()
    ?? User::query()->role(ArkRole::Admin->value)->orderBy('id')->first();
if ($advisor === null) {
    fwrite(STDERR, "No advisor/admin user available.\n");
    exit(1);
}
auth()->login($advisor);

$money = static fn (int $cents): string => '$'.number_format($cents / 100, 2);
$fail = 0;
$pass = static function (string $label, bool $ok, string $detail = '') use (&$fail): void {
    echo ($ok ? 'PASS' : 'FAIL')."  {$label}".($detail !== '' ? " — {$detail}" : '')."\n";
    if (! $ok) {
        $fail++;
    }
};

$balancesFor = static function (Customer $customer): array {
    $account = app(AccountsReceivableOutstandingQuery::class)->customerAccount($customer);
    $rows = [];
    $total = 0;
    foreach ($account['invoices'] as $invoice) {
        $due = (int) $invoice['balance_due_cents'];
        $rows[] = [
            'invoice_id' => (int) $invoice['invoice_id'],
            'ro_id' => (int) $invoice['repair_order_id'],
            'due' => $due,
        ];
        $total += $due;
    }

    return ['rows' => $rows, 'total' => $total];
};

echo "ARK #224 acceptance scenarios 1–4\n";
echo 'Actor: '.$advisor->email."\n";
echo 'HEAD: '.trim((string) shell_exec('git -C '.escapeshellarg(dirname(__DIR__)).' rev-parse HEAD'))."\n\n";

// --- 1 Multi ---
$multi = Customer::query()->where('email', 'ar224.multi@example.test')->firstOrFail();
$before = $balancesFor($multi);
$pass('1a Multi outstanding starts at $350', $before['total'] === 35000, $money($before['total']));

$alloc200 = [];
$remaining = 20000;
foreach ($before['rows'] as $row) {
    if ($remaining <= 0) {
        break;
    }
    $apply = min($row['due'], $remaining);
    $alloc200[] = ['invoice_id' => $row['invoice_id'], 'amount_cents' => $apply];
    $remaining -= $apply;
}
$r1 = app(AllocateCustomerPaymentAction::class)->execute(
    $multi, 20000, PaymentMethod::Check, $alloc200, (string) Str::uuid(), $advisor, 'CHK-ACCEPT-200',
);
$mid = $balancesFor($multi);
$pass('1b After $200 check outstanding is $150', $mid['total'] === 15000, $money($mid['total']).'; receipt allocated '.$money($r1->allocated_cents));

$alloc150 = [];
$remaining = 15000;
foreach ($mid['rows'] as $row) {
    if ($remaining <= 0) {
        break;
    }
    $apply = min($row['due'], $remaining);
    $alloc150[] = ['invoice_id' => $row['invoice_id'], 'amount_cents' => $apply];
    $remaining -= $apply;
}
$r2 = app(AllocateCustomerPaymentAction::class)->execute(
    $multi, 15000, PaymentMethod::Check, $alloc150, (string) Str::uuid(), $advisor, 'CHK-ACCEPT-150',
);
$after = $balancesFor($multi);
$pass('1c After $150 check all invoices paid', $after['total'] === 0, $money($after['total']).'; receipts '.$r1->id.'/'.$r2->id);

// --- 2 Credit ---
$credit = Customer::query()->where('email', 'ar224.credit@example.test')->firstOrFail();
$creditBefore = $balancesFor($credit);
$pass('2a Credit customer starts $180 due + $50 credit', $creditBefore['total'] === 18000 && (int) $credit->store_credit_balance_cents === 5000,
    'due '.$money($creditBefore['total']).' credit '.$money((int) $credit->store_credit_balance_cents));

$invoiceId = $creditBefore['rows'][0]['invoice_id'];
$creditReceipt = app(ApplyCustomerStoreCreditAction::class)->execute(
    $credit,
    [['invoice_id' => $invoiceId, 'amount_cents' => 5000]],
    (string) Str::uuid(),
    $advisor,
    'CREDIT-APPLY-50',
);
$credit->refresh();
$afterCredit = $balancesFor($credit);
$pass('2b $50 credit applied with no new charge', $afterCredit['total'] === 13000 && (int) $credit->store_credit_balance_cents === 0
    && $creditReceipt->amount_cents === 5000 && $creditReceipt->allocated_cents === 5000,
    'due '.$money($afterCredit['total']).' credit '.$money((int) $credit->store_credit_balance_cents));

$keep = app(AllocateCustomerPaymentAction::class)->execute(
    $credit->fresh(),
    15000,
    PaymentMethod::Check,
    [['invoice_id' => $invoiceId, 'amount_cents' => 13000]],
    (string) Str::uuid(),
    $advisor,
    'CHK-KEEP-CREDIT',
    null,
    OverpaymentDisposition::KeepCredit,
);
$credit->refresh();
$afterKeep = $balancesFor($credit);
$pass('2c $150 check keeps $20 account credit', $afterKeep['total'] === 0 && (int) $credit->store_credit_balance_cents === 2000
    && $keep->unapplied_cents === 2000,
    'due '.$money($afterKeep['total']).' credit '.$money((int) $credit->store_credit_balance_cents).' unapplied '.$money($keep->unapplied_cents));

// --- 3 Refund ---
$refundCustomer = Customer::query()->where('email', 'ar224.refund@example.test')->firstOrFail();
$refundBefore = $balancesFor($refundCustomer);
$refundInvoice = $refundBefore['rows'][0]['invoice_id'];
$pending = app(AllocateCustomerPaymentAction::class)->execute(
    $refundCustomer,
    13000,
    PaymentMethod::Check,
    [['invoice_id' => $refundInvoice, 'amount_cents' => 10000]],
    (string) Str::uuid(),
    $advisor,
    'CHK-PENDING-130',
    null,
    OverpaymentDisposition::Refund,
);
$pass('3a $130 check leaves $30 pending refund', $pending->allocated_cents === 10000 && $pending->refund_pending_cents === 3000
    && $pending->change_cents === 0 && $pending->unapplied_cents === 0,
    'allocated '.$money($pending->allocated_cents).' pending '.$money($pending->refund_pending_cents));

$confirmed = app(ConfirmCustomerPaymentRefundAction::class)->execute(
    $pending->fresh(),
    'CHK-RETURNED-ACCEPT-1',
    $advisor,
);
$pass('3b Manual return clears pending with proof', $confirmed->refund_pending_cents === 0 && $confirmed->change_cents === 3000
    && $confirmed->refund_evidence_reference === 'CHK-RETURNED-ACCEPT-1'
    && $confirmed->refund_confirmed_by === $advisor->id
    && $confirmed->refund_confirmed_at !== null,
    'pending '.$money($confirmed->refund_pending_cents).' change '.$money($confirmed->change_cents).' by '.$advisor->id);

// --- 4 Statement ---
$fleet = Customer::query()->where('email', 'ar224.statement@example.test')->firstOrFail();
$statement = app(CustomerAccountStatementBuilder::class)->forCustomer($fleet);
$pass('4a Statement total outstanding $395', (int) $statement['total_outstanding_cents'] === 39500,
    $money((int) $statement['total_outstanding_cents']).' invoices '.count($statement['outstanding_invoices']));
$pass('4b Statement isolates fleet customer email', ($statement['customer']['email'] ?? null) === 'ar224.statement@example.test'
    && count($statement['outstanding_invoices']) === 2);

$receiptCount = CustomerPaymentReceipt::query()->whereIn('customer_id', [$multi->id, $credit->id, $refundCustomer->id])->whereNull('voided_at')->count();
echo "\nActive receipts created/used in scenarios 1–3: {$receiptCount}\n";
echo $fail === 0 ? "\nRESULT: ALL SCENARIOS 1–4 PASSED\n" : "\nRESULT: {$fail} CHECK(S) FAILED\n";
exit($fail === 0 ? 0 : 1);
