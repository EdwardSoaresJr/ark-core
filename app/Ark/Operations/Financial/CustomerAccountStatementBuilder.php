<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Carbon;

/**
 * Canonical account statement payload for staff print/PDF/email and future portal access.
 */
final class CustomerAccountStatementBuilder
{
    public function __construct(
        private readonly AccountsReceivableOutstandingQuery $receivables,
        private readonly BalanceDueCalculator $balanceDue,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forCustomer(Customer $customer, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $outstanding = $this->receivables->customerAccount($customer);
        $settings = ShopSettings::current();

        $paid = EstimateDocument::query()
            ->where('document_type', FinancialDocumentType::Invoice->value)
            ->where('status', InvoiceStatus::Paid->value)
            ->whereNotNull('posted_to_receivables_at')
            ->whereHas('repairOrder', fn ($query) => $query->where('customer_id', $customer->id))
            ->with(['repairOrder.vehicle'])
            ->orderByDesc('issued_at')
            ->limit(25)
            ->get()
            ->map(function (EstimateDocument $document) {
                $repairOrder = $document->repairOrder;
                $balance = $repairOrder !== null
                    ? $this->balanceDue->forRepairOrder($repairOrder)
                    : null;

                return [
                    'invoice_id' => $document->id,
                    'document_number' => $document->document_number,
                    'issued_at' => $document->issued_at,
                    'due_at' => $document->due_at,
                    'invoice_total_cents' => $balance?->invoiceTotalCents ?? 0,
                    'amount_paid_cents' => $balance?->paymentsAppliedCents ?? 0,
                    'balance_due_cents' => 0,
                    'repair_order_id' => $repairOrder?->id,
                    'repair_order_number' => $repairOrder?->repair_order_id,
                    'vehicle_label' => $repairOrder?->vehicle?->display_name,
                    'status' => $document->status,
                ];
            })
            ->all();

        $receipts = CustomerPaymentReceipt::query()
            ->where('customer_id', $customer->id)
            ->whereNull('voided_at')
            ->orderByDesc('recorded_at')
            ->limit(25)
            ->get()
            ->map(fn (CustomerPaymentReceipt $receipt): array => [
                'id' => $receipt->id,
                'recorded_at' => $receipt->recorded_at,
                'amount_cents' => $receipt->amount_cents,
                'allocated_cents' => $receipt->allocated_cents,
                'unapplied_cents' => $receipt->unapplied_cents,
                'payment_method' => ManualPaymentMethods::label($receipt->payment_method) ?? (string) $receipt->payment_method,
                'reference' => $receipt->reference,
            ])
            ->all();

        return [
            'as_of' => $asOf,
            'shop' => [
                'name' => $settings->shop_name ?: config('app.name', 'ARK'),
                'phone' => $settings->phone,
                'email' => $settings->email,
                'address' => trim(implode(', ', array_filter([
                    $settings->address_line_1,
                    $settings->address_line_2,
                    trim(implode(' ', array_filter([
                        $settings->city,
                        $settings->state,
                        $settings->postal_code,
                    ]))),
                ]))),
            ],
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address_lines' => array_values(array_filter([
                    $customer->address_line_1,
                    $customer->address_line_2,
                    trim(implode(', ', array_filter([
                        $customer->city,
                        $customer->state,
                    ]))).(filled($customer->postal_code) ? ' '.$customer->postal_code : ''),
                ])),
                'has_address' => $customer->hasAddress(),
                'has_email' => $customer->hasEmail(),
                'store_credit_cents' => (int) $customer->store_credit_balance_cents,
            ],
            'total_outstanding_cents' => $outstanding['total_outstanding_cents'],
            'past_due_cents' => $outstanding['past_due_cents'],
            'current_cents' => $outstanding['current_cents'],
            'aging' => $outstanding['aging'],
            'outstanding_invoices' => $outstanding['invoices'],
            'paid_invoices' => $paid,
            'payments' => $receipts,
        ];
    }
}
