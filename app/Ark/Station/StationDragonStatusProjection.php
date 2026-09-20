<?php

namespace App\Ark\Station;

use App\Ark\Dragon\Agent\Contracts\DragonModelProvider;

/**
 * Glass Dragon chip + ARK-derived observations. No model call on dashboard poll.
 */
final class StationDragonStatusProjection
{
    /**
     * @param  array<string, mixed>  $attention
     * @param  array<string, mixed>  $todaySummary
     * @return array<string, mixed>
     */
    public function payload(array $attention, array $todaySummary): array
    {
        $ready = $this->hostedReady();
        $observations = $this->observations($attention, $todaySummary);

        return [
            'ready' => $ready,
            'status_label' => $ready ? 'Dragon Ready' : 'Dragon unavailable',
            'observations' => $ready ? $observations : [],
        ];
    }

    public function hostedReady(): bool
    {
        if (! (bool) config('dragon.hosted_chat_enabled', false)) {
            return false;
        }

        return app(DragonModelProvider::class)->health()['ok'] ?? false;
    }

    /**
     * @param  array<string, mixed>  $attention
     * @param  array<string, mixed>  $todaySummary
     * @return list<string>
     */
    public function observations(array $attention, array $todaySummary): array
    {
        $shop = is_array($attention['shop_summary'] ?? null) ? $attention['shop_summary'] : [];
        $lines = [];

        $approvals = (int) ($shop['waiting_approval'] ?? $todaySummary['waiting_for_approval_count'] ?? $todaySummary['waiting_for_approval_count'] ?? 0);
        $money = (string) ($shop['waiting_approval_amount_label'] ?? $todaySummary['waiting_approval_amount_label'] ?? '');
        if ($approvals > 0) {
            $lines[] = $money !== ''
                ? $approvals.' waiting approval · '.$money.' sitting.'
                : $approvals.' waiting approval.';
        }

        $techCounts = $todaySummary['technician_counts'] ?? [];
        if (is_array($techCounts) && $techCounts !== []) {
            $heaviest = null;
            $load = -1;
            foreach ($techCounts as $name => $count) {
                if ((string) $name === 'Unassigned') {
                    continue;
                }
                if ((int) $count > $load) {
                    $load = (int) $count;
                    $heaviest = (string) $name;
                }
            }
            if ($heaviest !== null && $load > 0) {
                $lines[] = $heaviest.' is carrying the heaviest load ('.$load.').';
            }
        }

        $unassigned = (int) ($shop['unassigned'] ?? $todaySummary['unassigned_count'] ?? 0);
        if ($unassigned > 0) {
            $lines[] = $unassigned.' unassigned.';
        }

        $parts = (int) ($shop['waiting_parts'] ?? $todaySummary['waiting_parts_count'] ?? 0);
        if ($parts > 0 && count($lines) < 3) {
            $lines[] = $parts.' waiting on parts.';
        }

        return array_slice($lines, 0, 3);
    }
}
