<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\HtmlPdfBuilder;
use Illuminate\Support\Collection;

/**
 * Avery 5160-compatible address label sheet (3×10 on US Letter).
 */
final class CustomerAddressLabelSheet
{
    public function __construct(
        private readonly HtmlPdfBuilder $pdf,
    ) {}

    /**
     * @param  Collection<int, Customer>|list<Customer>  $customers
     * @return array{labels: list<array{name: string, lines: list<string>}>, missing_address: list<string>}
     */
    public function payload(iterable $customers): array
    {
        $labels = [];
        $missing = [];

        foreach ($customers as $customer) {
            if (! $customer->hasAddress()) {
                $missing[] = $customer->name;
                continue;
            }

            $labels[] = [
                'name' => $customer->name,
                'lines' => array_values(array_filter([
                    $customer->address_line_1,
                    $customer->address_line_2,
                    trim(implode(', ', array_filter([
                        $customer->city,
                        $customer->state,
                    ]))).(filled($customer->postal_code) ? ' '.$customer->postal_code : ''),
                ])),
            ];
        }

        return [
            'labels' => $labels,
            'missing_address' => $missing,
        ];
    }

    /**
     * @param  Collection<int, Customer>|list<Customer>  $customers
     */
    public function html(iterable $customers): string
    {
        $payload = $this->payload($customers);

        return view('operations.financials.accounts-receivable.address-labels', $payload)->render();
    }

    /**
     * @param  Collection<int, Customer>|list<Customer>  $customers
     */
    public function pdfBytes(iterable $customers): string
    {
        return $this->pdf->toPdfBytes($this->html($customers));
    }
}
