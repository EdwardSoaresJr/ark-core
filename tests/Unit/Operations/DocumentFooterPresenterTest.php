<?php

use App\Ark\Operations\Documents\DocumentFooterPresenter;

test('document footer merges global recommendation and retail disclaimers into important information', function () {
    $footer = app(DocumentFooterPresenter::class)->present([
        'document_type' => 'estimate',
        'documents' => [
            'global_disclaimer' => 'Estimate is based on visible conditions. Final cost may change.',
            'customer_type' => 'Retail',
            'customer_type_disclaimer' => 'This estimate reflects repairs recommended based on our inspection.',
            'authorization_language' => "By approving this estimate, I authorize Demo Auto Repair.\n\nI agree to pay for authorized work.",
        ],
        'settings' => [
            'recommendation_disclaimer' => 'Recommendations are based on verified findings. Further testing may change the repair path.',
        ],
        'repair_order' => ['status' => 'waiting_approval'],
        'concerns' => [
            ['disposition' => 'recommended'],
        ],
    ]);

    expect($footer['important_information'])->toHaveCount(5)
        ->and($footer['customer_type_terms'])->toBeNull()
        ->and($footer['authorization'])->toHaveCount(2)
        ->and($footer['approval']['status_label'])->toBe('Pending Approval')
        ->and($footer['approval']['show_signature_lines'])->toBeTrue();
});

test('document footer keeps fleet terms separate from important information', function () {
    $footer = app(DocumentFooterPresenter::class)->present([
        'document_type' => 'estimate',
        'documents' => [
            'global_disclaimer' => 'Estimate is based on visible conditions.',
            'customer_type' => 'Fleet',
            'customer_type_disclaimer' => 'Repairs will not begin until fleet authorization is received.',
            'authorization_language' => 'I authorize the listed repairs.',
        ],
        'settings' => [],
        'repair_order' => ['status' => 'waiting_approval'],
        'concerns' => [
            ['disposition' => 'recommended'],
        ],
    ]);

    expect($footer['customer_type_terms']['heading'])->toBe('Fleet Terms')
        ->and($footer['customer_type_terms']['bullets'])->toHaveCount(1)
        ->and(collect($footer['important_information'])->contains(
            fn (string $bullet): bool => str_contains($bullet, 'fleet authorization'),
        ))->toBeFalse();
});

test('pdf important information compresses long disclaimer lists for presentation', function () {
    $presenter = app(DocumentFooterPresenter::class);

    expect($presenter->pdfImportantInformationBullets([
        'Line one.',
        'Line two.',
        'Line three.',
    ]))->toHaveCount(3)
        ->and($presenter->pdfImportantInformationBullets([
            'Line one.',
            'Line two.',
            'Line three.',
            'Line four.',
            'Line five.',
        ]))->toHaveCount(4);
});

test('footer total label reads approved total when estimate has approved scopes', function () {
    $presenter = app(DocumentFooterPresenter::class);

    expect($presenter->footerTotalLabel([
        'document_type' => 'estimate',
        'concerns' => [
            ['disposition' => 'approved'],
            ['disposition' => 'deferred'],
        ],
    ]))->toBe('Approved Total')
        ->and($presenter->footerTotalLabel([
            'document_type' => 'estimate',
            'concerns' => [
                ['disposition' => 'recommended'],
            ],
        ]))->toBe('Total')
        ->and($presenter->footerTotalLabel([
            'document_type' => 'invoice',
            'concerns' => [
                ['disposition' => 'approved'],
            ],
        ]))->toBe('Total');
});

test('document footer shows approval evidence when authorization event exists', function () {
    $footer = app(DocumentFooterPresenter::class)->present([
        'document_type' => 'estimate',
        'documents' => [],
        'settings' => [],
        'staff' => [
            'approval_events' => [[
                'approved_by' => 'Morgan Brown',
                'approved_at_display' => 'May 28, 2026 2:15 PM',
                'source_label' => 'Phone',
                'approval_type_label' => 'Repair authorization',
                'approved_amount_cents' => 15593,
                'approved_at' => '2026-05-28T14:15:00.000000Z',
            ]],
        ],
        'repair_order' => ['status' => 'approved'],
        'concerns' => [
            ['disposition' => 'approved'],
        ],
    ]);

    expect($footer['approval']['status_label'])->toBe('Approved')
        ->and($footer['approval']['approved_by'])->toBe('Morgan Brown')
        ->and($footer['approval']['show_signature_lines'])->toBeFalse();
});

test('document footer shows deferred when portal response deferred all scopes', function () {
    $footer = app(DocumentFooterPresenter::class)->present([
        'document_type' => 'estimate',
        'documents' => [],
        'settings' => [],
        'staff' => [
            'approval_events' => [[
                'approved_by' => 'Morgan Brown',
                'approved_at_display' => 'May 28, 2026 2:15 PM',
                'source_label' => 'Online estimate',
                'approval_type_label' => 'Partial authorization',
                'approved_amount_cents' => 0,
                'approved_at' => '2026-05-28T14:15:00.000000Z',
            ]],
        ],
        'repair_order' => ['status' => 'waiting_approval'],
        'concerns' => [
            ['disposition' => 'deferred'],
        ],
    ]);

    expect($footer['approval']['status_label'])->toBe('Deferred')
        ->and($footer['approval']['status'])->toBe('deferred')
        ->and($footer['approval']['approved_by'])->toBe('Morgan Brown');
});

test('document footer shows declined when portal response declined all scopes', function () {
    $footer = app(DocumentFooterPresenter::class)->present([
        'document_type' => 'estimate',
        'documents' => [],
        'settings' => [],
        'staff' => [
            'approval_events' => [[
                'approved_by' => 'Morgan Brown',
                'approved_at_display' => 'May 28, 2026 2:15 PM',
                'source_label' => 'Online estimate',
                'approval_type_label' => 'Partial authorization',
                'approved_amount_cents' => 0,
                'approved_at' => '2026-05-28T14:15:00.000000Z',
            ]],
        ],
        'repair_order' => ['status' => 'waiting_approval'],
        'concerns' => [
            ['disposition' => 'declined'],
        ],
    ]);

    expect($footer['approval']['status_label'])->toBe('Declined')
        ->and($footer['approval']['status'])->toBe('declined')
        ->and($footer['approval']['approved_by'])->toBe('Morgan Brown');
});

test('document footer defers to concern disposition over stale approval event amount', function () {
    $footer = app(DocumentFooterPresenter::class)->present([
        'document_type' => 'estimate',
        'documents' => [],
        'settings' => [],
        'staff' => [
            'approval_events' => [[
                'approved_by' => 'Emirhan Cadas',
                'approved_at_display' => 'Jun 22, 2026 3:26 PM',
                'source_label' => 'Portal',
                'approval_type_label' => 'Repair authorization',
                'approved_amount_cents' => 99118,
                'approved_at' => '2026-06-22T21:26:00.000000Z',
            ]],
        ],
        'repair_order' => ['status' => 'approved', 'status_label' => 'Approved'],
        'concerns' => [
            ['disposition' => 'deferred'],
            ['disposition' => 'deferred'],
        ],
    ]);

    expect($footer['approval']['status_label'])->toBe('Deferred')
        ->and($footer['approval']['status'])->toBe('deferred');
});
