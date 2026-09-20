<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * @return array{result: mixed, queries: list<array<string, mixed>>, count: int}
 */
function measureQueries(callable $callback): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $result = $callback();

    $queries = array_values(array_filter(
        DB::getQueryLog(),
        fn (array $query): bool => ! isSqliteSchemaIntrospectionQuery((string) ($query['query'] ?? '')),
    ));

    return [
        'result' => $result,
        'queries' => $queries,
        'count' => count($queries),
    ];
}

function isSqliteSchemaIntrospectionQuery(string $sql): bool
{
    $sql = ltrim($sql);

    // SQLite-only Schema grammar traffic — not production MySQL cost.
    return (bool) preg_match(
        '/\bpragma\b|\bsqlite_master\b|\bsqlite_temp_master\b|dflt_value as ["\']default["\']/i',
        $sql,
    );
}

function queryBudget(callable $callback, int $maxQueries): mixed
{
    $measured = measureQueries($callback);

    expect($measured['count'])->toBeLessThanOrEqual(
        $maxQueries,
        sprintf('Expected at most %d queries but ran %d.', $maxQueries, $measured['count']),
    );

    return $measured['result'];
}

/**
 * @param  list<array<string, mixed>>  $queries
 * @return list<array<string, mixed>>
 */
function repairOrderLineUpdateQueries(array $queries): array
{
    return array_values(array_filter(
        $queries,
        fn (array $query): bool => preg_match('/update\s+[`"]?repair_order_lines[`"]?/i', $query['query']) === 1,
    ));
}

function assertGetDoesNotUpdateRepairOrderLines(callable $callback): mixed
{
    $measured = measureQueries($callback);

    expect(repairOrderLineUpdateQueries($measured['queries']))->toBeEmpty(
        'GET requests must not UPDATE repair_order_lines.',
    );

    return $measured['result'];
}

/**
 * @param  list<array<string, mixed>>  $queries
 * @return list<array<string, mixed>>
 */
function getMutationQueries(array $queries): array
{
    return array_values(array_filter(
        $queries,
        fn (array $query): bool => preg_match('/^\s*(update|delete|insert)\s+/i', trim($query['query'])) === 1,
    ));
}

/**
 * @param  list<array<string, mixed>>  $queries
 * @return list<array<string, mixed>>
 */
function callSessionMutationQueries(array $queries): array
{
    return array_values(array_filter(
        getMutationQueries($queries),
        fn (array $query): bool => preg_match('/\bcall_sessions\b/i', $query['query']) === 1,
    ));
}

/**
 * @param  list<array<string, mixed>>  $queries
 * @return list<array<string, mixed>>
 */
function staffLastSeenMutationQueries(array $queries): array
{
    return array_values(array_filter(
        getMutationQueries($queries),
        fn (array $query): bool => preg_match('/\busers\b/i', $query['query']) === 1
            && preg_match('/last_seen_at/i', $query['query']) === 1,
    ));
}

function assertGetHasNoMutations(callable $callback): mixed
{
    $measured = measureQueries($callback);

    expect(getMutationQueries($measured['queries']))->toBeEmpty(
        'GET requests must not mutate operational data.',
    );

    return $measured['result'];
}

function assertOkWithinQueryBudget(string $url, int $maxQueries, ?callable $setup = null): TestResponse
{
    if ($setup !== null) {
        $setup();
    }

    /** @var TestResponse $response */
    $response = queryBudget(
        fn (): TestResponse => test()->get($url)->assertOk(),
        $maxQueries,
    );

    return $response;
}
