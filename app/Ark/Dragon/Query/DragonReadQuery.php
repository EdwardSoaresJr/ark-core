<?php

namespace App\Ark\Dragon\Query;

use InvalidArgumentException;

/**
 * Constrained read-only repair-order query. Fail closed.
 *
 * Allowlisted fields: repair_order_id, vehicle_label, status, status_label,
 * status_group, assigned_technician, next_action, opened_at, updated_at,
 * age_days, concern_summary.
 *
 * Allowlisted operators depend on field type. Unknown entity/field/op → reject.
 * Semantic status aliases reuse DragonWorkProjection production / waiting-approval definitions.
 */
final class DragonReadQuery
{
    public const ENTITY = 'repair_orders';

    public const DEFAULT_LIMIT = 20;

    public const MAX_LIMIT = 50;

    public const FIELDS = [
        'repair_order_id',
        'vehicle_label',
        'status',
        'status_label',
        'status_group',
        'assigned_technician',
        'next_action',
        'opened_at',
        'updated_at',
        'age_days',
        'concern_summary',
    ];

    public const SORT_FIELDS = [
        'opened_at',
        'updated_at',
        'repair_order_id',
        'age_days',
        'status',
        'assigned_technician',
    ];

    public const AGGREGATION_FIELDS = [
        'assigned_technician',
        'status',
        'status_group',
    ];

    public const STATUS_ALIASES = [
        'waiting approval' => 'waiting_approval',
        'waiting for approval' => 'waiting_approval',
        'awaiting approval' => 'waiting_approval',
        'needs approval' => 'waiting_approval',
        'waiting parts' => 'waiting_parts',
        'waiting on parts' => 'waiting_parts',
        'in progress' => 'in_progress',
        'ready for work' => 'ready_for_work',
        'quality check' => 'quality_check',
    ];

    public const STATUS_GROUPS = [
        'in_production',
        'waiting_approval',
        'open',
    ];

    /** @var list<array{field: string, op: string, value: mixed}> */
    public array $filters = [];

    /** @var list<array{field: string, direction: string}> */
    public array $sort = [];

    public int $limit = self::DEFAULT_LIMIT;

    public bool $limitClamped = false;

    public ?string $aggregation = null;

    public ?string $aggregationField = null;

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $forbidden = ['action', 'mutation', 'sql', 'update', 'delete', 'insert'];
        foreach ($forbidden as $verb) {
            if (array_key_exists($verb, $payload)) {
                throw new InvalidArgumentException('Query schema contains no mutation verbs.');
            }
        }

        $allowedKeys = ['entity', 'filters', 'sort', 'limit', 'aggregation'];
        $extra = array_diff(array_keys($payload), $allowedKeys);
        if ($extra !== []) {
            throw new InvalidArgumentException('Unknown query keys are not allowed.');
        }

        $entity = $payload['entity'] ?? null;
        if ($entity !== self::ENTITY) {
            throw new InvalidArgumentException('Only entity repair_orders is allowed.');
        }

        $query = new self;
        $query->filters = self::parseFilters($payload['filters'] ?? []);
        $query->sort = self::parseSort($payload['sort'] ?? []);
        [$query->limit, $query->limitClamped] = self::parseLimit($payload['limit'] ?? null);
        [$query->aggregation, $query->aggregationField] = self::parseAggregation($payload['aggregation'] ?? null);

        if ($query->aggregation !== null && $query->sort !== []) {
            throw new InvalidArgumentException('Sort is not allowed with aggregation.');
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'entity' => self::ENTITY,
            'filters' => $this->filters,
            'sort' => $this->sort,
            'limit' => $this->limit,
        ];
        if ($this->aggregation !== null) {
            $out['aggregation'] = $this->aggregation === 'group_count'
                ? ['type' => 'group_count', 'field' => $this->aggregationField]
                : 'count';
        }

        return $out;
    }

    /**
     * Safe log metadata — no values that may contain PII (concern text, names beyond field ops).
     *
     * @return array<string, mixed>
     */
    public function auditMetadata(): array
    {
        return [
            'entity' => self::ENTITY,
            'filter_fields' => array_values(array_unique(array_map(fn (array $filter): string => $filter['field'], $this->filters))),
            'filter_ops' => array_values(array_unique(array_map(fn (array $filter): string => $filter['op'], $this->filters))),
            'sort' => $this->sort,
            'limit' => $this->limit,
            'limit_clamped' => $this->limitClamped,
            'aggregation' => $this->aggregation,
            'aggregation_field' => $this->aggregationField,
        ];
    }

    /**
     * @param  mixed  $raw
     * @return list<array{field: string, op: string, value: mixed}>
     */
    private static function parseFilters(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }
        if (! is_array($raw)) {
            throw new InvalidArgumentException('filters must be an array.');
        }

        $out = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('Each filter must be an object.');
            }
            $field = (string) ($item['field'] ?? '');
            $op = strtolower((string) ($item['op'] ?? ''));
            if ($field === '' || str_starts_with($field, '__') || ! in_array($field, self::FIELDS, true)) {
                throw new InvalidArgumentException("Field [{$field}] is not allowed.");
            }
            $allowed = self::operatorsFor($field);
            if (! in_array($op, $allowed, true)) {
                throw new InvalidArgumentException("Operator [{$op}] is not allowed on [{$field}].");
            }
            $value = $item['value'] ?? null;
            if (in_array($op, ['is_null', 'not_null'], true)) {
                $out[] = ['field' => $field, 'op' => $op, 'value' => null];

                continue;
            }
            $out[] = ['field' => $field, 'op' => $op, 'value' => self::normalizeValue($field, $op, $value)];
        }

        return $out;
    }

    /**
     * @param  mixed  $raw
     * @return list<array{field: string, direction: string}>
     */
    private static function parseSort(mixed $raw): array
    {
        if ($raw === null || $raw === []) {
            return [];
        }
        if (! is_array($raw)) {
            throw new InvalidArgumentException('sort must be an array.');
        }
        $out = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('Each sort must be an object.');
            }
            $field = (string) ($item['field'] ?? '');
            $direction = strtolower((string) ($item['direction'] ?? 'asc'));
            if (! in_array($field, self::SORT_FIELDS, true)) {
                throw new InvalidArgumentException("Sort field [{$field}] is not allowed.");
            }
            if (! in_array($direction, ['asc', 'desc'], true)) {
                throw new InvalidArgumentException('Sort direction must be asc or desc.');
            }
            $out[] = ['field' => $field, 'direction' => $direction];
        }

        return $out;
    }

    /**
     * @return array{0: int, 1: bool}
     */
    private static function parseLimit(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [self::DEFAULT_LIMIT, false];
        }
        if (! is_numeric($raw)) {
            throw new InvalidArgumentException('limit must be a number.');
        }
        $limit = (int) $raw;
        if ($limit < 1) {
            throw new InvalidArgumentException('limit must be at least 1.');
        }
        if ($limit > self::MAX_LIMIT) {
            return [self::MAX_LIMIT, true];
        }

        return [$limit, false];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private static function parseAggregation(mixed $raw): array
    {
        if ($raw === null || $raw === '' || $raw === false) {
            return [null, null];
        }
        if ($raw === 'count') {
            return ['count', null];
        }
        if (is_array($raw)) {
            $type = $raw['type'] ?? $raw['aggregation'] ?? null;
            if ($type === 'count') {
                return ['count', null];
            }
            if ($type === 'group_count') {
                $field = (string) ($raw['field'] ?? '');
                if (! in_array($field, self::AGGREGATION_FIELDS, true)) {
                    throw new InvalidArgumentException("Aggregation field [{$field}] is not allowed.");
                }

                return ['group_count', $field];
            }
        }

        throw new InvalidArgumentException('aggregation must be count or group_count.');
    }

    /**
     * @return list<string>
     */
    public static function operatorsFor(string $field): array
    {
        return match ($field) {
            'repair_order_id' => ['eq', 'neq', 'in', 'not_in'],
            'status', 'status_label' => ['eq', 'neq', 'in', 'not_in'],
            'status_group' => ['eq', 'in'],
            'assigned_technician' => ['eq', 'neq', 'in', 'contains', 'is_null', 'not_null'],
            'vehicle_label', 'concern_summary', 'next_action' => ['contains', 'eq'],
            'opened_at', 'updated_at' => ['before', 'after', 'older_than', 'newer_than', 'lt', 'lte', 'gt', 'gte'],
            'age_days' => ['eq', 'lt', 'lte', 'gt', 'gte'],
            default => [],
        };
    }

    private static function normalizeValue(string $field, string $op, mixed $value): mixed
    {
        if (in_array($op, ['in', 'not_in'], true)) {
            if (! is_array($value) || $value === []) {
                throw new InvalidArgumentException("Operator [{$op}] requires a non-empty list.");
            }

            return array_map(fn ($item) => self::normalizeScalar($field, $item), array_values($value));
        }

        if (in_array($op, ['older_than', 'newer_than'], true)) {
            return self::normalizeDuration($value);
        }

        return self::normalizeScalar($field, $value);
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeDuration(mixed $value): array
    {
        if (is_string($value)) {
            $calendar = strtolower(trim($value));
            if (in_array($calendar, ['today', 'yesterday', 'this_week'], true)) {
                return ['calendar' => $calendar];
            }
        }
        if (! is_array($value)) {
            throw new InvalidArgumentException('Relative time value must be a duration object or calendar name.');
        }
        if (isset($value['calendar'])) {
            $calendar = strtolower((string) $value['calendar']);
            if (! in_array($calendar, ['today', 'yesterday', 'this_week'], true)) {
                throw new InvalidArgumentException('calendar must be today, yesterday, or this_week.');
            }

            return ['calendar' => $calendar];
        }
        $days = isset($value['days']) ? (int) $value['days'] : 0;
        $weeks = isset($value['weeks']) ? (int) $value['weeks'] : 0;
        $hours = isset($value['hours']) ? (int) $value['hours'] : 0;
        if ($days < 0 || $weeks < 0 || $hours < 0 || ($days + $weeks + $hours) === 0) {
            throw new InvalidArgumentException('Duration must be a positive days/weeks/hours value.');
        }

        return ['days' => $days, 'weeks' => $weeks, 'hours' => $hours];
    }

    private static function normalizeScalar(string $field, mixed $value): mixed
    {
        if ($value === null || is_array($value)) {
            throw new InvalidArgumentException("Invalid value for [{$field}].");
        }

        if ($field === 'age_days' || $field === 'repair_order_id') {
            return is_numeric($value) ? (string) $value : (string) $value;
        }

        if ($field === 'status' || $field === 'status_group' || $field === 'status_label') {
            return self::canonicalizeStatus((string) $value);
        }

        if (in_array($field, ['opened_at', 'updated_at'], true) && is_string($value)) {
            $calendar = strtolower(trim($value));
            if (in_array($calendar, ['today', 'yesterday', 'this_week'], true)) {
                return ['calendar' => $calendar];
            }
        }

        return $value;
    }

    public static function canonicalizeStatus(string $value): string
    {
        $trimmed = strtolower(trim($value));
        $trimmed = str_replace(['-', '_'], ' ', $trimmed);
        $collapsed = preg_replace('/\s+/', ' ', $trimmed) ?? $trimmed;

        if (isset(self::STATUS_ALIASES[$collapsed])) {
            return self::STATUS_ALIASES[$collapsed];
        }

        $slug = str_replace(' ', '_', $collapsed);
        if ($slug === 'in_production' || in_array($slug, self::STATUS_GROUPS, true)) {
            return $slug;
        }

        return $slug;
    }
}
