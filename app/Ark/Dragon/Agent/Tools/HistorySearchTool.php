<?php

namespace App\Ark\Dragon\Agent\Tools;

use App\Ark\Dragon\Agent\DragonAgentTool;
use App\Ark\Dragon\DragonHistoryProjection;
use Illuminate\Http\Request;

final class HistorySearchTool implements DragonAgentTool
{
    public function __construct(private readonly DragonHistoryProjection $history) {}

    public function name(): string
    {
        return 'history.search';
    }

    public function description(): string
    {
        return 'Search closed repair-order history snapshots. Use for past work on a vehicle, not today\'s open board. No customer identity, no money, no full VIN.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'vehicle' => ['type' => 'string'],
                'limit' => ['type' => 'integer'],
            ],
        ];
    }

    public function invoke(array $arguments): array
    {
        $request = Request::create('/api/dragon/history/repair-orders', 'GET', [
            'limit' => max(1, min(25, (int) ($arguments['limit'] ?? 10))),
            'diagnostic' => false,
        ]);

        $payload = $this->history->listRepairOrders($request);
        $needle = mb_strtolower(trim((string) ($arguments['vehicle'] ?? '')));
        $items = $payload['items'] ?? [];
        if ($needle !== '') {
            $items = array_values(array_filter(
                $items,
                function (array $row) use ($needle): bool {
                    $vehicle = is_array($row['vehicle'] ?? null) ? $row['vehicle'] : [];
                    $haystack = mb_strtolower(trim(implode(' ', array_filter([
                        $row['vehicle_label'] ?? null,
                        $vehicle['year'] ?? null,
                        $vehicle['make'] ?? null,
                        $vehicle['model'] ?? null,
                    ]))));

                    return $haystack !== '' && str_contains($haystack, $needle);
                },
            ));
        }

        return [
            'ok' => true,
            '_ark_categories' => ['closed_repair_history'],
            'summary' => $payload['summary'] ?? $this->history->summary(),
            'items' => array_slice($items, 0, 10),
            'note' => $payload['summary']['semantics'] ?? null,
        ];
    }
}
