<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use Illuminate\Support\Facades\DB;

/**
 * Outstanding finalized invoices for Accounts Receivable.
 * Amounts come from BalanceDueCalculator (ledger + immutable invoice), never live estimate math.
 */
final class AccountsReceivableOutstandingQuery
{
    public function __construct(
        private readonly BalanceDueCalculator $balanceDue,
    ) {}

    /**
     * @return array{
     *     total_outstanding_cents: int,
     *     past_due_cents: int,
     *     current_cents: int,
     *     aging: array{current: int, days_1_30: int, days_31_60: int, days_61_90: int, days_90_plus: int},
     *     accounts: list<array{customer_id: int, customer_name: string, invoice_count: int, balance_cents: int, past_due_cents: int}>,
     *     invoices: list<array<string, mixed>>,
     * }
     */
    public function dashboard(?string $search = null, ?int $customerId = null): array
    {
        $invoices = $this->outstandingInvoices($search, $customerId);
        $now = now();

        $aging = [
            'current' => 0,
            'days_1_30' => 0,
            'days_31_60' => 0,
            'days_61_90' => 0,
            'days_90_plus' => 0,
        ];
        $accounts = [];
        $total = 0;
        $pastDue = 0;
        $rows = [];

        foreach ($invoices as $row) {
            $balanceCents = (int) $row['balance_due_cents'];
            $total += $balanceCents;
            $dueAt = $row['due_at'];
            $daysPastDue = $dueAt === null ? 0 : max(0, $dueAt->startOfDay()->diffInDays($now->copy()->startOfDay(), false));

            if ($daysPastDue > 0) {
                $pastDue += $balanceCents;
            }

            $bucket = match (true) {
                $daysPastDue <= 0 => 'current',
                $daysPastDue <= 30 => 'days_1_30',
                $daysPastDue <= 60 => 'days_31_60',
                $daysPastDue <= 90 => 'days_61_90',
                default => 'days_90_plus',
            };
            $aging[$bucket] += $balanceCents;

            $customerIdKey = (int) $row['customer_id'];
            if (! isset($accounts[$customerIdKey])) {
                $accounts[$customerIdKey] = [
                    'customer_id' => $customerIdKey,
                    'customer_name' => (string) $row['customer_name'],
                    'invoice_count' => 0,
                    'balance_cents' => 0,
                    'past_due_cents' => 0,
                ];
            }
            $accounts[$customerIdKey]['invoice_count']++;
            $accounts[$customerIdKey]['balance_cents'] += $balanceCents;
            if ($daysPastDue > 0) {
                $accounts[$customerIdKey]['past_due_cents'] += $balanceCents;
            }

            $rows[] = [
                ...$row,
                'days_past_due' => $daysPastDue,
                'aging_bucket' => $bucket,
            ];
        }

        usort($accounts, fn (array $a, array $b): int => $b['balance_cents'] <=> $a['balance_cents']);

        return [
            'total_outstanding_cents' => $total,
            'past_due_cents' => $pastDue,
            'current_cents' => $total - $pastDue,
            'aging' => $aging,
            'accounts' => array_values($accounts),
            'invoices' => $rows,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function outstandingInvoices(?string $search = null, ?int $customerId = null): array
    {
        $query = EstimateDocument::query()
            ->select('estimate_documents.*')
            ->where('estimate_documents.document_type', FinancialDocumentType::Invoice->value)
            ->whereIn('estimate_documents.status', [
                InvoiceStatus::Issued->value,
                InvoiceStatus::PartiallyPaid->value,
            ])
            ->whereNotNull('estimate_documents.posted_to_receivables_at')
            ->join('repair_orders', 'repair_orders.id', '=', 'estimate_documents.repair_order_id')
            ->join('customers', 'customers.id', '=', 'repair_orders.customer_id')
            ->with(['repairOrder.customer', 'repairOrder.vehicle']);

        if ($customerId !== null) {
            $query->where('repair_orders.customer_id', $customerId);
        }

        if (filled($search)) {
            $term = '%'.trim((string) $search).'%';
            $query->where(function ($builder) use ($term): void {
                $builder->where('customers.first_name', 'like', $term)
                    ->orWhere('customers.last_name', 'like', $term)
                    ->orWhere('customers.phone', 'like', $term)
                    ->orWhere('customers.email', 'like', $term)
                    ->orWhere(DB::raw('CAST(repair_orders.repair_order_id AS CHAR)'), 'like', $term)
                    ->orWhere(DB::raw('CAST(estimate_documents.document_number AS CHAR)'), 'like', $term);
            });
        }

        $documents = $query
            ->orderByRaw('estimate_documents.due_at is null')
            ->orderBy('estimate_documents.due_at')
            ->orderBy('estimate_documents.id')
            ->get();

        if ($documents->isEmpty()) {
            return [];
        }

        $repairOrders = $documents->map->repairOrder->filter()->unique('id')->values();
        $balances = $this->balanceDue->mapForRepairOrders($repairOrders);

        $rows = [];

        foreach ($documents as $document) {
            $repairOrder = $document->repairOrder;
            if ($repairOrder === null) {
                continue;
            }

            $balance = $balances[$repairOrder->id] ?? $this->balanceDue->forRepairOrder($repairOrder);
            if ($balance->balanceDueCents <= 0) {
                continue;
            }

            $customer = $repairOrder->customer;
            $vehicle = $repairOrder->vehicle;

            $rows[] = [
                'invoice_id' => $document->id,
                'document_number' => $document->document_number,
                'status' => $document->status,
                'issued_at' => $document->issued_at,
                'due_at' => $document->due_at,
                'payment_terms_days' => $document->payment_terms_days,
                'invoice_total_cents' => $balance->invoiceTotalCents,
                'amount_paid_cents' => $balance->paymentsAppliedCents,
                'balance_due_cents' => $balance->balanceDueCents,
                'repair_order_id' => $repairOrder->id,
                'repair_order_number' => $repairOrder->repair_order_id,
                'customer_id' => $customer?->id,
                'customer_name' => $customer?->name ?? 'Customer',
                'vehicle_label' => $vehicle?->display_name
                    ?? trim(implode(' ', array_filter([
                        $vehicle?->year,
                        $vehicle?->make,
                        $vehicle?->model,
                    ]))),
            ];
        }

        return $rows;
    }

    public function customerAccount(Customer $customer): array
    {
        return $this->dashboard(customerId: (int) $customer->id);
    }
}
