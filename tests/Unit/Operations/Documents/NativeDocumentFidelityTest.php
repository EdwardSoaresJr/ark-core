<?php

use App\Ark\Operations\Documents\NativePhpPdfRenderer;

it('native estimate template preserves branding financial and authorization fields without renderer disclaimers', function (): void {
    $snapshot = [
        'pdf_document_label' => 'Estimate',
        'pdf_work_column_label' => 'Estimated Work',
        'generated_at_display' => 'Nov 1, 2026',
        'shop' => [
            'name' => 'Fidelity Auto',
            'phone' => '555-0100',
            'email' => 'shop@example.test',
            'address_line_1' => '1 Main St',
            'city' => 'Denver',
            'state' => 'CO',
            'postal_code' => '80202',
        ],
        'customer' => ['name' => 'Ada Customer', 'phone' => '555-0199'],
        'vehicle' => ['year' => 2018, 'make' => 'Honda', 'model' => 'Civic', 'vin' => 'VIN123'],
        'repair_order' => ['repair_order_id' => '1001', 'created_at' => '2026-11-01'],
        'concerns' => [[
            'title' => 'Brakes',
            'charge_lines' => [[
                'description' => 'Front pads',
                'quantity_label' => '1',
                'amount' => '$120.00',
                'detail' => 'Parts',
            ]],
            'charge_subtotal' => '$120.00',
        ]],
        'totals' => [
            'parts' => '$120.00',
            'labor' => '$80.00',
            'fees' => '$10.00',
            'tax' => '$15.00',
            'total' => '$225.00',
            'standing_discount' => '$5.00',
        ],
        'financial' => [
            'deposits_applied' => '$25.00',
            'balance_due' => '$200.00',
        ],
        'document_footer' => [
            'authorization' => ['I authorize the listed repairs.'],
            'important_information' => ['Prices subject to inspection.'],
            'concern_terms' => [[
                'billing_class_name' => 'Standard',
                'warranty' => ['summary' => '12 months / 12,000 miles'],
            ]],
        ],
    ];

    $document = new class
    {
        public function isInvoice(): bool
        {
            return false;
        }
    };

    $html = view(NativePhpPdfRenderer::VIEW, [
        'document' => $document,
        'snapshot' => $snapshot,
    ])->render();

    expect($html)
        ->toContain('Fidelity Auto')
        ->toContain('RO #1001')
        ->toContain('Prepared Nov 1, 2026')
        ->toContain('Front pads')
        ->toContain('$225.00')
        ->toContain('$25.00')
        ->toContain('$200.00')
        ->toContain('I authorize the listed repairs.')
        ->toContain('12 months / 12,000 miles')
        ->not->toContain('native PHP PDF engine')
        ->not->toContain('Chromium');
});
