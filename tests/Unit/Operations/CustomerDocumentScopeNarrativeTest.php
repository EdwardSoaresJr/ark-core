<?php

use App\Ark\Operations\Documents\CustomerDocumentScopeNarrative;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;

test('customer document narrative does not use a long complaint as the service title', function (): void {
    $complaint = 'I had a repairman install a new starter to my Fiat after they recommended that might solve my issue with the car not starting and having a high pitched noise coming from the starter.';

    $presented = (new CustomerDocumentScopeNarrative)->present([
        'intake' => [
            'visit_reason' => $complaint,
        ],
        'concerns' => [[
            'summary' => $complaint,
            'customer_states' => $complaint,
            'verified_findings' => null,
            'recommendation' => null,
            'disposition' => 'approved',
            'disposition_label' => 'Approved',
            'recommendation_intent' => 'diagnostic',
            'subtotal' => '$188.20',
            'lines' => [
                [
                    'type' => RepairOrderLineType::Note->value,
                    'description' => 'Diagnosed crank/no-start condition after previous starter replacement elsewhere. Testing found the MultiAir hydraulic valve-control system had lost oil prime. Recommend maintaining battery charge during storage.',
                ],
                [
                    'type' => RepairOrderLineType::Part->value,
                    'description' => 'Syntec Euro 5W-40 Motor Oil 1Qt',
                    'quantity' => '1.00',
                    'subtotal' => '$15.59',
                ],
                [
                    'type' => RepairOrderLineType::Labor->value,
                    'description' => 'Diagnostics & Testing',
                    'quantity' => '1.00',
                    'subtotal' => '$165.00',
                ],
            ],
        ]],
    ]);

    $concern = $presented['concerns'][0];

    expect($concern['customer_title'])->toBe('Diagnostics & Testing')
        ->and($concern['customer_title'])->not->toContain('I had a repairman')
        ->and($concern['customer_findings'])->toContain('Diagnosed crank/no-start condition')
        ->and($concern['customer_findings'])->not->toContain('Recommend maintaining')
        ->and($concern['customer_recommendation'])->toContain('Recommend maintaining battery charge')
        ->and($concern['customer_status_pills'])->toContain('Approved')
        ->and($concern['charge_lines'])->toHaveCount(2)
        ->and($concern['charge_lines'][0]['description'])->toBe('Syntec Euro 5W-40 Motor Oil 1Qt')
        ->and($concern['charge_lines'][1]['description'])->toBe('Diagnostics & Testing')
        ->and($concern['charge_lines'][1]['quantity_label'])->toBe('1 hr')
        ->and($concern['charge_subtotal'])->toBe('$180.59')
        ->and($concern['lines'][0]['customer_narrative'])->toBe('findings');
});

test('customer document narrative keeps a short service title and existing findings', function (): void {
    $presented = (new CustomerDocumentScopeNarrative)->present([
        'intake' => ['visit_reason' => 'Brakes grind when stopping'],
        'concerns' => [[
            'summary' => 'Front brake service',
            'verified_findings' => 'Pads are at 2mm. Rotors are below spec.',
            'recommendation' => 'Replace pads and rotors now.',
            'disposition' => 'recommended',
            'disposition_label' => 'Pending',
            'recommendation_intent' => 'immediate_attention',
            'lines' => [
                [
                    'type' => RepairOrderLineType::Labor->value,
                    'description' => 'R&R Front brake pads',
                    'quantity' => '1.50',
                    'subtotal' => '$247.50',
                ],
            ],
        ]],
    ]);

    $concern = $presented['concerns'][0];

    expect($concern['customer_title'])->toBe('Front brake service')
        ->and($concern['customer_findings'])->toBe('Pads are at 2mm. Rotors are below spec.')
        ->and($concern['customer_recommendation'])->toBe('Replace pads and rotors now.')
        ->and($concern['charge_lines'][0]['quantity_label'])->toBe('1.5 hr');
});

test('customer document narrative keeps builder service titles that are not the visit reason', function (): void {
    $visit = 'Suspension front & rear, coolant leak, transmission fluid leak, radio went out, power steering pump leaking like a drip a day. Rear trailing arm bushings? Exhaust weld hanger.';

    $presented = (new CustomerDocumentScopeNarrative)->present([
        'intake' => ['visit_reason' => $visit],
        'concerns' => [
            [
                'summary' => 'Suspension front & rear, transmission fluid leak, radio went out, power steering pump leaking like a drip a day. Rear trailing arm bushings? Exhaust weld hanger.',
                'verified_findings' => 'Unable to reproduce or verify an active coolant leak at this time. Transmission fluid seepage was observed around the transmission assembly bolts. Bolts were tightened; however, it could not be confirmed whether this fully resolved the seepage. Transmission fluid level remains full. Recommend monitoring for further leakage and reinspection if seepage continues.',
                'recommendation' => null,
                'disposition' => 'approved',
                'disposition_label' => 'Approved',
                'recommendation_intent' => 'maintenance',
                'lines' => [
                    [
                        'type' => RepairOrderLineType::Labor->value,
                        'description' => 'Front Struts R&R',
                        'quantity' => '3.00',
                        'subtotal' => '$495.00',
                    ],
                    [
                        'type' => RepairOrderLineType::Labor->value,
                        'description' => 'Rear Struts R&R',
                        'quantity' => '2.00',
                        'subtotal' => '$330.00',
                    ],
                    [
                        'type' => RepairOrderLineType::Labor->value,
                        'description' => 'Front Sway Bar Links R&R',
                        'quantity' => '1.00',
                        'subtotal' => '$0.00',
                    ],
                    [
                        'type' => RepairOrderLineType::Note->value,
                        'description' => 'Spoke with Hunter about needing top hats for all four struts, we went over FCP Euro options and he placed an order, awaiting shipment of those.',
                    ],
                    [
                        'type' => RepairOrderLineType::Note->value,
                        'description' => 'Additional labor performed on this vehicle to include welding of the hanger for the exhaust to the vehicle',
                    ],
                ],
            ],
            [
                'summary' => 'Exhaust welding to include hanger',
                'customer_states' => 'Exhaust welding to include hanger',
                'verified_findings' => null,
                'recommendation' => null,
                'disposition' => 'approved',
                'disposition_label' => 'Approved',
                'recommendation_intent' => 'maintenance',
                'lines' => [
                    [
                        'type' => RepairOrderLineType::Part->value,
                        'description' => 'Exhaust System Accessory',
                        'quantity' => '1.00',
                        'subtotal' => '$11.17',
                    ],
                ],
            ],
            [
                'summary' => 'Power steering leaking',
                'customer_states' => 'Power steering leaking',
                'verified_findings' => null,
                'recommendation' => null,
                'disposition' => 'approved',
                'disposition_label' => 'Approved',
                'recommendation_intent' => 'maintenance',
                'lines' => [
                    [
                        'type' => RepairOrderLineType::Note->value,
                        'description' => 'Resolved by replacing banjo fitting with rubberized metal crush washers.',
                    ],
                ],
            ],
        ],
    ]);

    expect($presented['concerns'][0]['customer_title'])->toBe('Front Struts R&R · Rear Struts R&R · Front Sway Bar Links R&R')
        ->and($presented['concerns'][0]['customer_title'])->not->toContain('radio went out')
        ->and($presented['concerns'][0]['customer_recommendation'])->toBe('Recommend monitoring for further leakage and reinspection if seepage continues.')
        ->and($presented['concerns'][0]['customer_findings'])->toContain('Unable to reproduce or verify an active coolant leak')
        ->and($presented['concerns'][0]['customer_findings'])->toContain('Spoke with Hunter about needing top hats')
        ->and($presented['concerns'][0]['customer_findings'])->toContain('welding of the hanger')
        ->and($presented['concerns'][0]['customer_recommendation'])->not->toContain('top hats')
        ->and($presented['concerns'][1]['customer_title'])->toBe('Exhaust welding to include hanger')
        ->and($presented['concerns'][2]['customer_title'])->toBe('Power steering leaking')
        ->and($presented['concerns'][2]['customer_findings'])->toBe('Resolved by replacing banjo fitting with rubberized metal crush washers.');
});
