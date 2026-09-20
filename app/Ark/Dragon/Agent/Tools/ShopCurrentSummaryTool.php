<?php

namespace App\Ark\Dragon\Agent\Tools;

use App\Ark\Dragon\Agent\DragonAgentTool;
use App\Ark\Dragon\DragonWorkProjection;

final class ShopCurrentSummaryTool implements DragonAgentTool
{
    public function __construct(private readonly DragonWorkProjection $projection) {}

    public function name(): string
    {
        return 'shop.current_summary';
    }

    public function description(): string
    {
        return 'Live Demo Auto Repair floor snapshot: open RO counts, waiting approval, in production, unassigned work, technician load, oldest/stale jobs, plus short lists of unassigned_repair_orders and in_production_repair_orders. Use before answering questions about current priorities, worry, focus, bottlenecks, workload, what looks ugly, who is buried, what is unassigned, or what is in production. Name vehicles from those lists when asked. No customer PII.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => (object) [],
        ];
    }

    public function invoke(array $arguments): array
    {
        return [
            'read_only' => true,
            'summary' => $this->projection->summaryOnly(),
        ];
    }
}
