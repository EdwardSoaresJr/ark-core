<?php

namespace App\Ark\Operations\Communications;

use App\Models\User;
use Illuminate\Support\Carbon;

class CommunicationsQueueSinceLastShift
{
    /**
     * @param  array<int, array<string, mixed>>  $needsAttention
     * @return array{boundary: Carbon, boundary_label: string, rows: array<int, array<string, mixed>>}
     */
    public function project(array $needsAttention, ?User $viewer, ?Carbon $previousLastSeenAt): array
    {
        $boundary = $this->boundary($viewer, $previousLastSeenAt);

        $rows = array_values(array_filter(
            $needsAttention,
            fn (array $row): bool => $this->occurredAfter($row, $boundary),
        ));

        usort(
            $rows,
            fn (array $left, array $right): int => $this->occurredTimestamp($left) <=> $this->occurredTimestamp($right),
        );

        return [
            'boundary' => $boundary,
            'boundary_label' => $boundary->timezone(config('app.display_timezone'))->format('M j, g:i A'),
            'rows' => $rows,
        ];
    }

    private function boundary(?User $viewer, ?Carbon $previousLastSeenAt): Carbon
    {
        if ($previousLastSeenAt instanceof Carbon) {
            return $previousLastSeenAt;
        }

        if ($viewer?->last_seen_at instanceof Carbon) {
            return $viewer->last_seen_at;
        }

        return now()->timezone(config('app.display_timezone'))->startOfDay();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function occurredAfter(array $row, Carbon $boundary): bool
    {
        return $this->occurredTimestamp($row) > $boundary->timestamp;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function occurredTimestamp(array $row): int
    {
        return strtotime((string) ($row['occurred_at'] ?? '')) ?: 0;
    }
}
