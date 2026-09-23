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

    // SQLite-only Schema grammar traffic - not production MySQL cost.
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

/**
 * Schema existence / column-catalog queries for one table.
 *
 * @param  list<array<string, mixed>>  $queries
 * @return list<array<string, mixed>>
 */
function schemaQueriesForTable(array $queries, string $table): array
{
    return array_values(array_filter(
        $queries,
        function (array $query) use ($table): bool {
            $sql = strtolower((string) $query['query']);
            $schemaSql = str_contains($sql, 'information_schema')
                || str_contains($sql, 'sqlite_master')
                || str_contains($sql, 'pragma');

            if (! $schemaSql) {
                return false;
            }

            if (str_contains($sql, $table)) {
                return true;
            }

            foreach ($query['bindings'] ?? [] as $binding) {
                if (is_string($binding) && str_contains(strtolower($binding), $table)) {
                    return true;
                }
            }

            return false;
        },
    ));
}

/**
 * Per-row reloads: select the parent by a single id. Batched whereIn stays out of this list.
 *
 * @param  list<array<string, mixed>>  $queries
 * @return list<array<string, mixed>>
 */
function singleIdReloads(array $queries, string $table): array
{
    return array_values(array_filter(
        $queries,
        function (array $query) use ($table): bool {
            $sql = strtolower((string) $query['query']);

            if (! str_contains($sql, $table) || ! str_contains($sql, 'where')) {
                return false;
            }

            $bindings = array_values(array_filter(
                $query['bindings'] ?? [],
                fn (mixed $binding): bool => is_int($binding) || (is_string($binding) && ctype_digit($binding)),
            ));

            return count($bindings) === 1;
        },
    ));
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
