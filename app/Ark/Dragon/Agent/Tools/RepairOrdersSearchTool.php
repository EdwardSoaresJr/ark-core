<?php

namespace App\Ark\Dragon\Agent\Tools;

use App\Ark\Dragon\Agent\DragonAgentTool;
use App\Ark\Dragon\Query\DragonQueryExecutor;
use App\Ark\Dragon\Query\DragonReadQuery;
use InvalidArgumentException;

final class RepairOrdersSearchTool implements DragonAgentTool
{
    public function __construct(private readonly DragonQueryExecutor $executor) {}

    public function name(): string
    {
        return 'repair_orders.search';
    }

    public function description(): string
    {
        return 'Search open repair orders with allowlisted filters (status, assigned_technician including is_null for unassigned, vehicle_label contains, age, next_action). Use this to name vehicles — not only counts — for unassigned work, in-production work, or a named car. Locate vehicles/people/ROs before concluding they are not on the board. If the first search is empty, try one alternate filter (make vs model, technician name, status group) before saying not found. Read-only. No SQL.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'filters' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'field' => ['type' => 'string'],
                            'op' => ['type' => 'string'],
                            'value' => [
                                'type' => ['string', 'number', 'boolean', 'null'],
                            ],
                        ],
                    ],
                ],
                'sort' => ['type' => 'array'],
                'limit' => ['type' => 'integer'],
                'aggregation' => [
                    'type' => ['string', 'object', 'null'],
                ],
            ],
        ];
    }

    public function invoke(array $arguments): array
    {
        foreach (['sql', 'query_sql', 'statement', 'mutate'] as $blocked) {
            if (array_key_exists($blocked, $arguments)) {
                return ['ok' => false, 'error' => 'SQL and mutation keys are not allowed.', 'read_only' => true];
            }
        }

        $payload = [
            'entity' => DragonReadQuery::ENTITY,
            'filters' => $arguments['filters'] ?? [],
            'sort' => $arguments['sort'] ?? [],
            'limit' => $arguments['limit'] ?? DragonReadQuery::DEFAULT_LIMIT,
        ];
        if (array_key_exists('aggregation', $arguments) && $arguments['aggregation'] !== null) {
            $payload['aggregation'] = $arguments['aggregation'];
        }

        try {
            $query = DragonReadQuery::fromArray($payload);
        } catch (InvalidArgumentException $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'read_only' => true,
            ];
        }

        return $this->executor->execute($query);
    }
}
