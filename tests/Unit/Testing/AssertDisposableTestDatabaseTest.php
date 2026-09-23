<?php

use Tests\Support\AssertDisposableTestDatabase;

/**
 * Pure policy tests - do not boot Laravel / RefreshDatabase.
 * These prove the fail-closed identity rules themselves.
 */
test('permits disposable sqlite identities', function (string $database) {
    expect(AssertDisposableTestDatabase::isPermitted(strtolower($database), 'sqlite'))->toBeTrue();
})->with([
    ':memory:',
    'testing',
    'testing_payments',
    'ark_test_payments',
    'database/testing.sqlite',
    '/tmp/foo/testing.sqlite',
    'shop_payments_test',
    'shop_payments_testing',
]);

test('rejects certification rehearsal restore and developer identities', function (string $database) {
    expect(AssertDisposableTestDatabase::isPermitted(strtolower($database), 'mysql'))->toBeFalse();

    expect(fn () => AssertDisposableTestDatabase::abortUnlessSafe(
        appEnv: 'testing',
        connection: 'mysql',
        database: $database,
        driver: 'mysql',
    ))->toThrow(RuntimeException::class, 'ARK test DB guard');
})->with([
    'lnp_restore_cert_20260905',
    'lnp_public_migrate_rehearsal_20260905',
    'lnp_public_migrate_rehearsal_payment_p1_20260905',
    'ark',
    'arksmsv2',
    'production_shop',
    'prod_lugsnplugs',
    'my_dev_db',
]);

test('rejects non-testing APP_ENV even for :memory:', function () {
    expect(fn () => AssertDisposableTestDatabase::abortUnlessSafe(
        appEnv: 'local',
        connection: 'sqlite',
        database: ':memory:',
        driver: 'sqlite',
    ))->toThrow(RuntimeException::class, "APP_ENV must be 'testing'");
});

test('permits designated disposable mysql test names under testing env', function () {
    AssertDisposableTestDatabase::abortUnlessSafe(
        appEnv: 'testing',
        connection: 'mysql',
        database: 'ark_test_payments',
        driver: 'mysql',
    );

    expect(true)->toBeTrue();
});
