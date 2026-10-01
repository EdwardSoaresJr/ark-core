<?php

namespace App\Ark\Dragon;

use App\Ark\Dragon\Assist\DragonAssistRequest;
use App\Ark\Dragon\Assist\DragonAssistStatus;
use App\Ark\Station\StationDashboardProjection;
use Illuminate\Support\Carbon;

/**
 * Hosted Dragon status for internal diagnostics. The Flutter station does not use this.
 */
final class DragonStationProjection
{
    public function __construct(
        private readonly StationDashboardProjection $station,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $core = $this->station->payload();
        $core['privacy'] = 'no_customer_pii';
        $core = $this->stripCustomerLabels($core);
        $core['dragon'] = [
            'chat_ready' => (bool) config('dragon.hosted_chat_enabled', true),
            'hosted_chat_path' => '/api/dragon-agent/chat',
            'note' => 'Hosted Dragon in ARK. Shop state lives on /api/station/dashboard.',
        ];
        $core['admin'] = $this->adminSnapshot();

        return $core;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function stripCustomerLabels(array $payload): array
    {
        $walk = function (&$node) use (&$walk): void {
            if (! is_array($node)) {
                return;
            }
            unset($node['customer_label']);
            foreach ($node as &$child) {
                $walk($child);
            }
        };
        $walk($payload);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function adminSnapshot(): array
    {
        $today = Carbon::today();

        return [
            'ark' => 'ok',
            'dragon' => 'hosted',
            'requests' => [
                'pending' => DragonAssistRequest::query()->where('status', DragonAssistStatus::Pending->value)->count(),
                'completed_today' => DragonAssistRequest::query()
                    ->where('status', DragonAssistStatus::Completed->value)
                    ->whereDate('completed_at', $today)
                    ->count(),
                'failed_today' => DragonAssistRequest::query()
                    ->where('status', DragonAssistStatus::Failed->value)
                    ->whereDate('failed_at', $today)
                    ->count(),
            ],
        ];
    }
}
