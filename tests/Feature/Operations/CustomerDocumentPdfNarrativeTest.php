<?php

use App\Ark\Operations\Documents\CustomerDocumentScopeNarrative;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;

test('customer pdf concern renders findings and charges instead of a complaint title', function (): void {
    $complaint = 'I had a repairman install a new starter to my Fiat after they recommended that might solve my issue with the car not starting.';

    $concern = (new CustomerDocumentScopeNarrative)->present([
        'intake' => ['visit_reason' => $complaint],
        'concerns' => [[
            'summary' => $complaint,
            'verified_findings' => null,
            'recommendation' => null,
            'disposition' => 'approved',
            'disposition_label' => 'Approved',
            'recommendation_intent' => 'diagnostic',
            'subtotal' => '$188.20',
            'lines' => [
                [
                    'type' => RepairOrderLineType::Note->value,
                    'description' => 'Diagnosed crank/no-start condition after previous starter replacement. Recommend maintaining battery charge during storage.',
                ],
                [
                    'type' => RepairOrderLineType::Labor->value,
                    'description' => 'Diagnostics & Testing',
                    'quantity' => '1.00',
                    'subtotal' => '$165.00',
                ],
                [
                    'type' => RepairOrderLineType::Part->value,
                    'description' => 'Syntec Euro 5W-40 Motor Oil 1Qt',
                    'quantity' => '1.00',
                    'subtotal' => '$15.59',
                ],
            ],
        ]],
    ])['concerns'][0];

    $html = view('operations.documents.partials._pdf-concern', [
        'snapshot' => ['document_type' => 'estimate'],
        'concern' => $concern,
        'duplicateCustomerStates' => true,
    ])->render();

    expect($html)
        ->toContain('Diagnostics &amp; Testing')
        ->toContain('Technician Findings')
        ->toContain('Diagnosed crank/no-start')
        ->toContain('Recommendation')
        ->toContain('Work subtotal')
        ->toContain('$180.59')
        ->not->toContain('$188.20')
        ->not->toContain('I had a repairman')
        ->not->toContain('line-note-label');
});

test('customer pdf has no operational title band and does not print preferred visit', function (): void {
    $snapshot = app(\App\Ark\Operations\Documents\CustomerFacingDocumentBoundary::class)->sanitize([
        'document_type' => 'estimate',
        'intake' => [
            'visit_reason' => "Preferred visit: Thursday, September 10\n\nThe car will not start.",
        ],
        'repair_order' => [
            'repair_order_id' => 1737,
            'status' => 'approved',
            'status_label' => 'Approved',
        ],
        'concerns' => [[
            'summary' => 'Diagnostics & Testing',
            'disposition' => 'approved',
            'disposition_label' => 'Approved',
            'lines' => [],
        ]],
    ]);

    $css = file_get_contents(resource_path('views/operations/documents/pdf/document.blade.php'));

    expect($snapshot)
        ->not->toHaveKey('pdf_status_badge')
        ->not->toHaveKey('pdf_document_heading')
        ->and($snapshot['repair_order']['status_label'])->toBe('Approved')
        ->and($snapshot['customer_visit']['preferred_visit'])->toBe('Thursday, September 10')
        ->and($snapshot['customer_visit']['concern'])->toBe('The car will not start.');

    expect($css)
        ->not->toContain('document-title-row')
        ->not->toContain('document-status-pill')
        ->not->toContain('Preferred visit')
        ->toContain('<p class="eyebrow">Customer Concern</p>')
        ->not->toContain('Reason for Visit')
        ->not->toContain('Work Performed')
        ->not->toContain('$isInvoicePdf');
});

test('customer pdf closing block is compact and does not trap the whole footer on a new page', function (): void {
    $html = view('operations.documents.partials._document-footer', [
        'variant' => 'pdf',
        'snapshot' => [
            'document_type' => 'estimate',
            'totals' => [
                'labor' => '$165.00',
                'parts' => '$15.59',
                'fees' => '$6.33',
                'tax' => '$1.28',
                'total' => '$188.20',
                'labor_cents' => 16500,
                'parts_cents' => 1559,
                'fees_cents' => 633,
                'tax_cents' => 128,
                'total_cents' => 18820,
                'standing_discount_cents' => 0,
            ],
            'document_footer' => [
                'important_information' => [
                    'Pricing reflects today\'s findings and may change if additional work is discovered.',
                    'Parts availability, labor, and pricing may change after the estimate validity period.',
                ],
                'authorization' => ['By approving this estimate, I authorize the approved work described above.'],
                'approval' => [
                    'status_label' => 'Approved',
                    'show_signature_lines' => false,
                ],
                'total_label' => 'Approved Total',
            ],
            'repair_portal' => [
                'headline' => 'Vehicle Portal',
                'cta' => 'View your vehicle online',
                'callout' => 'Vehicle Portal — Track this repair, Approve work & View invoices.',
                'qr_data_uri' => 'data:image/svg+xml;base64,Zg==',
                'bullets' => ['Track this repair', 'Approve work', 'View invoices'],
            ],
        ],
    ])->render();

    $css = file_get_contents(resource_path('views/operations/documents/pdf/document.blade.php'));

    expect($html)
        ->toContain('footer-decision-heading')
        ->toContain('Approved')
        ->toContain('Approved Total')
        ->toContain('$188.20')
        ->toContain('Important:')
        ->toContain('Pricing reflects today')
        ->toContain('repair-portal-ad-title')
        ->toContain('repair-portal-ad-detail')
        ->toContain('Vehicle Portal')
        ->toContain('Track this repair')
        ->toContain('width="64"')
        ->not->toContain('Vehicle Portal — Track this repair')
        ->not->toContain('Approval Status')
        ->not->toContain('Important Information')
        ->not->toContain('Scan to view')
        ->not->toContain('View your vehicle online')
        ->not->toContain('width="88"');

    expect($css)
        ->toContain('.footer-decision-area')
        ->toContain('.footer-decision-body')
        ->and($css)->toMatch('/\.footer-decision-area\s*\{[^}]*page-break-inside:\s*auto/s')
        ->and($css)->toMatch('/\.footer-decision-body\s*\{[^}]*page-break-inside:\s*avoid/s')
        ->and($css)->toContain('.repair-portal-ad-copy')
        ->and($css)->toMatch('/\.document-footer\s*\{[^}]*page-break-inside:\s*auto/s');
});
