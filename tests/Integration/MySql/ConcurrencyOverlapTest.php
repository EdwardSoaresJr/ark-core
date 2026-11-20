<?php

use Tests\Support\DisposableMySqlIntegration;

/**
 * These races need a disposable MySQL server (ARK_TEST_MYSQL=1 + loopback ark_test_* credentials).
 * Focused CI without that env must skip — not fail — so harness absence is not mistaken for a lock bug.
 */
$requiresDisposableMysql = getenv('ARK_TEST_MYSQL') !== '1';

test('two overlapping writers cannot both mutate from the same estimate version', function () {
    $payload = DisposableMySqlIntegration::run(dirname(__DIR__, 2).'/Support/repair_order_concurrency_overlap.php');
    expect($payload)->toBeArray();

    $winner = $payload['winner'];
    $loser = $payload['loser'];
    $rollback = $payload['rollback'];

    expect($winner['status'])->toBe(200)
        ->and($winner['description'])->toBe('Winner')
        ->and($winner['estimate_version'])->toBe(5)
        ->and($winner['broadcasts'])->toBe(1)
        ->and($loser['status'])->toBe(409)
        ->and($loser['description'])->toBe('Winner')
        ->and($loser['finished_at'])->toBeGreaterThan($winner['lock']['released_at'])
        ->and($rollback['threw'])->toBeTrue()
        ->and($rollback['description'])->toBe('Keep this labor')
        ->and($rollback['estimate_version'])->toBe(7)
        ->and($rollback['broadcasts'])->toBe(0);
})->skip($requiresDisposableMysql, 'Disposable MySQL integration requires ARK_TEST_MYSQL=1');

test('overlapping same-key deposits and payments record one effect and different keys stay distinct', function () {
    $payload = DisposableMySqlIntegration::run(dirname(__DIR__, 2).'/Support/financial_submission_intent_overlap.php');

    expect($payload['deposit_same_key']['rows'])->toBe(1)
        ->and($payload['deposit_same_key']['cents'])->toBe(1000)
        ->and($payload['deposit_same_key']['events'])->toBe(1)
        ->and($payload['deposit_same_key']['statuses'])->toEqual([200, 200])
        ->and($payload['deposit_same_key']['replays'])->toBe(1)
        ->and($payload['deposit_different_key']['rows'])->toBe(2)
        ->and($payload['deposit_different_key']['cents'])->toBe(2000)
        ->and($payload['payment_same_key']['rows'])->toBe(1)
        ->and($payload['payment_same_key']['cents'])->toBe(1000)
        ->and($payload['payment_same_key']['events'])->toBe(1)
        ->and($payload['payment_same_key']['statuses'])->toEqual([200, 200])
        ->and($payload['payment_same_key']['replays'])->toBe(1)
        ->and($payload['payment_different_key']['rows'])->toBe(2)
        ->and($payload['payment_different_key']['cents'])->toBe(2000);
})->skip($requiresDisposableMysql, 'Disposable MySQL integration requires ARK_TEST_MYSQL=1');

test('overlapping store-credit applications cannot double-spend credit or overpay an invoice', function () {
    $payload = DisposableMySqlIntegration::run(dirname(__DIR__, 2).'/Support/store_credit_application_overlap.php');

    expect($payload['credit_race']['winners'])->toBe(1)
        ->and($payload['credit_race']['active_application_rows'])->toBe(1)
        ->and($payload['credit_race']['active_application_cents'])->toBe(10000)
        ->and($payload['credit_race']['balance_cents'])->toBe(0)
        ->and($payload['invoice_race']['winners'])->toBe(1)
        ->and($payload['invoice_race']['active_application_rows'])->toBe(1)
        ->and($payload['invoice_race']['active_application_cents'])->toBe(15000)
        ->and($payload['invoice_race']['balance_due_cents'])->toBe(0)
        ->and($payload['invoice_race']['remaining_credit_cents'])->toBe(5000);
})->skip($requiresDisposableMysql, 'Disposable MySQL integration requires ARK_TEST_MYSQL=1');
