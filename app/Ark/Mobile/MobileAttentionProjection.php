<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Attention\CustomerDecisionPressure;
use App\Ark\Operations\Communications\CommunicationsQueueResolver;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Mobile Attention slice — composes existing Attention projections only.
 */
final class MobileAttentionProjection
{
    private const MAX_ITEMS_PER_SECTION = 25;

    public function __construct(
        private readonly MobileStaffAccess $access,
        private readonly CustomerDecisionPressure $customerDecisionPressure,
        private readonly CommunicationsQueueResolver $communicationsQueue,
    ) {}

    /**
     * @return array{
     *     sections: list<array<string, mixed>>,
     *     total_count: int,
     *     poll_after_seconds: int,
     *     push_enabled: bool,
     * }
     */
    public function forUser(User $user): array
    {
        if (! $this->access->canViewShopAttention($user)) {
            return [
                'sections' => [],
                'total_count' => 0,
                'poll_after_seconds' => 45,
                'push_enabled' => $this->pushEnabled(),
            ];
        }

        $previousLastSeenAt = $user->last_seen_at instanceof Carbon
            ? $user->last_seen_at
            : null;

        $decision = $this->customerDecisionPressure->resolve($user);
        $comms = $this->communicationsQueue->resolveAttention($user, $previousLastSeenAt);

        $decisionItems = collect([
            ...$decision['customer_decision_needed'] ?? [],
            ...$decision['estimate_ready_not_sent'] ?? [],
            ...$decision['approved_work_stalled'] ?? [],
        ])
            ->map(fn (array $row): array => $this->mapDecisionRow($row))
            ->take(self::MAX_ITEMS_PER_SECTION)
            ->values()
            ->all();

        $sinceLastShift = collect($comms['since_last_shift'] ?? [])
            ->map(fn (array $row): array => $this->mapCommsRow($row))
            ->take(self::MAX_ITEMS_PER_SECTION)
            ->values()
            ->all();

        $callsWaiting = collect($comms['needs_attention'] ?? [])
            ->filter(fn (array $row): bool => ($row['kind'] ?? '') === 'call')
            ->map(fn (array $row): array => $this->mapCommsRow($row))
            ->take(self::MAX_ITEMS_PER_SECTION)
            ->values()
            ->all();

        $sections = [];

        if ($decisionItems !== []) {
            $sections[] = [
                'key' => 'customer_decision',
                'label' => 'Customer Decision',
                'count' => count($decisionItems),
                'items' => $decisionItems,
            ];
        }

        if ($sinceLastShift !== []) {
            $sections[] = [
                'key' => 'since_last_shift',
                'label' => (string) ($comms['since_last_shift_boundary_label'] ?? 'Since Last Shift'),
                'count' => count($sinceLastShift),
                'items' => $sinceLastShift,
            ];
        }

        if ($callsWaiting !== []) {
            $sections[] = [
                'key' => 'calls_waiting',
                'label' => 'Calls Waiting',
                'count' => count($callsWaiting),
                'items' => $callsWaiting,
            ];
        }

        $totalCount = array_sum(array_column($sections, 'count'));

        return [
            'sections' => $sections,
            'total_count' => $totalCount,
            'poll_after_seconds' => 45,
            'push_enabled' => $this->pushEnabled(),
        ];
    }

    /**
     * Communications-only attention — Calls Waiting + Since Last Shift for the Comms hub.
     *
     * @return array{
     *     sections: list<array<string, mixed>>,
     *     total_count: int,
     *     poll_after_seconds: int,
     * }
     */
    public function commsForUser(User $user): array
    {
        if (! $this->access->canAccessShopCommunications($user)) {
            return [
                'sections' => [],
                'total_count' => 0,
                'poll_after_seconds' => 30,
            ];
        }

        $previousLastSeenAt = $user->last_seen_at instanceof Carbon
            ? $user->last_seen_at
            : null;

        $comms = $this->communicationsQueue->resolveAttention($user, $previousLastSeenAt);

        $sinceLastShift = collect($comms['since_last_shift'] ?? [])
            ->map(fn (array $row): array => $this->mapCommsRow($row))
            ->take(self::MAX_ITEMS_PER_SECTION)
            ->values()
            ->all();

        $callsWaiting = collect($comms['needs_attention'] ?? [])
            ->filter(fn (array $row): bool => ($row['kind'] ?? '') === 'call')
            ->map(fn (array $row): array => $this->mapCommsRow($row))
            ->take(self::MAX_ITEMS_PER_SECTION)
            ->values()
            ->all();

        $sections = [];

        if ($callsWaiting !== []) {
            $sections[] = [
                'key' => 'calls_waiting',
                'label' => 'Calls Waiting',
                'count' => count($callsWaiting),
                'items' => $callsWaiting,
            ];
        }

        if ($sinceLastShift !== []) {
            $sections[] = [
                'key' => 'since_last_shift',
                'label' => (string) ($comms['since_last_shift_boundary_label'] ?? 'Since Last Shift'),
                'count' => count($sinceLastShift),
                'items' => $sinceLastShift,
            ];
        }

        return [
            'sections' => $sections,
            'total_count' => array_sum(array_column($sections, 'count')),
            'poll_after_seconds' => 30,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapDecisionRow(array $row): array
    {
        $vehicle = (string) ($row['vehicle_label'] ?? '');
        $dollars = (string) ($row['dollars_at_risk_label'] ?? '');
        $age = (string) ($row['age_label'] ?? '');
        $subtitle = trim(collect([$vehicle, $dollars, $age])->filter()->implode(' · '));
        $kind = (string) ($row['kind'] ?? 'customer_decision');

        // Explainable tone — money waiting on a decision is amber "waiting":
        // someone/something is waiting on the shop. Observation drives the glyph.
        [$observation, $tone] = match ($kind) {
            'approved_work_stalled' => ['repair_order_stalled', 'waiting'],
            'estimate_ready_not_sent' => ['estimate_ready_not_sent', 'waiting'],
            default => ['customer_decision_needed', 'waiting'],
        };

        return [
            'kind' => $kind,
            'title' => (string) ($row['customer_name'] ?? 'Customer'),
            'subtitle' => $subtitle,
            'detail' => (string) ($row['detail'] ?? ''),
            'observation' => $observation,
            'tone' => $tone,
            'category' => 'estimate',
            'customer_id' => isset($row['customer_id']) ? (int) $row['customer_id'] : null,
            'repair_order_id' => MobileRepairOrderRouteId::normalize(
                isset($row['repair_order_id']) ? (int) $row['repair_order_id'] : null,
            ),
            'conversation_id' => null,
            'call_session_id' => null,
            'deep_link' => 'repair_order',
            'occurred_at' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapCommsRow(array $row): array
    {
        $kind = (string) ($row['kind'] ?? 'message');
        $repairOrderId = null;

        if (isset($row['open_repair_order_ids']) && is_array($row['open_repair_order_ids'])) {
            $repairOrderId = $row['open_repair_order_ids'][0] ?? null;
        }

        if ($repairOrderId === null && isset($row['repair_order_id'])) {
            $repairOrderId = $row['repair_order_id'];
        }

        $deepLink = match ($kind) {
            'call' => 'call',
            default => 'conversation',
        };

        // A waiting call interrupts now (urgent/red); an unanswered customer
        // message is amber "waiting" — the customer is waiting on the shop.
        [$observation, $tone] = match ($kind) {
            'call' => ['incoming_call', 'urgent'],
            default => ['customer_waiting_response', 'waiting'],
        };

        return [
            'kind' => $kind,
            'title' => (string) ($row['headline'] ?? 'Needs attention'),
            'subtitle' => (string) ($row['state_label'] ?? $row['snippet'] ?? $row['direction_label'] ?? ''),
            'detail' => (string) ($row['display_contact'] ?? $row['phone'] ?? ''),
            'observation' => $observation,
            'tone' => $tone,
            'category' => 'communication',
            'customer_id' => isset($row['customer_id']) ? (int) $row['customer_id'] : null,
            'repair_order_id' => MobileRepairOrderRouteId::normalize($repairOrderId !== null ? (int) $repairOrderId : null),
            'conversation_id' => $row['conversation_id'] ?? null,
            'call_session_id' => $row['call_session_id'] ?? $row['id'] ?? null,
            'deep_link' => $deepLink,
            'occurred_at' => $row['occurred_at'] ?? $row['started_at'] ?? null,
        ];
    }

    private function pushEnabled(): bool
    {
        return \App\Ark\Mobile\Push\MobilePushSettings::current()->isOperational();
    }
}
