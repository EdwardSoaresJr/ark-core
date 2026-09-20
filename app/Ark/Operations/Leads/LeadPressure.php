<?php

namespace App\Ark\Operations\Leads;

use App\Ark\Operations\Communications\CommunicationsNeedsYou;
use App\Ark\Runtime\Database\SchemaPresence;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Read-only lead pressure for navigation and Attention surfaces.
 */
class LeadPressure
{
    private const REQUEST_CACHE_KEY = 'lead_pressure';

    /**
     * Rail badge only — one COUNT, not six lead aggregates.
     *
     * @return array{open_count: int, leads_url: string}
     */
    public function resolveOpenCount(?User $viewer): array
    {
        if ($viewer === null || ! $viewer->can('repair_orders.manage') || ! SchemaPresence::hasTable('leads')) {
            return [
                'open_count' => 0,
                'leads_url' => CommunicationsNeedsYou::url(),
            ];
        }

        $request = request();
        $cacheKey = self::REQUEST_CACHE_KEY.':open_count';

        if ($request !== null && $request->attributes->has($cacheKey)) {
            return $request->attributes->get($cacheKey);
        }

        // Prefer full resolve cache when already warm (Leads/Attention pages).
        if ($request !== null && $request->attributes->has(self::REQUEST_CACHE_KEY)) {
            $full = $request->attributes->get(self::REQUEST_CACHE_KEY);

            return [
                'open_count' => (int) ($full['open_count'] ?? 0),
                'leads_url' => (string) ($full['leads_url'] ?? CommunicationsNeedsYou::url()),
            ];
        }

        $resolved = [
            'open_count' => (int) Lead::query()->open()->count(),
            'leads_url' => CommunicationsNeedsYou::url(),
        ];

        $request?->attributes->set($cacheKey, $resolved);

        return $resolved;
    }

    /**
     * @return array{
     *     new_count: int,
     *     not_contacted_count: int,
     *     waiting_customer_count: int,
     *     aging_count: int,
     *     waiting_follow_up_count: int,
     *     open_count: int,
     *     leads_url: string,
     *     summary: list<array{label: string, count: int, hint: string}>
     * }
     */
    public function resolve(?User $viewer): array
    {
        if ($viewer === null || ! $viewer->can('repair_orders.manage')) {
            return $this->empty();
        }

        if (! SchemaPresence::hasTable('leads')) {
            return $this->empty();
        }

        $request = request();

        if ($request !== null && $request->attributes->has(self::REQUEST_CACHE_KEY)) {
            return $request->attributes->get(self::REQUEST_CACHE_KEY);
        }

        $now = now();

        $openCount = Lead::query()->open()->count();
        $newCount = Lead::query()->where('state', LeadState::Received)->count();
        $notContactedCount = Lead::query()->notContacted()->count();
        $waitingCustomerCount = Lead::query()
            ->open()
            ->where('state', LeadState::WaitingCustomer)
            ->count();
        $agingCount = Lead::query()->aging($now)->count();
        $waitingFollowUpCount = Lead::query()
            ->open()
            ->where('state', LeadState::WaitingCustomer)
            ->where('updated_at', '<', $now->copy()->subDays(2))
            ->count();

        $resolved = [
            'new_count' => $newCount,
            'not_contacted_count' => $notContactedCount,
            'waiting_customer_count' => $waitingCustomerCount,
            'aging_count' => $agingCount,
            'waiting_follow_up_count' => $waitingFollowUpCount,
            'open_count' => $openCount,
            'leads_url' => CommunicationsNeedsYou::url(),
            'summary' => array_values(array_filter([
                $newCount > 0 ? ['label' => 'New Leads', 'count' => $newCount, 'hint' => 'Not yet worked'] : null,
                $notContactedCount > 0 ? ['label' => 'Not Contacted', 'count' => $notContactedCount, 'hint' => 'No first contact'] : null,
                $waitingCustomerCount > 0 ? ['label' => 'Waiting Customer', 'count' => $waitingCustomerCount, 'hint' => 'Ball in their court'] : null,
            ])),
        ];

        $request?->attributes->set(self::REQUEST_CACHE_KEY, $resolved);
        $request?->attributes->set(self::REQUEST_CACHE_KEY.':open_count', [
            'open_count' => $openCount,
            'leads_url' => $resolved['leads_url'],
        ]);

        return $resolved;
    }

    /**
     * @return array{
     *     new_count: int,
     *     not_contacted_count: int,
     *     waiting_customer_count: int,
     *     aging_count: int,
     *     waiting_follow_up_count: int,
     *     open_count: int,
     *     leads_url: string,
     *     summary: list<array{label: string, count: int, hint: string}>
     * }
     */
    private function empty(): array
    {
        return [
            'new_count' => 0,
            'not_contacted_count' => 0,
            'waiting_customer_count' => 0,
            'aging_count' => 0,
            'waiting_follow_up_count' => 0,
            'open_count' => 0,
            'leads_url' => CommunicationsNeedsYou::url(),
            'summary' => [],
        ];
    }
}
