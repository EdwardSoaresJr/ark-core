<?php

use App\Ark\Operations\Communications\CommunicationsQueueResolver;
use App\Ark\Operations\Communications\ShopTurnAttentionQueue;
use App\Ark\Operations\Conversations\ConversationWork;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Workstations\WorkstationPresence;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
});

test('shop attention batches customer lead and advisor lookups', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $work = app(ConversationWork::class);

    foreach ([
        '7195552101',
        '7195552102',
        '7195552103',
        '7195552104',
    ] as $index => $phone) {
        $customer = Customer::query()->create([
            'first_name' => 'Batch',
            'last_name' => 'Caller '.$index,
            'phone' => $phone,
        ]);
        $conversation = $work->ensureForPhone($phone, $customer);
        $work->markNeedsAttention($conversation);
    }

    $measured = measureQueries(
        fn () => app(ShopTurnAttentionQueue::class)->rowsFor($advisor),
    );

    expect($measured['result'])->toHaveCount(4)
        ->and(perConversationOutboundLookups($measured['queries']))->toBe([])
        ->and(singlePhoneCustomerLookups($measured['queries']))->toBe([])
        ->and(whereInLookups($measured['queries'], 'conversation_messages'))->not->toBe([])
        ->and(whereInLookups($measured['queries'], 'customers'))->not->toBe([]);
});

test('a request builds the attention queue once', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $work = app(ConversationWork::class);
    $customer = Customer::query()->create([
        'first_name' => 'Once',
        'last_name' => 'Per Request',
        'phone' => '7195552110',
    ]);
    $work->markNeedsAttention($work->ensureForPhone('7195552110', $customer));

    $resolver = app(CommunicationsQueueResolver::class);
    $full = $resolver->resolve($advisor);
    $again = measureQueries(fn () => $resolver->resolveAttention($advisor));

    expect($full['needs_attention'])->not->toBeEmpty()
        ->and($again['result']['recent_activity'])->toBe([])
        ->and($again['result']['needs_attention'])->toBe($full['needs_attention'])
        ->and(whereInLookups($again['queries'], 'conversation_messages'))->toBe([]);
});

/**
 * @param  list<array<string, mixed>>  $queries
 * @return list<array<string, mixed>>
 */
function perConversationOutboundLookups(array $queries): array
{
    return array_values(array_filter(
        $queries,
        function (array $query): bool {
            $sql = strtolower((string) $query['query']);

            return str_contains($sql, 'conversation_messages')
                && str_contains($sql, 'outbound')
                && ! str_contains($sql, ' in (');
        },
    ));
}

/**
 * @param  list<array<string, mixed>>  $queries
 * @return list<array<string, mixed>>
 */
function singlePhoneCustomerLookups(array $queries): array
{
    return array_values(array_filter(
        $queries,
        function (array $query): bool {
            $sql = strtolower((string) $query['query']);
            if (! str_contains($sql, 'customers') || ! str_contains($sql, 'phone') || str_contains($sql, ' in (')) {
                return false;
            }

            $phones = array_values(array_filter(
                $query['bindings'] ?? [],
                fn (mixed $binding): bool => is_string($binding) && preg_match('/^\d{10}$/', $binding) === 1,
            ));

            return count($phones) === 1;
        },
    ));
}

/**
 * @param  list<array<string, mixed>>  $queries
 * @return list<array<string, mixed>>
 */
function whereInLookups(array $queries, string $table): array
{
    return array_values(array_filter(
        $queries,
        function (array $query) use ($table): bool {
            $sql = strtolower((string) $query['query']);

            return str_contains($sql, $table) && str_contains($sql, ' in (');
        },
    ));
}
