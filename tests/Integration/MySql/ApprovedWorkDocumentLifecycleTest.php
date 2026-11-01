<?php

use Tests\Support\DisposableMySqlIntegration;

test('approval, increase, supplemental approval, deposit, payment and close keep every amount and document truthful', function () {
    $result = DisposableMySqlIntegration::run(dirname(__DIR__, 2).'/Support/approved_work_document_lifecycle.php');

    expect($result['first_errors'])->toBe([]);

    $approvedFirst = $result['approved_first_cents'];
    $increase = $result['pending']['needs_authorization_cents'];
    $afterIncrease = $result['after_increase_cents'];

    expect($approvedFirst)->toBeGreaterThan(0)
        ->and($increase)->toBeGreaterThan(0)
        ->and($afterIncrease)->toBe($approvedFirst + $increase);

    // The increase waits for authorization: the PDF separates the approved amount from the pending change.
    expect($result['pending'])->toBe([
        'approved_cents' => $approvedFirst,
        'needs_authorization_cents' => $increase,
        'projected_cents' => $afterIncrease,
        'estimate_pdf_says_approved_total' => false,
        'estimate_pdf_shows_changes' => true,
        'estimate_pdf_shows_approved' => true,
    ]);

    // An amount that contradicts the approved work is refused; a stale form default records the true total.
    expect($result['contradicting']['errors'][0] ?? '')->toContain('the approved total becomes '.$result['contradicting']['expected_message_total'])
        ->and($result['contradicting']['events_after'])->toBe(1)
        ->and($result['supplemental_errors'])->toBe([])
        ->and($result['events'])->toBe([
            ['type' => 'diagnostic', 'amount_cents' => $approvedFirst],
            ['type' => 'diagnostic', 'amount_cents' => $afterIncrease],
        ])
        ->and($result['scope_labor_subtotals'][1])->toBe($result['labor_subtotal_now'])
        ->and($result['scope_labor_subtotals'][0])->toBeLessThan($result['labor_subtotal_now']);

    // History: each event keeps its own recorded amount; no approval reads as a deferral.
    expect($result['history'])->toBe(['shows_first' => true, 'shows_supplemental' => true, 'says_deferred' => false]);

    expect($result['deposit_errors'])->toBe([])
        ->and(array_filter($result['moves']))->toBe([]);

    // The final invoice bills the authorized total; closing renders its PDF once more with the payment,
    // never rewriting its snapshot, and the closed invoice is then served as stored.
    expect($result['invoice'])->toBe([
        'issued' => true,
        'total_cents' => $afterIncrease,
        'at_issue_shows_balance' => true,
        'payment_errors' => [],
        'flagged_after_payment' => true,
        'close_errors' => [],
        'status' => 'paid',
        'repair_order_status' => 'closed',
        'invoice_renders_at_close' => 1,
        'close_render_paid_in_full' => true,
        'close_render_balance_zero' => true,
        'needs_pdf_refresh' => false,
        'snapshot_unchanged' => true,
        'served_status' => 200,
        'invoice_renders_after_serving' => 1,
    ])
        ->and($result['ledger'])->toBe([['deposit', 5000], ['payment', $afterIncrease - 5000]]);
});
