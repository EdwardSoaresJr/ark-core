<?php

/**
 * Proves process-env bleed cannot bind a certification DB after the guardrail.
 * Runs under Pest/TestCase (which reconciles $_ENV → $_SERVER then asserts).
 */
test('phpunit testing identity wins over polluted shell rehearsal exports', function () {
    expect(app()->environment())->toBe('testing')
        ->and(config('database.default'))->toBe('sqlite')
        ->and(config('database.connections.sqlite.database'))->toBe(':memory:');

    // Even if the parent shell still advertises the wiped rehearsal name:
    expect($_ENV['DB_DATABASE'] ?? null)->toBe(':memory:')
        ->and($_SERVER['DB_DATABASE'] ?? null)->toBe(':memory:')
        ->and(getenv('DB_DATABASE'))->toBe(':memory:');
});
