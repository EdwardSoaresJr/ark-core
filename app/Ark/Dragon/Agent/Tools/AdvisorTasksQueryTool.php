<?php

namespace App\Ark\Dragon\Agent\Tools;

use App\Ark\Dragon\Agent\DragonAgentTool;
use App\Ark\Station\Http\AuthenticateStationDevice;
use App\Ark\Station\StationDeviceToken;
use App\Ark\Station\StationGlassConfig;
use App\Ark\Station\StationGlassTasksProjection;

final class AdvisorTasksQueryTool implements DragonAgentTool
{
    public function __construct(
        private readonly StationGlassTasksProjection $tasks,
        private readonly StationGlassConfig $config,
    ) {}

    public function name(): string
    {
        return 'advisor_tasks.query';
    }

    public function description(): string
    {
        return 'Read-only advisor TODOs on Shop Glass: who owns open follow-ups, shared/unassigned work, overdue items, linked repair_order_id. Never creates, completes, assigns, or invents tasks.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'assigned_user_id' => [
                    'type' => 'integer',
                    'description' => 'Only that advisor’s open TODOs. Omit for all lanes plus shared.',
                ],
                'shared_only' => [
                    'type' => 'boolean',
                ],
            ],
        ];
    }

    public function invoke(array $arguments): array
    {
        $token = request()->attributes->get(AuthenticateStationDevice::REQUEST_ATTR);
        if (! $token instanceof StationDeviceToken) {
            $token = StationDeviceToken::query()->whereNull('revoked_at')->orderByDesc('last_used_at')->first();
        }
        $config = $token !== null
            ? $this->config->forToken($token)
            : [
                'advisor_mode' => 'two',
                'primary_advisor_user_id' => null,
                'secondary_advisor_user_id' => null,
                'eligible_advisors' => $this->config->eligibleAdvisors(),
            ];

        $payload = $this->tasks->payload($config);

        if (($arguments['shared_only'] ?? false) === true) {
            return ['read_only' => true, 'shared' => $payload['shared']];
        }

        $assigned = $arguments['assigned_user_id'] ?? null;
        if (is_int($assigned) || (is_string($assigned) && ctype_digit($assigned))) {
            $id = (int) $assigned;
            $lane = collect($payload['lanes'])->firstWhere('user_id', $id);

            return [
                'read_only' => true,
                'advisor' => $lane,
                'shared' => $payload['shared'],
            ];
        }

        return ['read_only' => true, ...$payload];
    }
}
