<?php

namespace App\Ark\Dragon\Agent\Tools;

use App\Ark\Dragon\Agent\DragonAgentTool;
use App\Ark\Dragon\DragonWorkProjection;

final class RepairOrdersGetTool implements DragonAgentTool
{
    public function __construct(private readonly DragonWorkProjection $projection) {}

    public function name(): string
    {
        return 'repair_orders.get';
    }

    public function description(): string
    {
        return 'Get one open repair order card: vehicle, status, technician, concern, next action, timestamps. Use after search when you have a repair_order_id and need the next action or details. No customer PII.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'repair_order_id' => ['type' => 'string'],
            ],
            'required' => ['repair_order_id'],
        ];
    }

    public function invoke(array $arguments): array
    {
        $id = trim((string) ($arguments['repair_order_id'] ?? ''));
        if ($id === '') {
            return ['ok' => false, 'error' => 'repair_order_id is required.'];
        }

        $card = $this->projection->repairOrder($id);
        if ($card === null) {
            return ['ok' => false, 'error' => 'Open repair order not found.'];
        }

        return ['ok' => true, 'repair_order' => $card];
    }
}
