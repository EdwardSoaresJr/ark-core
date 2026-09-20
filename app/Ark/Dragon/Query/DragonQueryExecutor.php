<?php

namespace App\Ark\Dragon\Query;

use App\Ark\Dragon\DragonWorkProjection;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use Illuminate\Support\Carbon;

/**
 * Executes a validated DragonReadQuery against all open repair orders.
 *
 * Completeness: loads the full open set (same as summary), then filters.
 * Does not use the work-items card cap. Counts and groups are over the full match set.
 * Row lists are capped at query limit; truncated=true when more matches exist.
 */
final class DragonQueryExecutor
{
    public function __construct(private readonly DragonWorkProjection $projection) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(DragonReadQuery $query, ?Carbon $now = null): array
    {
        $now = $now ?? Carbon::now('UTC');
        $cards = $this->projection->allOpenCards();
        $matched = array_values(array_filter(
            $cards,
            fn (array $card): bool => $this->matches($card, $query, $now),
        ));

        $executedAt = $now->copy()->utc()->toIso8601String();
        $base = [
            'ok' => true,
            'entity' => DragonReadQuery::ENTITY,
            'query' => $query->toArray(),
            'executed_at' => $executedAt,
            'timezone' => ShopDisplayTimezone::resolve(),
            'limit_clamped' => $query->limitClamped,
            'read_only' => true,
        ];

        if ($query->aggregation === 'count') {
            return array_merge($base, [
                'count' => count($matched),
                'rows' => [],
                'groups' => [],
                'truncated' => false,
                'complete' => true,
            ]);
        }

        if ($query->aggregation === 'group_count') {
            $groups = $this->groupCount($matched, $query->aggregationField ?? 'assigned_technician');

            return array_merge($base, [
                'count' => count($matched),
                'rows' => [],
                'groups' => $groups,
                'aggregation_field' => $query->aggregationField,
                'truncated' => false,
                'complete' => true,
            ]);
        }

        $sorted = $this->sort($matched, $query->sort);
        $total = count($sorted);
        $rows = array_slice($sorted, 0, $query->limit);
        $truncated = $total > count($rows);

        return array_merge($base, [
            'count' => $total,
            'rows' => $rows,
            'groups' => [],
            'truncated' => $truncated,
            'complete' => ! $truncated,
        ]);
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private function matches(array $card, DragonReadQuery $query, Carbon $now): bool
    {
        foreach ($query->filters as $filter) {
            if (! $this->matchFilter($card, $filter, $now)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $card
     * @param  array{field: string, op: string, value: mixed}  $filter
     */
    private function matchFilter(array $card, array $filter, Carbon $now): bool
    {
        $field = $filter['field'];
        $op = $filter['op'];
        $actual = $this->fieldValue($card, $field, $now);

        if ($op === 'is_null') {
            return $actual === null || $actual === '';
        }
        if ($op === 'not_null') {
            return $actual !== null && $actual !== '';
        }

        if (in_array($field, ['opened_at', 'updated_at'], true)) {
            return $this->matchInstant($actual, $op, $filter['value'], $now);
        }

        if ($field === 'age_days') {
            $left = is_numeric($actual) ? (int) $actual : null;
            $right = is_numeric($filter['value']) ? (int) $filter['value'] : null;
            if ($left === null || $right === null) {
                return false;
            }

            return match ($op) {
                'eq' => $left === $right,
                'lt' => $left < $right,
                'lte' => $left <= $right,
                'gt' => $left > $right,
                'gte' => $left >= $right,
                default => false,
            };
        }

        if ($op === 'in') {
            return $this->inList($actual, $filter['value'], $field);
        }
        if ($op === 'not_in') {
            return ! $this->inList($actual, $filter['value'], $field);
        }
        if ($op === 'contains') {
            return is_string($actual) && is_string($filter['value'])
                && str_contains(mb_strtolower($actual), mb_strtolower((string) $filter['value']));
        }

        $expected = $this->normalizeComparable($field, $filter['value']);
        $left = $this->normalizeComparable($field, $actual);

        return match ($op) {
            'eq' => $left === $expected,
            'neq' => $left !== $expected,
            default => false,
        };
    }

    private function matchInstant(mixed $actual, string $op, mixed $value, Carbon $now): bool
    {
        $left = $this->parseInstant($actual);
        if ($left === null) {
            return false;
        }

        if (in_array($op, ['older_than', 'newer_than'], true)) {
            $bound = $this->resolveRelativeBound($value, $now);
            if ($bound === null) {
                return false;
            }

            return $op === 'older_than' ? $left->lt($bound) : $left->gt($bound);
        }

        $right = $this->resolveComparisonInstant($value, $now);
        if ($right === null) {
            return false;
        }

        return match ($op) {
            'before', 'lt' => $left->lt($right),
            'lte' => $left->lte($right),
            'after', 'gt' => $left->gt($right),
            'gte' => $left->gte($right),
            default => false,
        };
    }

    /**
     * older_than / newer_than:
     * - {days/weeks/hours} → now minus that duration (UTC instant)
     * - calendar today → start of today shop-local
     * - yesterday → start of yesterday shop-local
     * - this_week → Monday 00:00 shop-local (ISO week)
     */
    private function resolveRelativeBound(mixed $value, Carbon $now): ?Carbon
    {
        if (! is_array($value)) {
            return null;
        }
        if (isset($value['calendar'])) {
            return $this->calendarStart((string) $value['calendar'], $now);
        }
        $days = (int) ($value['days'] ?? 0);
        $weeks = (int) ($value['weeks'] ?? 0);
        $hours = (int) ($value['hours'] ?? 0);

        return $now->copy()->utc()->subWeeks($weeks)->subDays($days)->subHours($hours);
    }

    private function resolveComparisonInstant(mixed $value, Carbon $now): ?Carbon
    {
        if (is_array($value) && isset($value['calendar'])) {
            return $this->calendarStart((string) $value['calendar'], $now);
        }
        if (is_string($value) || $value instanceof Carbon) {
            return $this->parseInstant($value);
        }

        return null;
    }

    private function calendarStart(string $calendar, Carbon $now): Carbon
    {
        $timezone = ShopDisplayTimezone::resolve();
        $local = $now->copy()->utc()->timezone($timezone)->startOfDay();

        return match ($calendar) {
            'yesterday' => $local->subDay()->utc(),
            'this_week' => $local->startOfWeek(Carbon::MONDAY)->utc(),
            default => $local->utc(),
        };
    }

    private function parseInstant(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->utc();
        }
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private function fieldValue(array $card, string $field, Carbon $now): mixed
    {
        return match ($field) {
            'status_group' => $this->statusGroup((string) ($card['status'] ?? '')),
            'age_days' => $this->ageDays($card['opened_at'] ?? null, $now),
            'assigned_technician' => $card['assigned_technician'] ?? null,
            default => $card[$field] ?? null,
        };
    }

    private function statusGroup(string $status): string
    {
        if ($status === RepairOrderStatus::WaitingApproval->value) {
            return 'waiting_approval';
        }
        if (in_array($status, $this->projection->productionStatusSlugs(), true)) {
            return 'in_production';
        }

        return 'open';
    }

    private function ageDays(mixed $openedAt, Carbon $now): ?int
    {
        $opened = $this->parseInstant($openedAt);
        if ($opened === null) {
            return null;
        }

        return (int) $opened->diffInDays($now->copy()->utc());
    }

    private function inList(mixed $actual, mixed $expected, string $field): bool
    {
        if (! is_array($expected)) {
            return false;
        }
        $left = $this->normalizeComparable($field, $actual);
        foreach ($expected as $item) {
            if ($left === $this->normalizeComparable($field, $item)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeComparable(string $field, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = strtolower(trim((string) $value));
        if ($field === 'status' || $field === 'status_group') {
            return DragonReadQuery::canonicalizeStatus($text);
        }

        return $text;
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     * @return list<array{key: string, count: int}>
     */
    private function groupCount(array $cards, string $field): array
    {
        $counts = [];
        foreach ($cards as $card) {
            $raw = $this->fieldValue($card, $field, Carbon::now('UTC'));
            $key = $raw === null || $raw === '' ? 'Unassigned' : (string) $raw;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        arsort($counts);
        $groups = [];
        foreach ($counts as $key => $count) {
            $groups[] = ['key' => $key, 'count' => $count];
        }

        return $groups;
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     * @param  list<array{field: string, direction: string}>  $sort
     * @return list<array<string, mixed>>
     */
    private function sort(array $cards, array $sort): array
    {
        if ($sort === []) {
            $sort = [['field' => 'opened_at', 'direction' => 'asc']];
        }
        usort($cards, function (array $left, array $right) use ($sort): int {
            foreach ($sort as $rule) {
                $field = $rule['field'];
                $cmp = $this->compareValues(
                    $this->fieldValue($left, $field, Carbon::now('UTC')),
                    $this->fieldValue($right, $field, Carbon::now('UTC')),
                );
                if ($cmp !== 0) {
                    return $rule['direction'] === 'desc' ? -$cmp : $cmp;
                }
            }

            return strcmp((string) ($left['repair_order_id'] ?? ''), (string) ($right['repair_order_id'] ?? ''));
        });

        return $cards;
    }

    private function compareValues(mixed $left, mixed $right): int
    {
        if ($left === null && $right === null) {
            return 0;
        }
        if ($left === null) {
            return 1;
        }
        if ($right === null) {
            return -1;
        }
        $leftInstant = is_string($left) ? $this->parseInstant($left) : null;
        $rightInstant = is_string($right) ? $this->parseInstant($right) : null;
        if ($leftInstant !== null && $rightInstant !== null) {
            return $leftInstant->getTimestamp() <=> $rightInstant->getTimestamp();
        }
        if (is_numeric($left) && is_numeric($right)) {
            return ((int) $left) <=> ((int) $right);
        }

        return strcasecmp((string) $left, (string) $right);
    }
}
