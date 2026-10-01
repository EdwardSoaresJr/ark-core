<?php

namespace App\Ark\Operations\Staff;

use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use Illuminate\Support\Carbon;

final class StaffFrontDoorLandingSummary
{
    /**
     * @return array{
     *     days: int,
     *     attention: int,
     *     workboard: int,
     *     other: int,
     *     total: int,
     *     attention_share: int|null
     * }
     */
    public function lastDays(int $days = 7): array
    {
        $since = Carbon::now()->subDays($days);

        $events = OperationalEvent::query()
            ->where('event_name', OperationalEventName::StaffFrontDoorLanded->value)
            ->where('occurred_at', '>=', $since)
            ->get(['payload_json']);

        $attention = 0;
        $workboard = 0;
        $other = 0;

        foreach ($events as $event) {
            $surface = is_array($event->payload_json) ? ($event->payload_json['surface'] ?? '') : '';

            match ($surface) {
                'attention' => $attention++,
                'workboard' => $workboard++,
                default => $other++,
            };
        }

        $total = $attention + $workboard + $other;

        return [
            'days' => $days,
            'attention' => $attention,
            'workboard' => $workboard,
            'other' => $other,
            'total' => $total,
            'attention_share' => $total > 0 ? (int) round(($attention / $total) * 100) : null,
        ];
    }
}
